@extends('layouts.iframe')

@section('title', '<?= t("modules.website.banners_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-page mb-3 sm:mb-0"><?= t('modules.website.banners_title') ?></h2>
        <button id="btnNovoBanner" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
            <i class="fas fa-plus mr-2"></i><?= t('modules.website.add_banner') ?>
        </button>
    </div>

    <div id="bannersLista" class="space-y-3"></div>
    <div id="bannersVazio" class="hidden bg-white shadow-md rounded-lg p-8 text-center text-slate-400">
        <i class="fas fa-images text-4xl mb-3"></i>
        <p><?= t('modules.website.banners_title') ?> - Nenhum banner cadastrado</p>
    </div>

    <!-- Modal Editar Banner -->
    <div id="modalBanner" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 id="modalBannerTitulo" class="font-semibold"><?= t('modules.website.add_banner') ?></h3>
                <button type="button" class="text-slate-400 hover:text-slate-600" onclick="document.getElementById('modalBanner').classList.add('hidden')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formBanner" class="p-4 space-y-4">
                <input type="hidden" id="bannerId" value="">

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.banner_title') ?></label>
                    <input type="text" id="bannerTitulo" class="form-input-group-field" required>
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.banner_message') ?></label>
                    <input type="text" id="bannerMensagem" class="form-input-group-field">
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.banner_image') ?></label>
                    <input type="file" id="bannerFoto" accept="image/*" class="form-input-group-field">
                    <img id="bannerFotoPreview" class="mt-2 h-20 object-contain hidden" src="">
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.banner_alt') ?></label>
                    <input type="text" id="bannerAlt" class="form-input-group-field">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.website.banner_link') ?></label>
                        <input type="text" id="bannerLink" class="form-input-group-field" placeholder="https://...">
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group"><?= t('modules.website.banner_target') ?></label>
                        <select id="bannerTarget" class="form-input-group-field">
                            <option value="_blank"><?= t('modules.website.new_window') ?></option>
                            <option value="_self"><?= t('modules.website.same_window') ?></option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-outline py-2 px-4 rounded-md text-sm" onclick="document.getElementById('modalBanner').classList.add('hidden')"><?= t('common.buttons.cancel') ?></button>
                    <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm"><i class="fas fa-save mr-1"></i> <?= t('common.buttons.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    let banners = [];

    pageLoading.start();
    carregarBanners();

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
            const div = document.createElement('div');
            div.className = 'bg-white shadow-md rounded-lg p-4 flex items-center gap-4';
            div.innerHTML = `
                <div class="text-slate-300 cursor-grab"><i class="fas fa-grip-vertical"></i></div>
                <div class="w-20 h-14 bg-slate-100 rounded overflow-hidden flex-shrink-0">
                    ${b.foto_url ? `<img src="${b.foto_url}" class="w-full h-full object-cover">` : '<div class="flex items-center justify-center h-full text-slate-300"><i class="fas fa-image"></i></div>'}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm truncate">${b.titulo || 'Sem titulo'}</p>
                    <p class="text-xs text-slate-400 truncate">${b.mensagem || ''}</p>
                </div>
                <div class="flex items-center gap-1">
                    <span class="px-2 py-0.5 rounded text-xs ${b.ativo == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}">${b.ativo == 1 ? 'Ativo' : 'Inativo'}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button class="text-blue-500 hover:text-blue-700 text-sm btn-editar" data-index="${i}"><i class="fas fa-edit"></i></button>
                    <button class="text-red-500 hover:text-red-700 text-sm btn-excluir" data-id="${b.id}"><i class="fas fa-trash"></i></button>
                </div>
            `;
            lista.appendChild(div);
        });
    }

    // Novo banner
    document.getElementById('btnNovoBanner').addEventListener('click', function() {
        document.getElementById('bannerId').value = '';
        document.getElementById('bannerTitulo').value = '';
        document.getElementById('bannerMensagem').value = '';
        document.getElementById('bannerAlt').value = '';
        document.getElementById('bannerLink').value = '';
        document.getElementById('bannerTarget').value = '_blank';
        document.getElementById('bannerFoto').value = '';
        document.getElementById('bannerFotoPreview').classList.add('hidden');
        document.getElementById('modalBannerTitulo').textContent = '<?= t("modules.website.add_banner") ?>';
        document.getElementById('modalBanner').classList.remove('hidden');
    });

    // Editar banner
    document.getElementById('bannersLista').addEventListener('click', function(e) {
        const btnEditar = e.target.closest('.btn-editar');
        if (btnEditar) {
            const b = banners[parseInt(btnEditar.dataset.index)];
            document.getElementById('bannerId').value = b.id;
            document.getElementById('bannerTitulo').value = b.titulo || '';
            document.getElementById('bannerMensagem').value = b.mensagem || '';
            document.getElementById('bannerAlt').value = b.alt_text || '';
            document.getElementById('bannerLink').value = b.link_url || '';
            document.getElementById('bannerTarget').value = b.link_target || '_blank';
            document.getElementById('bannerFoto').value = '';

            if (b.foto_url) {
                document.getElementById('bannerFotoPreview').src = b.foto_url;
                document.getElementById('bannerFotoPreview').classList.remove('hidden');
            } else {
                document.getElementById('bannerFotoPreview').classList.add('hidden');
            }

            document.getElementById('modalBannerTitulo').textContent = '<?= t("modules.website.banner_title") ?>';
            document.getElementById('modalBanner').classList.remove('hidden');
        }

        const btnExcluir = e.target.closest('.btn-excluir');
        if (btnExcluir) {
            excluirBanner(btnExcluir.dataset.id);
        }
    });

    // Salvar banner
    document.getElementById('formBanner').addEventListener('submit', async function(e) {
        e.preventDefault();

        const id = document.getElementById('bannerId').value;
        const dados = {
            titulo: document.getElementById('bannerTitulo').value,
            mensagem: document.getElementById('bannerMensagem').value,
            alt_text: document.getElementById('bannerAlt').value,
            link_url: document.getElementById('bannerLink').value,
            link_target: document.getElementById('bannerTarget').value,
            ativo: 1,
        };

        try {
            let result;
            if (id) {
                result = await API.put('/api/website/banners/' + id, dados);
            } else {
                result = await API.post('/api/website/banners', dados);
            }

            if (result.success) {
                toast.success('<?= t("common.messages.saved") ?>');
                document.getElementById('modalBanner').classList.add('hidden');
                carregarBanners();
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });

    async function excluirBanner(id) {
        try {
            const result = await API.delete('/api/website/banners/' + id);
            if (result.success) {
                toast.success('<?= t("common.messages.deleted") ?>');
                carregarBanners();
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    }

    // Preview foto
    document.getElementById('bannerFoto').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('bannerFotoPreview').src = e.target.result;
                document.getElementById('bannerFotoPreview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
})();
</script>
@endsection
