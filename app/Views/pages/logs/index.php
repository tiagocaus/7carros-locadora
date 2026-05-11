@extends('layouts.iframe')

@section('title', t('modules.logs.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="logs-header flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.logs.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.logs.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.logs.table.date') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.logs.table.user') ?></th>
                    <th class="table-header"><?= t('modules.logs.table.message') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.logs.table.ip') ?></th>
                    <th class="table-header px-2 w-20 text-center"><?= t('modules.logs.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="logsTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.logs.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= str_replace([':start', ':end', ':total'], ['0', '0', '0'], t('modules.logs.pagination.showing')) ?></span>
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
            loading: '<?= addslashes(t("common.labels.loading")) ?>',
            noRecords: '<?= addslashes(t("modules.logs.no_records")) ?>',
            loadError: '<?= addslashes(t("modules.logs.messages.load_error")) ?>',
            serverError: '<?= addslashes(t("modules.logs.messages.server_error")) ?>',
            detailsTitle: '<?= addslashes(t("modules.logs.details_title")) ?>',
            emptyValue: '<?= addslashes(t("modules.logs.empty_value")) ?>',
            unrecognizedFormat: '<?= addslashes(t("modules.logs.unrecognized_format")) ?>',
            viewDetails: '<?= addslashes(t("modules.logs.view_details")) ?>',
            noDetails: '<?= addslashes(t("modules.logs.no_details")) ?>',
            showingPagination: '<?= addslashes(t("modules.logs.pagination.showing")) ?>',
            showingLazy: '<?= addslashes(t("modules.logs.pagination.showing_lazy")) ?>',
        };

        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let searchTimeout = null;

        function showLoading() {
            document.getElementById('loadingOverlay')?.classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay')?.classList.add('hidden');
        }

        async function carregarLogs(page = 1, recordsPerPage = 10, search = '') {
            showLoading();

            try {
                const result = await API.get('/api/logs', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search
                });

                if (result.success) {
                    renderLogs(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    console.error('Erro ao carregar logs:', result.message);
                    mostrarMensagemErro(i18n.loadError);
                }
            } catch (error) {
                console.error('Erro ao buscar logs:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            } finally {
                hideLoading();
            }
        }

        function mostrarMensagemErro(mensagem) {
            const tbody = document.querySelector('#logsTableBody');
            if (!tbody) return;
            tbody.innerHTML = `
            <tr>
                <td colspan="5" class="table-cell text-center text-red-600">
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
            const seg = String(data.getSeconds()).padStart(2, '0');
            return `${dia}/${mes}/${ano} ${hora}:${min}:${seg}`;
        }

        function renderLogs(logs) {
            const tbody = document.querySelector('#logsTableBody');
            if (!tbody) return;

            if (logs.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            logs.forEach(log => {
                const data = formatarData(log.data);
                const usuario = log.usuario_nome || '-';
                const mensagem = log.mensagem || '-';
                const ip = log.ip || '-';
                const temDetalhes = log.campos_alterados && log.campos_alterados.trim() !== '';

                const mensagemEscapada = mensagem.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const camposAlterados = temDetalhes ? log.campos_alterados.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell whitespace-nowrap">${data}</td>
                    <td class="table-cell hidden md:table-cell">${usuario}</td>
                    <td class="table-cell">${mensagemEscapada}</td>
                    <td class="table-cell hidden lg:table-cell">${ip}</td>
                    <td class="table-cell px-2 w-20 text-right">
                        ${temDetalhes ? `
                            <button title="${i18n.viewDetails}" class="btn-icon text-sky-600 hover:text-sky-800 btn-ver-detalhes" data-detalhes="${camposAlterados}">
                                <i class="fas fa-eye"></i>
                            </button>
                        ` : `
                            <span class="text-slate-300" title="${i18n.noDetails}"><i class="fas fa-minus"></i></span>
                        `}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            tbody.querySelectorAll('.btn-ver-detalhes').forEach(button => {
                button.addEventListener('click', function() {
                    const detalhes = this.getAttribute('data-detalhes');
                    abrirPainel(detalhes);
                });
            });
        }

        function formatarValorLog(valor) {
            if (valor === null || valor === undefined) {
                return i18n.emptyValue;
            }

            if (Array.isArray(valor)) {
                if (valor.length === 0) return i18n.emptyValue;

                let html = '<ul class="list-disc list-inside text-xs space-y-2">';
                valor.forEach(item => {
                    if (typeof item === 'object' && item !== null) {
                        // Tentar campo descritivo primeiro, senao listar todas as chaves
                        const desc = item.descricao || item.item;
                        if (desc) {
                            html += `<li>${desc}</li>`;
                        } else {
                            // Renderizar objeto como lista de chave: valor
                            const entries = Object.entries(item).filter(([, v]) => v !== null && v !== undefined && v !== '');
                            if (entries.length > 0) {
                                html += '<li><div class="ml-1 space-y-0.5">';
                                entries.forEach(([key, val]) => {
                                    html += `<div><span class="font-medium text-slate-600">${key}:</span> ${val}</div>`;
                                });
                                html += '</div></li>';
                            } else {
                                html += `<li>${JSON.stringify(item)}</li>`;
                            }
                        }
                    } else {
                        html += `<li>${item}</li>`;
                    }
                });
                html += '</ul>';
                return html;
            }

            if (typeof valor === 'object') {
                return '<pre class="text-xs">' + JSON.stringify(valor, null, 2) + '</pre>';
            }

            return String(valor);
        }

        function renderizarCampo(item) {
            const campo = item.label || item.campo || 'Campo';
            const de = formatarValorLog(item.de);
            const para = formatarValorLog(item.para);
            const isCriacao = item.de === null || item.de === undefined;

            if (item.txt !== undefined) {
                return `
                <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <div class="font-medium text-slate-800 mb-1">${campo}</div>
                    <div class="text-sm text-slate-600">${item.txt}</div>
                </li>`;
            }

            if (isCriacao) {
                return `
                <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <div class="font-medium text-slate-800 mb-2">${campo}</div>
                    <div class="text-sm text-green-600 bg-green-50 px-2 py-1 rounded">${para}</div>
                </li>`;
            }

            return `
            <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                <div class="font-medium text-slate-800 mb-2">${campo}</div>
                <div class="flex items-center text-sm">
                    <span class="text-red-600 bg-red-50 px-2 py-1 rounded">${de}</span>
                    <i class="fas fa-arrow-right mx-2 text-slate-400"></i>
                    <span class="text-green-600 bg-green-50 px-2 py-1 rounded">${para}</span>
                </div>
            </li>`;
        }

        function abrirPainel(detalhesJson) {
            let html = '';

            try {
                const detalhes = JSON.parse(detalhesJson);

                if (typeof detalhes === 'object' && !Array.isArray(detalhes)) {
                    const abas = Object.keys(detalhes);
                    if (abas.length > 0 && Array.isArray(detalhes[abas[0]])) {
                        html = '<div class="space-y-4">';
                        abas.forEach(nomeAba => {
                            const campos = detalhes[nomeAba];
                            if (!Array.isArray(campos) || campos.length === 0) return;

                            html += `
                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                <div class="bg-slate-100 px-3 py-2 font-medium text-slate-700 text-sm border-b border-slate-200">
                                    <i class="fas fa-folder-open mr-2 text-slate-400"></i>${nomeAba}
                                </div>
                                <ul class="p-3 space-y-2">
                            `;
                            campos.forEach(item => {
                                html += renderizarCampo(item);
                            });
                            html += '</ul></div>';
                        });
                        html += '</div>';
                    } else {
                        html = '<div class="bg-slate-50 p-3 rounded-lg border border-slate-200">';
                        html += '<pre class="text-sm text-slate-700 whitespace-pre-wrap">' + JSON.stringify(detalhes, null, 2) + '</pre>';
                        html += '</div>';
                    }
                }
                else if (Array.isArray(detalhes) && detalhes.length > 0) {
                    html = '<ul class="space-y-3">';
                    detalhes.forEach(item => {
                        if (item.aba) {
                            html += `<li class="text-xs text-slate-400 -mb-2 mt-2">${item.aba}</li>`;
                        }
                        html += renderizarCampo(item);
                    });
                    html += '</ul>';
                } else {
                    html = '<p class="text-slate-500">' + i18n.unrecognizedFormat + '</p>';
                }
            } catch (e) {
                html = '<div class="bg-slate-50 p-3 rounded-lg border border-slate-200">';
                html += '<pre class="text-sm text-slate-700 whitespace-pre-wrap">' + detalhesJson + '</pre>';
                html += '</div>';
            }

            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openOffcanvasContent',
                    content: html,
                    title: i18n.detailsTitle,
                    width: '40%'
                }, '*');
            }
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement) return;

            const { page, perPage, total } = pagination;

            if (total === null) {
                const start = ((page - 1) * perPage) + 1;
                const end = start + perPage - 1;
                infoElement.textContent = i18n.showingLazy.replace(':start', start).replace(':end', end);
            } else {
                const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
                const end = Math.min(page * perPage, total);
                infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
            }
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

            if (totalPages === null) {
                buttons += `
                <li>
                    <button class="pagination-button numbered active">${page}</button>
                </li>
            `;
            } else {
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
            carregarLogs(currentPage, perPage, searchTerm);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarLogs(currentPage, perPage, searchTerm);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarLogs(currentPage, perPage, searchTerm);
            }, 300);
        });

        carregarLogs(currentPage, perPage, searchTerm);
    })();
</script>
@endsection
