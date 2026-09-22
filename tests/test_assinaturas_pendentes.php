<?php

/**
 * Regressão: documentos pendentes de assinatura.
 *
 * Execute: php tests/test_assinaturas_pendentes.php
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
use App\Models\Assinatura;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;

$falhas = 0;
$sucessos = 0;
$idCliente = null;
$idContrato = null;
$idAssinatura = null;

function checkAssinaturasPendentes(string $label, bool $ok): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}\n";
    if ($ok) {
        $sucessos++;
        return;
    }

    $falhas++;
}

function codigoAssinaturasPendentes(): string
{
    return 'CAP' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
}

function encontrarDocumentoPendente(array $documentos, int $id): ?array
{
    foreach ($documentos as $documento) {
        if ((int) ($documento['id'] ?? 0) === $id) {
            return $documento;
        }
    }

    return null;
}

echo "=== Teste documentos pendentes de assinatura ===\n";

try {
    $codigo = codigoAssinaturasPendentes();
    $nomeCliente = 'Cliente Assinatura ' . $codigo;
    $documentoCliente = (string) random_int(10000000000, 99999999999);

    $idCliente = Database::insertGetId('clientes', [
        'chave' => $chave,
        'foto' => '',
        'nome_rsocial' => $nomeCliente,
        'cpf_cnpj' => $documentoCliente,
        'data_cadastro' => date('Y-m-d'),
        'situacao' => 'A',
    ]);

    $idContrato = Database::insertGetId('contratos', [
        'chave' => $chave,
        'codigo' => $codigo,
        'id_cliente' => $idCliente,
        'data_ini' => '2026-01-01 08:00:00',
        'data_fim' => '2026-02-01 08:00:00',
        'contagem' => 'dia',
        'dias' => 31,
        'status' => 'A',
    ]);

    $model = new Assinatura();
    $resultado = $model->listarPendentesParaApp($chave, ['contrato'], $codigo, 1, 20);
    $documento = encontrarDocumentoPendente($resultado['data'], $idContrato);

    checkAssinaturasPendentes('contrato sem assinatura e listado', $documento !== null);
    checkAssinaturasPendentes(
        'nome do cliente vem do cadastro',
        ($documento['cliente_nome'] ?? null) === $nomeCliente
    );
    checkAssinaturasPendentes(
        'documento do cliente vem do cadastro',
        ($documento['cliente_documento'] ?? null) === $documentoCliente
    );

    $idAssinatura = Database::insertGetId('assinaturas', [
        'chave' => $chave,
        'id_contrato' => $idContrato,
        'id_cliente' => $idCliente,
        'arquivo' => 'teste_assinatura.webp',
        'ip_address' => '127.0.0.1',
        'tipo' => 'cliente',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $resultado = $model->listarPendentesParaApp($chave, ['contrato'], $codigo, 1, 20);
    $documento = encontrarDocumentoPendente($resultado['data'], $idContrato);
    checkAssinaturasPendentes('contrato assinado deixa de ser listado', $documento === null);
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    if ($idAssinatura !== null) {
        Database::execute('DELETE FROM assinaturas WHERE id = ? AND chave = ?', [$idAssinatura, $chave]);
    }

    if ($idContrato !== null) {
        Database::execute('DELETE FROM contratos WHERE id = ? AND chave = ?', [$idContrato, $chave]);
    }

    if ($idCliente !== null) {
        Database::execute('DELETE FROM clientes WHERE id = ? AND chave = ?', [$idCliente, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
