<?php

/**
 * Testes isolados da leitura e validacao da importacao de clientes.
 *
 * Execute: php tests/test_cliente_importacao.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

ini_set('session.save_path', sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Models\Cliente;
use App\Models\MatrizFilial;
use App\Models\Pais;
use App\Services\ClienteImportacaoService;

final class ClienteImportacaoFakeCliente extends Cliente
{
    public array $existentes = [];
    public array $importados = [];

    public function __construct()
    {
    }

    public function buscarDocumentosExistentesParaImportacao(array $documentos): array
    {
        return array_values(array_intersect($documentos, $this->existentes));
    }

    public function importarLote(array $registros): int
    {
        $this->importados = $registros;
        return count($registros);
    }
}

final class ClienteImportacaoFakeFilial extends MatrizFilial
{
    public function __construct()
    {
    }

    public function listarParaImportacao(?string $where = null, array $params = []): array
    {
        return [['id' => 1, 'nome' => 'Matriz', 'nome_fantasia' => 'Matriz']];
    }
}

final class ClienteImportacaoFakePais extends Pais
{
    public function __construct()
    {
    }

    public function listarAtivos(?string $locale = null): array
    {
        return [['codigo' => 'BR'], ['codigo' => 'US']];
    }
}

$cliente = new ClienteImportacaoFakeCliente();
$service = new ClienteImportacaoService(
    $cliente,
    new ClienteImportacaoFakeFilial(),
    new ClienteImportacaoFakePais()
);
$paises = ['BR', 'US'];
$falhas = 0;

$assert = static function (bool $condicao, string $descricao) use (&$falhas): void {
    if ($condicao) {
        echo "✓ {$descricao}\n";
        return;
    }

    echo "✗ {$descricao}\n";
    $falhas++;
};

$linhaValida = array_fill_keys(ClienteImportacaoService::HEADERS, '');
$linhaValida['tipo'] = 'PF';
$linhaValida['cpf_cnpj'] = '529.982.247-25';
$linhaValida['nome_rsocial'] = 'Cliente Teste';
$linhaValida['email'] = 'cliente@teste.com';
$linhaValida['telefone'] = '+55 11 99999-9999';

$valido = $service->validarLinhas([['line' => 2, 'data' => $linhaValida]], $paises, 1);
$assert($valido['success'] === true, 'aceita somente os tres campos obrigatorios do CSV e contatos opcionais validos');
$assert(($valido['data'][0]['cpf_cnpj'] ?? null) === '52998224725', 'normaliza CPF antes da persistencia');
$assert(($valido['data'][0]['id_matriz_filial'] ?? null) === 1, 'aplica a filial selecionada fora do arquivo');
$assert(array_key_exists('foto', $valido['data'][0]) && $valido['data'][0]['foto'] === '', 'define foto vazia exigida pelo schema');

$semNome = $linhaValida;
$semNome['nome_rsocial'] = '';
$invalido = $service->validarLinhas([['line' => 7, 'data' => $semNome]], $paises, 1);
$assert($invalido['success'] === false, 'bloqueia o lote quando um obrigatorio esta ausente');
$assert(($invalido['errors'][0]['line'] ?? null) === 7, 'informa a linha que contem o erro');

$duplicado = $service->validarLinhas([
    ['line' => 2, 'data' => $linhaValida],
    ['line' => 3, 'data' => $linhaValida],
], $paises, 1);
$assert(
    $duplicado['success'] === true
    && count($duplicado['data'] ?? []) === 1
    && ($duplicado['ignored'][0]['line'] ?? null) === 3
    && ($duplicado['ignored'][0]['reason'] ?? '') === 'documento_repetido_arquivo',
    'importa a primeira ocorrencia e ignora repeticoes posteriores no arquivo'
);

$cliente->existentes = ['52998224725'];
$existente = $service->validarLinhas([['line' => 2, 'data' => $linhaValida]], $paises, 1);
$assert(
    $existente['success'] === true
    && ($existente['data'] ?? []) === []
    && ($existente['ignored'][0]['reason'] ?? '') === 'documento_existente',
    'ignora documento ja cadastrado no tenant sem bloquear o lote'
);

$linhaNova = $linhaValida;
$linhaNova['cpf_cnpj'] = '111.444.777-35';
$linhaNova['nome_rsocial'] = 'Cliente Novo';
$misto = $service->validarLinhas([
    ['line' => 2, 'data' => $linhaValida],
    ['line' => 3, 'data' => $linhaNova],
], $paises, 1);
$assert(
    $misto['success'] === true
    && count($misto['data'] ?? []) === 1
    && ($misto['data'][0]['cpf_cnpj'] ?? '') === '11144477735'
    && ($misto['ignored'][0]['line'] ?? null) === 2,
    'ignora o documento existente e mantem o proximo cliente valido'
);
$cliente->existentes = [];

$dataInvalida = $linhaValida;
$dataInvalida['nascimento'] = '31/02/2025';
$resultadoDataInvalida = $service->validarLinhas([['line' => 9, 'data' => $dataInvalida]], $paises, 1);
$assert(
    $resultadoDataInvalida['success'] === false
    && ($resultadoDataInvalida['errors'][0]['field'] ?? '') === 'nascimento',
    'rejeita data inexistente sem aceitar a correcao automatica do PHP'
);

$arquivo = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
if ($arquivo === false) {
    throw new RuntimeException('Nao foi possivel criar arquivo temporario.');
}

$handle = fopen($arquivo, 'wb');
fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, ClienteImportacaoService::HEADERS, ';', '"', '');
fputcsv($handle, array_values($linhaValida), ';', '"', '');
fclose($handle);

$leitura = $service->lerCsv($arquivo);
$assert($leitura['success'] === true && count($leitura['rows'] ?? []) === 1, 'le CSV com BOM e delimitador ponto e virgula');
$importacao = $service->importar($arquivo, 1);
$assert($importacao['success'] === true && ($importacao['importados'] ?? 0) === 1, 'importa o lote para uma filial permitida');
$assert(($cliente->importados[0]['id_matriz_filial'] ?? null) === 1, 'vincula todos os registros a filial escolhida');
$filialBloqueada = $service->importar($arquivo, 999);
$assert($filialBloqueada['success'] === false, 'bloqueia filial inexistente, inativa ou sem acesso');
unlink($arquivo);

$cliente->existentes = ['52998224725'];
$arquivoDuplicadoExistente = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
$handle = fopen($arquivoDuplicadoExistente, 'wb');
fputcsv($handle, ClienteImportacaoService::HEADERS, ';', '"', '');
fputcsv($handle, array_values($linhaValida), ';', '"', '');
fclose($handle);
$somenteExistente = $service->importar($arquivoDuplicadoExistente, 1);
unlink($arquivoDuplicadoExistente);
$assert(
    $somenteExistente['success'] === true
    && ($somenteExistente['importados'] ?? -1) === 0
    && ($somenteExistente['ignorados'] ?? 0) === 1,
    'arquivo somente com documento existente conclui com zero importados e um ignorado'
);
$cliente->existentes = [];

$arquivoInvalido = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
file_put_contents($arquivoInvalido, "nome;cpf\nTeste;123\n");
$cabecalhoInvalido = $service->lerCsv($arquivoInvalido);
unlink($arquivoInvalido);
$assert($cabecalhoInvalido['success'] === false, 'recusa cabecalho diferente do modelo oficial');

$arquivoModeloAntigo = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
$handle = fopen($arquivoModeloAntigo, 'wb');
fputcsv($handle, array_merge(['filial'], ClienteImportacaoService::HEADERS), ';', '"', '');
fclose($handle);
$modeloAntigo = $service->lerCsv($arquivoModeloAntigo);
unlink($arquivoModeloAntigo);
$assert(
    $modeloAntigo['success'] === false
    && str_contains($modeloAntigo['errors'][0]['message'] ?? '', 'modelo anterior'),
    'orienta baixar novamente quando o CSV ainda possui a coluna filial'
);

$modelo = $service->gerarModelo();
$linhasModelo = preg_split('/\r\n|\r|\n/', trim($modelo));
$cabecalhoModelo = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $linhasModelo[0]), ';', '"', '');
$assert(!in_array('filial', $cabecalhoModelo, true), 'modelo baixado nao possui a coluna filial');
$assert(count($linhasModelo) === 4, 'modelo contem cabecalho e tres exemplos');
$assert(
    (str_getcsv($linhasModelo[1], ';', '"', '')[0] ?? '') === 'PF'
    && (str_getcsv($linhasModelo[2], ';', '"', '')[0] ?? '') === 'PJ'
    && (str_getcsv($linhasModelo[3], ';', '"', '')[0] ?? '') === 'ES',
    'modelo apresenta tipos validos nos exemplos de PF, PJ e estrangeiro'
);
$assert(
    (str_getcsv($linhasModelo[1], ';', '"', '')[1] ?? '') === '999.999.999-99'
    && (str_getcsv($linhasModelo[2], ';', '"', '')[1] ?? '') === '99.999.999/9999-99'
    && (str_getcsv($linhasModelo[3], ';', '"', '')[1] ?? '') === 'X9999999',
    'modelo identifica exemplos pelos documentos ficticios acordados'
);
$arquivoSomenteExemplos = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
file_put_contents($arquivoSomenteExemplos, $modelo);
$leituraExemplos = $service->lerCsv($arquivoSomenteExemplos);
unlink($arquivoSomenteExemplos);
$assert(
    $leituraExemplos['success'] === false
    && str_contains($leituraExemplos['errors'][0]['message'] ?? '', 'nao possui clientes'),
    'importador ignora automaticamente as tres combinacoes exatas de exemplo'
);

$exemplosUtilizaveis = [];
$documentosValidos = ['529.982.247-25', '11.222.333/0001-81', 'X1234567'];
for ($indice = 1; $indice <= 3; $indice++) {
    $valores = str_getcsv($linhasModelo[$indice], ';', '"', '');
    $valores[1] = $documentosValidos[$indice - 1];
    $exemplosUtilizaveis[] = [
        'line' => $indice + 1,
        'data' => array_combine(ClienteImportacaoService::HEADERS, $valores),
    ];
}
$assert(
    $service->validarLinhas($exemplosUtilizaveis, $paises, 1)['success'] === true,
    'os tres exemplos tornam-se validos ao substituir somente o documento ficticio'
);

$arquivoSemelhantes = tempnam(sys_get_temp_dir(), 'cliente-importacao-');
$handle = fopen($arquivoSemelhantes, 'wb');
fputcsv($handle, ClienteImportacaoService::HEADERS, ';', '"', '');
foreach ([
    ['PF', '999.999.999-98', 'PF semelhante'],
    ['PJ', '99.999.999/9999-98', 'PJ semelhante'],
    ['ES', 'X9999998', 'ES semelhante'],
] as [$tipo, $documento, $nome]) {
    $linha = array_fill_keys(ClienteImportacaoService::HEADERS, '');
    $linha['tipo'] = $tipo;
    $linha['cpf_cnpj'] = $documento;
    $linha['nome_rsocial'] = $nome;
    fputcsv($handle, array_values($linha), ';', '"', '');
}
fclose($handle);
$semelhantes = $service->lerCsv($arquivoSemelhantes);
unlink($arquivoSemelhantes);
$assert(
    $semelhantes['success'] === true && count($semelhantes['rows'] ?? []) === 3,
    'documentos apenas semelhantes aos ficticios nao sao ignorados'
);

if ($falhas > 0) {
    echo "\n{$falhas} teste(s) falharam.\n";
    exit(1);
}

echo "\nTodos os testes de importacao de clientes passaram.\n";
