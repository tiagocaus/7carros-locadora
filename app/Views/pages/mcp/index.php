@extends('layouts.iframe')
@section('title', 'Integrações — IA/MCP')
@section('content')
<div class="pl-1 pr-2 py-0">
<h2 class="title-section mb-6">Integrações — IA/MCP</h2>
<p class="mb-4">Endereço para aplicativos homologados: <code><?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?></code></p>
<p class="mb-4">Revogar impede novas consultas. Dados já compartilhados poderão permanecer no aplicativo.</p>
<div class="bg-white shadow-md rounded-lg overflow-x-auto">
<table class="w-full divide-y divide-slate-200">
<thead class="table-header-custom"><tr><th>Aplicativo</th><th>Usuário</th><th>Categorias autorizadas</th><th>Validade</th><th>Último acesso</th><th>Ação</th></tr></thead>
<tbody class="divide-y divide-slate-200">
<?php foreach ($connections as $connection): ?>
<tr>
<td><?= htmlspecialchars($connection['application'] ?? 'Aplicativo removido', ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($connection['employee'] ?? 'Usuário removido', ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars(implode(', ', array_map(fn ($s) => $scopeLabels[$s] ?? $s, json_decode($connection['scopes'], true) ?? [])), ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($connection['expires_at'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($connection['last_used_at'] ?? 'Sem consultas', ENT_QUOTES, 'UTF-8') ?></td>
<td><?php if ($connection['revoked_at']): ?>Revogada<?php else: ?>
<form method="post" action="/integracoes/mcp/revogar">
<?= \App\Views\Template::csrf() ?>
<input type="hidden" name="connection_id" value="<?= htmlspecialchars($connection['connection_id'], ENT_QUOTES, 'UTF-8') ?>">
<button class="btn-secondary rounded-md py-2 px-4" type="submit">Revogar acesso</button>
</form><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$connections): ?><tr><td colspan="6">Nenhuma conexão disponível.</td></tr><?php endif; ?>
</tbody></table>
</div>
<nav class="mt-4 flex gap-4" aria-label="Paginação das conexões">
<?php if ($page > 1): ?><a href="/pages/integracoes/mcp?pagina=<?= $page - 1 ?>">Anterior</a><?php endif; ?>
<span>Página <?= $page ?></span>
<?php if (count($connections) === 25): ?><a href="/pages/integracoes/mcp?pagina=<?= $page + 1 ?>">Próxima</a><?php endif; ?>
</nav></div>
@endsection
