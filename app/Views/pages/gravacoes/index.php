@extends('layouts.iframe')

@section('title', t('modules.gravacoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="logs-header flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.gravacoes.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <button type="button" onclick="window.parent.ScreenRecorder.start();" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                <i class="fas fa-record-vinyl mr-2"></i><?= t('modules.gravacoes.new_recording') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.gravacoes.table.date') ?></th>
                    <th class="table-header"><?= t('modules.gravacoes.table.size') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.gravacoes.table.days_remaining') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.gravacoes.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="gravacoesTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.gravacoes.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= str_replace([':start', ':end', ':total'], ['0', '0', '0'], t('modules.gravacoes.pagination.showing')) ?></span>
        </div>
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px" id="paginationNav">
                <li><button class="pagination-button arrow-button rounded-l-md" disabled><i class="fas fa-chevron-left"></i></button></li>
                <li><button class="pagination-button numbered active">1</button></li>
                <li><button class="pagination-button arrow-button rounded-r-md" disabled><i class="fas fa-chevron-right"></i></button></li>
            </ul>
        </nav>
    </div>

    <div id="loadingOverlay" class="hidden fixed inset-0 bg-white/80 flex items-center justify-center z-50">
        <div class="flex flex-col items-center">
            <i class="fas fa-spinner fa-spin text-3xl text-sky-600"></i>
            <span class="mt-2 text-sm text-slate-600"><?= t('common.labels.loading') ?></span>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            noRecords: <?= json_encode(t('modules.gravacoes.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            noRecordsHint: <?= json_encode(t('modules.gravacoes.no_records_hint'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            loadError: <?= json_encode(t('modules.gravacoes.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            serverError: <?= json_encode(t('modules.gravacoes.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            linkCopied: <?= json_encode(t('modules.gravacoes.messages.link_copied'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            copyError: <?= json_encode(t('modules.gravacoes.messages.copy_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            deleteError: <?= json_encode(t('modules.gravacoes.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            recordName: <?= json_encode(t('modules.gravacoes.record_name'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            showingPagination: <?= json_encode(t('modules.gravacoes.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            actionWatch: <?= json_encode(t('modules.gravacoes.actions.watch'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            actionShare: <?= json_encode(t('modules.gravacoes.actions.share'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            actionDelete: <?= json_encode(t('modules.gravacoes.actions.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        };

        let currentPage = 1;
        let perPage = 10;

        function showLoading() {
            document.getElementById('loadingOverlay')?.classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay')?.classList.add('hidden');
        }

        async function carregarGravacoes(page = 1, recordsPerPage = 10) {
            showLoading();

            try {
                const result = await API.get('/api/gravacoes', {
                    page: page,
                    perPage: recordsPerPage
                });

                if (result.success) {
                    renderGravacoes(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    console.error('Erro ao carregar gravacoes:', result.message);
                    mostrarMensagemErro(i18n.loadError);
                }
            } catch (error) {
                console.error('Erro ao buscar gravacoes:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            } finally {
                hideLoading();
            }
        }

        function mostrarMensagemErro(mensagem) {
            const tbody = document.querySelector('#gravacoesTableBody');
            if (!tbody) return;
            tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function formatarData(dataString) {
            if (!dataString) return '-';
            const data = new Date(dataString);
            const dia = String(data.getDate()).padStart(2, '0');
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const ano = data.getFullYear();
            const hora = String(data.getHours()).padStart(2, '0');
            const min = String(data.getMinutes()).padStart(2, '0');
            return `${dia}/${mes}/${ano} ${hora}:${min}`;
        }

        function renderGravacoes(gravacoes) {
            const tbody = document.querySelector('#gravacoesTableBody');
            if (!tbody) return;

            if (gravacoes.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="table-cell text-center text-slate-500">
                        <i class="fas fa-video-slash mr-2"></i>${i18n.noRecords}
                        <p class="text-xs mt-2">${i18n.noRecordsHint}</p>
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            gravacoes.forEach(gravacao => {
                const data = formatarData(gravacao.created_at);
                const tamanho = gravacao.size || '-';
                const diasRestantes = gravacao.dias_restantes || 0;
                const diasClass = diasRestantes <= 7 ? 'text-red-600' : (diasRestantes <= 14 ? 'text-amber-600' : 'text-green-600');

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell whitespace-nowrap">${data}</td>
                    <td class="table-cell">${tamanho}</td>
                    <td class="table-cell hidden md:table-cell">
                        <span class="${diasClass} font-medium">${diasRestantes} dias</span>
                    </td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionWatch}" class="btn-icon text-sky-600 hover:text-sky-800" onclick="abrirVideo(${gravacao.id})">
                            <i class="fas fa-play"></i>
                        </button>
                        <button title="${i18n.actionShare}" class="btn-icon text-green-600 hover:text-green-800 ml-2" onclick="compartilharLink('${gravacao.share_url}')">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 ml-2" onclick="confirmarExclusao(${gravacao.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;
        }

        window.abrirVideo = function(id) {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openVideoModal',
                    videoUrl: '/api/gravacoes/' + id
                }, '*');
            }
        };

        window.compartilharLink = function(shareUrl) {
            const fullUrl = window.location.origin + shareUrl;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(fullUrl).then(function() {
                    mostrarNotificacao(i18n.linkCopied, 'success');
                }).catch(function(err) {
                    console.error('Erro ao copiar:', err);
                    mostrarNotificacao(i18n.copyError, 'error');
                });
            } else {
                const input = document.createElement('input');
                input.value = fullUrl;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                mostrarNotificacao(i18n.linkCopied, 'success');
            }
        };

        function mostrarNotificacao(mensagem, tipo) {
            const notification = document.createElement('div');
            notification.textContent = mensagem;

            const cores = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${cores[tipo] || cores.info};
                color: white;
                padding: 12px 20px;
                border-radius: 6px;
                z-index: 999999;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;

            document.body.appendChild(notification);

            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        window.confirmarExclusao = function(id) {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openDeleteModal',
                    recordId: id,
                    recordName: i18n.recordName,
                    recordType: 'gravacao',
                    confirmType: 'none'
                }, '*');
            }
        };

        async function excluirGravacao(id) {
            showLoading();

            try {
                const result = await API.delete('/api/gravacoes/' + id);

                if (result.success) {
                    carregarGravacoes(currentPage, perPage);
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
                }
            } catch (error) {
                console.error('Erro ao excluir:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.deleteError }, '*');
            } finally {
                hideLoading();
            }
        }

        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'confirmDelete') {
                excluirGravacao(event.data.recordId);
            }
        });

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement) return;

            const { page, perPage, total } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);
            infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.getElementById('paginationNav');
            if (!paginationNav) return;

            const { page, totalPages, hasPrev, hasNext } = pagination;

            let buttons = '';

            buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

            const maxButtons = 5;
            let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);

            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                buttons += `
                <li>
                    <button class="pagination-button numbered ${i === page ? 'active' : ''}"
                            onclick="irParaPagina(${i})">
                        ${i}
                    </button>
                </li>
            `;
            }

            buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasNext ? 'disabled' : ''}
                        onclick="irParaPagina(${page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

            paginationNav.innerHTML = buttons;
        }

        window.irParaPagina = function(page) {
            currentPage = page;
            carregarGravacoes(currentPage, perPage);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarGravacoes(currentPage, perPage);
        });

        carregarGravacoes(currentPage, perPage);
    })();
</script>
@endsection
