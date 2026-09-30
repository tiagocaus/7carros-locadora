<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Cliente;
use App\Models\Model;
use App\Models\NFSe;
use App\Models\NFSeConfiguracao;
use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\NFSeErros;
use App\Services\NFSe\NFSeService;

function verificarMunicipio(bool $ok, string $mensagem): void
{
    if (!$ok) throw new RuntimeException($mensagem);
}

function deveBloquearMunicipio(callable $acao, string $trecho): void
{
    try {
        $acao();
    } catch (InvalidArgumentException $e) {
        verificarMunicipio(str_contains($e->getMessage(), $trecho), $e->getMessage());
        return;
    }
    throw new RuntimeException('Deveria bloquear: ' . $trecho);
}

class ConfiguracaoMunicipioSemReserva extends NFSeConfiguracao
{
    public int $consultas = 0;
    public function __construct() {}
    public function consultarProximoNumero(int $idMatrizFilial, ?string $chave = null): int
    {
        $this->consultas++;
        throw new RuntimeException('Validacao de endereco nao pode chegar a numeracao.');
    }
}

$service = (new ReflectionClass(NFSeService::class))->newInstanceWithoutConstructor();
$validar = new ReflectionMethod(NFSeService::class, 'validarEnderecoBetha');
$validarXML = new ReflectionMethod(NFSeService::class, 'validarEnderecoBethaXML');
$base = [
    'tipo_emissao' => 'betha', 'ambiente' => 2, 'municipio_codigo' => '4204558',
    'serie' => '1', 'numero' => 1, 'data_emissao' => '2026-09-30T10:00:00-03:00',
    'data_competencia' => '2026-09-30',
    'prestador' => ['cnpj' => '11222333000181', 'regime_tributario' => 1],
    'tomador' => ['tipo' => 'PF', 'pais' => 'BR', 'nome' => 'Cliente de teste',
        'cpf_cnpj' => '52998224725', 'endereco' => ['pais' => 'BR', 'codigo_municipio' => '3205200', 'cep' => '29119-021']],
    'servico' => ['codigo' => '1.1101.11', 'codigo_tributacao_nacional' => '990101', 'descricao' => 'LOCACAO'],
    'valores' => ['preencher_ibscbs' => 'S', 'c_ind_op_ibscbs' => '100301',
        'cst_ibscbs' => '000', 'c_class_trib_ibscbs' => '000001', 'servicos' => 100],
];
$validar->invoke($service, $base);
$xml = (new NFSeXMLBetha())->gerarXML($base);
verificarMunicipio(str_contains($xml, '<cMun>3205200</cMun><CEP>29119021</CEP>'), 'XML deve conter IBGE e CEP do tomador.');
$validarXML->invoke($service, $xml, 'betha');

$preparar = new ReflectionMethod(NFSeService::class, 'prepararXMLAssinado');
$guard = new ConfiguracaoMunicipioSemReserva();
(new ReflectionProperty(NFSeService::class, 'configModel'))->setValue($service, $guard);
foreach ([['codigo_municipio', '', 'TOMADOR_MUNICIPIO'], ['codigo_municipio', '0000000', 'TOMADOR_MUNICIPIO'],
    ['codigo_municipio', '320520', 'TOMADOR_MUNICIPIO'], ['cep', '', 'TOMADOR_ENDERECO'],
    ['cep', '00000000', 'TOMADOR_ENDERECO']] as [$campo, $valor, $codigo]) {
    $dados = $base;
    $dados['tomador']['endereco'][$campo] = $valor;
    $resultado = $preparar->invokeArgs($service, ['betha', &$dados, ['id_matriz_filial' => 0], '1111111111111', []]);
    verificarMunicipio(!$resultado['sucesso'] && $resultado['codigo'] === $codigo, 'Validacao deve informar ' . $codigo);
    verificarMunicipio($guard->consultas === 0, 'Validacao deve acontecer antes da reserva/assinatura/envio.');
}
$semEndereco = $base;
$semEndereco['tomador']['endereco'] = [];
$xmlSemEndereco = (new NFSeXMLBetha())->gerarXML($semEndereco);
deveBloquearMunicipio(fn() => $validarXML->invoke($service, $xmlSemEndereco, 'betha'), 'não possui financeiro');
foreach (['nacional', 'issnet'] as $tipo) {
    $dados = $semEndereco; $dados['tipo_emissao'] = $tipo;
    $validar->invoke($service, $dados);
}
$dados = $semEndereco; $dados['valores']['preencher_ibscbs'] = 'N'; $validar->invoke($service, $dados);
$dados = $semEndereco; $dados['valores']['c_ind_op_ibscbs'] = '100302'; $validar->invoke($service, $dados);
$dados = $semEndereco; $dados['tomador']['tipo'] = 'ES'; $dados['tomador']['pais'] = 'PT'; $validar->invoke($service, $dados);
verificarMunicipio(NFSeErros::mapearErroRetorno('00000', '4 - Para o Indicador de operação informados, o município do tomador deve ser informado') === 'TOMADOR_MUNICIPIO', 'Retorno Betha deve ser classificado.');
verificarMunicipio(!NFSeErros::isRecuperavel('TOMADOR_MUNICIPIO'), 'Cron nao deve repetir erro cadastral.');

// Persistencia e isolamento reais, exclusivamente no localhost, com rollback.
verificarMunicipio(Database::env('DB_HOST') === 'localhost' && ($_ENV['APP_ENV'] ?? 'development') === 'development', 'Teste de persistencia permitido apenas em development/localhost.');
$_SESSION['chave'] = '1111111111111';
$cliente = new Cliente(); $nfse = new NFSe(); $db = Model::sharedMysqli();
$db->begin_transaction();
try {
    $id = $cliente->criar(['foto' => '', 'tipo' => 'PF', 'nome_rsocial' => 'Teste IBGE',
        'cpf_cnpj' => '52998224725', 'pais' => 'BR', 'cep' => '29119-021', 'cidade' => 'Vila Velha',
        'estado' => 'ES', 'codigo_municipio' => '3205200']);
    verificarMunicipio($cliente->buscarPorId($id)['codigo_municipio'] === '3205200', 'IBGE deve persistir no cliente.');
    $cliente->atualizar($id, ['nome_rsocial' => 'Teste IBGE editado']);
    verificarMunicipio($cliente->buscarPorId($id)['codigo_municipio'] === '3205200', 'Atualizacao parcial sem endereco deve preservar IBGE.');

    $montar = new ReflectionMethod(NFSeService::class, 'montarDadosNFSe');
    $config = ['tipo_emissao' => 'betha', 'codigo_municipio' => '4204558', 'preencher_ibscbs' => 'S',
        'codigo_tributacao_nacional' => '990101', 'c_ind_op_ibscbs' => '100301', 'cst_ibscbs' => '000', 'c_class_trib_ibscbs' => '000001'];
    $dados = $montar->invoke($service, ['id' => 0, 'id_cliente' => $id, 'id_matriz_filial' => 0, 'valor_total' => 100], $config, '1111111111111', ['tomador_codigo_municipio' => '3550308']);
    verificarMunicipio($dados['tomador']['endereco']['codigo_municipio'] === '3205200', 'IBGE enviado pelo navegador nao pode sobrescrever o cadastro.');

    $idNota = $nfse->criar(['id_matriz_filial' => 0, 'status' => 'rejeitada', 'tomador_endereco' => '{}']);
    $nfse->atualizarParaReenvio($idNota, ['numero' => 2, 'serie' => '1', 'xml_envio' => $xml,
        'tomador_endereco' => json_encode($dados['tomador']['endereco'])]);
    $nota = $nfse->buscarPorId($idNota);
    verificarMunicipio(json_decode($nota['tomador_endereco'], true)['codigo_municipio'] === '3205200', 'Snapshot do reenvio deve acompanhar XML.');

    $_SESSION['chave'] = 'teste-isolamento-ibge';
    $outroCliente = new Cliente(); $outraNota = new NFSe();
    verificarMunicipio($outroCliente->buscarPorId($id) === null, 'Cliente de outro tenant nao pode ser lido.');
    verificarMunicipio($outroCliente->atualizar($id, ['codigo_municipio' => '3550308']) === 0, 'Cliente de outro tenant nao pode ser alterado.');
    verificarMunicipio($outraNota->atualizarParaReenvio($idNota, ['tomador_endereco' => '{}']) === 0, 'Snapshot de outro tenant nao pode ser alterado.');
    $_SESSION['chave'] = '1111111111111';

    $cliente->atualizar($id, ['cidade' => 'Outra cidade']);
    verificarMunicipio($cliente->buscarPorId($id)['codigo_municipio'] === null, 'Mudanca de municipio sem IBGE deve invalidar codigo anterior.');
    deveBloquearMunicipio(fn() => $cliente->atualizar($id, ['codigo_municipio' => 'ABC5200']), '7 dígitos');
    $cliente->atualizar($id, ['pais' => 'PT', 'codigo_municipio' => '3205200']);
    verificarMunicipio($cliente->buscarPorId($id)['codigo_municipio'] === null, 'Pais estrangeiro deve persistir IBGE nulo.');
} finally {
    $db->rollback();
}
echo "Teste de municipio do tomador, persistencia e isolamento passou (rollback local).\n";
