@extends('layouts.iframe')

@section('title', '<?= t("modules.website.banners_title") ?>')

@section('content')
<?php $canManageWebsite = \App\Core\Auth::can('website.configurar') || \App\Core\Auth::can('website.editar'); ?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-page mb-3 sm:mb-0"><?= t('modules.website.banners_title') ?></h2>
        <?php if ($canManageWebsite): ?>
        <button id="btnNovoBanner" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
            <i class="fas fa-plus mr-2"></i><?= t('modules.website.add_banner') ?>
        </button>
        <?php endif; ?>
    </div>

    <div id="bannersLista" class="space-y-3"></div>
    <div id="bannersVazio" class="hidden bg-white shadow-md rounded-lg p-8 text-center text-slate-400">
        <i class="fas fa-images text-4xl mb-3"></i>
        <p><?= t('modules.website.banners_title') ?> - Nenhum banner cadastrado</p>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    let banners = [];
    const canManageWebsite = <?= $canManageWebsite ? 'true' : 'false' ?>;

    pageLoading.start();
    carregarBanners();

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    async function carregarBanners() {
        try {
            const result = await API.get('/api/website/banners');
            if (result.success) {
                banners = result.data || [];
                renderizar();
            }
        } catch (error) {
            toast.error('Erro ao carregar banners');
        } finally {
            pageLoading.done();
        }
    }

    function renderizar() {
        const lista = document.getElementById('bannersLista');
        const vazio = document.getElementById('bannersVazio');
        lista.innerHTML = '';

        if (banners.length === 0) {
            vazio.classList.remove('hidden');
            return;
        }
        vazio.classList.add('hidden');

        banners.forEach((b, i) => {
            const fotoUrl = escapeHtml(b.foto_url || '');
            const titulo = escapeHtml(b.titulo || 'Sem titulo');
            const mensagem = escapeHtml(b.mensagem || '');
            const div = document.createElement('div');
            div.className = 'bg-white shadow-md rounded-lg p-4 flex items-center gap-4';
            div.innerHTML = `
                <div class="text-slate-300 cursor-grab"><i class="fas fa-grip-vertical"></i></div>
                <div class="w-20 h-14 bg-slate-100 rounded overflow-hidden flex-shrink-0">
                    ${fotoUrl ? `<img src="${fotoUrl}" class="w-full h-full object-cover" alt="${titulo}">` : '<div class="flex items-center justify-center h-full text-slate-300"><i class="fas fa-image"></i></div>'}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm truncate">${titulo}</p>
                    <p class="text-xs text-slate-400 truncate">${mensagem}</p>
                </div>
                <div class="flex items-center gap-1">
                    <span class="px-2 py-0.5 rounded text-xs ${b.ativo == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}">${b.ativo == 1 ? 'Ativo' : 'Inativo'}</span>
                </div>
                ${canManageWebsite ? `<div class="flex items-center gap-2">
                    <button class="text-blue-500 hover:text-blue-700 text-sm btn-editar" data-index="${i}"><i class="fas fa-edit"></i></button>
                    <button class="text-red-500 hover:text-red-700 text-sm btn-excluir" data-index="${i}" data-id="${b.id}"><i class="fas fa-trash"></i></button>
                </div>` : ''}
            `;
            lista.appendChild(div);
        });
    }

    // Novo banner
    document.getElementById('btnNovoBanner')?.addEventListener('click', function() {
        if (!canManageWebsite) return;
        window.parent.postMessage({
            action: 'openWebsiteBannerModal',
            banner: null
        }, '*');
    });

    // Editar banner
    document.getElementById('bannersLista').addEventListener('click', function(e) {
        if (!canManageWebsite) return;

        const btnEditar = e.target.closest('.btn-editar');
        if (btnEditar) {
            const b = banners[parseInt(btnEditar.dataset.index)];
            window.parent.postMessage({
                action: 'openWebsiteBannerModal',
                banner: b
            }, '*');
        }

        const btnExcluir = e.target.closest('.btn-excluir');
        if (btnExcluir) {
            const b = banners[parseInt(btnExcluir.dataset.index)];
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: btnExcluir.dataset.id,
                recordName: b?.titulo || 'Banner',
                recordType: 'banner',
                confirmType: 'text'
            }, '*');
        }
    });

    async function excluirBanner(id) {
        if (!canManageWebsite) return;

        try {
            const result = await API.post('/api/website/banners/' + id + '/excluir');
            if (result.success) {
                toast.success('<?= t("common.messages.deleted") ?>');
                carregarBanners();
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

    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'websiteBannerSaved') {
            carregarBanners();
        } else if (canManageWebsite && event.data && event.data.action === 'confirmDelete') {
            excluirBanner(event.data.recordId);
        }
    });
})();
</script>
@endsection
