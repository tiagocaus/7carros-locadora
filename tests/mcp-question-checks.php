<?php
/** Disposable localhost fixtures. Prompt text must never affect the business query. */
declare(strict_types=1);

use App\Services\Mcp\Catalog;
use App\Models\Mcp\ReadModel;

[$status, $questionTools] = requestMcp('tools/list', [], $access);
foreach ($questionTools['result']['tools'] as $tool) {
    checkMcp(($tool['inputSchema']['properties']['pergunta_usuario']['type'] ?? '') === 'string'
        && !in_array('pergunta_usuario', $tool['inputSchema']['required'] ?? [], true),
        'Pergunta opcional anunciada em ' . $tool['name']);
    checkMcp(!str_contains(json_encode($tool['outputSchema']), 'pergunta_usuario'), 'Pergunta não amplia os dados de saída de ' . $tool['name']);
}
$originalQuestion = "  Quantos veículos tenho? 🚙
E os clientes, contratos, locações e financeiro? Não reformule!  ";
foreach (Catalog::CATEGORIES as $questionCategory => $definition) {
    $baseline = (new ReadModel($store->context($captured['connection_id'], $clientId, $scopes)))->listing($questionCategory, []);
    foreach ([false, true] as $detailMode) {
        $questionTool = $detailMode ? 'obter_' . Catalog::SINGULAR[$questionCategory] : 'listar_' . $questionCategory;
        $questionArgs = ['pergunta_usuario' => $originalQuestion];
        if ($detailMode) $questionArgs['id'] = (int) $baseline['dados'][0]['id'];
        [$status, $questionResult] = requestMcp('tools/call', ['name' => $questionTool, 'arguments' => $questionArgs], $access);
        $questionAudit = $qb->table('mcp_audit')->withChave($a)->where('event', '=', $questionTool)->orderByDesc('id')->first();
        $questionRequest = json_decode($questionAudit['request_payload'], true);
        checkMcp($status === 200 && empty($questionResult['result']['isError'])
            && $questionRequest['argumentos']['pergunta_usuario'] === $originalQuestion,
            'Mensagem literal preservada em ' . $questionTool);
        checkMcp(!isset($questionResult['result']['structuredContent']['filtros']['pergunta_usuario'])
            && ($detailMode || $questionResult['result']['structuredContent']['dados'] === $baseline['dados']),
            'Pergunta não integra filtros nem modifica a consulta em ' . $questionTool);
    }
}
[$status, $questionResult] = requestMcp('tools/call', ['name' => 'resumir_financeiro',
    'arguments' => ['pergunta_usuario' => $originalQuestion]], $access);
$questionAudit = $qb->table('mcp_audit')->withChave($a)->where('event', '=', 'resumir_financeiro')->orderByDesc('id')->first();
checkMcp(empty($questionResult['result']['isError'])
    && json_decode($questionAudit['request_payload'], true)['argumentos']['pergunta_usuario'] === $originalQuestion
    && $questionResult['result']['structuredContent']['dados'][0]['total'] === '200.00',
    'Resumo financeiro recebe a mesma pergunta literal sem alterar totais.');
foreach ([1, 2] as $questionPage) {
    [$status, $questionResult] = requestMcp('tools/call', ['name' => 'listar_lancamentos',
        'arguments' => ['filtros' => ['pagina' => $questionPage, 'por_pagina' => 1], 'pergunta_usuario' => 'E as ativas?']], $access);
    $questionAudit = $qb->table('mcp_audit')->withChave($a)->where('event', '=', 'listar_lancamentos')->orderByDesc('id')->first();
    checkMcp(empty($questionResult['result']['isError'])
        && json_decode($questionAudit['request_payload'], true)['argumentos']['pergunta_usuario'] === 'E as ativas?',
        'Continuação literal repetida na página ' . $questionPage);
}
[$status, $questionResult] = requestMcp('tools/call', ['name' => 'listar_veiculos', 'arguments' => ['pergunta_usuario' => '']], $access);
$questionAudit = $qb->table('mcp_audit')->withChave($a)->where('event', '=', 'listar_veiculos')->orderByDesc('id')->first();
checkMcp(empty($questionResult['result']['isError'])
    && !isset(json_decode($questionAudit['request_payload'], true)['argumentos']['pergunta_usuario']),
    'Pergunta vazia não é inventada e mantém consulta compatível.');
[$status, $questionResult] = requestMcp('tools/call', ['name' => 'listar_veiculos', 'arguments' => ['pergunta_usuario' => ['invalido']]], $access);
checkMcp(isset($questionResult['error']) || !empty($questionResult['result']['isError']), 'Schema rejeita pergunta que não é texto.');
