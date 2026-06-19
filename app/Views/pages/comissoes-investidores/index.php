@extends('layouts.iframe')

@section('title', t('modules.comissoes_investidores.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.comissoes_investidores.title') ?></h2>
    </div>

    <!-- Filtros -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.comissoes_investidores.filters.investor') ?></label>
                <select id="filtroInvestidor" class="form-input-focus w-full" data-chosen-type="server" data-api-url="/api/fornecedores/investidores/select">
                    <option value=""><?= t('modules.comissoes_investidores.status_options.all') ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.comissoes_investidores.filters.status') ?></label>
                <select id="filtroStatus" class="form-input-focus w-full">
                    <option value=""><?= t('modules.comissoes_investidores.status_options.all') ?></option>
                    <option value="pendente"><?= t('modules.comissoes_investidores.status_options.pending') ?></option>
                    <option value="pago"><?= t('modules.comissoes_investidores.status_options.paid') ?></option>
                    <option value="cancelado"><?= t('modules.comissoes_investidores.status_options.cancelled') ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.comissoes_investidores.filters.type') ?></label>
                <select id="filtroTipo" class="form-input-focus w-full">
                    <option value=""><?= t('modules.comissoes_investidores.type_options.all') ?></option>
                    <option value="locacao"><?= t('modules.comissoes_investidores.type_options.rental') ?></option>
                    <option value="contrato"><?= t('modules.comissoes_investidores.type_options.contract') ?></option>
                    <option value="mensal"><?= t('modules.comissoes_investidores.type_options.monthly') ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.comissoes_investidores.filters.date_start') ?></label>
                <input type="date" id="filtroDataInicio" class="form-input-focus w-full">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.comissoes_investidores.filters.date_end') ?></label>
                <input type="date" id="filtroDataFim" class="form-input-focus w-full">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button id="btnLimparFiltros" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium mr-2">
                <i class="fas fa-times mr-1"></i><?= t('common.buttons.clear') ?>
            </button>
            <button id="btnFiltrar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                <i class="fas fa-filter mr-1"></i><?= t('common.buttons.filter') ?>
            </button>
        </div>
    </div>

    <!-- Totalizadores -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-amber-600 font-medium"><?= t('modules.comissoes_investidores.totals.pending') ?></p>
                    <p class="text-2xl font-bold text-amber-700" id="totalPendente">R$ 0,00</p>
                </div>
                <div class="text-amber-500">
                    <i class="fas fa-clock text-3xl"></i>
                </div>
            </div>
            <p class="text-xs text-amber-600 mt-1"><span id="qtdPendente">0</span> <?= t('modules.comissoes_investidores.totals.commissions_count') ?></p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-600 font-medium"><?= t('modules.comissoes_investidores.totals.paid') ?></p>
                    <p class="text-2xl font-bold text-green-700" id="totalPago">R$ 0,00</p>
                </div>
                <div class="text-green-500">
                    <i class="fas fa-check-circle text-3xl"></i>
                </div>
            </div>
            <p class="text-xs text-green-600 mt-1"><span id="qtdPago">0</span> <?= t('modules.comissoes_investidores.totals.commissions_count') ?></p>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-600 font-medium"><?= t('modules.comissoes_investidores.totals.cancelled') ?></p>
                    <p class="text-2xl font-bold text-slate-700" id="totalCancelado">R$ 0,00</p>
                </div>
                <div class="text-slate-400">
                    <i class="fas fa-ban text-3xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-1"><span id="qtdCancelado">0</span> <?= t('modules.comissoes_investidores.totals.commissions_count') ?></p>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.comissoes_investidores.table.date_ref') ?></th>
                    <th class="table-header"><?= t('modules.comissoes_investidores.table.investor') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.comissoes_investidores.table.vehicle') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.comissoes_investidores.table.type') ?></th>
                    <th class="table-header text-right"><?= t('modules.comissoes_investidores.table.base_value') ?></th>
                    <th class="table-header text-right"><?= t('modules.comissoes_investidores.table.rental_company') ?></th>
                    <th class="table-header text-right"><?= t('modules.comissoes_investidores.table.investor_value') ?></th>
                    <th class="table-header text-center"><?= t('modules.comissoes_investidores.table.status') ?></th>
                    <th class="table-header px-2 w-28 text-center"><?= t('modules.comissoes_investidores.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas inseridas pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.comissoes_investidores.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= str_replace([':start', ':end', ':total'], ['0', '0', '0'], t('modules.comissoes_investidores.pagination.showing')) ?></span>
        </div>
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px" id="paginationContainer">
            </ul>
        </nav>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.comissoes_investidores.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.comissoes_investidores.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.comissoes_investidores.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.comissoes_investidores.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusPending: <?= json_encode(t('modules.comissoes_investidores.status_options.pending'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusPaid: <?= json_encode(t('modules.comissoes_investidores.status_options.paid'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusCancelled: <?= json_encode(t('modules.comissoes_investidores.status_options.cancelled'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeRental: <?= json_encode(t('modules.comissoes_investidores.type_options.rental'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeContract: <?= json_encode(t('modules.comissoes_investidores.type_options.contract'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeMonthly: <?= json_encode(t('modules.comissoes_investidores.type_options.monthly'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionMarkPaid: <?= json_encode(t('modules.comissoes_investidores.actions.mark_paid'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionCancel: <?= json_encode(t('modules.comissoes_investidores.actions.cancel'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        confirmPayment: <?= json_encode(t('modules.comissoes_investidores.messages.confirm_payment'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        paidSuccess: <?= json_encode(t('modules.comissoes_investidores.messages.paid_success'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        cancelReason: <?= json_encode(t('modules.comissoes_investidores.messages.cancel_reason'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        cancelledSuccess: <?= json_encode(t('modules.comissoes_investidores.messages.cancelled_success'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    // Estado
    let currentPage = 1;
    let perPage = 10;
    let filtros = {};
    let pendingAction = null;

    // Elementos
    const tbody = document.getElementById('tableBody');

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarDados() {
        try {
            mostrarLoading();

            const params = {
                page: currentPage,
                perPage: perPage,
                ...filtros
            };

            const result = await API.get('/api/comissoes-investidores', params);

            if (result.success) {
                renderDados(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    async function carregarTotais() {
        try {
            const result = await API.get('/api/comissoes-investidores/totais', filtros);

            if (result.success) {
                const t = result.data;

                document.getElementById('totalPendente').textContent = formatarMoeda(t.pendente.valor_repasse);
                document.getElementById('qtdPendente').textContent = t.pendente.quantidade;

                document.getElementById('totalPago').textContent = formatarMoeda(t.pago.valor_repasse);
                document.getElementById('qtdPago').textContent = t.pago.quantidade;

                document.getElementById('totalCancelado').textContent = formatarMoeda(t.cancelado.valor_repasse);
                document.getElementById('qtdCancelado').textContent = t.cancelado.quantidade;
            }
        } catch (error) {
            console.error('Erro ao carregar totais:', error);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderDados(items) {
        if (!items || items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        items.forEach(item => {
            const dataRef = formatarData(item.data_referencia);
            const investidor = escapeHtml(item.fornecedor_nome || '-');
            const veiculo = item.veiculo_placa ? `${item.veiculo_placa} - ${item.veiculo_modelo || ''}` : '-';
            const tipoOrigem = formatarTipoOrigem(item.tipo_origem);
            const valorBase = formatarMoeda(item.valor_base);
            const valorLocadora = formatarMoeda(item.valor_comissao_locadora);
            const valorInvestidor = formatarMoeda(item.valor_repasse_investidor);
            const statusBadge = renderStatusBadge(item.status);

            const podeAcoes = item.status === 'pendente';

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell text-slate-600">${dataRef}</td>
                    <td class="table-cell">
                        <div class="font-medium">${investidor}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell text-slate-600">${escapeHtml(veiculo)}</td>
                    <td class="table-cell hidden lg:table-cell">${tipoOrigem}</td>
                    <td class="table-cell text-right text-slate-600">${valorBase}</td>
                    <td class="table-cell text-right text-blue-600 font-medium">${valorLocadora}</td>
                    <td class="table-cell text-right text-green-600 font-medium">${valorInvestidor}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-28 text-right">
                        ${podeAcoes ? `
                            <button title="${i18n.actionMarkPaid}" class="btn-icon text-green-600 hover:text-green-800 btn-pagar" data-id="${item.id}">
                                <i class="fas fa-check"></i>
                            </button>
                            <button title="${i18n.actionCancel}" class="btn-icon text-red-600 hover:text-red-800 btn-cancelar" data-id="${item.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : '-'}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners
        tbody.querySelectorAll('.btn-pagar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                confirmarPagamento(id);
            });
        });

        tbody.querySelectorAll('.btn-cancelar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                confirmarCancelamento(id);
            });
        });
    }

    function renderStatusBadge(status) {
        switch(status) {
            case 'pendente':
                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-clock mr-1"></i>' + i18n.statusPending + '</span>';
            case 'pago':
                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>' + i18n.statusPaid + '</span>';
            case 'cancelado':
                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-ban mr-1"></i>' + i18n.statusCancelled + '</span>';
            default:
                return status;
        }
    }

    function formatarTipoOrigem(tipo) {
        switch(tipo) {
            case 'locacao': return '<span class="text-blue-600"><i class="fas fa-car mr-1"></i>' + i18n.typeRental + '</span>';
            case 'contrato': return '<span class="text-purple-600"><i class="fas fa-file-contract mr-1"></i>' + i18n.typeContract + '</span>';
            case 'mensal': return '<span class="text-teal-600"><i class="fas fa-calendar mr-1"></i>' + i18n.typeMonthly + '</span>';
            default: return tipo;
        }
    }

    // ===== ACOES =====

    async function confirmarPagamento(id) {
        pendingAction = { type: 'payment', id: id };
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: i18n.actionMarkPaid,
            message: i18n.confirmPayment,
            confirmText: <?= json_encode(t('common.buttons.confirm'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
        }, '*');
    }

    async function executarPagamento(id) {
        try {
            const result = await API.post(`/comissoes-investidores/${id}/pagar`);

            if (result.success) {
                toast.success(i18n.paidSuccess);
                carregarDados();
                carregarTotais();
            } else {
                toast.error(result.message);
            }
        } catch (error) {
            toast.error(error.message);
        }
    }

    async function confirmarCancelamento(id) {
        pendingAction = { type: 'cancel', id: id };
        window.parent.postMessage({
            action: 'openInputModal',
            title: i18n.actionCancel,
            message: i18n.cancelReason,
            confirmText: <?= json_encode(t('common.buttons.confirm'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
        }, '*');
    }

    async function executarCancelamento(id, motivo) {
        try {
            const result = await API.post(`/comissoes-investidores/${id}/cancelar`, { motivo });

            if (result.success) {
                toast.success(i18n.cancelledSuccess);
                carregarDados();
                carregarTotais();
            } else {
                toast.error(result.message);
            }
        } catch (error) {
            toast.error(error.message);
        }
    }

    // Escutar mensagens do parent
    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        if (event.data.action === 'genericConfirmed' && pendingAction) {
            if (pendingAction.type === 'payment') {
                executarPagamento(pendingAction.id);
            }
            pendingAction = null;
        } else if (event.data.action === 'genericModalClosed' && pendingAction) {
            pendingAction = null;
        } else if (event.data.action === 'inputModalConfirmed' && pendingAction) {
            if (pendingAction.type === 'cancel') {
                executarCancelamento(pendingAction.id, event.data.value || '');
            }
            pendingAction = null;
        } else if (event.data.action === 'inputModalClosed' && pendingAction) {
            pendingAction = null;
        }
    });

    // ===== PAGINACAO =====

    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement || !pagination) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
    }

    function atualizarPaginacao(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container || !pagination) return;

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

        container.innerHTML = buttons;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarDados();
    };

    // ===== FILTROS =====

    document.getElementById('btnFiltrar')?.addEventListener('click', function() {
        filtros = {
            id_fornecedor: document.getElementById('filtroInvestidor').value,
            status: document.getElementById('filtroStatus').value,
            tipo_origem: document.getElementById('filtroTipo').value,
            data_inicio: document.getElementById('filtroDataInicio').value,
            data_fim: document.getElementById('filtroDataFim').value
        };

        // Remover vazios
        Object.keys(filtros).forEach(key => {
            if (!filtros[key]) delete filtros[key];
        });

        currentPage = 1;
        carregarDados();
        carregarTotais();
    });

    document.getElementById('btnLimparFiltros')?.addEventListener('click', function() {
        document.getElementById('filtroInvestidor').value = '';
        document.getElementById('filtroStatus').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroDataInicio').value = '';
        document.getElementById('filtroDataFim').value = '';

        // Limpar Chosen se existir
        if (typeof jQuery !== 'undefined' && jQuery.fn.chosen) {
            jQuery('#filtroInvestidor').trigger('chosen:updated');
        }

        filtros = {};
        currentPage = 1;
        carregarDados();
        carregarTotais();
    });

    document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarDados();
    });

    // ===== HELPERS =====

    function formatarMoeda(valor) {
        if (!valor && valor !== 0) return 'R$ 0,00';
        const num = parseFloat(valor);
        return 'R$ ' + num.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    }

    function formatarData(data) {
        if (!data) return '-';
        const d = new Date(data + 'T00:00:00');
        return d.toLocaleDateString('pt-BR');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== INICIALIZACAO =====

    carregarDados();
    carregarTotais();
})();
</script>
@endsection
