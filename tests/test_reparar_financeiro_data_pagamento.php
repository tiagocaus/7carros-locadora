<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Financeiro;
use App\Models\Model;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$chave = 'TESTE_REPARO_' . strtoupper(bin2hex(random_bytes(8)));
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

$model = new Financeiro();
$id = null;

try {
    $id = $model->criar([
        'chave' => $chave,
        'tipo' => 'D',
        'pago' => 'N',
        'descricao' => 'Teste script reparo data pagamento',
        'data_criada' => '2026-07-01',
        'data_venci' => '2026-07-31',
        'valor_subtotal' => 10,
    ]);

    $mysqli = Model::sharedMysqli();
    $forcarInconsistencia = $mysqli->prepare("
        UPDATE financeiro
        SET data_pago = '2026-07-23'
        WHERE id = ? AND chave = ?
    ");
    $forcarInconsistencia->bind_param('is', $id, $chave);
    $forcarInconsistencia->execute();
    $forcarInconsistencia->close();

    $comando = sprintf(
        '%s %s --env=development --tenant=%s --apply --confirm=NORMALIZAR_DATA_PAGO_PENDENTES 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(APP_ROOT . '/scripts/reparar-financeiro-data-pagamento.php'),
        escapeshellarg($chave)
    );

    exec($comando, $saida, $codigo);
    if ($codigo !== 0) {
        throw new RuntimeException("Script de reparo falhou:\n" . implode("\n", $saida));
    }

    $lancamento = $model->buscarPorId($id);
    if ($lancamento['pago'] !== 'N' || $lancamento['data_pago'] !== null) {
        throw new RuntimeException('Script nao normalizou a data do lancamento pendente');
    }

    $saidaTexto = implode("\n", $saida);
    if (!str_contains($saidaTexto, 'TOTAL_APLICADO | registros=1 | restantes=0')) {
        throw new RuntimeException("Resumo inesperado do script:\n{$saidaTexto}");
    }

    echo "OK: script normalizou somente o tenant de teste e terminou sem pendencias\n";
} finally {
    if ($id !== null) {
        $model->deletar($id);
    }
}
