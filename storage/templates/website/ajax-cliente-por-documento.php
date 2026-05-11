<?php
/**
 * Proxy AJAX — consulta cliente por CPF/CNPJ exato na API publica do sistema.
 *
 * Chamado pelo custom.js quando o visitante digita o documento no passo 4.
 * Retorna sempre shape neutro (success=true, data=null) quando nao encontra,
 * mitigando enumeration.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/functions.php';

$documento = preg_replace('/\D/', '', (string) ($_GET['documento'] ?? ''));
if (strlen($documento) < 11) {
    echo json_encode(['success' => true, 'data' => null]);
    exit;
}

$resposta = $api->buscarClientePorDocumento($documento);
echo json_encode($resposta);
