<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ContratoEncerramentoService;

$service = new ContratoEncerramentoService();
$falhas = [];
$assert = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    if (!$condicao) {
        $falhas[] = $mensagem;
    }
};
$assertValor = static function (float $esperado, float $atual, string $mensagem) use ($assert): void {
    $assert(abs($esperado - $atual) < 0.01, "$mensagem (esperado: $esperado; atual: $atual)");
};

$veiculo = static function (int $id, string $saida, float $tarifa, array $extras = []): array {
    return array_merge([
        'id' => $id,
        'id_veiculo' => $id,
        'data_saida' => $saida,
        'data_entrada' => null,
        'odometro_saida' => 1000,
        'combustivel_saida' => 8,
        'plano' => 'KL',
        'valor_plano_km_livre' => $tarifa,
        'valor_plano_km_controlado' => 0,
        'valor_plano_km_pago' => 0,
        'seguro_carro' => 0,
        'seguro_terceiros' => 0,
        'veiculo_valor_por_fracao' => 0,
    ], $extras);
};
$contrato = static fn(string $contagem, string $inicio, float $total, float $desconto = 0): array => [
    'contagem' => $contagem,
    'data_ini' => $inicio,
    'total_pagar' => $total,
    'valor_desconto' => $desconto,
];
$devolucao = static fn(int $id, string $data): array => [
    'id_contrato_veiculo' => $id,
    'data_entrada' => $data,
    'odometro_entrada' => 1000,
    'combustivel_entrada' => 8,
];

$semanal = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 1400),
    [$veiculo(1, '2026-08-01 10:00:00', 700)],
    [],
    [$devolucao(1, '2026-08-11 10:00:00')],
    [],
    1400
);
$assertValor(1000, $semanal['total_veiculos'], 'Semanal deve cobrar uma semana e tres diarias');
$assert($semanal['veiculos'][0]['ciclos_completos'] === 1, 'Semanal deve identificar um ciclo completo');
$assert($semanal['veiculos'][0]['dias_restantes'] === 3, 'Semanal deve identificar tres dias restantes');
$assert($semanal['ajuste_tipo'] === 'D', 'Semanal antecipado deve gerar credito');
$assertValor(400, $semanal['ajuste_valor'], 'Credito semanal incorreto');

$mensal = $service->calcular(
    $contrato('mes', '2026-01-15 10:00:00', 9000),
    [$veiculo(2, '2026-01-15 10:00:00', 3000)],
    [],
    [$devolucao(2, '2026-03-20 10:00:00')],
    [],
    9000
);
$assertValor(6500, $mensal['total_veiculos'], 'Mensal deve cobrar dois meses de calendario e cinco diarias de base 30');

$fimDoMes = $service->calcularPeriodo('2025-01-31 10:00:00', '2025-02-28 10:00:00', 'mes');
$assert($fimDoMes['ciclos_completos'] === 1 && $fimDoMes['dias_restantes'] === 0, 'Mes civil iniciado no dia 31 deve vencer no ultimo dia de fevereiro');

$anual = $service->calcular(
    $contrato('ano', '2024-01-01 10:00:00', 73000),
    [$veiculo(3, '2024-01-01 10:00:00', 36500)],
    [],
    [$devolucao(3, '2025-03-03 10:00:00')],
    [],
    73000
);
$assertValor(42600, $anual['total_veiculos'], 'Anual deve cobrar um ano de calendario e 61 diarias de base 365');

$menosDeUmDia = $service->calcular(
    $contrato('semana', '2026-08-11 10:00:00', 700),
    [$veiculo(4, '2026-08-11 10:00:00', 700)],
    [],
    [$devolucao(4, '2026-08-12 09:59:59')],
    [],
    700
);
$assertValor(0, $menosDeUmDia['total_veiculos'], 'Periodo inferior a 24 horas nao deve gerar diaria completa');

$comDesconto = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 1260, 140),
    [$veiculo(5, '2026-08-01 10:00:00', 700)],
    [],
    [$devolucao(5, '2026-08-08 10:00:00')],
    [],
    1260
);
$assertValor(70, $comDesconto['desconto_aplicado'], 'Desconto deve acompanhar a proporcao do aluguel utilizado');
$assertValor(630, $comDesconto['total_final'], 'Total com desconto proporcional incorreto');

$parcial = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 2800),
    [$veiculo(6, '2026-08-01 10:00:00', 700), $veiculo(7, '2026-08-01 10:00:00', 700)],
    [],
    [$devolucao(6, '2026-08-08 10:00:00')],
    [['valor_total' => 50]],
    2800
);
$assert(!$parcial['encerramento_final'], 'Devolucao parcial nao deve encerrar o contrato');
$assertValor(50, $parcial['total_final'], 'Devolucao parcial deve lancar somente adicionais');
$assert($parcial['ajuste_tipo'] === 'R', 'Adicional da devolucao parcial deve gerar receita');

$historicoOculto = $service->calcular(
    $contrato('semana', '2025-12-10 05:20:00', 1890),
    [
        array_merge($veiculo(8, '2025-12-10 05:20:00', 630), ['data_entrada' => '2025-12-12 12:00:00']),
        $veiculo(9, '2025-12-12 12:00:00', 630),
        $veiculo(10, '2026-06-19 08:40:56', 630),
    ],
    [],
    [$devolucao(9, '2026-08-11 12:00:00')],
    [],
    1890
);
$assert(count($historicoOculto['veiculos']) === 1, 'Preview deve listar somente o veiculo marcado na devolucao atual');
$assert(($historicoOculto['veiculos'][0]['id_contrato_veiculo'] ?? 0) === 9, 'Preview exibiu um veiculo historico em vez do selecionado');
$assert(count($historicoOculto['veiculos_historico_calculo']) === 2, 'Snapshot deve preservar os periodos historicos usados no calculo');

$finalComHistorico = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 1400),
    [
        array_merge($veiculo(11, '2026-08-01 10:00:00', 700), ['data_entrada' => '2026-08-08 10:00:00']),
        $veiculo(12, '2026-08-08 10:00:00', 700),
    ],
    [],
    [$devolucao(12, '2026-08-15 10:00:00')],
    [],
    1400
);
$assert($finalComHistorico['encerramento_final'], 'Ultimo veiculo selecionado deve encerrar o contrato');
$assert(count($finalComHistorico['veiculos']) === 1, 'Encerramento final deve exibir somente o veiculo devolvido agora');
$assertValor(1400, $finalComHistorico['total_veiculos'], 'Historico oculto deve continuar compondo o total final');

$valoresAjustadosKl = $devolucao(13, '2026-08-08 10:00:00');
$valoresAjustadosKl['valores_ajustados'] = [
    'valor_plano_km_livre' => 1000,
    'seguro_carro' => 1,
    'valor_seguro_carro' => 100,
    'seguro_terceiros' => 1,
    'valor_seguro_terceiros' => 50,
];
$ajusteKl = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 700),
    [$veiculo(13, '2026-08-01 10:00:00', 700)],
    [],
    [$valoresAjustadosKl],
    [],
    700
);
$assertValor(1000, $ajusteKl['total_veiculos'], 'Preview deve usar o valor de plano ajustado');
$assertValor(150, $ajusteKl['total_seguros'], 'Preview deve usar ativacao e valores de seguros ajustados');
$assertValor(1150, $ajusteKl['total_final'], 'Total final deve refletir os valores ajustados antes da persistencia');

$valoresAjustadosKmc = $devolucao(14, '2026-08-08 10:00:00');
$valoresAjustadosKmc['odometro_entrada'] = 1800;
$valoresAjustadosKmc['valores_ajustados'] = [
    'valor_plano_km_controlado' => 700,
    'km_franquia' => 700,
    'valor_km_excedente' => 2,
];
$ajusteKmc = $service->calcular(
    $contrato('semana', '2026-08-01 10:00:00', 700),
    [$veiculo(14, '2026-08-01 10:00:00', 700, [
        'plano' => 'KMC',
        'valor_plano_km_livre' => 0,
        'valor_plano_km_controlado' => 700,
        'km_franquia' => 1000,
        'valor_km_excedente' => 1,
    ])],
    [],
    [$valoresAjustadosKmc],
    [],
    700
);
$assertValor(200, $ajusteKmc['total_km'], 'Km controlado deve usar franquia e valor excedente ajustados');
$assert(($ajusteKmc['veiculos'][0]['km']['franquia'] ?? 0) === 700, 'Preview deve exibir a franquia ajustada');
$assert(($ajusteKmc['veiculos'][0]['km']['excedente'] ?? 0) === 100, 'Preview deve exibir o excedente calculado com a nova franquia');

if ($falhas) {
    fwrite(STDERR, implode(PHP_EOL, $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - calculo proporcional, devolucao parcial e valores ajustados\n";
