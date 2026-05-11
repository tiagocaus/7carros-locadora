<?php
/**
 * Proxy AJAX do site publico — consulta disponibilidade de grupos na API do sistema.
 *
 * Chamado via GET pelo JS do proprio site (custom.js). Usa SiteApi para autenticar
 * com X-Site-Token e repassar a resposta em JSON. Mantem o token do lado do servidor.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/functions.php';

$params = [
    'id_matriz_filial' => (int) ($_GET['id_matriz_filial'] ?? 0),
    'data_saida'       => (string) ($_GET['data_saida']     ?? ''),
    'hora_saida'       => (string) ($_GET['hora_saida']     ?? ''),
    'data_prevista'    => (string) ($_GET['data_prevista']  ?? ''),
    'hora_devolucao'   => (string) ($_GET['hora_devolucao'] ?? ''),
];

if ($params['id_matriz_filial'] <= 0
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['data_saida'])
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['data_prevista'])
    || !preg_match('/^\d{2}:\d{2}$/', $params['hora_saida'])
    || !preg_match('/^\d{2}:\d{2}$/', $params['hora_devolucao'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos']);
    exit;
}

$resposta = $api->getDisponibilidade($params);
echo json_encode($resposta);
