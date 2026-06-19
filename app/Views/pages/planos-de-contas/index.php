@extends('layouts.iframe')

@section('title', t('modules.planos_contas.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0">{{ t('modules.planos_contas.title') }}</h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filterTipo" class="form-input-focus w-40">
                <option value="">{{ t('modules.planos_contas.filters.all_types') }}</option>
                <option value="A">{{ t('modules.planos_contas.fields.tipo_ativo') }}</option>
                <option value="P">{{ t('modules.planos_contas.fields.tipo_passivo') }}</option>
                <option value="D">{{ t('modules.planos_contas.fields.tipo_despesa') }}</option>
                <option value="R">{{ t('modules.planos_contas.fields.tipo_receita') }}</option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="{{ t('modules.planos_contas.placeholders.search') }}" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i>{{ t('common.buttons.new') }}
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header w-32">{{ t('modules.planos_contas.fields.hierarquia') }}</th>
                    <th class="table-header">{{ t('modules.planos_contas.fields.descricao') }}</th>
                    <th class="table-header hidden md:table-cell w-32 text-center">{{ t('modules.planos_contas.fields.tipo') }}</th>
                    <th class="table-header px-2 w-28 text-center">{{ t('common.labels.actions') }}</th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2">{{ t('common.labels.rows_per_page') }}:</label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo">{{ t('common.labels.showing_records', ['start' => 0, 'end' => 0, 'total' => 0]) }}</span>
        </div>
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px">
                <li><button class="pagination-button arrow-button rounded-l-md" disabled><i class="fas fa-chevron-left"></i></button></li>
                <li><button class="pagination-button numbered active">1</button></li>
                <li><button class="pagination-button arrow-button rounded-r-md" disabled><i class="fas fa-chevron-right"></i></button></li>
            </ul>
        </nav>
    </div>
</div>
@endsection

@section('scripts')
<?php
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$i18nPlanosContas = [
    'errorList' => t('modules.planos_contas.messages.error_list'),
    'serverError' => t('common.errors.server_error'),
    'loading' => t('common.labels.loading'),
    'noRecords' => t('modules.planos_contas.messages.no_records'),
    'edit' => t('common.buttons.edit'),
    'delete' => t('common.buttons.delete'),
    'thisRecord' => t('modules.planos_contas.messages.this_record'),
    'showing' => t('common.labels.showing'),
    'of' => t('common.labels.of'),
    'records' => t('common.labels.records'),
    'errorDelete' => t('modules.planos_contas.messages.error_delete'),
];
?>
<script>
    (function() {
        const i18n = <?= json_encode($i18nPlanosContas, $jsFlags) ?>;
        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let tipoFilter = '';
        let searchTimeout = null;

        const tbody = document.getElementById('tableBody');

        function navegarPara(page) {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: page
                }, '*');
            } else {
                window.location.href = page;
            }
        }

        async function carregarDados(page = 1, recordsPerPage = 10, search = '', tipo = '') {
            try {
                mostrarLoading();

                const params = {
                    page: page,
                    perPage: recordsPerPage
                };

                if (search) params.search = search;
                if (tipo) params.tipo = tipo;

                const result = await API.get('/api/planos-de-contas', params);

                if (result.success) {
                    renderTabela(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    mostrarMensagemErro(i18n.errorList + ': ' + (result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            }
        }

        function mostrarLoading() {
            tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
        }

        function mostrarMensagemErro(mensagem) {
            tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function getTipoBadgeClass(tipo) {
            switch(tipo) {
                case 'A': return 'bg-blue-100 text-blue-700';
                case 'P': return 'bg-red-100 text-red-700';
                case 'D': return 'bg-amber-100 text-amber-700';
                case 'R': return 'bg-green-100 text-green-700';
                default: return 'bg-slate-100 text-slate-700';
            }
        }

        function renderTabela(dados) {
            if (!dados || dados.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            dados.forEach(item => {
                const hierarquia = item.hierarquia || '';
                const descricao = item.descricao || '';
                const tipoLabel = item.tipo_label || item.tipo;
                const tipoClass = getTipoBadgeClass(item.tipo);

                // Indentacao baseada no nivel da hierarquia
                const nivel = (hierarquia.match(/\./g) || []).length;
                const indentClass = nivel > 0 ? `pl-${Math.min(nivel * 4, 16)}` : '';
                const fontClass = nivel === 0 ? 'font-bold' : (nivel === 1 ? 'font-semibold' : '');

                // Planos do sistema nao podem ser editados/excluidos
                const isSystem = item.is_system === true;

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell font-mono text-sm">${escapeHtml(hierarquia)}</td>
                    <td class="table-cell ${indentClass} ${fontClass}">${escapeHtml(descricao)}</td>
                    <td class="table-cell hidden md:table-cell text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${tipoClass}">${escapeHtml(tipoLabel)}</span>
                    </td>
                    <td class="table-cell px-2 w-28 text-right">
                        ${!isSystem ? `<button title="${i18n.edit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>` : ''}
                        ${!isSystem ? `<button title="${i18n.delete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${escapeHtml(hierarquia + ' - ' + descricao)}"><i class="fas fa-trash"></i></button>` : ''}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/planos-de-contas/adicionar?id=' + id);
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisRecord;

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: id,
                            recordName: name,
                            recordType: 'plano_contas',
                            confirmType: 'none'
                        }, '*');
                    }
                });
            });
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement || !pagination) return;

            const { page, perPage, total } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = `${i18n.showing} ${start}-${end} ${i18n.of} ${total} ${i18n.records}`;
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
            if (!paginationNav || !pagination) return;

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
            let endPage = Math.min(totalPages || 1, startPage + maxButtons - 1);

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
            carregarDados(currentPage, perPage, searchTerm, tipoFilter);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm, tipoFilter);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarDados(currentPage, perPage, searchTerm, tipoFilter);
            }, 300);
        });

        document.getElementById('filterTipo')?.addEventListener('change', function(e) {
            tipoFilter = e.target.value;
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm, tipoFilter);
        });

        document.getElementById('btnNovo')?.addEventListener('click', function() {
            navegarPara('/pages/planos-de-contas/adicionar');
        });

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/planos-de-contas/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm, tipoFilter);
                } else {
                    openAlert(result.message || i18n.errorDelete);
                }
            } catch (error) {
                console.error('Erro:', error);
                openAlert(i18n.errorDelete);
            }
        }

        function openAlert(message) {
            if (window.parent !== window) {
                window.parent.postMessage({ action: 'openAlert', message }, '*');
            } else {
                console.error(message);
            }
        }

        window.addEventListener('message', function(event) {
            if (!event.data || !event.data.action) return;

            if (event.data.action === 'confirmDelete') {
                excluirRegistro(event.data.recordId);
            }
        });

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        carregarDados(currentPage, perPage, searchTerm, tipoFilter);
    })();
</script>
@endsection
