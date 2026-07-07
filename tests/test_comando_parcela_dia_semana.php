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

$datasSeg = ComandoParcela::calcularDatasVencimento('Seg', '2026-06-29', 1);
assertComandoParcelaSame(['2026-07-06'], $datasSeg, 'Seg com data base em segunda deve vencer na proxima segunda.');

$datasQua = ComandoParcela::calcularDatasVencimento('Qua', '2026-06-29', 1);
assertComandoParcelaSame(['2026-07-01'], $datasQua, 'Qua deve ajustar para a proxima quarta.');

$numParcelasW4Seg = ComandoParcela::calcularNumParcelasAutomatico('w4-Seg', '2026-06-29', '2026-07-06');
assertComandoParcelaSame(4, $numParcelasW4Seg, 'w4-Seg deve continuar gerando 4 parcelas.');

$numParcelasPrazos = ComandoParcela::calcularNumParcelasAutomatico('7/14/21/28', '2026-06-29', '2026-07-06');
assertComandoParcelaSame(4, $numParcelasPrazos, 'Prazos fixos devem continuar gerando uma parcela por prazo.');

echo "Teste de comandos de parcelas por dia da semana passou.\n";
