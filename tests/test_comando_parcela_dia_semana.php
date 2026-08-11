<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\ComandoParcela;

function assertComandoParcelaSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        $expectedValue = var_export($expected, true);
        $actualValue = var_export($actual, true);
        throw new RuntimeException("{$message} Esperado: {$expectedValue} Obtido: {$actualValue}");
    }
}

$seg = ComandoParcela::parseComando('Seg');
assertComandoParcelaSame('dias_semana', $seg['tipo'], 'Seg deve ser reconhecido como dia da semana.');
assertComandoParcelaSame([1], $seg['opcoes'], 'Seg deve permitir apenas uma parcela.');
assertComandoParcelaSame('Seg', $seg['dia_semana'], 'Seg deve registrar o dia unico do comando.');

$listaDias = ComandoParcela::parseComando('Seg,Qua,Sex');
assertComandoParcelaSame('desconhecido', $listaDias['tipo'], 'Lista de dias com virgula nao deve ser comando valido.');

$w4Seg = ComandoParcela::parseComando('w4-Seg');
assertComandoParcelaSame('semanal_dia', $w4Seg['tipo'], 'w4-Seg deve continuar valido.');
assertComandoParcelaSame(4, $w4Seg['max'], 'w4-Seg deve manter 4 parcelas.');

$w4Invalido = ComandoParcela::parseComando('w4-ABC');
assertComandoParcelaSame('desconhecido', $w4Invalido['tipo'], 'w4-ABC deve ser invalido.');

$numParcelas = ComandoParcela::calcularNumParcelasAutomatico('Seg', '2026-06-29', '2026-07-06');
assertComandoParcelaSame(1, $numParcelas, 'Seg nao deve parcelar pela quantidade de segundas no periodo.');

$datasNoProprioDia = [
    'Seg' => '2026-06-29',
    'Ter' => '2026-06-30',
    'Qua' => '2026-07-01',
    'Qui' => '2026-07-02',
    'Sex' => '2026-07-03',
    'Sab' => '2026-07-04',
    'Dom' => '2026-07-05',
];
foreach ($datasNoProprioDia as $comando => $dataBase) {
    assertComandoParcelaSame(
        [$dataBase],
        ComandoParcela::calcularDatasVencimento($comando, $dataBase, 1),
        "{$comando} deve manter a data base quando ela ja corresponde ao dia configurado."
    );
}

$datasQua = ComandoParcela::calcularDatasVencimento('Qua', '2026-06-29', 1);
assertComandoParcelaSame(['2026-07-01'], $datasQua, 'Qua deve ajustar para a proxima quarta.');

$datasSegAposTerca = ComandoParcela::calcularDatasVencimento('Seg', '2026-06-30', 1);
assertComandoParcelaSame(['2026-07-06'], $datasSegAposTerca, 'Seg deve avancar a semana quando a segunda atual ja passou.');

$numParcelasW4Seg = ComandoParcela::calcularNumParcelasAutomatico('w4-Seg', '2026-06-29', '2026-07-06');
assertComandoParcelaSame(4, $numParcelasW4Seg, 'w4-Seg deve continuar gerando 4 parcelas.');

$datasW4Seg = ComandoParcela::calcularDatasVencimento('w4-Seg', '2026-06-29', 4);
assertComandoParcelaSame(
    ['2026-06-29', '2026-07-06', '2026-07-13', '2026-07-20'],
    $datasW4Seg,
    'w4-Seg deve manter o comportamento semanal existente.'
);

$numParcelasPrazos = ComandoParcela::calcularNumParcelasAutomatico('7/14/21/28', '2026-06-29', '2026-07-06');
assertComandoParcelaSame(4, $numParcelasPrazos, 'Prazos fixos devem continuar gerando uma parcela por prazo.');

echo "Teste de comandos de parcelas por dia da semana passou.\n";
