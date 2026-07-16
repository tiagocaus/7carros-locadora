<?php

/**
 * Teste: devolucao de contrato com acao criar_os deve gerar uma OS preenchida.
 *
 * Execute: php tests/test_contrato_devolucao_criar_os.php
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
use App\Models\Manutencao;
use App\Models\VeiculoDisponibilidadeSync;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$veiculoId = null;
$manutencaoId = null;

function checkDevolucaoCriarOs(bool $condicao, string $mensagem): void
{
    global $falhas, $sucessos;

    echo '   ' . ($condicao ? 'PASS' : 'FAIL') . " {$mensagem}\n";
    if ($condicao) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

echo "=== Teste devolucao de contrato cria OS ===\n";

try {
    $controllerSource = file_get_contents(APP_ROOT . '/app/Controllers/ContratosController.php');
    $viewSource = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/devolver.php');

    checkDevolucaoCriarOs(
        $controllerSource !== false
            && str_contains($controllerSource, "'motivo' => \$observacao")
            && str_contains($controllerSource, "'data_enviado' => \$dataEntradaEfetiva")
            && str_contains($controllerSource, "'odo_enviado' => \$odometroEntrada")
            && str_contains($controllerSource, "'tanque_enviado' => \$combustivelEntrada"),
        'controller vincula os dados da devolucao a OS'
    );
    checkDevolucaoCriarOs(
        $controllerSource !== false
            && str_contains($controllerSource, "return_page.inform_os_reason")
            && str_contains($controllerSource, 'mb_strlen($observacao) > 255'),
        'backend exige e limita o motivo antes de processar o lote'
    );
    checkDevolucaoCriarOs(
        $viewSource !== false
            && str_contains($viewSource, 'observacao.required = obrigatoria')
            && str_contains($viewSource, "action: 'openAlert', message: i18n.informOsReason")
            && str_contains($viewSource, 'maxlength="255"'),
        'tela exige observacao para criar OS sem alert nativo'
    );

    $veiculoId = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => 'TO' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6),
        'marca' => 'Teste',
        'modelo' => 'Devolucao OS',
        'disponibilidade' => 'D',
        'odometro' => '45678',
        'tanque_fracao' => '6',
    ]);

    $motivo = 'Ruido no motor identificado na devolucao';
    $dataEnvio = '2026-07-16 14:30:00';
    $manutencaoModel = new Manutencao();
    $manutencaoId = $manutencaoModel->criar([
        'chave' => $chave,
        'id_veiculo' => $veiculoId,
        'data_enviado' => $dataEnvio,
        'odo_enviado' => 45678,
        'tanque_enviado' => 6,
        'motivo' => $motivo,
        'status' => 'C',
    ]);
    (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo($veiculoId, 'M');

    $manutencao = Database::fetchOne(
        'SELECT os, id_veiculo, data_enviado, odo_enviado, tanque_enviado, motivo, status '
        . 'FROM manutencoes WHERE id = ? AND chave = ?',
        [$manutencaoId, $chave]
    );
    $disponibilidade = Database::fetchColumn(
        'SELECT disponibilidade FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    );

    checkDevolucaoCriarOs(!empty($manutencao['os']), 'OS recebe codigo automatico');
    checkDevolucaoCriarOs(($manutencao['status'] ?? null) === 'C', 'OS nasce com status Criada');
    checkDevolucaoCriarOs((int) ($manutencao['id_veiculo'] ?? 0) === $veiculoId, 'OS fica vinculada ao veiculo');
    checkDevolucaoCriarOs(($manutencao['motivo'] ?? null) === $motivo, 'observacao preenche motivo do envio a oficina');
    checkDevolucaoCriarOs(($manutencao['data_enviado'] ?? null) === $dataEnvio, 'data da devolucao preenche data de envio');
    checkDevolucaoCriarOs((int) ($manutencao['odo_enviado'] ?? 0) === 45678, 'odometro da devolucao preenche odometro de envio');
    checkDevolucaoCriarOs((int) ($manutencao['tanque_enviado'] ?? -1) === 6, 'tanque da devolucao preenche tanque de envio');
    checkDevolucaoCriarOs($disponibilidade === 'O', 'veiculo fica com disponibilidade Oficina');
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    if ($manutencaoId !== null) {
        Database::execute('DELETE FROM manutencoes WHERE id = ? AND chave = ?', [$manutencaoId, $chave]);
    }
    if ($veiculoId !== null) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$veiculoId, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
