@extends('layouts.iframe')
@section('title', 'IA/MCP')
@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-page mb-3 sm:mb-0">IA/MCP</h2>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6 items-stretch">
        <section class="flex flex-col min-w-0" aria-labelledby="mcp-info-title">
            <h3 id="mcp-info-title" class="text-sm font-semibold text-slate-600 mb-2">Informações do MCP</h3>
            <div class="bg-white shadow-md rounded-lg p-4 sm:p-6 h-full">
                <dl class="space-y-4 text-sm">
                    <div class="sm:flex sm:gap-4">
                        <dt class="font-medium text-slate-500 sm:w-36 sm:flex-shrink-0">Nome do MCP:</dt>
                        <dd class="font-semibold text-slate-700 mt-1 sm:mt-0">7Carros</dd>
                    </div>
                    <div class="sm:flex sm:gap-4">
                        <dt class="font-medium text-slate-500 sm:w-36 sm:flex-shrink-0">URL do MCP:</dt>
                        <dd class="font-semibold text-slate-700 break-all min-w-0 mt-1 sm:mt-0"><?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="sm:flex sm:gap-4">
                        <dt class="font-medium text-slate-500 sm:w-36 sm:flex-shrink-0">Autenticação:</dt>
                        <dd class="font-semibold text-slate-700 mt-1 sm:mt-0">OAuth</dd>
                    </div>
                </dl>
            </div>
        </section>
        <section class="flex flex-col min-w-0" aria-labelledby="mcp-downloads-title">
            <h3 id="mcp-downloads-title" class="text-sm font-semibold text-slate-600 mb-2">Downloads</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 h-full">
                <?php foreach ($packages as $application => $package): ?>
                <div class="bg-white shadow-md rounded-lg p-4 sm:p-6 flex flex-col items-start">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 text-blue-600 text-xl"><i class="fas fa-file-zipper" aria-hidden="true"></i></span>
                        <div>
                            <h4 class="font-semibold text-slate-700"><?= htmlspecialchars($package['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="text-xs text-slate-500">Versão <?= htmlspecialchars($package['version'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 mb-4"><?= htmlspecialchars($package['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <a class="btn-secondary inline-flex items-center gap-2 rounded-md py-2 px-4 text-sm font-medium shadow hover:shadow-md transition-shadow mt-auto" href="/integracoes/mcp/pacotes/<?= htmlspecialchars($application, ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars(basename($package['file']), ENT_QUOTES, 'UTF-8') ?>" aria-label="Baixar ZIP do <?= htmlspecialchars($package['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-download" aria-hidden="true"></i> Baixar ZIP
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <h3 class="text-sm font-semibold text-slate-600 mb-2">Conexões autorizadas</h3>
    <p class="text-sm text-slate-500 mb-4">Revogar impede novas consultas. Dados já compartilhados poderão permanecer no aplicativo.</p>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th scope="col" class="table-header">Aplicativo</th>
                    <th scope="col" class="table-header">Usuário</th>
                    <th scope="col" class="table-header">Categorias autorizadas</th>
                    <th scope="col" class="table-header">Validade</th>
                    <th scope="col" class="table-header">Último acesso</th>
                    <th scope="col" class="table-header">Segurança e cotas</th>
                    <th scope="col" class="table-header text-center w-40">Ação</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200">
                <?php foreach ($connections as $connection): ?>
                <tr class="hover:bg-slate-50">
                    <td class="table-cell"><?= htmlspecialchars($connection['application'] ?? 'Aplicativo removido', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell"><?= htmlspecialchars($connection['employee'] ?? 'Usuário removido', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell"><?= htmlspecialchars(implode(', ', array_map(fn ($s) => $scopeLabels[$s] ?? $s, json_decode($connection['scopes'], true) ?? [])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell whitespace-nowrap"><?= htmlspecialchars(format_datetime($connection['expires_at']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell whitespace-nowrap"><?= htmlspecialchars($connection['last_used_at'] ? format_datetime($connection['last_used_at']) : 'Sem consultas', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell">
                        <?php $state = $connection['security']; $suspended = $state['suspended_until'] > time(); ?>
                        <?php if ($suspended): ?>
                        <p class="font-semibold text-red-700">Suspensa por infrações repetidas até <?= htmlspecialchars(format_datetime(date('Y-m-d H:i:s', $state['suspended_until'])), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                        <p>Sem suspensão de segurança</p>
                        <?php endif; ?>
                        <p>Registros: <?= $state['hour_records'] ?>/<?= $state['limits']['hour_records'] ?> por hora · <?= $state['day_records'] ?>/<?= $state['limits']['day_records'] ?> por dia</p>
                        <p>Clientes com documentos/contatos: <?= $state['hour_sensitive'] ?>/<?= $state['limits']['hour_sensitive'] ?> por hora · <?= $state['day_sensitive'] ?>/<?= $state['limits']['day_sensitive'] ?> por dia</p>
                        <p class="text-xs text-slate-500">Cotas compartilhadas entre os aplicativos do usuário. Renovação na próxima hora e à meia-noite UTC.</p>
                        <?php if ($suspended && $securityAdmin): ?>
                        <form method="post" action="/integracoes/mcp/liberar">
                            <?= \App\Views\Template::csrf() ?>
                            <input type="hidden" name="connection_id" value="<?= htmlspecialchars($connection['connection_id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn-secondary rounded-md py-2 px-4 text-sm" type="submit">Encerrar suspensão do usuário</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td class="table-cell text-center">
                        <?php if ($connection['revoked_at']): ?>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-600">Revogada</span>
                        <?php else: ?>
                        <form method="post" action="/integracoes/mcp/revogar">
                            <?= \App\Views\Template::csrf() ?>
                            <input type="hidden" name="connection_id" value="<?= htmlspecialchars($connection['connection_id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn-secondary rounded-md py-2 px-4 text-sm font-medium shadow hover:shadow-md transition-shadow whitespace-nowrap" type="submit">Revogar acesso</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$connections): ?>
                <tr><td colspan="7" class="table-cell text-center py-8 text-slate-500">Nenhuma conexão disponível.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <h3 class="text-sm font-semibold text-slate-600 mt-6 mb-2">Eventos recentes de segurança</h3>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full divide-y divide-slate-200">
            <thead class="table-header-custom"><tr>
                <th class="table-header">Data</th><th class="table-header">Usuário (ID)</th>
                <th class="table-header">Evento</th><th class="table-header">Motivo</th><th class="table-header">Referência</th>
            </tr></thead>
            <tbody>
                <?php $eventLabels = ['security_infraction' => 'Infração', 'security_suspended' => 'Suspensão', 'security_released' => 'Liberação administrativa', 'security_quota' => 'Cota atingida'];
                $reasonLabels = ['unknown_tool' => 'Ferramenta inexistente', 'forbidden_tool' => 'Operação sem autorização', 'forbidden_argument' => 'Argumento não permitido', 'mcp_suspended' => 'Infrações repetidas', 'quota_exceeded' => 'Limite de registros atingido', 'success' => 'Concluída']; ?>
                <?php foreach ($securityEvents as $securityEvent): ?>
                <tr>
                    <td class="table-cell"><?= htmlspecialchars(format_datetime($securityEvent['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell"><?= (int) $securityEvent['affected_employee_id'] ?><?php if ($securityEvent['event'] === 'security_released'): ?> (liberado por <?= (int) $securityEvent['employee_id'] ?>)<?php endif; ?></td>
                    <td class="table-cell"><?= htmlspecialchars($eventLabels[$securityEvent['event']] ?? 'Evento de segurança', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell"><?= htmlspecialchars($reasonLabels[$securityEvent['result']] ?? 'Indisponível', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="table-cell"><?= htmlspecialchars($securityEvent['request_id'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$securityEvents): ?><tr><td class="table-cell text-center" colspan="5">Nenhum evento de segurança.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center gap-3">
        <span class="text-sm text-slate-600">Página <?= $page ?></span>
        <nav aria-label="Paginação das conexões">
            <ul class="inline-flex items-center -space-x-px">
                <li>
                    <?php if ($page > 1): ?>
                    <a class="pagination-button arrow-button rounded-l-md" href="/pages/integracoes/mcp?pagina=<?= $page - 1 ?>" aria-label="Página anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                    <?php else: ?>
                    <button class="pagination-button arrow-button rounded-l-md" type="button" disabled aria-label="Página anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </li>
                <li><span class="pagination-button numbered active" aria-current="page"><?= $page ?></span></li>
                <li>
                    <?php if (count($connections) === 25): ?>
                    <a class="pagination-button arrow-button rounded-r-md" href="/pages/integracoes/mcp?pagina=<?= $page + 1 ?>" aria-label="Próxima página"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    <?php else: ?>
                    <button class="pagination-button arrow-button rounded-r-md" type="button" disabled aria-label="Próxima página"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    </div>
</div>
@endsection
