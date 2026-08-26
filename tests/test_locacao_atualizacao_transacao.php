<?php

/**
 * Regressao: a atualizacao da locacao confirma ou reverte todas as escritas
 * realizadas na conexao Singleton compartilhada pelos Models.
 *
 * Execute: php tests/test_locacao_atualizacao_transacao.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Model;
use App\Services\LocacaoAtualizacaoService;

$db = Model::sharedMysqli();
$db->query('CREATE TEMPORARY TABLE teste_locacao_atualizacao_transacao (id INT PRIMARY KEY, valor INT NOT NULL)');
$db->query('INSERT INTO teste_locacao_atualizacao_transacao (id, valor) VALUES (1, 10)');
$falhas = 0;

$check = static function (string $rotulo, int $esperado, int $atual) use (&$falhas): void {
    $ok = $esperado === $atual;
    echo ($ok ? 'PASS' : 'FAIL') . " {$rotulo}\n";
    if (!$ok) {
        echo "  esperado={$esperado} atual={$atual}\n";
        $falhas++;
    }
};

$valorAtual = static fn(): int => (int) $db
    ->query('SELECT valor FROM teste_locacao_atualizacao_transacao WHERE id=1')
    ->fetch_assoc()['valor'];

$transacao = new LocacaoAtualizacaoService($db);
$transacao->iniciar();
$db->query('UPDATE teste_locacao_atualizacao_transacao SET valor=20 WHERE id=1');
$transacao->reverter();
$check('rollback remove alteracao parcial', 10, $valorAtual());

$transacao->iniciar();
$db->query('UPDATE teste_locacao_atualizacao_transacao SET valor=30 WHERE id=1');
$transacao->confirmar();
$check('commit preserva alteracao concluida', 30, $valorAtual());

$db->query('DROP TEMPORARY TABLE teste_locacao_atualizacao_transacao');
exit($falhas > 0 ? 1 : 0);
