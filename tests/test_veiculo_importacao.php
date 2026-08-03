<?php

/**
 * Testes isolados da leitura e validacao da importacao de veiculos.
 *
 * Execute: php tests/test_veiculo_importacao.php
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

use App\Models\Fornecedor;
use App\Models\Grupo;
use App\Models\ManutencaoPlano;
use App\Models\MatrizFilial;
use App\Models\Veiculo;
use App\Services\VeiculoImportacaoService;

final class VeiculoImportacaoFakeVeiculo extends Veiculo
{
    public array $existentes = [];
    public array $importados = [];

    public function __construct()
    {
    }

    public function buscarPlacasExistentesParaImportacao(array $placas): array
    {
        return array_values(array_intersect($placas, $this->existentes));
    }

    public function importarLote(array $registros): int
    {
        $this->importados = $registros;
        return count($registros);
    }
}

final class VeiculoImportacaoFakeFilial extends MatrizFilial
{
    public function __construct()
    {
    }

    public function listarParaImportacao(?string $where = null, array $params = []): array
    {
        return [
            ['id' => 1, 'nome' => 'Matriz', 'nome_fantasia' => 'Matriz'],
            ['id' => 2, 'nome' => 'Filial', 'nome_fantasia' => 'Filial'],
        ];
    }
}

final class VeiculoImportacaoFakeFornecedor extends Fornecedor
{
    public function __construct()
    {
    }

    public function listarParaImportacaoVeiculos(): array
    {
        return [['id' => 10, 'nome' => 'Fornecedor']];
    }
}

final class VeiculoImportacaoFakeGrupo extends Grupo
{
    public function __construct()
    {
    }

    public function listarParaImportacaoVeiculos(): array
    {
        return [['id' => 20, 'nome' => 'Grupo']];
    }
}

final class VeiculoImportacaoFakePlano extends ManutencaoPlano
{
    public function __construct()
    {
    }

    public function listarParaImportacaoVeiculos(): array
    {
        return [['id' => 30, 'nome' => 'Plano']];
    }

    public function buscarPorId(int $id): ?array
    {
        if ($id !== 30) {
            return null;
        }

        return [
            'id' => 30,
            'status' => 'A',
            'array' => json_encode([
                'motor_oleo' => '10.000',
                'motor_bateria' => '0',
            ]),
        ];
    }
}

$_SESSION['chave'] = '1111111111111';
$_SESSION['filiais_permitidas'] = [];

$veiculo = new VeiculoImportacaoFakeVeiculo();
$service = new VeiculoImportacaoService(
    $veiculo,
    new VeiculoImportacaoFakeFilial(),
    new VeiculoImportacaoFakeFornecedor(),
    new VeiculoImportacaoFakeGrupo(),
    new VeiculoImportacaoFakePlano()
);

$falhas = 0;
$assert = static function (bool $condicao, string $descricao) use (&$falhas): void {
    if ($condicao) {
        echo "✓ {$descricao}\n";
        return;
    }

    echo "✗ {$descricao}\n";
    $falhas++;
};

$opcoes = [
    'id_matriz_filial' => 1,
    'id_fornecedor' => 10,
    'id_grupo' => 20,
    'id_matriz_filial_localizacao' => 2,
    'id_plano_manutencao' => 30,
];

$linhaValida = array_fill_keys(VeiculoImportacaoService::HEADERS, '');
$linhaValida['placa'] = 'ABC-1D23';
$linhaValida['odometro'] = '25.000';
$linhaValida['marca'] = 'Marca';
$linhaValida['modelo'] = 'Modelo';
$linhaValida['ano'] = '2025/2026';
$linhaValida['data_compra'] = '2026-01-15';
$linhaValida['valor_compra'] = '85.000,50';

$plano = (new VeiculoImportacaoFakePlano())->buscarPorId(30);
$valido = $service->validarLinhas([['line' => 2, 'data' => $linhaValida]], $opcoes, $plano);
$assert($valido['success'] === true, 'aceita os quatro campos obrigatorios e opcoes validas');
$assert(($valido['data'][0]['id_grupo'] ?? null) === 20, 'aplica o grupo obrigatorio a todo o lote');
$assert(($valido['data'][0]['disponibilidade'] ?? null) === 'D', 'aplica disponibilidade padrao');
$assert(($valido['data'][0]['valor_compra'] ?? null) === 85000.50, 'normaliza valor monetario');
$planoVeiculo = json_decode($valido['data'][0]['plano_manutencao_array'] ?? '', true);
$assert(($planoVeiculo['motor_oleo'] ?? null) === '35.000', 'soma odometro ao intervalo do plano');
$assert(($planoVeiculo['motor_bateria'] ?? null) === '0', 'preserva item desativado do plano com intervalo zero');

$semMarca = $linhaValida;
$semMarca['marca'] = '';
$invalido = $service->validarLinhas([['line' => 7, 'data' => $semMarca]], $opcoes, $plano);
$assert($invalido['success'] === false && ($invalido['errors'][0]['line'] ?? null) === 7, 'bloqueia todo o lote com linha invalida');

$dataInvalida = $linhaValida;
$dataInvalida['data_compra'] = '2026-02-31';
$resultadoData = $service->validarLinhas([['line' => 9, 'data' => $dataInvalida]], $opcoes, $plano);
$assert($resultadoData['success'] === false, 'rejeita data ISO inexistente');

$duplicado = $service->validarLinhas([
    ['line' => 2, 'data' => $linhaValida],
    ['line' => 3, 'data' => $linhaValida],
], $opcoes, $plano);
$assert(
    $duplicado['success'] === true
    && count($duplicado['data'] ?? []) === 1
    && ($duplicado['ignored'][0]['reason'] ?? '') === 'placa_duplicada_arquivo',
    'importa a primeira placa e ignora repeticoes posteriores no arquivo'
);

$veiculo->existentes = ['ABC1D23'];
$existente = $service->validarLinhas([['line' => 2, 'data' => $linhaValida]], $opcoes, $plano);
$assert(
    $existente['success'] === true
    && ($existente['data'] ?? []) === []
    && ($existente['ignored'][0]['reason'] ?? '') === 'placa_existente',
    'ignora placa ja cadastrada no tenant'
);
$veiculo->existentes = [];

$arquivo = tempnam(sys_get_temp_dir(), 'veiculo-importacao-');
if ($arquivo === false) {
    throw new RuntimeException('Nao foi possivel criar arquivo temporario.');
}
$handle = fopen($arquivo, 'wb');
fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, VeiculoImportacaoService::HEADERS, ';', '"', '');
fputcsv($handle, array_values($linhaValida), ';', '"', '');
fclose($handle);

$leitura = $service->lerCsv($arquivo);
$assert($leitura['success'] === true && count($leitura['rows'] ?? []) === 1, 'le CSV com BOM e delimitador ponto e virgula');
$importacao = $service->importar($arquivo, $opcoes, 1);
$assert($importacao['success'] === true && ($importacao['importados'] ?? 0) === 1, 'importa o lote com relacionamentos validos');
$assert(($veiculo->importados[0]['id_matriz_filial_localizacao'] ?? null) === 2, 'aplica a localizacao escolhida');

$grupoAusente = $opcoes;
$grupoAusente['id_grupo'] = 0;
$semGrupo = $service->importar($arquivo, $grupoAusente, 1);
$assert($semGrupo['success'] === false, 'bloqueia importacao sem grupo');

$semVagas = $service->importar($arquivo, $opcoes, 0);
$assert($semVagas['success'] === false, 'bloqueia lote ativo quando o plano nao possui vagas');
unlink($arquivo);

$modelo = $service->gerarModelo();
$linhasModelo = preg_split('/\r\n|\r|\n/', trim($modelo));
$cabecalhoModelo = str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $linhasModelo[0]), ';', '"', '');
$assert($cabecalhoModelo === VeiculoImportacaoService::HEADERS, 'modelo usa exatamente os cabecalhos oficiais');
$arquivoModelo = tempnam(sys_get_temp_dir(), 'veiculo-modelo-');
file_put_contents($arquivoModelo, $modelo);
$somenteExemplo = $service->lerCsv($arquivoModelo);
unlink($arquivoModelo);
$assert($somenteExemplo['success'] === false, 'linha EXEMPLO do modelo e ignorada automaticamente');

$layout = file_get_contents(APP_ROOT . '/app/Views/layouts/app.php');
$inicioModal = strpos($layout, '<!-- Modal Global de Importação de Veículos -->');
$fimModal = strpos($layout, '<!-- Modal de Alerta Global -->', $inicioModal ?: 0);
$htmlModal = $inicioModal !== false && $fimModal !== false
    ? substr($layout, $inicioModal, $fimModal - $inicioModal)
    : '';
$endpointsChosen = [
    '/api/matrizes-filiais/buscar',
    '/api/fornecedores/select',
    '/api/grupos',
    '/api/manutencoes-planos/select',
];
$assert(substr_count($htmlModal, 'data-chosen-type="server-side"') === 5, 'os cinco selects do modal usam Chosen server-side');
foreach ($endpointsChosen as $endpointChosen) {
    $assert(str_contains($htmlModal, 'data-chosen-search-url="' . $endpointChosen . '"'), "modal usa o endpoint server-side {$endpointChosen}");
}
$assert(substr_count($htmlModal, 'data-chosen-allow-clear="false"') === 4, 'somente os quatro relacionamentos obrigatorios bloqueiam limpeza');

$rotas = file_get_contents(APP_ROOT . '/app/Routes/web.php');
$assert(str_contains($rotas, "'/api/manutencoes-planos/select'"), 'rota server-side de planos esta registrada');
$assert(!str_contains($rotas, "'/api/veiculos/importacao/opcoes'"), 'endpoint de carregamento antecipado foi removido');

$cssComponentes = file_get_contents(APP_ROOT . '/public/assets/css/components.css');
$assert(!str_contains($cssComponentes, '#veiculoImportacaoConfig .form-label-group'), 'modal nao sobrescreve altura dos labels do formulario');
$assert(!str_contains($cssComponentes, '#veiculoImportacaoConfig .form-input-group-field'), 'modal nao sobrescreve altura dos campos do formulario');
$assert(!str_contains($cssComponentes, '#veiculoImportacaoArquivo::file-selector-button'), 'arquivo do modal segue o tamanho padrao dos formularios');

if ($falhas > 0) {
    echo "\n{$falhas} teste(s) falharam.\n";
    exit(1);
}

echo "\nTodos os testes de importacao de veiculos passaram.\n";
