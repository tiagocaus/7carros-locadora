<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ContratoEncerramentoService;

$service = new ContratoEncerramentoService();
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS - {$message}\n";
};
$veiculo = static fn(int $id, string $saida, array $extra = []): array => array_merge([
    'id' => $id, 'data_saida' => $saida, 'plano' => 'KL', 'valor_plano_km_livre' => 750,
], $extra);
$calcular = static function (string $contagem, string $saida, string $entrada, string $modo = 'integral') use ($service, $veiculo): array {
    return $service->calcular(['contagem' => $contagem, 'data_ini' => $saida, 'total_pagar' => 9000],
        [$veiculo(1, $saida)], [], [['id_contrato_veiculo' => 1, 'data_entrada' => $entrada]], [], 19500, $modo);
};
foreach ([
    ['semana', '2026-03-12 17:26:00', '2026-09-08 16:30:00', 26, 19500],
    ['semana', '2026-01-01 10:00:00', '2026-01-08 10:00:00', 1, 750],
    ['semana', '2026-01-01 10:00:00', '2026-01-08 11:00:00', 1, 750],
    ['semana', '2026-01-01 10:00:00', '2026-01-09 10:00:00', 2, 1500],
    ['semana', '2026-01-01 10:00:00', '2026-01-02 09:59:59', 0, 0],
    ['semana', '2026-01-01 10:00:00', '2026-01-02 10:00:00', 1, 750],
    ['mes', '2026-01-31 10:00:00', '2026-02-28 11:00:00', 1, 750],
    ['mes', '2026-01-31 10:00:00', '2026-03-01 10:00:00', 2, 1500],
    ['mes', '2026-01-31 10:00:00', '2026-03-31 10:00:00', 2, 1500],
    ['ano', '2024-02-29 10:00:00', '2025-02-28 11:00:00', 1, 750],
    ['ano', '2024-02-29 10:00:00', '2025-03-01 10:00:00', 2, 1500],
    ['ano', '2024-02-29 10:00:00', '2028-02-29 10:00:00', 4, 3000],
] as [$contagem, $saida, $entrada, $ciclos, $total]) {
    $r = $calcular($contagem, $saida, $entrada);
    $check($r['veiculos'][0]['ciclos_cobrados'] === $ciclos && abs($r['total_final'] - $total) < .001,
        "{$contagem}: {$saida} ate {$entrada}");
}
$r = $calcular('semana', '2026-03-12 17:26:00', '2026-09-08 16:30:00');
$check($r['ajuste_tipo'] === 'N' && $r['ajuste_valor'] === 0.0, 'Caso real integral sem ajuste');
$check($r['veiculos'][0]['dias_equivalentes'] === 179 && $r['veiculos'][0]['dias_equivalentes_cobrados'] === 182, 'Preserva uso real separado da cobranca');
$r = $calcular('semana', '2026-03-12 17:26:00', '2026-09-08 16:30:00', 'proporcional');
$check($r['total_final'] === 19178.57 && $r['ajuste_valor'] === 321.43, 'Caso real proporcional preservado');
$r = $calcular('dia', '2026-01-01 10:00:00', '2026-01-02 11:00:00');
$check($r['modo_cobranca'] === 'proporcional' && $r['total_final'] === 750.0, 'Diarias preservadas');
try {
    $calcular('semana', '2026-01-01', '2026-01-09', 'invalido');
    throw new RuntimeException('Modalidade invalida aceita');
} catch (InvalidArgumentException $e) {
    $check(true, 'Modalidade invalida rejeitada');
}
$contrato = ['contagem' => 'semana', 'data_ini' => '2026-01-01 10:00:00', 'total_pagar' => 1750, 'valor_desconto' => 100];
$veiculos = [$veiculo(1, $contrato['data_ini'], ['seguro_carro' => 1, 'valor_seguro_carro' => 70,
    'plano' => 'KMC', 'valor_plano_km_controlado' => 750, 'km_franquia' => 700, 'valor_km_excedente' => 1,
    'odometro_saida' => 1000])];
$taxas = [
    ['base_calculo' => 'PER', 'tipo_valor' => 'MON', 'valor_unitario' => 10, 'quantidade' => 1, 'valor_total' => 140],
    ['base_calculo' => 'VLT', 'tipo_valor' => 'POR', 'valor_unitario' => 10, 'quantidade' => 1, 'valor_total' => 70],
];
$devolucoes = [['id_contrato_veiculo' => 1, 'data_entrada' => '2026-01-09 10:00:00', 'odometro_entrada' => 1900]];
$r = $service->calcular($contrato, $veiculos, $taxas, $devolucoes, [], 0, 'integral');
$check($r['total_seguros'] === 140.0 && $r['total_taxas_contrato'] === 304.0 && $r['desconto_aplicado'] === 100.0, 'Seguros, taxas PER/percentuais e desconto seguem base cobrada');
$check($r['veiculos'][0]['km']['franquia'] == 800 && $r['total_km'] === 100.0, 'Franquia continua baseada em oito dias reais');
$veiculos[] = $veiculo(2, $contrato['data_ini']);
$r = $service->calcular($contrato, $veiculos, [], $devolucoes, [], 0, 'integral');
$check(!$r['encerramento_final'] && $r['total_final'] === 100.0, 'Devolucao parcial cobra somente adicionais');
$veiculos[0]['data_entrada'] = '2026-01-09 10:00:00';
$r = $service->calcular($contrato, $veiculos, [], [['id_contrato_veiculo' => 2, 'data_entrada' => '2026-01-09 10:00:00']], [], 0, 'integral');
$check(count($r['veiculos']) === 1 && count($r['veiculos_historico_calculo']) === 2 && $r['total_veiculos'] === 3000.0, 'Ultima devolucao inclui vinculos historicos');
$snapshot = json_decode(json_encode($r), true);
$check($snapshot['modo_cobranca'] === 'integral' && $snapshot['veiculos'][0]['ciclos_cobrados'] === 2, 'Snapshot preserva modalidade e ciclos cobrados');
