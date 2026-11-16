<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Autorizar aplicativo de IA — 7Carros</title><script src="https://cdn.tailwindcss.com"></script><script src="/assets/js/mcp-consent.min.js?v=20261116-1" defer></script><link href="/assets/css/base.min.css" rel="stylesheet"><link href="/assets/css/components.min.css" rel="stylesheet"></head>
<body class="bg-slate-100 p-6">
<main class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md space-y-4">
<h1 class="text-2xl font-bold">Conectar aplicativo de IA</h1>
<p>Aplicativo: <strong><?= htmlspecialchars($pending['application'], ENT_QUOTES, 'UTF-8') ?></strong></p>
<section aria-labelledby="mcp-branches-title">
<h2 id="mcp-branches-title" class="text-lg font-semibold">Empresas/filiais com acesso (<?= count($pending['branch_access']['units']) ?>)</h2>
<?php if ($pending['branch_access']['units'] === []): ?>
<p>Nenhuma empresa/filial disponível para consulta com seu acesso atual.</p>
<?php else: ?>
<p><?= $pending['branch_access']['all'] ? 'Todas as empresas/filiais deste cadastro.' : 'Somente as empresas/filiais permitidas ao seu usuário.' ?></p>
<ul class="list-disc pl-6">
<?php foreach ($pending['branch_access']['units'] as $unit): ?>
<li><?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p>A abrangência acompanha as permissões atuais do seu usuário. Alterações nessas permissões também se aplicam às próximas consultas.</p>
</section>
<p>Escolha as categorias que deseja compartilhar. O aplicativo terá acesso somente às consultas permitidas ao seu usuário e às suas filiais.</p>
<p>As informações enviadas ao aplicativo de IA poderão permanecer nele mesmo após você revogar esta conexão.</p>
<form method="post" action="/oauth/mcp/authorize" data-mcp-consent>
<?= \App\Views\Template::csrf() ?>
<input type="hidden" name="nonce" value="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
<fieldset class="my-4"><legend>Categorias de consulta</legend>
<?php foreach ($pending['scopes'] as $scope): ?>
<p><label><input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($scope, ['clientes:documentos', 'clientes:contatos'], true) ? '' : 'checked' ?>> <?= htmlspecialchars($labels[$scope], ENT_QUOTES, 'UTF-8') ?></label></p>
<?php endforeach; ?>
</fieldset>
<button class="btn-blue rounded-md py-2 px-4" type="submit" name="decision" value="allow">Autorizar consultas</button>
<button class="btn-secondary rounded-md py-2 px-4" type="submit" name="decision" value="deny">Recusar</button>
</form>
</main>
</body></html>
