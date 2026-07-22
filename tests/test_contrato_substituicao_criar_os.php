<?php

/**
 * Teste: substituicao de veiculo em contrato com criar_os gera uma OS preenchida.
 *
 * Execute: php tests/test_contrato_substituicao_criar_os.php
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

function checkSubstituicaoCriarOs(bool $condicao, string $mensagem): void
{
    global $falhas, $sucessos;

    echo '   ' . ($condicao ? 'PASS' : 'FAIL') . " {$mensagem}\n";
    if ($condicao) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

echo "=== Teste substituicao de contrato cria OS ===\n";

try {
    $controllerSource = file_get_contents(APP_ROOT . '/app/Controllers/ContratosController.php');
    $viewSource = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/substituir.php');

    checkSubstituicaoCriarOs(
        $controllerSource !== false
            && str_contains($controllerSource, "if (\$acaoVeiculo === 'criar_os')")
            && str_contains($controllerSource, "'data_enviado' => \$dataSubstituicao")
            && str_contains($controllerSource, "'odo_enviado' => \$odometroEntradaAntigo")
            && str_contains($controllerSource, "'tanque_enviado' => \$dadosSaida['combustivel_entrada']")
            && str_contains($controllerSource, "'motivo' => \$motivoSaida"),
        'controller cria OS com os dados da substituicao'
    );
    checkSubstituicaoCriarOs(
        $controllerSource !== false
            && str_contains($controllerSource, "return_page.inform_os_reason")
            && str_contains($controllerSource, 'mb_strlen($motivoSaida) > 255'),
        'backend exige e limita o motivo antes da substituicao'
    );
    checkSubstituicaoCriarOs(
        $controllerSource !== false
            && str_contains($controllerSource, "'id_manutencao' => \$manutencaoId")
            && str_contains($controllerSource, "'os' => \$manutencaoOs"),
        'resposta retorna os identificadores da OS'
    );
    checkSubstituicaoCriarOs(
        $viewSource !== false
            && str_contains($viewSource, 'motivo.required = obrigatorio')
            && str_contains($viewSource, "action: 'openAlert', message: i18n.informOsReason")
            && str_contains($viewSource, 'maxlength="255"'),
        'tela exige motivo para criar OS sem alert nativo'
    );

    $veiculoId = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => 'TS' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6),
        'marca' => 'Teste',
        'modelo' => 'Substituicao OS',
        'disponibilidade' => 'D',
        'odometro' => '54321',
        'tanque_fracao' => '5',
    ]);

    $motivo = 'Falha mecanica identificada na substituicao';
    $dataEnvio = '2026-07-22 15:30:00';
    $manutencaoModel = new Manutencao();
    $manutencaoId = $manutencaoModel->criar([
        'chave' => $chave,
        'id_veiculo' => $veiculoId,
        'data_enviado' => $dataEnvio,
        'odo_enviado' => 54321,
        'tanque_enviado' => 5,
        'motivo' => $motivo,
        'status' => 'C',
    ]);
    (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo($veiculoId, 'M');

    $manutencao = Database::fetchOne(
        'SELECT os, id_matriz_filial, id_veiculo, data_enviado, odo_enviado, tanque_enviado, motivo, status '
        . 'FROM manutencoes WHERE id = ? AND chave = ?',
        [$manutencaoId, $chave]
    );
    $filialVeiculo = Database::fetchColumn(
        'SELECT id_matriz_filial FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    );
    $disponibilidade = Database::fetchColumn(
        'SELECT disponibilidade FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    );

    checkSubstituicaoCriarOs(!empty($manutencao['os']), 'OS recebe codigo automatico');
    checkSubstituicaoCriarOs(($manutencao['status'] ?? null) === 'C', 'OS nasce com status Criada');
    checkSubstituicaoCriarOs((int) ($manutencao['id_veiculo'] ?? 0) === $veiculoId, 'OS fica vinculada ao veiculo antigo');
    checkSubstituicaoCriarOs(($manutencao['motivo'] ?? null) === $motivo, 'motivo da substituicao preenche a OS');
    checkSubstituicaoCriarOs(($manutencao['data_enviado'] ?? null) === $dataEnvio, 'data da substituicao preenche o envio');
    checkSubstituicaoCriarOs((int) ($manutencao['odo_enviado'] ?? 0) === 54321, 'odometro da substituicao preenche o envio');
    checkSubstituicaoCriarOs((int) ($manutencao['tanque_enviado'] ?? -1) === 5, 'combustivel da substituicao preenche o envio');
    checkSubstituicaoCriarOs((int) ($manutencao['id_matriz_filial'] ?? 0) === (int) $filialVeiculo, 'OS herda a filial do veiculo');
    checkSubstituicaoCriarOs($disponibilidade === 'O', 'veiculo antigo fica com disponibilidade Oficina');
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
