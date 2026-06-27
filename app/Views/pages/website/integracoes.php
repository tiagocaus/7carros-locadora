@extends('layouts.iframe')

@section('title', '<?= t("modules.website.integrations_title") ?>')

@section('content')
<?php $canManageWebsite = \App\Core\Auth::can('website.configurar') || \App\Core\Auth::can('website.editar'); ?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-page mb-3 sm:mb-0"><?= t('modules.website.integrations_title') ?></h2>
        <?php if ($canManageWebsite): ?>
        <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
            <i class="fas fa-plus mr-2"></i><?= t('modules.website.add_integration') ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Lista por tipo -->
    <?php $tipos = ['head' => t('modules.website.code_types.head'), 'body_inicio' => t('modules.website.code_types.body_inicio'), 'body_fim' => t('modules.website.code_types.body_fim')]; ?>
    <?php foreach ($tipos as $tipo => $label): ?>
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-slate-600 mb-2 flex items-center">
            <i class="fas fa-code mr-2 text-slate-400"></i> <?= $label ?>
        </h3>
        <div id="lista-<?= $tipo ?>" class="space-y-2"></div>
    </div>
    <?php endforeach; ?>

    <!-- Modal global — HTML vive em app/Views/layouts/app.php (openIntegracaoModal). -->
</div>
@endsection

@section('scripts')
<script>
(function() {
    let integracoes = [];
    const canManageWebsite = <?= $canManageWebsite ? 'true' : 'false' ?>;

    pageLoading.start();
    carregar();

    async function carregar() {
        try {
            const result = await API.get('/api/website/integracoes');
            if (result.success) {
                integracoes = result.data || [];
                renderizar();
            }
        } catch (error) {
            toast.error('Erro ao carregar');
        } finally {
            pageLoading.done();
        }
    }

    function renderizar() {
        ['head', 'body_inicio', 'body_fim'].forEach(tipo => {
            const container = document.getElementById('lista-' + tipo);
            const items = integracoes.filter(i => i.tipo === tipo);
            container.innerHTML = '';

            if (items.length === 0) {
                container.innerHTML = '<p class="text-sm text-slate-400 pl-6">Nenhum codigo cadastrado</p>';
                return;
            }

            items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'bg-white shadow-sm rounded-lg p-4 flex items-center justify-between';
                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 rounded text-xs ${item.ativo == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}">${item.ativo == 1 ? 'Ativo' : 'Inativo'}</span>
                        <span class="font-medium text-sm">${item.descricao || 'Sem descricao'}</span>
                        <span class="text-xs text-slate-400">${(item.codigo || '').substring(0, 60)}...</span>
                    </div>
                    ${canManageWebsite ? `<div class="flex items-center gap-2">
                        <button class="text-blue-500 hover:text-blue-700 text-sm btn-editar" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                        <button class="text-red-500 hover:text-red-700 text-sm btn-excluir" data-id="${item.id}"><i class="fas fa-trash"></i></button>
                    </div>` : ''}
                `;
                container.appendChild(div);
            });
        });
    }

    // Novo — abre o modal global via postMessage
    document.getElementById('btnNovo')?.addEventListener('click', function() {
        if (!canManageWebsite) return;
        window.parent.postMessage({
            action: 'openIntegracaoModal',
            integracao: null,
        }, '*');
    });

    // Editar / Excluir
    document.addEventListener('click', function(e) {
        if (!canManageWebsite) return;

        const btnEditar = e.target.closest('.btn-editar');
        if (btnEditar) {
            const item = integracoes.find(i => i.id == btnEditar.dataset.id);
            if (item) {
                window.parent.postMessage({
                    action: 'openIntegracaoModal',
                    integracao: item,
                }, '*');
            }
        }

        const btnExcluir = e.target.closest('.btn-excluir');
        if (btnExcluir) {
            const item = integracoes.find(i => i.id == btnExcluir.dataset.id);
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: btnExcluir.dataset.id,
                recordName: item?.descricao || 'Integração',
                recordType: 'integração',
                confirmType: 'text',
                customAction: 'deleteWebsiteIntegracao'
            }, '*');
        }
    });

    async function excluirIntegracao(id) {
        if (!canManageWebsite) return;

        try {
            const result = await API.post('/api/website/integracoes/' + id + '/excluir');
            if (result.success) {
                toast.success('<?= t("common.messages.deleted") ?>');
                carregar();
            } else {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: result.message || '<?= t("common.messages.error") ?>'
                }, '*');
            }
        } catch (error) {
            window.parent.postMessage({
                action: 'openAlert',
                message: error.message || '<?= t("common.messages.error") ?>'
            }, '*');
        }
    }

    // Escuta confirmação do modal global
    window.addEventListener('message', async function(event) {
        if (event.data && event.data.action === 'confirmDelete' && event.data.customAction === 'deleteWebsiteIntegracao') {
            excluirIntegracao(event.data.recordId);
            return;
        }

        if (!event.data || event.data.action !== 'integracaoModalConfirmado') return;
        if (!canManageWebsite) return;

        const data = event.data.data;
        try {
            const result = await API.post('/api/website/integracoes', data);
            if (result.success) {
                toast.success('<?= t("common.messages.saved") ?>');
                carregar();
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });
})();
</script>
@endsection
