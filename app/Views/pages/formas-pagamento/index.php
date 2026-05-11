@extends('layouts.iframe')

@section('title', '<?= t("modules.formas_pagamento.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.formas_pagamento.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.formas_pagamento.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnComandos" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-terminal mr-2"></i><?= t('modules.formas_pagamento.actions.installment_commands') ?>
            </button>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.formas_pagamento.actions.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.formas_pagamento.table.name') ?></th>
                    <th class="table-header hidden lg:table-cell text-center"><?= t('modules.formas_pagamento.table.fees') ?></th>
                    <th class="table-header hidden lg:table-cell text-center"><?= t('modules.formas_pagamento.table.early_discount') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.formas_pagamento.table.post_as_paid') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.formas_pagamento.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.formas_pagamento.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.formas_pagamento.pagination.rows_per_page') ?></label>
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
            loadError: '<?= addslashes(t('modules.formas_pagamento.messages.load_error')) ?>',
            serverError: '<?= addslashes(t('modules.formas_pagamento.messages.server_error')) ?>',
            noRecords: '<?= addslashes(t('modules.formas_pagamento.messages.no_records')) ?>',
            deleteError: '<?= addslashes(t('modules.formas_pagamento.messages.delete_error')) ?>',
            thisRecord: '<?= addslashes(t('modules.formas_pagamento.messages.this_record')) ?>',
            deleteConfirm: '<?= addslashes(t('modules.formas_pagamento.messages.delete_confirm')) ?>',
            recordType: '<?= addslashes(t('modules.formas_pagamento.record_type')) ?>',
            noName: '<?= addslashes(t('modules.formas_pagamento.badges.no_name')) ?>',
            badgeFixed: '<?= addslashes(t('modules.formas_pagamento.badges.fixed')) ?>',
            badgeFixedInstallment: '<?= addslashes(t('modules.formas_pagamento.badges.fixed_installment')) ?>',
            badgePercentInstallment: '<?= addslashes(t('modules.formas_pagamento.badges.percent_installment')) ?>',
            noFees: '<?= addslashes(t('modules.formas_pagamento.badges.no_fees')) ?>',
            badgeYes: '<?= addslashes(t('modules.formas_pagamento.badges.yes')) ?>',
            badgeNo: '<?= addslashes(t('modules.formas_pagamento.badges.no')) ?>',
            badgeActive: '<?= addslashes(t('modules.formas_pagamento.badges.active')) ?>',
            badgeInactive: '<?= addslashes(t('modules.formas_pagamento.badges.inactive')) ?>',
            badgeInDays: '<?= addslashes(t('modules.formas_pagamento.badges.in_days')) ?>',
            actionEdit: '<?= addslashes(t('modules.formas_pagamento.actions.edit')) ?>',
            actionDelete: '<?= addslashes(t('modules.formas_pagamento.actions.delete')) ?>',
            showingPagination: '<?= addslashes(t('modules.formas_pagamento.pagination.showing')) ?>',
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

                const result = await API.get('/api/formas-pagamento', {
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

                // Taxas
                let taxasInfo = [];
                if (parseFloat(item.taxa_fixa) > 0) {
                    taxasInfo.push(`${i18n.badgeFixed}: ${Currency.format(item.taxa_fixa, true)}`);
                }
                if (parseFloat(item.taxa_fixa_parcela) > 0) {
                    taxasInfo.push(`${i18n.badgeFixedInstallment}: ${Currency.format(item.taxa_fixa_parcela, true)}`);
                }
                if (parseFloat(item.taxa_percentual_parcela) > 0) {
                    taxasInfo.push(`${parseFloat(item.taxa_percentual_parcela).toFixed(2)}${i18n.badgePercentInstallment}`);
                }
                const taxasBadge = taxasInfo.length > 0 ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${taxasInfo.join(' | ')}</span>` :
                    `<span class="text-slate-400 text-xs">${i18n.noFees}</span>`;

                // Desconto antecipacao
                let descontoBadge = '<span class="text-slate-400 text-xs">-</span>';
                if (parseInt(item.desconto_antecipacao_dias) > 0 && parseFloat(item.desconto_antecipacao_percentual) > 0) {
                    descontoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${parseFloat(item.desconto_antecipacao_percentual).toFixed(2)}% ${i18n.badgeInDays.replace(':days', item.desconto_antecipacao_dias)}</span>`;
                }

                // Lancar Pago
                const lancarPagoBadge = item.lancar_pago === 'S' ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-check mr-1"></i>${i18n.badgeYes}</span>` :
                    `<span class="text-slate-400 text-xs">${i18n.badgeNo}</span>`;

                // Status
                const statusBadge = item.status === 'A' ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.badgeActive}</span>` :
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-times mr-1"></i>${i18n.badgeInactive}</span>`;

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                    </td>
                    <td class="table-cell hidden lg:table-cell text-center">${taxasBadge}</td>
                    <td class="table-cell hidden lg:table-cell text-center">${descontoBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${lancarPagoBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${statusBadge}</td>
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
                    navegarPara('/pages/formas-pagamento/adicionar?id=' + id);
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
                            recordType: i18n.recordType,
                            confirmType: 'none'
                        }, '*');
                    } else {
                        if (confirm(i18n.deleteConfirm.replace(':name', name))) {
                            excluirRegistro(id);
                        }
                    }
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
            navegarPara('/pages/formas-pagamento/adicionar');
        });

        document.getElementById('btnComandos')?.addEventListener('click', function() {
            navegarPara('/pages/comandos-parcelas');
        });

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/formas-pagamento/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    alert(result.message || i18n.deleteError);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert(i18n.deleteError);
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
