<?php

/**
 * Teste de integracao do relatorio Quilometragem Media.
 *
 * Execute: php tests/test_relatorio_quilometragem_media.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Controllers\Relatorios\BaseRelatorioController;
use App\Helpers\DateHelper;
use App\Helpers\PdfHelper;
use App\Models\Relatorios\VeicularReport;

class ReportPdfContextProbe extends BaseRelatorioController
{
    public function company(?array $user): array
    {
        return $this->resolveReportPdfCompany($user);
    }
}

$chave = 'TEST_KM_' . strtoupper(bin2hex(random_bytes(6)));
$outraChave = $chave . '_OUTRO';
$_SESSION['chave'] = $chave;
$falhas = 0;

function assertKm(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas;
    if ($atual !== $esperado) {
        $falhas++;
        echo "FAIL: {$label} - esperado=" . var_export($esperado, true)
            . ', atual=' . var_export($atual, true) . "\n";
        return;
    }
    echo "PASS: {$label}\n";
}

function assertKmTranslationKeys(string $path): void
{
    $source = (string) file_get_contents($path);
    preg_match_all('/<\?=\s*t\([\'\"]([^\'\"]+)[\'\"]\)/', $source, $matches);

    foreach (array_unique($matches[1] ?? []) as $key) {
        assertKm("traducao existente {$key}", t($key) !== $key, true);
    }
}

function criarFilialKm(string $chave, string $nome): int
{
    return Database::insertGetId('matrizes_filiais', [
        'chave' => $chave,
        'tipo' => 'F',
        'nome_fantasia' => $nome,
    ]);
}

function criarGrupoKm(string $chave, string $nome): int
{
    return Database::insertGetId('grupos', ['chave' => $chave, 'nome' => $nome]);
}

function criarVeiculoKm(string $chave, int $filial, int $grupo, string $placa): int
{
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $filial,
        'id_grupo' => $grupo,
        'placa' => $placa,
        'marca' => 'Teste',
        'modelo' => $placa,
    ]);
}

function criarLocacaoKm(
    string $chave,
    int $veiculo,
    int $grupo,
    int $filialRetirada,
    int $filialDevolucao,
    string $codigo,
    string $saida,
    ?string $entrada,
    int $odometroSaida,
    ?int $odometroEntrada,
    string $status = 'F'
): int {
    $locacao = Database::insertGetId('locacoes', [
        'codigo' => $codigo,
        'chave' => $chave,
        'id_matriz_filial_retirada' => $filialRetirada,
        'id_matriz_filial_devolucao' => $filialDevolucao,
        'status' => $status,
        'data_saida' => $saida,
        'data_prevista' => $entrada ?? '2098-03-10 10:00:00',
        'data_chegada' => $entrada,
        'dias' => 1,
        'cliente_nome' => 'Cliente Teste',
    ]);
    Database::insertGetId('locacoes_veiculos', [
        'id_locacao' => $locacao,
        'id_veiculo' => $veiculo,
        'id_grupo' => $grupo,
        'data_saida' => $saida,
        'data_entrada' => $entrada,
        'plano' => 'KL',
        'odometro_saida' => $odometroSaida,
        'odometro_entrada' => $odometroEntrada,
        'chave' => $chave,
    ]);
    return $locacao;
}

function criarContratoKm(
    string $chave,
    int $veiculo,
    int $grupo,
    int $filial,
    string $codigo,
    int $odometroSaida,
    ?int $odometroEntrada,
    ?string $dataEntrada,
    string $status
): array {
    $contrato = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => $codigo,
        'id_matriz_filial_retirada' => $filial,
        'data_ini' => '2098-01-10 08:00:00',
        'data_fim' => '2098-12-31 18:00:00',
        'contagem' => 'Mensal',
        'dias' => 365,
        'status' => $status,
    ]);
    $contratoVeiculo = Database::insertGetId('contratos_veiculos', [
        'id_contrato' => $contrato,
        'id_veiculo' => $veiculo,
        'id_grupo' => $grupo,
        'data_saida' => '2098-01-10 08:00:00',
        'data_entrada' => $dataEntrada,
        'plano' => 'KL',
        'odometro_saida' => $odometroSaida,
        'odometro_entrada' => $odometroEntrada,
        'chave' => $chave,
    ]);
    return [$contrato, $contratoVeiculo];
}

function limparTenantKm(string $chave): void
{
    foreach ([
        'contratos_odometros', 'contratos_veiculos', 'contratos',
        'locacoes_veiculos', 'locacoes', 'veiculos', 'grupos', 'matrizes_filiais',
    ] as $tabela) {
        Database::execute("DELETE FROM {$tabela} WHERE chave = ?", [$chave]);
    }
}

try {
    $filialA = criarFilialKm($chave, 'Filial A');
    $filialB = criarFilialKm($chave, 'Filial B');
    $grupoA = criarGrupoKm($chave, 'Grupo A');
    $grupoB = criarGrupoKm($chave, 'Grupo B');
    $grupoC = criarGrupoKm($chave, 'Grupo C');

    $pdfContext = new ReportPdfContextProbe();
    $empresaSemFilial = $pdfContext->company(['id_matriz_filial' => null]);
    assertKm('PDF sem filial usa unidade do mesmo tenant', $empresaSemFilial['nome'], 'Filial A');
    $empresaComFilial = $pdfContext->company(['id_matriz_filial' => $filialB]);
    assertKm('PDF preserva filial valida da sessao', $empresaComFilial['nome'], 'Filial B');
    assertKmTranslationKeys(APP_ROOT . '/app/Views/pages/relatorios/veicular/evolucao-quilometragem.php');
    assertKmTranslationKeys(APP_ROOT . '/app/Views/pages/relatorios/imprimir/veicular/evolucao-quilometragem.php');

    $valores = [0, 100, 110, 120, 130, 140, 1000];
    $placasGrupoA = [];
    foreach ($valores as $indice => $km) {
        $placa = 'KMA' . str_pad((string) $indice, 4, '0', STR_PAD_LEFT);
        $placasGrupoA[] = $placa;
        $veiculo = criarVeiculoKm($chave, $filialA, $grupoA, $placa);
        $saida = $indice === 1 ? '2098-01-15 08:00:00' : '2098-02-01 08:00:00';
        $entrada = sprintf('2098-02-%02d 18:00:00', 2 + ($indice * 2));
        $inicial = $indice === 0 ? 5000 : 1000 + ($indice * 2000);
        $final = $indice === 0 ? 4900 : $inicial + $km;
        criarLocacaoKm(
            $chave,
            $veiculo,
            $grupoA,
            $indice === 1 ? $filialB : $filialA,
            $filialA,
            'LKM' . $indice,
            $saida,
            $entrada,
            $inicial,
            $final
        );
    }

    $veiculoAberto = criarVeiculoKm($chave, $filialA, $grupoA, 'KMABERTO');
    criarLocacaoKm(
        $chave, $veiculoAberto, $grupoA, $filialA, $filialA, 'LKMAB',
        '2098-02-05 08:00:00', null, 9000, null, 'A'
    );

    for ($indice = 0; $indice < 5; $indice++) {
        $veiculo = criarVeiculoKm($chave, $filialA, $grupoC, 'KMC' . str_pad((string) $indice, 5, '0', STR_PAD_LEFT));
        criarLocacaoKm(
            $chave, $veiculo, $grupoC, $filialA, $filialA, 'LKMC' . $indice,
            '2098-02-01 08:00:00', '2098-02-18 18:00:00',
            20000 + ($indice * 1000), 20050 + ($indice * 1000)
        );
    }

    $veiculoAtivo = criarVeiculoKm($chave, $filialB, $grupoB, 'KMCATIVO');
    [$contratoAtivo, $cvAtivo] = criarContratoKm(
        $chave, $veiculoAtivo, $grupoB, $filialB, 'CKMA', 1000, null, null, 'A'
    );
    Database::insertGetId('contratos_odometros', [
        'id_contrato' => $contratoAtivo,
        'id_contrato_veiculo' => $cvAtivo,
        'data' => '2098-01-20',
        'odometro' => 1050,
        'diferenca' => 50,
        'chave' => $chave,
    ]);
    Database::insertGetId('contratos_odometros', [
        'id_contrato' => $contratoAtivo,
        'id_contrato_veiculo' => $cvAtivo,
        'data' => '2098-02-05',
        'odometro' => 1100,
        'diferenca' => 100,
        'chave' => $chave,
    ]);
    Database::insertGetId('contratos_odometros', [
        'id_contrato' => $contratoAtivo,
        'id_contrato_veiculo' => $cvAtivo,
        'data' => '2098-02-05',
        'odometro' => 1150,
        'diferenca' => null,
        'chave' => $chave,
    ]);

    $veiculoFinal = criarVeiculoKm($chave, $filialB, $grupoB, 'KMCFINAL');
    [$contratoFinal, $cvFinal] = criarContratoKm(
        $chave, $veiculoFinal, $grupoB, $filialB, 'CKMF', 2000, 2250,
        '2098-02-20 18:00:00', 'F'
    );
    Database::insertGetId('contratos_odometros', [
        'id_contrato' => $contratoFinal,
        'id_contrato_veiculo' => $cvFinal,
        'data' => '2098-02-10',
        'odometro' => 2100,
        'diferenca' => 100,
        'chave' => $chave,
    ]);

    $filialOutro = criarFilialKm($outraChave, 'Outra Empresa');
    $grupoOutro = criarGrupoKm($outraChave, 'Outro Grupo');
    $veiculoOutro = criarVeiculoKm($outraChave, $filialOutro, $grupoOutro, 'KMOUTRO');
    criarLocacaoKm(
        $outraChave, $veiculoOutro, $grupoOutro, $filialOutro, $filialOutro,
        'LKMOUT', '2098-02-01 08:00:00', '2098-02-03 18:00:00', 0, 999999
    );

    $model = new VeicularReport();
    $resultado = $model->quilometragemMedia(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', []
    );
    $porPlaca = array_column($resultado['details'], null, 'placa');

    assertKm('total reconhecido sem dupla contagem', $resultado['totals']['km_total'], 2200);
    assertKm('quantidade de veiculos medidos', $resultado['totals']['qtd_veiculos'], 14);
    assertKm('utilizacoes distintas', $resultado['totals']['qtd_locacoes'], 14);
    assertKm('locacao aberta sem leitura nao entra', isset($porPlaca['KMABERTO']), false);
    assertKm('outro tenant nao entra', isset($porPlaca['KMOUTRO']), false);
    assertKm('odometro regressivo vira zero', $porPlaca['KMA0000']['km_total'], 0);
    assertKm('contrato ativo soma leituras do mesmo dia', $porPlaca['KMCATIVO']['km_total'], 100);
    assertKm('contrato ativo usa leitura anterior ao periodo como base', $porPlaca['KMCATIVO']['km_inicial'], 1050);
    assertKm('contrato ativo preserva km final', $porPlaca['KMCATIVO']['km_final'], 1150);
    assertKm('devolucao soma leitura e apenas cauda final', $porPlaca['KMCFINAL']['km_total'], 250);
    assertKm('outlier inferior por IQR', $porPlaca['KMA0000']['alerta_km'], 'baixo');
    assertKm('outlier superior por IQR', $porPlaca['KMA0006']['alerta_km'], 'alto');
    assertKm('grupo pequeno fica sem amostra', $porPlaca['KMCATIVO']['alerta_km'], 'amostra_insuficiente');
    assertKm('IQR zero fica sem amostra', $porPlaca['KMC00000']['alerta_km'], 'amostra_insuficiente');

    $filtradoFilial = $model->quilometragemMedia(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], (string) $filialA
    );
    assertKm('filial considera devolucao da locacao', $filtradoFilial['totals']['km_total'], 1850);
    assertKm('filial de contrato continua sendo retirada', $filtradoFilial['totals']['qtd_veiculos'], 12);

    $filtradoGrupo = $model->quilometragemMedia(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', (string) $grupoB
    );
    assertKm('filtro de grupo', $filtradoGrupo['totals']['km_total'], 350);

    $filtradoVeiculo = $model->quilometragemMedia(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', '', (string) $veiculoAtivo
    );
    assertKm('filtro de veiculo', $filtradoVeiculo['totals']['km_total'], 100);

    $umDia = $model->quilometragemMedia(
        '2098-02-05', '2098-02-05', '1=1', [], '1=1', [], '', '', (string) $veiculoAtivo
    );
    assertKm('periodo de um dia usa divisor inclusivo', $umDia['totals']['media_km_dia'], 100.0);

    $evolucaoDiaria = $model->evolucaoQuilometragem(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', '', '', 'dia'
    );
    $evolucaoPorData = array_column($evolucaoDiaria['details'], null, 'periodo');
    assertKm('evolucao diaria preserva o total reconhecido', $evolucaoDiaria['totals']['km_total'], 2200);
    assertKm('evolucao diaria inclui todos os dias do filtro', count($evolucaoDiaria['details']), 28);
    assertKm('evolucao diaria inclui periodo zerado', $evolucaoPorData['2098-02-03']['km_total'], 0);
    assertKm('evolucao diaria agrupa leituras e devolucoes', $evolucaoPorData['2098-02-10']['km_total'], 230);
    assertKm('evolucao diaria identifica o pico', $evolucaoDiaria['totals']['pico_km'], 1000);
    assertKm('evolucao diaria conta veiculos medidos', $evolucaoDiaria['totals']['qtd_veiculos'], 14);
    assertKm('evolucao diaria conta utilizacoes distintas', $evolucaoDiaria['totals']['qtd_utilizacoes'], 14);

    $evolucaoMensal = $model->evolucaoQuilometragem(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', '', '', 'mes'
    );
    assertKm('evolucao mensal consolida o intervalo', count($evolucaoMensal['details']), 1);
    assertKm('evolucao mensal preserva o total', $evolucaoMensal['details'][0]['km_total'], 2200);

    $evolucaoAnual = $model->evolucaoQuilometragem(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', '', '', 'ano'
    );
    assertKm('evolucao anual consolida o intervalo', count($evolucaoAnual['details']), 1);
    assertKm('evolucao anual preserva o total', $evolucaoAnual['details'][0]['km_total'], 2200);

    $evolucaoFiltrada = $model->evolucaoQuilometragem(
        '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', (string) $grupoB, '', 'semana'
    );
    assertKm('evolucao respeita filtro de grupo', $evolucaoFiltrada['totals']['km_total'], 350);

    assertKm('semana ISO inicia na segunda ao cruzar o ano', DateHelper::periodStartForDatabase('2027-01-01', 'semana'), '2026-12-28');
    assertKm('semana ISO termina no domingo ao cruzar o ano', DateHelper::periodEndForDatabase('2027-01-01', 'semana'), '2027-01-03');
    assertKm('avanco semanal preserva limite ISO', DateHelper::addPeriodsForDatabase(1, '2027-01-01', 'semana'), '2027-01-04');

    $granularidadeInvalida = false;
    try {
        $model->evolucaoQuilometragem(
            '2098-02-01', '2098-02-28', '1=1', [], '1=1', [], '', '', '', 'trimestre'
        );
    } catch (InvalidArgumentException) {
        $granularidadeInvalida = true;
    }
    assertKm('evolucao rejeita granularidade invalida', $granularidadeInvalida, true);

    $tco = $model->tco(
        '2098-02-01', '2098-02-28', '1=1', [],
        '1=1', [], '1=1', []
    );
    assertKm('TCO reutiliza a nova assinatura do relatorio', isset($tco['totals']['tco_total']), true);

    $totals = $resultado['totals'];
    $details = $resultado['details'];
    $titulo = 'Quilometragem Média';
    $descricao = 'Teste de geração do PDF';
    $dataInicio = '2098-02-01';
    $dataFim = '2098-02-28';
    $empresa = $empresaSemFilial;
    $usuario = 'Teste';

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        ob_start();
        include APP_ROOT . '/app/Views/pages/relatorios/imprimir/veicular/quilometragem-media.php';
        $htmlPdf = ob_get_clean();
        $pdf = PdfHelper::generateAsString($htmlPdf, ['orientation' => 'L']);
        assertKm('PDF e gerado sem warnings de producao', str_starts_with($pdf, '%PDF-'), true);

        $totals = $evolucaoDiaria['totals'];
        $details = $evolucaoDiaria['details'];
        $titulo = 'Evolução da Quilometragem';
        $descricao = 'Teste de evolução temporal';
        ob_start();
        include APP_ROOT . '/app/Views/pages/relatorios/imprimir/veicular/evolucao-quilometragem.php';
        $htmlEvolucaoPdf = ob_get_clean();
        $pdfEvolucao = PdfHelper::generateAsString($htmlEvolucaoPdf, ['orientation' => 'L']);
        assertKm('PDF da evolucao e gerado sem warnings', str_starts_with($pdfEvolucao, '%PDF-'), true);
        assertKm('PDF da evolucao nao usa medias', str_contains($htmlEvolucaoPdf, 'Média'), false);
    } finally {
        if (ob_get_level() > 0) ob_end_clean();
        restore_error_handler();
    }
} finally {
    limparTenantKm($chave);
    limparTenantKm($outraChave);
    unset($_SESSION['chave']);
}

exit($falhas > 0 ? 1 : 0);
