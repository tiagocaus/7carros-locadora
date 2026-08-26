<?php

/**
 * Regressao: uma promocao existente preserva o snapshot por padrao e so e
 * recalculada quando o usuario solicita explicitamente a reaplicacao.
 *
 * Execute: php tests/test_locacao_promocao_reaplicacao.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\LocacoesController;
use App\Models\Model;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$db = Model::sharedMysqli();
$filial = $db->query("SELECT id, chave FROM matrizes_filiais WHERE chave <> '' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$filial) {
    fwrite(STDERR, "SKIP: nenhuma filial disponivel.\n");
    exit(0);
}

$_SESSION['chave'] = $filial['chave'];
$codigo = 'TRP' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
$falhas = 0;

$check = static function (string $rotulo, mixed $atual, mixed $esperado) use (&$falhas): void {
    $ok = $atual === $esperado;
    echo ($ok ? 'PASS' : 'FAIL') . " {$rotulo}\n";
    if (!$ok) {
        echo '  esperado=' . var_export($esperado, true) . ' atual=' . var_export($atual, true) . "\n";
        $falhas++;
    }
};

$metodoEdicao = new ReflectionMethod(LocacoesController::class, 'aplicarPromocaoEdicao');
$metodoEdicao->setAccessible(true);
$metodoNova = new ReflectionMethod(LocacoesController::class, 'aplicarPromocaoNova');
$metodoNova->setAccessible(true);
$controller = new LocacoesController();

$db->begin_transaction();
try {
    $stmt = $db->prepare("INSERT INTO grupos (chave,nome,descricao) VALUES (?,?,'Fixture promocao locacao')");
    $grupoNome = 'Grupo ' . $codigo;
    $stmt->bind_param('ss', $filial['chave'], $grupoNome);
    $stmt->execute();
    $grupoId = $db->insert_id;

    $stmt = $db->prepare("INSERT INTO promocoes (chave,codigo,nome,validade,dias,valor,tipo,onde_exibir,status,todos_grupos) VALUES (?,?,?,NULL,3,910,'DFIX','SIS','A',1)");
    $nome = 'Promocao reaplicacao';
    $stmt->bind_param('sss', $filial['chave'], $codigo, $nome);
    $stmt->execute();
    $promocaoId = $db->insert_id;

    $filialId = (int) $filial['id'];
    $stmt = $db->prepare('INSERT INTO promocoes_filiais (id_promocao,id_matriz_filial,chave) VALUES (?,?,?)');
    $stmt->bind_param('iis', $promocaoId, $filialId, $filial['chave']);
    $stmt->execute();
    $stmt = $db->prepare('INSERT INTO promocoes_valores_filiais (chave,id_promocao,id_matriz_filial,valor) VALUES (?,?,?,910)');
    $stmt->bind_param('sii', $filial['chave'], $promocaoId, $filialId);
    $stmt->execute();

    $locacao = [
        'promocao_codigo' => $codigo,
        'valor_desconto' => '210.00',
        'id_grupo' => $grupoId,
    ];
    $base = [
        'promocao_codigo' => $codigo,
        'valor_desconto' => '910,00',
        'id_grupo' => $grupoId,
        'id_matriz_filial_retirada' => $filialId,
        'dias' => 10,
    ];

    $semReaplicar = array_merge($base, ['reaplicar_promocao' => '0']);
    $recalcular = $metodoEdicao->invokeArgs($controller, [&$semReaplicar, $locacao]);
    $check('salvamento comum preserva snapshot', $recalcular, false);
    $check('desconto historico permanece', (float) $semReaplicar['valor_desconto'], 210.0);

    $comReaplicacao = array_merge($base, ['reaplicar_promocao' => '1']);
    $recalcular = $metodoEdicao->invokeArgs($controller, [&$comReaplicacao, $locacao]);
    $check('clique explicito solicita recalculo', $recalcular, true);
    $metodoNova->invokeArgs($controller, [&$comReaplicacao, 4000.0]);
    $check('reaplicacao usa valor vigente', (float) $comReaplicacao['valor_desconto'], 910.0);

    $manual = array_merge($base, [
        'promocao_codigo' => '',
        'valor_desconto' => '700,00',
        'reaplicar_promocao' => '0',
    ]);
    $recalcular = $metodoEdicao->invokeArgs($controller, [&$manual, $locacao]);
    $check('codigo vazio nao recalcula promocao', $recalcular, false);
    $check('codigo vazio preserva desconto manual', $manual['valor_desconto'], '700,00');

    $db->rollback();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}

exit($falhas > 0 ? 1 : 0);
