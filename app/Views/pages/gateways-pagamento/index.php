@extends('layouts.iframe')

@section('title', '<?= t("modules.gateways_pagamento.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.gateways_pagamento.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.gateways_pagamento.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
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
                    <th class="table-header"><?= t('modules.gateways_pagamento.table.gateway') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.gateways_pagamento.table.branch') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.gateways_pagamento.table.methods') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.gateways_pagamento.table.environment') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.gateways_pagamento.table.status') ?></th>
                    <th class="table-header px-2 w-48 text-center"><?= t('modules.gateways_pagamento.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.gateways_pagamento.pagination.rows_per_page') ?></label>
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
            loading: '<?= addslashes(t("common.labels.loading")) ?>',
            loadError: '<?= addslashes(t("modules.gateways_pagamento.messages.load_error")) ?>',
            serverError: '<?= addslashes(t("modules.gateways_pagamento.messages.server_error")) ?>',
            noRecords: '<?= addslashes(t("modules.gateways_pagamento.messages.no_records")) ?>',
            noName: '<?= addslashes(t("modules.gateways_pagamento.messages.no_name")) ?>',
            methodPix: '<?= addslashes(t("modules.gateways_pagamento.methods.pix")) ?>',
            methodBoleto: '<?= addslashes(t("modules.gateways_pagamento.methods.boleto")) ?>',
            methodCredit: '<?= addslashes(t("modules.gateways_pagamento.methods.credit_card")) ?>',
            methodDebit: '<?= addslashes(t("modules.gateways_pagamento.methods.debit_card")) ?>',
            methodNone: '<?= addslashes(t("modules.gateways_pagamento.methods.none")) ?>',
            envProduction: '<?= addslashes(t("modules.gateways_pagamento.environment.production")) ?>',
            envSandbox: '<?= addslashes(t("modules.gateways_pagamento.environment.sandbox")) ?>',
            statusActive: '<?= addslashes(t("modules.gateways_pagamento.status_options.active")) ?>',
            statusInactive: '<?= addslashes(t("modules.gateways_pagamento.status_options.inactive")) ?>',
            statusNotConfigured: '<?= addslashes(t("modules.gateways_pagamento.status_options.not_configured")) ?>',
            allBranches: '<?= addslashes(t("modules.gateways_pagamento.table.all_branches")) ?>',
            countryBR: '<?= addslashes(t("modules.gateways_pagamento.countries.BR")) ?>',
            countryPY: '<?= addslashes(t("modules.gateways_pagamento.countries.PY")) ?>',
            countryINTL: '<?= addslashes(t("modules.gateways_pagamento.countries.INTL")) ?>',
            actionTest: '<?= addslashes(t("modules.gateways_pagamento.actions.test_connection")) ?>',
            actionEdit: '<?= addslashes(t("common.buttons.edit")) ?>',
            actionConfigure: '<?= addslashes(t("modules.gateways_pagamento.actions.configure")) ?>',
            actionDeactivate: '<?= addslashes(t("modules.gateways_pagamento.actions.deactivate")) ?>',
            actionActivate: '<?= addslashes(t("modules.gateways_pagamento.actions.activate")) ?>',
            actionDelete: '<?= addslashes(t("common.buttons.delete")) ?>',
            recordType: '<?= addslashes(t("modules.gateways_pagamento.record_type")) ?>',
            testSuccess: '<?= addslashes(t("modules.gateways_pagamento.messages.test_success")) ?>',
            testFail: '<?= addslashes(t("modules.gateways_pagamento.messages.test_fail")) ?>',
            testError: '<?= addslashes(t("modules.gateways_pagamento.messages.test_error")) ?>',
            statusError: '<?= addslashes(t("modules.gateways_pagamento.messages.status_error")) ?>',
            deleteError: '<?= addslashes(t("modules.gateways_pagamento.messages.delete_error")) ?>',
            showingPagination: '<?= addslashes(t("modules.gateways_pagamento.pagination.showing")) ?>',
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

                const result = await API.get('/api/gateways-pagamento', {
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

        function getGatewayIcon(code) {
            const icons = {
                'asaas': 'fa-solid fa-a',
                'stripe': 'fa-brands fa-stripe',
                'square': 'fa-solid fa-square',
                'cora': 'fa-solid fa-c',
                'efipay': 'fa-solid fa-e',
                'inter': 'fa-solid fa-i',
                'bradesco': 'fa-solid fa-b',
                'itau': 'fa-solid fa-i',
                'bancard': 'fa-solid fa-credit-card',
                'pagopar': 'fa-solid fa-p'
            };
            return icons[code] || 'fa-solid fa-plug';
        }

        function getCountryFlag(code) {
            const gatewayCountries = {
                'asaas': 'BR',
                'stripe': 'INTL',
                'square': 'INTL',
                'cora': 'BR',
                'efipay': 'BR',
                'inter': 'BR',
                'bradesco': 'BR',
                'itau': 'BR',
                'bancard': 'PY',
                'pagopar': 'PY'
            };
            const country = gatewayCountries[code] || 'INTL';

            if (country === 'BR') return `<span class="mr-1" title="${i18n.countryBR}">🇧🇷</span>`;
            if (country === 'PY') return `<span class="mr-1" title="${i18n.countryPY}">🇵🇾</span>`;
            return `<span class="mr-1" title="${i18n.countryINTL}">🌎</span>`;
        }

        function formatCurrencies(currencies) {
            if (!currencies) return '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-xs">BRL</span>';

            let arr = currencies;
            if (typeof currencies === 'string') {
                try {
                    arr = JSON.parse(currencies);
                } catch(e) {
                    arr = ['BRL'];
                }
            }

            if (!Array.isArray(arr) || arr.length === 0) {
                arr = ['BRL'];
            }

            return arr.map(c => `<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-xs">${escapeHtml(c)}</span>`).join('');
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
                const configured = item.configured !== false;
                const nome = item.nome || i18n.noName;
                const nomeEscapado = escapeHtml(nome);
                const gatewayCode = item.gateway_code || '';

                // Metodos habilitados
                let metodos = [];
                if (item.pix_enabled == 1) metodos.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.methodPix}</span>`);
                if (item.boleto_enabled == 1) metodos.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">${i18n.methodBoleto}</span>`);
                if (item.credit_card_enabled == 1) metodos.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">${i18n.methodCredit}</span>`);
                if (item.debit_card_enabled == 1) metodos.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${i18n.methodDebit}</span>`);
                const metodosBadges = metodos.length > 0 ? metodos.join(' ') : `<span class="text-slate-400 text-xs">${i18n.methodNone}</span>`;

                // Ambiente
                const ambienteBadge = !configured ?
                    `<span class="text-slate-400 text-xs">-</span>` :
                    (item.ambiente === 'production' ?
                        `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check-circle mr-1"></i>${i18n.envProduction}</span>` :
                        `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-flask mr-1"></i>${i18n.envSandbox}</span>`);

                // Status
                const statusBadge = !configured ?
                    `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-50 text-sky-700"><i class="fas fa-circle-plus mr-1"></i>${i18n.statusNotConfigured}</span>` :
                    (item.status === 'A' ?
                        `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.statusActive}</span>` :
                        `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-times mr-1"></i>${i18n.statusInactive}</span>`);

                // Filiais
                const filiais = configured ? (item.filiais_nomes || i18n.allBranches) : '-';
                const actions = configured ? `
                        <button title="${i18n.actionTest}" class="btn-icon text-green-600 hover:text-green-800 btn-test" data-id="${item.id}"><i class="fas fa-plug"></i></button>
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                        <button title="${item.status === 'A' ? i18n.actionDeactivate : i18n.actionActivate}" class="btn-icon ${item.status === 'A' ? 'text-green-600 hover:text-green-800' : 'text-slate-500 hover:text-slate-700'} btn-status" data-id="${item.id}" data-status="${item.status}"><i class="fas ${item.status === 'A' ? 'fa-toggle-on' : 'fa-toggle-off'}"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    ` : `
                        <button title="${i18n.actionConfigure}" class="btn-icon text-blue-600 hover:text-blue-800 btn-configure" data-gateway="${escapeHtml(gatewayCode)}"><i class="fas fa-gear"></i></button>
                    `;

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-slate-100 rounded-lg mr-3">
                                <i class="${getGatewayIcon(gatewayCode)} text-slate-600"></i>
                            </div>
                            <div>
                                <div class="font-medium flex items-center">
                                    ${getCountryFlag(gatewayCode)}
                                    ${nomeEscapado}
                                </div>
                                <div class="text-xs text-slate-500">
                                    ${escapeHtml(gatewayCode.toUpperCase())}
                                    ${formatCurrencies(item.currencies)}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="table-cell hidden lg:table-cell">${escapeHtml(filiais)}</td>
                    <td class="table-cell hidden md:table-cell text-center">
                        <div class="flex flex-wrap justify-center gap-1">${metodosBadges}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell text-center">${ambienteBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-48 text-right">
                        ${actions}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            // Event listeners
            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/gateways-pagamento/adicionar?id=' + id);
                });
            });

            tbody.querySelectorAll('.btn-test').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    testarConexao(id, this);
                });
            });

            tbody.querySelectorAll('.btn-status').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    alterarStatus(id);
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || '';

                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: i18n.recordType,
                        confirmType: 'none'
                    }, '*');
                });
            });

            tbody.querySelectorAll('.btn-configure').forEach(button => {
                button.addEventListener('click', function() {
                    const gateway = this.getAttribute('data-gateway');
                    navegarPara('/pages/gateways-pagamento/adicionar?gateway=' + encodeURIComponent(gateway));
                });
            });
        }

        async function testarConexao(id, button) {
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            try {
                const result = await API.post(`/api/gateways-pagamento/${id}/testar`);

                if (result.success) {
                    mostrarAlerta(i18n.testSuccess);
                } else {
                    mostrarAlerta(result.message || i18n.testFail);
                }
            } catch (error) {
                console.error('Erro ao testar:', error);
                mostrarAlerta(i18n.testError);
            } finally {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        }

        async function alterarStatus(id) {
            try {
                const result = await API.post(`/gateways-pagamento/${id}/status`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    mostrarAlerta(result.message || i18n.statusError);
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarAlerta(i18n.statusError);
            }
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement || !pagination) return;

            const { page, perPage, total } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
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
            navegarPara('/pages/gateways-pagamento/adicionar');
        });

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/gateways-pagamento/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    mostrarAlerta(result.message || i18n.deleteError);
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarAlerta(i18n.deleteError);
            }
        }

        function mostrarAlerta(mensagem) {
            window.parent.postMessage({
                action: 'openAlert',
                message: mensagem
            }, '*');
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
