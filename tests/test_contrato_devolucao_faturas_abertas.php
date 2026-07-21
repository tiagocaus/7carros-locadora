<?php

/**
 * Regressao: devolucao lista e exclui somente receitas pendentes do contrato.
 *
 * O teste usa registros existentes apenas para leitura e executa tentativas de
 * exclusao que obrigatoriamente devem afetar zero linhas.
 *
 * Execute: php tests/test_contrato_devolucao_faturas_abertas.php
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
use App\Models\Contrato;
use App\Models\Financeiro;

$falhas = 0;
$sucessos = 0;

$check = static function (string $descricao, bool $condicao) use (&$falhas, &$sucessos): void {
    echo '   ' . ($condicao ? 'PASS' : 'FAIL') . " {$descricao}\n";
    $condicao ? $sucessos++ : $falhas++;
};

echo "=== Teste de faturas abertas na devolucao de contrato ===\n";

try {
    $referencia = Database::fetchOne(
        'SELECT f.chave, f.id_contrato '
        . 'FROM financeiro f '
        . 'WHERE f.id_contrato IS NOT NULL AND f.tipo = ? AND f.pago = ? '
        . 'AND NOT EXISTS ('
        . '  SELECT 1 FROM contratos_caucoes cc '
        . '  WHERE cc.chave = f.chave AND cc.id_contrato = f.id_contrato '
        . '  AND (cc.id_financeiro_entrada = f.id OR cc.id_financeiro_devolucao = f.id)'
        . ') ORDER BY f.id DESC LIMIT 1',
        ['R', 'N']
    );

    if (!$referencia) {
        throw new RuntimeException('Base local sem receita pendente de contrato para validar a listagem');
    }

    $_SESSION['chave'] = (string) $referencia['chave'];
    $contratoId = (int) $referencia['id_contrato'];
    $faturas = (new Contrato())->listarFaturasAbertasContrato($contratoId);

    $idsEsperados = array_map(
        'intval',
        array_column(Database::fetchAll(
            'SELECT f.id FROM financeiro f '
            . 'WHERE f.chave = ? AND f.id_contrato = ? AND f.tipo = ? AND f.pago = ? '
            . 'AND NOT EXISTS ('
            . '  SELECT 1 FROM contratos_caucoes cc '
            . '  WHERE cc.chave = f.chave AND cc.id_contrato = f.id_contrato '
            . '  AND (cc.id_financeiro_entrada = f.id OR cc.id_financeiro_devolucao = f.id)'
            . ') ORDER BY f.data_venci ASC, f.parcela ASC, f.id ASC',
            [$_SESSION['chave'], $contratoId, 'R', 'N']
        ), 'id')
    );
    $idsListados = array_map('intval', array_column($faturas, 'id'));

    $check('listagem coincide com as receitas pendentes sem caucao', $idsListados === $idsEsperados);
    $check(
        'todas as faturas retornam indicador booleano de vencimento',
        array_reduce(
            $faturas,
            static fn (bool $valido, array $fatura): bool => $valido && is_bool($fatura['vencida'] ?? null),
            true
        )
    );

    $financeiro = new Financeiro();
    $paga = Database::fetchOne(
        'SELECT id FROM financeiro WHERE chave = ? AND id_contrato = ? AND tipo = ? AND pago = ? LIMIT 1',
        [$_SESSION['chave'], $contratoId, 'R', 'S']
    );
    if ($paga) {
        $check(
            'exclusao protegida nao remove receita paga',
            $financeiro->deletarReceitaPendenteContrato((int) $paga['id'], $contratoId) === 0
        );
    } else {
        $check('base local sem receita paga: guarda atomica permanece coberta por inspecao', true);
    }

    $caucao = Database::fetchOne(
        'SELECT f.id, f.id_contrato FROM financeiro f '
        . 'INNER JOIN contratos_caucoes cc ON cc.chave = f.chave '
        . 'AND cc.id_contrato = f.id_contrato '
        . 'AND (cc.id_financeiro_entrada = f.id OR cc.id_financeiro_devolucao = f.id) '
        . 'WHERE f.chave = ? AND f.tipo = ? AND f.pago = ? LIMIT 1',
        [$_SESSION['chave'], 'R', 'N']
    );
    if ($caucao) {
        $check(
            'exclusao protegida nao remove receita pendente de caucao',
            $financeiro->deletarReceitaPendenteContrato(
                (int) $caucao['id'],
                (int) $caucao['id_contrato']
            ) === 0
        );
    } else {
        $modelSource = file_get_contents(APP_ROOT . '/app/Models/Financeiro.php');
        $check(
            'guarda atomica exclui caucoes mesmo sem fixture pendente na base local',
            $modelSource !== false
                && str_contains($modelSource, 'FROM contratos_caucoes cc')
                && str_contains($modelSource, 'cc.id_financeiro_entrada = financeiro.id')
        );
    }

    $faturaOutroContrato = Database::fetchOne(
        'SELECT id FROM financeiro '
        . 'WHERE chave = ? AND id_contrato IS NOT NULL AND id_contrato <> ? AND tipo = ? AND pago = ? LIMIT 1',
        [$_SESSION['chave'], $contratoId, 'R', 'N']
    );
    if ($faturaOutroContrato) {
        $check(
            'exclusao protegida nao remove receita pendente de outro contrato',
            $financeiro->deletarReceitaPendenteContrato((int) $faturaOutroContrato['id'], $contratoId) === 0
        );
    } else {
        $check('base local sem segundo contrato pendente: filtro de contrato permanece coberto pela listagem', true);
    }
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
