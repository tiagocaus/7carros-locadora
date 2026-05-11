@extends('layouts.iframe')

@section('title', '<?= t("modules.taxas_servicos.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.taxas_servicos.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.taxas_servicos.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.taxas_servicos.table.name') ?></th>
                    <th class="table-header hidden lg:table-cell text-center"><?= t('modules.taxas_servicos.table.calculation_base') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.taxas_servicos.table.value') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.taxas_servicos.table.auto_apply') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.taxas_servicos.table.branches') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.taxas_servicos.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.taxas_servicos.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"></span>
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
<script>
    (function() {
        const i18n = {
            loading: '<?= addslashes(t('common.labels.loading')) ?>',
            loadError: '<?= addslashes(t('modules.taxas_servicos.messages.load_error')) ?>',
            serverError: '<?= addslashes(t('modules.taxas_servicos.messages.server_error')) ?>',
            noRecords: '<?= addslashes(t('modules.taxas_servicos.messages.no_records')) ?>',
            noName: '<?= addslashes(t('modules.taxas_servicos.messages.no_name')) ?>',
            allBranches: '<?= addslashes(t('modules.taxas_servicos.messages.all_branches')) ?>',
            baseFixed: '<?= addslashes(t('modules.taxas_servicos.badges.base_fixed')) ?>',
            basePerPeriod: '<?= addslashes(t('modules.taxas_servicos.badges.base_per_period')) ?>',
            baseTotalValue: '<?= addslashes(t('modules.taxas_servicos.badges.base_total_value')) ?>',
            applyYes: '<?= addslashes(t('modules.taxas_servicos.badges.apply_yes')) ?>',
            applyNo: '<?= addslashes(t('modules.taxas_servicos.badges.apply_no')) ?>',
            actionEdit: '<?= addslashes(t('common.buttons.edit')) ?>',
            actionDelete: '<?= addslashes(t('common.buttons.delete')) ?>',
            thisRecord: '<?= addslashes(t('modules.taxas_servicos.messages.this_record')) ?>',
            recordType: '<?= addslashes(t('modules.taxas_servicos.record_type')) ?>',
            deleteError: '<?= addslashes(t('modules.taxas_servicos.messages.delete_error')) ?>',
            showingPagination: '<?= addslashes(t('modules.taxas_servicos.pagination.showing')) ?>',
        };

        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
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

        async function carregarDados(page = 1, recordsPerPage = 10, search = '') {
            try {
                mostrarLoading();

                const result = await API.get('/api/taxas-e-servicos', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search
                });

                if (result.success) {
                    renderTabela(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            }
        }

        function mostrarLoading() {
            tbody.innerHTML = `
            <tr>
                <td colspan="6" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
        }

        function mostrarMensagemErro(mensagem) {
            tbody.innerHTML = `
            <tr>
                <td colspan="6" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function formatarMoeda(valor) {
            return parseFloat(valor || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        function formatarValor(valor, tipo) {
            if (tipo === 'POR') {
                return parseFloat(valor || 0).toFixed(2) + '%';
            }
            return formatarMoeda(valor);
        }

        function getBaseCalculoLabel(baseCalculo) {
            const labels = {
                'FIX': i18n.baseFixed,
                'PER': i18n.basePerPeriod,
                'VLT': i18n.baseTotalValue
            };
            return labels[baseCalculo] || baseCalculo;
        }

        function renderTabela(dados) {
            if (!dados || dados.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            dados.forEach(item => {
                const nome = item.nome || i18n.noName;
                const nomeEscapado = escapeHtml(nome);

                // Base de Calculo
                let baseCalculoBadge;
                switch (item.base_calculo) {
                    case 'FIX':
                        baseCalculoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">${i18n.baseFixed}</span>`;
                        break;
                    case 'PER':
                        baseCalculoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">${i18n.basePerPeriod}</span>`;
                        break;
                    case 'VLT':
                        baseCalculoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">${i18n.baseTotalValue}</span>`;
                        break;
                    default:
                        baseCalculoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">${i18n.baseFixed}</span>`;
                }

                // Valor
                const valorFormatado = formatarValor(item.valor, item.tipo_valor);
                const valorBadge = item.tipo_valor === 'POR' ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${valorFormatado}</span>` :
                    `<span class="font-medium">${valorFormatado}</span>`;

                // Aplicar Auto
                const aplicarBadge = item.aplicar === 'S' ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.applyYes}</span>` :
                    `<span class="text-slate-400 text-xs">${i18n.applyNo}</span>`;

                // Filiais
                const filiais = item.filiais_nomes || i18n.allBranches;
                const filiaisTexto = Str.limit(filiais, 30);
                const filiaisBadge = `<span class="text-xs text-slate-600" title="${escapeHtml(filiais)}">${escapeHtml(filiaisTexto)}</span>`;

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                    </td>
                    <td class="table-cell hidden lg:table-cell text-center">${baseCalculoBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${valorBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${aplicarBadge}</td>
                    <td class="table-cell hidden sm:table-cell">${filiaisBadge}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/taxas-e-servicos/adicionar?id=' + id);
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisRecord;

                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: i18n.recordType,
                        confirmType: 'text'
                    }, '*');
                });
            });
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement || !pagination) return;

            const {
                page,
                perPage,
                total
            } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
            if (!paginationNav || !pagination) return;

            const {
                page,
                totalPages,
                hasPrev,
                hasNext
            } = pagination;

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
            carregarDados(currentPage, perPage, searchTerm);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarDados(currentPage, perPage, searchTerm);
            }, 300);
        });

        document.getElementById('btnNovo')?.addEventListener('click', function() {
            navegarPara('/pages/taxas-e-servicos/adicionar');
        });

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/taxas-e-servicos/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.deleteError }, '*');
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

        carregarDados(currentPage, perPage, searchTerm);
    })();
</script>
@endsection
