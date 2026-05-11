@extends('layouts.iframe')

@section('title', '<?= t("modules.feature_requests.new_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-section"><?= t('modules.feature_requests.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formPedido" class="space-y-6">
        <!-- Titulo -->
        <div class="form-section">
            <label for="titulo" class="form-label-group"><?= t('modules.feature_requests.fields.title') ?> <span class="text-red-500">*</span></label>
            <input type="text" id="titulo" name="titulo" class="form-input-focus w-full"
                   placeholder="<?= t('modules.feature_requests.placeholders.title_input') ?>"
                   autocomplete="off">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.feature_requests.hints.title') ?></p>

            <!-- Container para resultados similares -->
            <div id="similaresContainer" class="hidden mt-3">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-lightbulb text-blue-500 mt-0.5 mr-2"></i>
                        <div class="flex-1">
                            <p class="text-sm text-blue-800 font-medium mb-2"><?= t('modules.feature_requests.similar.found') ?></p>
                            <div id="listaSimilares" class="space-y-2">
                            </div>
                            <p class="text-xs text-blue-600 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?= t('modules.feature_requests.similar.follow_existing') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modulo -->
        <div class="form-section">
            <label for="modulo_id" class="form-label-group"><?= t('modules.feature_requests.fields.module') ?> <span class="text-red-500">*</span></label>
            <select id="modulo_id" name="modulo_id" class="form-input-focus w-full">
                <option value=""><?= t('modules.feature_requests.placeholders.select_module') ?></option>
            </select>
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.feature_requests.hints.module') ?></p>
        </div>

        <!-- Descricao -->
        <div class="form-section">
            <label for="descricao" class="form-label-group"><?= t('modules.feature_requests.fields.description') ?> <span class="text-red-500">*</span></label>
            <textarea id="descricao" name="descricao" class="form-input-focus w-full" rows="6"
                      placeholder="<?= t('modules.feature_requests.placeholders.description_input') ?>"></textarea>
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.feature_requests.hints.description') ?></p>
        </div>

        <!-- Telefone (opcional) -->
        <div class="form-section">
            <label for="telefone" class="form-label-group"><?= t('modules.feature_requests.fields.phone') ?></label>
            <input type="text" id="telefone" name="telefone" class="form-input-focus w-full"
                   placeholder="<?= t('modules.feature_requests.placeholders.phone_input') ?>">
            <p class="text-xs text-slate-500 mt-1"><?= t('modules.feature_requests.hints.phone') ?></p>
        </div>

        <!-- Seguir automaticamente -->
        <div class="form-section">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="seguir_automaticamente" name="seguir_automaticamente" checked class="mr-2">
                <span class="text-sm"><?= t('modules.feature_requests.fields.follow_auto') ?></span>
            </label>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6"><?= t('common.buttons.cancel') ?></button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6">
                <i class="fas fa-paper-plane mr-2"></i><?= t('modules.feature_requests.actions.submit') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        votesLabel: '<?= addslashes(t('modules.feature_requests.info.votes_label')) ?>',
        view: '<?= addslashes(t('modules.feature_requests.actions.view')) ?>',
        followBtn: '<?= addslashes(t('modules.feature_requests.similar.follow_btn')) ?>',
        followSuccess: '<?= addslashes(t('modules.feature_requests.messages.follow_success')) ?>',
        followError: '<?= addslashes(t('modules.feature_requests.messages.follow_error')) ?>',
        titleRequired: '<?= addslashes(t('modules.feature_requests.messages.title_required')) ?>',
        moduleRequired: '<?= addslashes(t('modules.feature_requests.messages.module_required')) ?>',
        descriptionRequired: '<?= addslashes(t('modules.feature_requests.messages.description_required')) ?>',
        sending: '<?= addslashes(t('modules.feature_requests.actions.sending')) ?>',
        submitSuccess: '<?= addslashes(t('modules.feature_requests.messages.submit_success')) ?>',
        submitError: '<?= addslashes(t('modules.feature_requests.messages.submit_error')) ?>',
        btnSubmit: '<?= addslashes(t('modules.feature_requests.actions.submit')) ?>',
    };

    let searchTimeout = null;
    let ultimaBusca = '';

    // ===== NAVEGACAO =====
    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAR MODULOS =====
    async function carregarModulos() {
        try {
            const result = await API.get('/api/feature-requests/modulos');
            if (result.success) {
                const select = document.getElementById('modulo_id');
                result.data.forEach(m => {
                    const option = document.createElement('option');
                    option.value = m.id;
                    option.textContent = m.nome;
                    if (m.icone) {
                        option.dataset.icone = m.icone;
                    }
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar modulos:', error);
        }
    }

    // ===== BUSCA INTELIGENTE =====
    async function buscarSimilares(termo) {
        if (termo.length < 3 || termo === ultimaBusca) return;
        ultimaBusca = termo;

        try {
            const result = await API.get('/api/feature-requests/similares', { termo: termo });

            const container = document.getElementById('similaresContainer');
            const lista = document.getElementById('listaSimilares');

            if (result.success && result.data && result.data.length > 0) {
                lista.innerHTML = result.data.map(p => `
                    <div class="bg-white rounded border border-blue-100 p-3 flex justify-between items-start">
                        <div class="flex-1 mr-3">
                            <div class="font-medium text-sm text-slate-800">${escapeHtml(p.titulo)}</div>
                            <div class="flex items-center text-xs text-slate-500 mt-1">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs ${p.status_cor || 'bg-gray-100'}">
                                    ${p.status_label || p.status}
                                </span>
                                <span class="mx-2">|</span>
                                <i class="fas fa-thumbs-up mr-1"></i>${p.total_votos} ${i18n.votesLabel}
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button type="button" class="btn-similar-ver text-xs text-blue-600 hover:text-blue-800"
                                    data-id="${p.id}">
                                ${i18n.view}
                            </button>
                            <button type="button" class="btn-similar-seguir text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
                                    data-id="${p.id}">
                                <i class="fas fa-bell mr-1"></i>${i18n.followBtn}
                            </button>
                        </div>
                    </div>
                `).join('');

                container.classList.remove('hidden');

                // Event listeners
                lista.querySelectorAll('.btn-similar-ver').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        navegarPara('/pages/feature-requests/detalhes?id=' + id);
                    });
                });

                lista.querySelectorAll('.btn-similar-seguir').forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const id = this.dataset.id;
                        await seguirPedido(id);
                    });
                });
            } else {
                container.classList.add('hidden');
            }
        } catch (error) {
            console.error('Erro na busca:', error);
        }
    }

    async function seguirPedido(id) {
        try {
            const result = await API.post(`/feature-requests/${id}/seguir`);
            if (result.success) {
                showToast(i18n.followSuccess, 'success');
                navegarPara('/pages/feature-requests/detalhes?id=' + id);
            } else {
                showToast(result.message || i18n.followError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.followError, 'error');
        }
    }

    // ===== SALVAR PEDIDO =====
    async function salvarPedido(e) {
        e.preventDefault();

        const titulo = document.getElementById('titulo').value.trim();
        const modulo_id = document.getElementById('modulo_id').value;
        const descricao = document.getElementById('descricao').value.trim();
        const telefone = document.getElementById('telefone').value.trim();
        const seguir = document.getElementById('seguir_automaticamente').checked;

        // Validacao
        if (!titulo) {
            showToast(i18n.titleRequired, 'error');
            document.getElementById('titulo').focus();
            return;
        }

        if (!modulo_id) {
            showToast(i18n.moduleRequired, 'error');
            document.getElementById('modulo_id').focus();
            return;
        }

        if (!descricao) {
            showToast(i18n.descriptionRequired, 'error');
            document.getElementById('descricao').focus();
            return;
        }

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.sending}`;

        try {
            const result = await API.post('/feature-requests/salvar', {
                titulo: titulo,
                modulo_id: modulo_id,
                descricao: descricao,
                telefone_solicitante: telefone,
                seguir_automaticamente: seguir ? 1 : 0
            });

            if (result.success) {
                showToast(i18n.submitSuccess, 'success');
                navegarPara('/pages/feature-requests/detalhes?id=' + result.data.id);
            } else {
                showToast(result.message || i18n.submitError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.submitError, 'error');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-paper-plane mr-2"></i>${i18n.btnSubmit}`;
        }
    }

    // ===== EVENT LISTENERS =====
    document.getElementById('titulo')?.addEventListener('input', function (e) {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            buscarSimilares(e.target.value.trim());
        }, 500);
    });

    document.getElementById('formPedido')?.addEventListener('submit', salvarPedido);

    document.getElementById('btnVoltar')?.addEventListener('click', function () {
        navegarPara('/pages/feature-requests');
    });

    document.getElementById('btnCancelar')?.addEventListener('click', function () {
        navegarPara('/pages/feature-requests');
    });

    // ===== HELPERS =====
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        if (type === 'error') {
            window.parent.postMessage({ action: 'openAlert', message: message }, '*');
        } else {
            window.parent.postMessage({ action: 'showToast', message: message }, '*');
        }
    }

    // ===== INICIALIZACAO =====
    carregarModulos();
})();
</script>
@endsection
