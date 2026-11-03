<?php

require_once __DIR__ . '/../vendor/autoload.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\ComandoParcela;
use App\Models\Contrato;
use App\Models\Model;

function assertParcelasDuracao(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

foreach (['2026-11-01', '2026-12-15', '2027-01-15'] as $vencimento) {
    assertParcelasDuracao(6, ComandoParcela::calcularNumParcelasAutomatico('1-6', $vencimento, '2027-05-01T08:00', '2026-11-01T08:00'), 'Vencimento nao deve alterar a quantidade.');
}
assertParcelasDuracao(5, ComandoParcela::calcularNumParcelasAutomatico('1-6', '2026-12-15', '2027-05-01'), 'Chamada antiga deve continuar compativel.');
assertParcelasDuracao(2, ComandoParcela::calcularNumParcelasAutomatico('1-6', '2026-12-15', '2027-01-01', '2026-11-01'), 'Contrato menor deve gerar duas parcelas.');
assertParcelasDuracao(6, ComandoParcela::calcularNumParcelasAutomatico('1-6', '2026-12-15', '2027-11-01', '2026-11-01'), 'Respeitar o maximo.');
assertParcelasDuracao(3, ComandoParcela::calcularNumParcelasAutomatico('3-6', '2026-12-15', '2026-12-01', '2026-11-01'), 'Respeitar o minimo.');
assertParcelasDuracao(3, ComandoParcela::calcularNumParcelasAutomatico('1-6', '2026-12-15', '2027-01-02', '2026-11-01'), 'Fracao de mes deve arredondar para cima.');
assertParcelasDuracao(1, ComandoParcela::calcularNumParcelasAutomatico('1-6', '2027-12-15', '2027-06-01', '2027-05-01'), 'Periodo renovado de um mes deve gerar uma parcela.');
foreach (['0', '30', 'Seg', 'w4', 'w4-Seg', '30/60/90', 'd15'] as $comando) {
    assertParcelasDuracao(
        ComandoParcela::calcularNumParcelasAutomatico($comando, '2026-12-15', '2027-05-01'),
        ComandoParcela::calcularNumParcelasAutomatico($comando, '2026-12-15', '2027-05-01', '2026-11-01'),
        "Preservar comando {$comando}."
    );
}

// Integracao read-only: utilizar somente o MySQL local e um comando ja cadastrado.
$_ENV['APP_ENV'] = 'development';
assertParcelasDuracao('localhost', Database::env('DB_HOST'), 'Integracao exige banco local.');
$comando = Model::sharedMysqli()->query("SELECT id, chave FROM formas_pagamento_comandos WHERE comando = '1-6' LIMIT 1")->fetch_assoc();
if (!$comando) {
    throw new RuntimeException('Cadastre o comando 1-6 no banco local para executar a integracao.');
}
// Apenas preview: nenhuma gravacao, cobranca ou mensagem e executada.
$_SESSION['chave'] = $comando['chave'];

final class ContratoParcelasDuracaoFake extends Contrato
{
    public function buscarPorId(int $id): ?array
    {
        return ['id' => $id, 'data_ini' => '2026-11-01 08:00:00', 'data_fim' => '2027-05-01 08:00:00', 'total_pagar' => '18900.00'];
    }
}

$model = new ContratoParcelasDuracaoFake();
$config = [
    'id_comando_parcela' => (int) $comando['id'],
    'primeiro_vencimento' => '2026-12-15',
    'total_pagar' => 18900.00,
    'valor_desconto' => 100,
];
$datas = ['2026-12-15', '2027-01-15', '2027-02-15', '2027-03-15', '2027-04-15', '2027-05-15'];
foreach ([
    $model->gerarPreviewParcelas(0, $config + ['data_ini' => '2026-11-01T08:00', 'data_fim' => '2027-05-01T08:00']),
    $model->gerarPreviewParcelas(1, $config),
] as $preview) {
    assertParcelasDuracao(6, count($preview['parcelas']), 'Preview deve gerar seis parcelas.');
    assertParcelasDuracao($datas, array_column($preview['parcelas'], 'data_venci'), 'Preview deve manter as datas mensais.');
    assertParcelasDuracao(18900.0, array_sum(array_column($preview['parcelas'], 'valor_total')), 'Soma deve preservar o total liquido.');
    assertParcelasDuracao(3150.0, $preview['parcelas'][0]['valor_total'], 'Valor deve ser redistribuido em seis parcelas.');
}
$periodoRenovado = ['data_ini' => '2027-05-01', 'data_fim' => '2027-06-01'];
$preview = $model->gerarPreviewParcelas(1, array_replace($config, $periodoRenovado));
assertParcelasDuracao(1, count($preview['parcelas']), 'Renovacao/regularizacao deve usar o periodo informado.');
$preview = $model->gerarPreviewParcelas(1, $config + ['num_parcelas' => 3]);
assertParcelasDuracao(3, count($preview['parcelas']), 'Quantidade explicita continua prioritaria.');
// Para stateless, o total vem do payload e a ultima parcela absorve os centavos.
$preview = $model->gerarPreviewParcelas(0, array_replace($config, ['total_pagar' => 2000, 'num_parcelas' => 6]));
assertParcelasDuracao(2000.0, array_sum(array_column($preview['parcelas'], 'valor_total')), 'Arredondamento deve fechar o total.');
assertParcelasDuracao(333.35, $preview['parcelas'][5]['valor_total'], 'Ultima parcela deve absorver o arredondamento.');
assertParcelasDuracao(5, count($model->gerarPreviewParcelas(0, $config + ['data_fim' => '2027-05-01'])['parcelas']), 'Stateless antigo sem inicio deve permanecer compativel.');

echo "OK: quantidade mensal pela duracao, previews e compatibilidade.\n";
