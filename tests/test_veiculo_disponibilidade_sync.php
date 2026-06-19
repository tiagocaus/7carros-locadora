<?php

/**
 * Teste: sincronizacao de disponibilidade de veiculos por locacoes/contratos ativos.
 *
 * Execute: php tests/test_veiculo_disponibilidade_sync.php
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
use App\Models\VeiculoDisponibilidadeSync;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;

$falhas = 0;
$sucessos = 0;

function checkDisponibilidade(string $label, int $veiculoId, string $esperado): void
{
    global $falhas, $sucessos, $chave;

    $atual = Database::fetchColumn(
        'SELECT disponibilidade FROM veiculos WHERE id = ? AND chave = ?',
        [$veiculoId, $chave]
    );
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado={$esperado}, atual={$atual}\n";

    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function criarVeiculoTeste(string $chave, string $placa, string $status): int
{
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => $placa,
        'marca' => 'Teste',
        'modelo' => 'Disponibilidade',
        'disponibilidade' => $status,
        'odometro' => '0',
    ]);
}

echo "=== Teste sincronizacao disponibilidade veiculos ===\n";

$veiculosCriados = [];
$locacoesCriadas = [];
$contratosCriados = [];

try {
    $sync = new VeiculoDisponibilidadeSync();

    $veiculoLocacao = criarVeiculoTeste($chave, 'TSTL001', 'D');
    $veiculosCriados[] = $veiculoLocacao;
    $locacaoId = Database::insertGetId('locacoes', [
        'codigo' => 'TL' . substr((string) time(), -8),
        'chave' => $chave,
        'status' => 'A',
        'data_saida' => '2026-06-01 08:00:00',
        'data_prevista' => '2026-06-05 08:00:00',
        'dias' => 4,
        'cliente_nome' => 'Cliente Teste',
    ]);
    $locacoesCriadas[] = $locacaoId;
    Database::insertGetId('locacoes_veiculos', [
        'id_locacao' => $locacaoId,
        'id_veiculo' => $veiculoLocacao,
        'data_saida' => '2026-06-01 08:00:00',
        'plano' => 'KL',
        'chave' => $chave,
    ]);

    $sync->liberarSeSemVinculoAtivo($veiculoLocacao, 'D');
    checkDisponibilidade('locacao ativa mantem veiculo locado', $veiculoLocacao, 'L');

    Database::execute('UPDATE locacoes_veiculos SET data_entrada = ? WHERE id_locacao = ?', ['2026-06-05 08:00:00', $locacaoId]);
    Database::execute('UPDATE locacoes SET status = ? WHERE id = ?', ['F', $locacaoId]);
    $sync->liberarSeSemVinculoAtivo($veiculoLocacao, 'D');
    checkDisponibilidade('locacao fechada libera veiculo', $veiculoLocacao, 'D');

    $veiculoContrato = criarVeiculoTeste($chave, 'TSTC001', 'D');
    $veiculosCriados[] = $veiculoContrato;
    $contratoId = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => 'TC' . substr((string) time(), -8),
        'data_ini' => '2026-06-01 08:00:00',
        'data_fim' => '2026-06-30 08:00:00',
        'contagem' => 'DIARIA',
        'dias' => 30,
        'status' => 'A',
    ]);
    $contratosCriados[] = $contratoId;
    Database::insertGetId('contratos_veiculos', [
        'id_contrato' => $contratoId,
        'id_veiculo' => $veiculoContrato,
        'data_saida' => '2026-06-01 08:00:00',
        'plano' => 'KL',
        'chave' => $chave,
    ]);

    $sync->liberarSeSemVinculoAtivo($veiculoContrato, 'D');
    checkDisponibilidade('contrato ativo mantem veiculo locado', $veiculoContrato, 'L');

    Database::execute('UPDATE contratos_veiculos SET data_entrada = ? WHERE id_contrato = ?', ['2026-06-30 08:00:00', $contratoId]);
    Database::execute('UPDATE contratos SET status = ? WHERE id = ?', ['F', $contratoId]);
    $sync->liberarSeSemVinculoAtivo($veiculoContrato, 'M');
    checkDisponibilidade('contrato devolvido com OS envia para manutencao', $veiculoContrato, 'M');

    $veiculoSubstituicaoAntigo = criarVeiculoTeste($chave, 'TSTS001', 'L');
    $veiculoSubstituicaoNovo = criarVeiculoTeste($chave, 'TSTS002', 'D');
    $veiculosCriados[] = $veiculoSubstituicaoAntigo;
    $veiculosCriados[] = $veiculoSubstituicaoNovo;
    $sync->liberarSeSemVinculoAtivo($veiculoSubstituicaoAntigo, 'D');
    $sync->marcarLocado($veiculoSubstituicaoNovo);
    checkDisponibilidade('substituicao libera veiculo antigo sem vinculo', $veiculoSubstituicaoAntigo, 'D');
    checkDisponibilidade('substituicao marca novo veiculo como locado', $veiculoSubstituicaoNovo, 'L');
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($locacoesCriadas as $idLocacao) {
        Database::execute('DELETE FROM locacoes_veiculos WHERE id_locacao = ?', [$idLocacao]);
        Database::execute('DELETE FROM locacoes WHERE id = ?', [$idLocacao]);
    }
    foreach ($contratosCriados as $idContrato) {
        Database::execute('DELETE FROM contratos_veiculos WHERE id_contrato = ?', [$idContrato]);
        Database::execute('DELETE FROM contratos WHERE id = ?', [$idContrato]);
    }
    foreach ($veiculosCriados as $idVeiculo) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$idVeiculo, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
