@extends('layouts.iframe')

@section('title', t('modules.changelog.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.changelog.title') ?></h2>
        <?php if ($isAdmin ?? false): ?>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <button id="btnNovoChangelog" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.changelog.new') ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Container das versoes -->
    <div id="changelogContainer" class="space-y-6">
        <!-- Carregando -->
        <div id="loadingMessage" class="text-center py-8 text-slate-500">
            <i class="fas fa-spinner fa-spin mr-2"></i><?= t('common.labels.loading') ?>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        noRecords: <?= json_encode(t('modules.changelog.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        version: <?= json_encode(t('modules.changelog.version'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.changelog.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadErrorRetry: <?= json_encode(t('modules.changelog.messages.load_error_retry'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        unknownError: <?= json_encode(t('modules.changelog.messages.unknown_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        saved: <?= json_encode(t('modules.changelog.messages.saved'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleted: <?= json_encode(t('modules.changelog.messages.deleted'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.changelog.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteErrorRetry: <?= json_encode(t('modules.changelog.messages.delete_error_retry'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('common.buttons.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionDelete: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    let isAdmin = false;
    let idParaExcluir = null;
    let ultimaVersao = '';

    const container = document.getElementById('changelogContainer');
    const loadingMessage = document.getElementById('loadingMessage');

    // ===== CARREGAMENTO DE DADOS =====
    async function carregarChangelogs() {
        try {
            mostrarLoading(true);

            const result = await API.get('/api/changelog');

            if (result.success) {
                isAdmin = result.isAdmin || false;
                if (result.data && result.data.length > 0) {
                    ultimaVersao = result.data[0].versao || '';
                }
                renderChangelogs(result.data);
            } else {
                mostrarMensagem(i18n.loadError + ': ' + (result.message || i18n.unknownError));
            }
        } catch (error) {
            console.error('Erro ao carregar changelog:', error);
            mostrarMensagem(i18n.loadErrorRetry);
        } finally {
            mostrarLoading(false);
        }
    }

    function mostrarLoading(show) {
        loadingMessage.classList.toggle('hidden', !show);
    }

    function mostrarMensagem(msg) {
        container.innerHTML = `
            <div class="text-center py-8 text-slate-500">
                <i class="fas fa-exclamation-triangle mr-2"></i>${msg}
            </div>
        `;
    }

    // ===== RENDERIZACAO =====
    function renderChangelogs(versoes) {
        if (!versoes || versoes.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-slate-500">
                    <i class="fas fa-info-circle mr-2"></i>${i18n.noRecords}
                </div>
            `;
            return;
        }

        let html = '';
        versoes.forEach(grupo => {
            html += `
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="bg-slate-50 px-4 py-3 border-b">
                        <h3 class="text-lg font-semibold text-slate-800">
                            <i class="fas fa-tag text-slate-400 mr-2"></i>${i18n.version} ${escapeHtml(grupo.versao)}
                        </h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        ${grupo.itens.map(item => renderItem(item)).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function renderItem(item) {
        const dataFormatada = formatarData(item.data);
        const acoesHtml = isAdmin ? `
            <div class="flex items-center space-x-2 ml-4 flex-shrink-0">
                <button onclick="window.editarChangelog(${item.id})" class="text-slate-400 hover:text-blue-600" title="${i18n.actionEdit}">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="window.excluirChangelog(${item.id})" class="text-slate-400 hover:text-red-600" title="${i18n.actionDelete}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        ` : '';

        return `
            <div class="px-4 py-3 flex items-start justify-between hover:bg-slate-50">
                <div class="flex items-start space-x-3 flex-grow min-w-0">
                    <span class="inline-flex items-center justify-center w-24 px-2 py-0.5 rounded text-xs font-medium ${item.tipo_cor} flex-shrink-0">
                        ${escapeHtml(item.tipo_label)}
                    </span>
                    <span class="text-slate-700 break-words">${escapeHtml(item.mensagem)}</span>
                </div>
                <div class="flex items-center flex-shrink-0 ml-4">
                    <span class="text-xs text-slate-400">${dataFormatada}</span>
                    ${acoesHtml}
                </div>
            </div>
        `;
    }

    function formatarData(dataStr) {
        if (!dataStr) return '';
        const partes = dataStr.split('-');
        if (partes.length !== 3) return dataStr;
        return `${partes[2]}/${partes[1]}`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== ACOES - USAR POSTMESSAGE PARA MODAL NO PARENT =====
    const btnNovo = document.getElementById('btnNovoChangelog');
    if (btnNovo) {
        btnNovo.addEventListener('click', () => {
            window.parent.postMessage({ action: 'openChangelogModal', dados: { versao: ultimaVersao } }, '*');
        });
    }

    window.editarChangelog = async function(id) {
        try {
            const result = await API.get(`/api/changelog/${id}`);
            if (result.success) {
                window.parent.postMessage({ action: 'openChangelogModal', dados: result.data }, '*');
            } else {
                Toast.error(i18n.loadError);
            }
        } catch (error) {
            console.error('Erro ao carregar changelog:', error);
            Toast.error(i18n.loadError);
        }
    };

    window.excluirChangelog = function(id) {
        idParaExcluir = id;
        window.parent.postMessage({
            action: 'openDeleteModal',
            recordId: id,
            recordName: 'changelog',
            recordType: 'changelog',
            confirmType: 'none'
        }, '*');
    };

    // Escutar mensagens do parent
    window.addEventListener('message', async function(event) {
        if (event.data && event.data.action === 'changelogSaved') {
            Toast.success(i18n.saved);
            carregarChangelogs();
        } else if (event.data && event.data.action === 'confirmDelete' && idParaExcluir) {
            try {
                const result = await API.post(`/api/changelog/${idParaExcluir}/excluir`);
                if (result.success) {
                    Toast.success(result.message || i18n.deleted);
                    carregarChangelogs();
                } else {
                    Toast.error(result.message || i18n.deleteError);
                }
            } catch (error) {
                console.error('Erro ao excluir:', error);
                Toast.error(i18n.deleteErrorRetry);
            }
            idParaExcluir = null;
        }
    });

    // ===== INICIALIZACAO =====
    carregarChangelogs();
})();
</script>
@endsection
