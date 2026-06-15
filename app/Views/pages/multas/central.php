@extends('layouts.iframe')

@section('title', t('modules.multas.central.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.multas.central.title') ?></h2>
        <div class="flex items-center gap-2">
            <div class="relative">
                <input type="text" id="filtroSearch" class="form-input-focus text-sm sm:w-64 pr-8" placeholder="<?= t('modules.multas.central.search_placeholder') ?>">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.multas.central.add_fine') ?>
            </button>
            <button type="button" onclick="window.parent.openOrSwitchToTab('/pages/multas-online/indicacoes','<?= t('modules.multas.indicacoes.title') ?>','fas fa-user-shield','indicacoes-condutor')" class="bg-orange-600 hover:bg-orange-700 text-white py-2 px-4 rounded-md text-sm font-medium flex items-center whitespace-nowrap">
                <i class="fas fa-user-shield mr-2"></i><?= t('modules.multas.central.nominations.title') ?>
            </button>
            <button id="btnConsultarOnline" class="bg-emerald-600 hover:bg-emerald-700 text-white py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-satellite-dish mr-2"></i> <?= t('modules.multas.central.check_online') ?>
            </button>
            <button id="btnConsultarLote" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-layer-group mr-2"></i> <?= t('modules.multas.central.check_batch') ?>
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-red-500">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.overdue') ?></div>
            <div class="text-xl font-bold text-red-600 mt-1" id="kpiVencidas">
                <i class="fas fa-spinner fa-spin text-xs"></i>
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-amber-500">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.expiring_30d') ?></div>
            <div class="text-xl font-bold text-amber-600 mt-1" id="kpiVencendo">-</div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-green-500">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.on_time') ?></div>
            <div class="text-xl font-bold text-green-600 mt-1" id="kpiEmDia">-</div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-blue-500">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.pending') ?></div>
            <div class="text-xl font-bold text-blue-600 mt-1" id="kpiPendentes">-</div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-slate-400">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.paid') ?></div>
            <div class="text-xl font-bold text-slate-600 mt-1" id="kpiPagas">-</div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 border-l-4 border-indigo-500">
            <div class="text-xs text-slate-500 font-medium"><?= t('modules.multas.central.kpi.pending_value') ?></div>
            <div class="text-lg font-bold text-indigo-600 mt-1" id="kpiValorPendente">-</div>
        </div>
    </div>

    <!-- Cards secundarios: Saldo + Origem + Indicacoes -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <!-- Saldo Consultas -->
        <div class="bg-white shadow rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-slate-700">
                    <i class="fas fa-wallet mr-1 text-blue-500"></i> <?= t('modules.multas.central.balance.title') ?>
                </h3>
                <a href="javascript:void(0)" onclick="window.parent.openOrSwitchToTab('/pages/multas-online/saldo','<?= t('modules.multas.saldo.title') ?>','fas fa-wallet','saldo-consultas')" class="text-xs text-blue-600 hover:underline"><?= t('modules.multas.central.balance.manage') ?></a>
            </div>
            <div class="text-2xl font-bold text-blue-600" id="saldoAtual">-</div>
            <div class="flex gap-4 mt-2 text-xs text-slate-500">
                <span><?= t('modules.multas.central.balance.query') ?>: <strong id="precoConsulta">-</strong></span>
                <span><?= t('modules.multas.central.balance.event') ?>: <strong id="precoEvento">-</strong></span>
            </div>
        </div>

        <!-- Origem das multas -->
        <div class="bg-white shadow rounded-lg p-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-2">
                <i class="fas fa-chart-pie mr-1 text-amber-500"></i> <?= t('modules.multas.central.origin.title') ?>
            </h3>
            <div class="space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><i class="fas fa-pencil-alt mr-1 text-slate-400"></i> <?= t('modules.multas.central.origin.manual') ?></span>
                    <span class="font-medium" id="origemManual">-</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><i class="fas fa-search mr-1 text-blue-400"></i> <?= t('modules.multas.central.origin.online_query') ?></span>
                    <span class="font-medium" id="origemConsulta">-</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><i class="fas fa-bell mr-1 text-amber-400"></i> <?= t('modules.multas.central.origin.auto_event') ?></span>
                    <span class="font-medium" id="origemEvento">-</span>
                </div>
            </div>
        </div>

        <!-- Indicacoes -->
        <div class="bg-white shadow rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-slate-700">
                    <i class="fas fa-user-shield mr-1 text-orange-500"></i> <?= t('modules.multas.central.nominations.title') ?>
                </h3>
                <a href="javascript:void(0)" onclick="window.parent.openOrSwitchToTab('/pages/multas-online/indicacoes','<?= t('modules.multas.indicacoes.title') ?>','fas fa-user-shield','indicacoes-condutor')" class="text-xs text-blue-600 hover:underline"><?= t('modules.multas.central.nominations.view_all') ?></a>
            </div>
            <div class="space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><?= t('modules.multas.central.nominations.pending_nomination') ?></span>
                    <span class="font-medium text-amber-600" id="statusPendenteIndicacao">-</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><?= t('modules.multas.central.nominations.new_unprocessed') ?></span>
                    <span class="font-medium text-blue-600" id="statusNovo">-</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><?= t('modules.multas.central.nominations.sent') ?></span>
                    <span class="font-medium" id="indicacoesEnviadas">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Config Automacoes inline -->
    <div class="bg-white shadow rounded-lg p-4 mb-5">
        <div class="flex flex-wrap items-center gap-4">
            <h3 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-cog mr-1 text-slate-500"></i> <?= t('modules.multas.central.automation.title') ?>
            </h3>
            <label class="flex items-center text-sm text-slate-600 gap-1.5 cursor-pointer">
                <input type="checkbox" id="toggleAutoConsulta" class="rounded">
                <?= t('modules.multas.central.automation.auto_query') ?>
                <?= aviso(t('modules.multas.central.automation.auto_query_help')) ?>
            </label>
            <div id="intervaloWrapper" class="hidden flex items-center gap-1.5">
                <label class="text-xs text-slate-500"><?= t('modules.multas.central.automation.every') ?></label>
                <select id="intervaloConsulta" class="form-input-focus text-xs w-24 py-1">
                    <option value="1"><?= t('modules.multas.central.automation.interval_1d') ?></option>
                    <option value="3"><?= t('modules.multas.central.automation.interval_3d') ?></option>
                    <option value="7" selected><?= t('modules.multas.central.automation.interval_7d') ?></option>
                    <option value="14"><?= t('modules.multas.central.automation.interval_14d') ?></option>
                    <option value="30"><?= t('modules.multas.central.automation.interval_30d') ?></option>
                </select>
            </div>
            <label class="flex items-center text-sm text-slate-600 gap-1.5 cursor-pointer">
                <input type="checkbox" id="toggleAutoEventos" class="rounded">
                <?= t('modules.multas.central.automation.auto_events') ?>
                <?= aviso(t('modules.multas.central.automation.auto_events_help')) ?>
            </label>
            <span class="text-xs text-slate-400" id="ultimaConsultaInfo"></span>
        </div>
        <div id="consultaOnlineCnpjAviso" class="hidden mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2"></div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <select id="filtroTipo" class="form-input-focus text-sm w-36">
            <option value=""><?= t('modules.multas.central.filters.type_all') ?></option>
            <option value="C"><?= t('modules.multas.central.filters.type_contract') ?></option>
            <option value="L"><?= t('modules.multas.central.filters.type_rental') ?></option>
        </select>
        <select id="filtroPago" class="form-input-focus text-sm w-36">
            <option value=""><?= t('modules.multas.central.filters.payment_all') ?></option>
            <option value="N"><?= t('modules.multas.central.filters.payment_pending') ?></option>
            <option value="S"><?= t('modules.multas.central.filters.payment_paid') ?></option>
        </select>
        <select id="filtroVencimento" class="form-input-focus text-sm w-36">
            <option value=""><?= t('modules.multas.central.filters.due_all') ?></option>
            <option value="vencidas"><?= t('modules.multas.central.filters.due_overdue') ?></option>
            <option value="vencendo"><?= t('modules.multas.central.filters.due_expiring') ?></option>
            <option value="em_dia"><?= t('modules.multas.central.filters.due_on_time') ?></option>
        </select>
        <select id="filtroOrigem" class="form-input-focus text-sm w-40">
            <option value=""><?= t('modules.multas.central.filters.origin_all') ?></option>
            <option value="manual"><?= t('modules.multas.central.filters.origin_manual') ?></option>
            <option value="serpro_consulta"><?= t('modules.multas.central.filters.origin_online') ?></option>
            <option value="serpro_evento"><?= t('modules.multas.central.filters.origin_event') ?></option>
        </select>
        <select id="filtroStatus" class="form-input-focus text-sm w-44">
            <option value=""><?= t('modules.multas.central.filters.status_all') ?></option>
            <option value="novo"><?= t('modules.multas.central.filters.status_new') ?></option>
            <option value="pendente_indicacao"><?= t('modules.multas.central.filters.status_pending_nomination') ?></option>
            <option value="indicacao_enviada"><?= t('modules.multas.central.filters.status_nomination_sent') ?></option>
            <option value="indicado"><?= t('modules.multas.central.filters.status_nominated') ?></option>
            <option value="transferido"><?= t('modules.multas.central.filters.status_transferred') ?></option>
        </select>
    </div>

    <!-- Tabela de Multas -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.multas.central.table.plate') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.multas.central.table.client') ?></th>
                    <th class="table-header"><?= t('modules.multas.central.table.date') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.multas.central.table.infraction') ?></th>
                    <th class="table-header text-right"><?= t('modules.multas.central.table.value') ?></th>
                    <th class="table-header text-center"><?= t('modules.multas.central.table.due') ?></th>
                    <th class="table-header text-center"><?= t('modules.multas.central.table.payment') ?></th>
                    <th class="table-header text-center hidden lg:table-cell"><?= t('modules.multas.central.table.origin') ?></th>
                    <th class="table-header text-center hidden lg:table-cell"><?= t('modules.multas.central.table.status') ?></th>
                    <th class="table-header text-center w-36"><?= t('modules.multas.central.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label class="text-sm text-slate-600 mr-2"><?= t('modules.multas.central.pagination.rows') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600"><span id="registrosInfo"></span></div>
        <nav><ul class="inline-flex items-center -space-x-px" id="paginationContainer"></ul></nav>
    </div>

    <!-- Ranking de Veiculos (collapsible) -->
    <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
        <button id="btnToggleRanking" class="w-full flex justify-between items-center p-4 text-left hover:bg-slate-50 transition">
            <h3 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-trophy mr-1 text-amber-500"></i> <?= t('modules.multas.central.ranking.title') ?>
            </h3>
            <i class="fas fa-chevron-down text-slate-400 transition-transform" id="rankingIcon"></i>
        </button>
        <div id="rankingContent" class="hidden">
            <table class="w-full divide-y divide-slate-200">
                <thead class="table-header-custom">
                    <tr>
                        <th class="table-header"><?= t('modules.multas.central.ranking.position') ?></th>
                        <th class="table-header"><?= t('modules.multas.central.ranking.plate') ?></th>
                        <th class="table-header hidden sm:table-cell"><?= t('modules.multas.central.ranking.model') ?></th>
                        <th class="table-header text-center"><?= t('modules.multas.central.ranking.total') ?></th>
                        <th class="table-header text-center"><?= t('modules.multas.central.ranking.pending') ?></th>
                        <th class="table-header text-right"><?= t('modules.multas.central.ranking.pending_value') ?></th>
                    </tr>
                </thead>
                <tbody id="rankingBody" class="bg-white divide-y divide-slate-200"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: '<?= t('common.labels.loading') ?>',
        noFines: '<?= t('modules.multas.messages.no_records') ?>',
        paginationShowing: '<?= t('modules.multas.central.pagination.showing') ?>',
        badgeQuery: '<?= t('modules.multas.central.badges.origin_query') ?>',
        badgeEvent: '<?= t('modules.multas.central.badges.origin_event') ?>',
        badgeManual: '<?= t('modules.multas.central.badges.origin_manual') ?>',
        badgePaid: '<?= t('modules.multas.central.badges.paid') ?>',
        badgePending: '<?= t('modules.multas.central.badges.pending') ?>',
        markPaidTitle: '<?= t('modules.multas.central.confirm.mark_paid_title') ?>',
        markPaidMessage: '<?= t('modules.multas.central.confirm.mark_paid_message') ?>',
        revertTitle: '<?= t('modules.multas.central.confirm.revert_title') ?>',
        revertMessage: '<?= t('modules.multas.central.confirm.revert_message') ?>',
        cannotDeletePaid: '<?= t('modules.multas.central.confirm.cannot_delete_paid') ?>',
        activateAutoQueryTitle: '<?= t('modules.multas.central.confirm.activate_auto_query_title') ?>',
        activateAutoQueryMessage: '<?= t('modules.multas.central.confirm.activate_auto_query_message') ?>',
        activateAutoEventsTitle: '<?= t('modules.multas.central.confirm.activate_auto_events_title') ?>',
        activateAutoEventsMessage: '<?= t('modules.multas.central.confirm.activate_auto_events_message') ?>',
        confirmActivate: '<?= t('modules.multas.central.confirm.confirm_activate') ?>',
        fineDeleted: '<?= t('modules.multas.central.toast.fine_deleted') ?>',
        fineMarkedPaid: '<?= t('modules.multas.central.toast.fine_marked_paid') ?>',
        paymentReverted: '<?= t('modules.multas.central.toast.payment_reverted') ?>',
        configError: '<?= t('modules.multas.central.toast.config_error') ?>',
        lastQuery: '<?= t('modules.multas.central.automation.last_query') ?>',
        rankingNoData: '<?= t('modules.multas.central.ranking.no_data') ?>',
        thisRecord: '<?= t('modules.multas.messages.this_record') ?>',
        recordType: '<?= t('modules.multas.record_type') ?>',
        actionEdit: '<?= t('modules.multas.central.actions.edit') ?>',
        actionNominate: '<?= t('modules.multas.central.actions.nominate') ?>',
        actionMarkPaid: '<?= t('modules.multas.central.actions.mark_paid') ?>',
        actionMarkUnpaid: '<?= t('modules.multas.central.actions.mark_unpaid') ?>',
        actionDelete: '<?= t('modules.multas.central.actions.delete') ?>',
        actionPrint: '<?= addslashes(t('modules.multas.central.actions.print')) ?>',
        printTitle: '<?= addslashes(t('modules.multas.print.title')) ?>',
    };

    let currentPage = 1;
    let perPage = 15;
    let filtroSearch = '';
    let filtroTipo = '';
    let filtroPago = '';
    let filtroOrigem = '';
    let filtroStatus = '';
    let filtroVencimento = '';
    let searchTimeout = null;
    let dashboardData = null;

    const tbody = document.getElementById('tableBody');

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // =================================================================
    // DASHBOARD
    // =================================================================

    async function carregarDashboard() {
        try {
            const result = await API.get('/api/central-multas/dashboard');
            if (!result.success) return;

            dashboardData = result.data;
            const kpis = result.data.kpis;

            // KPIs
            document.getElementById('kpiVencidas').textContent = kpis.vencidas;
            document.getElementById('kpiVencendo').textContent = kpis.vencendo;
            document.getElementById('kpiEmDia').textContent = kpis.em_dia;
            document.getElementById('kpiPendentes').textContent = kpis.pendentes;
            document.getElementById('kpiPagas').textContent = kpis.pagas;
            document.getElementById('kpiValorPendente').textContent = Currency.format(kpis.valor_pendente);

            // Saldo
            const saldo = result.data.saldo;
            document.getElementById('saldoAtual').textContent = Currency.format(Number(saldo || 0));
            document.getElementById('precoConsulta').textContent = Currency.format(result.data.precos?.consulta || 0);
            document.getElementById('precoEvento').textContent = Currency.format(result.data.precos?.evento || 0);

            // Origem
            document.getElementById('origemManual').textContent = kpis.origem_manual;
            document.getElementById('origemConsulta').textContent = kpis.origem_serpro_consulta;
            document.getElementById('origemEvento').textContent = kpis.origem_serpro_evento;

            // Indicacoes
            document.getElementById('statusPendenteIndicacao').textContent = kpis.status_pendente_indicacao;
            document.getElementById('statusNovo').textContent = kpis.status_novo;

            const indic = result.data.indicacoes;
            document.getElementById('indicacoesEnviadas').textContent = (indic?.enviadas || 0);

            // Config
            const config = result.data.config;
            document.getElementById('toggleAutoConsulta').checked = config?.auto_consulta_ativo === 1;
            document.getElementById('toggleAutoEventos').checked = config?.auto_eventos_ativo === 1;
            if (config?.auto_consulta_ativo === 1) {
                document.getElementById('intervaloWrapper').classList.remove('hidden');
            } else {
                document.getElementById('intervaloWrapper').classList.add('hidden');
            }
            if (config?.intervalo_dias_consulta) {
                document.getElementById('intervaloConsulta').value = config.intervalo_dias_consulta;
            }
            if (config?.ultima_consulta) {
                document.getElementById('ultimaConsultaInfo').textContent = i18n.lastQuery.replace(':date', formatarData(config.ultima_consulta));
            }
            const cnpjAviso = document.getElementById('consultaOnlineCnpjAviso');
            if (config?.cnpj_aviso) {
                cnpjAviso.textContent = config.cnpj_aviso;
                cnpjAviso.classList.remove('hidden');
            } else {
                cnpjAviso.textContent = '';
                cnpjAviso.classList.add('hidden');
            }

        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
        }
    }

    // =================================================================
    // TABELA DE MULTAS
    // =================================================================

    async function carregarMultas(page = 1) {
        try {
            tbody.innerHTML = '<tr><td colspan="10" class="table-cell text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.loading + '</td></tr>';

            const params = { page, perPage };
            if (filtroSearch) params.search = filtroSearch;
            if (filtroTipo) params.tipo = filtroTipo;
            if (filtroPago) params.pago = filtroPago;
            if (filtroOrigem) params.origem = filtroOrigem;
            if (filtroStatus) params.status_processamento = filtroStatus;
            if (filtroVencimento) params.vencimento = filtroVencimento;

            const result = await API.get('/api/central-multas/multas', params);
            if (result.success) {
                renderMultas(result.data);
                atualizarPaginacao(result.pagination);
                const p = result.pagination;
                const start = p.total === 0 ? 0 : ((p.page - 1) * p.perPage) + 1;
                const end = Math.min(p.page * p.perPage, p.total);
                document.getElementById('registrosInfo').textContent = i18n.paginationShowing.replace(':start', start).replace(':end', end).replace(':total', p.total);
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="10" class="table-cell text-center text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>' + escapeHtml(e.message) + '</td></tr>';
        }
    }

    function getOrigemBadge(origem) {
        if (origem === 'serpro_consulta') return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">' + i18n.badgeQuery + '</span>';
        if (origem === 'serpro_evento') return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">' + i18n.badgeEvent + '</span>';
        return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">' + i18n.badgeManual + '</span>';
    }

    function getStatusBadge(status) {
        const map = {
            'novo': 'bg-blue-100 text-blue-700',
            'pendente_indicacao': 'bg-amber-100 text-amber-700',
            'indicacao_enviada': 'bg-indigo-100 text-indigo-700',
            'indicado': 'bg-green-100 text-green-700',
            'transferido': 'bg-emerald-100 text-emerald-700',
        };
        const cls = map[status] || 'bg-slate-100 text-slate-600';
        const label = status ? status.replace(/_/g, ' ') : '-';
        return `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${cls}">${escapeHtml(label)}</span>`;
    }

    function getPagoBadge(pago) {
        if (pago === 'S') return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-0.5"></i>' + i18n.badgePaid + '</span>';
        return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><i class="fas fa-clock mr-0.5"></i>' + i18n.badgePending + '</span>';
    }

    function getVencimentoBadge(dataVenc, pago) {
        if (!dataVenc || pago === 'S') return formatarDataCurta(dataVenc);
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);
        const venc = new Date(dataVenc + 'T00:00:00');
        const diff = Math.ceil((venc - hoje) / (1000 * 60 * 60 * 24));

        if (diff < 0) return `<span class="text-red-600 font-medium">${formatarDataCurta(dataVenc)}</span>`;
        if (diff <= 30) return `<span class="text-amber-600 font-medium">${formatarDataCurta(dataVenc)}</span>`;
        return `<span class="text-green-600">${formatarDataCurta(dataVenc)}</span>`;
    }

    function renderMultas(dados) {
        if (!dados || dados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="table-cell text-center text-slate-500"><i class="fas fa-inbox mr-2"></i>' + i18n.noFines + '</td></tr>';
            return;
        }

        let rows = '';
        dados.forEach(m => {
            const placa = m.veiculo_placa || '-';
            const veiculoInfo = ((m.veiculo_marca || '') + ' ' + (m.veiculo_modelo || '')).trim();
            const cliente = m.cliente_nome || '';
            const clienteCpf = m.cliente_cpf_cnpj || '';
            const dataHora = formatarDataCurta(m.data_hora);
            const infracao = m.codigo_infracao || m.n_infracao || m.tipo || '-';
            const valor = Currency.format(m.valor || 0);
            const desconto40 = m.valor_desconto_40 ? '<div class="text-xs text-green-600">40%: ' + Currency.format(m.valor_desconto_40) + '</div>' : '';
            const podeIndicar = !!(m.codigo_orgao && m.numero_ait && m.codigo_infracao && /^\d+$/.test(String(m.codigo_orgao)) && /^\d+$/.test(String(m.codigo_infracao)));
            const indicacaoLabel = `${placa} - ${m.numero_ait || m.n_infracao || '-'} ${valor}`;

            rows += `
            <tr class="border-b border-slate-200 hover:bg-slate-50">
                <td class="table-cell">
                    <span class="font-mono text-sm bg-slate-100 px-2 py-0.5 rounded">${escapeHtml(placa)}</span>
                    ${veiculoInfo ? `<div class="text-xs text-slate-500 mt-0.5">${escapeHtml(veiculoInfo)}</div>` : ''}
                </td>
                <td class="table-cell hidden sm:table-cell">
                    <div class="text-sm text-slate-600 max-w-[150px] truncate" title="${escapeHtml(cliente)}">${escapeHtml(cliente)}</div>
                    ${clienteCpf ? `<div class="text-xs text-slate-400">${escapeHtml(clienteCpf)}</div>` : ''}
                </td>
                <td class="table-cell text-sm">${dataHora}</td>
                <td class="table-cell hidden md:table-cell text-xs text-slate-600">
                    ${escapeHtml(infracao)}
                    ${m.orgao_autuador ? '<div class="text-xs text-slate-400 truncate max-w-[120px]" title="' + escapeHtml(m.orgao_autuador) + '">' + escapeHtml(m.orgao_autuador) + '</div>' : ''}
                </td>
                <td class="table-cell text-right">
                    <span class="font-medium">${valor}</span>
                    ${desconto40}
                </td>
                <td class="table-cell text-center text-sm">${getVencimentoBadge(m.data_vencimento, m.pago)}</td>
                <td class="table-cell text-center">${getPagoBadge(m.pago)}</td>
                <td class="table-cell text-center hidden lg:table-cell">${getOrigemBadge(m.origem)}</td>
                <td class="table-cell text-center hidden lg:table-cell">${getStatusBadge(m.status_processamento)}</td>
                <td class="table-cell text-center w-36">
                    <div class="flex items-center justify-center gap-1">
                        <button title="${i18n.actionPrint}" class="btn-icon text-blue-600 hover:text-blue-800 btn-imprimir" data-id="${m.id}"><i class="fas fa-print"></i></button>
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${m.id}"><i class="fas fa-edit"></i></button>
                        ${podeIndicar ? `<button title="${i18n.actionNominate}" class="btn-icon text-orange-600 hover:text-orange-800 btn-indicar" data-id="${m.id}" data-label="${escapeHtml(indicacaoLabel)}"><i class="fas fa-user-shield"></i></button>` : ''}
                        ${m.pago === 'N' ? `<button title="${i18n.actionMarkPaid}" class="btn-icon text-green-600 hover:text-green-800 btn-pagar" data-id="${m.id}"><i class="fas fa-check-circle"></i></button>` : `<button title="${i18n.actionMarkUnpaid}" class="btn-icon text-slate-400 hover:text-slate-600 btn-nao-pago" data-id="${m.id}"><i class="fas fa-undo"></i></button>`}
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${m.id}" data-name="${escapeHtml(placa)}" data-pago="${m.pago}"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = rows;

        // Bind acoes
        tbody.querySelectorAll('.btn-imprimir').forEach(btn => {
            btn.addEventListener('click', function() {
                window.parent.postMessage({
                    action: 'openOffcanvasIframe',
                    url: '/pages/multas/offcanvas-impressao?id=' + this.dataset.id,
                    title: i18n.printTitle,
                    width: '420px'
                }, '*');
            });
        });

        tbody.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                navegarPara('/pages/multas/adicionar?id=' + this.dataset.id);
            });
        });

        tbody.querySelectorAll('.btn-indicar').forEach(btn => {
            btn.addEventListener('click', function() {
                window.parent.postMessage({
                    action: 'openIndicacaoModal',
                    tipo: 'real_infrator',
                    id_multa: this.dataset.id,
                    multa_label: this.dataset.label || ''
                }, '*');
            });
        });

        tbody.querySelectorAll('.btn-pagar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.markPaidTitle,
                    message: i18n.markPaidMessage
                }, '*');
                window._pendingPagarId = id;
            });
        });

        tbody.querySelectorAll('.btn-nao-pago').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.revertTitle,
                    message: i18n.revertMessage
                }, '*');
                window._pendingNaoPagoId = id;
            });
        });

        tbody.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.dataset.pago === 'S') {
                    window.parent.postMessage({ action: 'openAlert', message: i18n.cannotDeletePaid }, '*');
                    return;
                }
                window.parent.postMessage({
                    action: 'openDeleteModal',
                    recordId: this.dataset.id,
                    recordName: this.dataset.name || i18n.thisRecord,
                    recordType: i18n.recordType,
                    confirmType: 'text'
                }, '*');
            });
        });
    }

    // =================================================================
    // RANKING
    // =================================================================

    async function carregarRanking() {
        try {
            const result = await API.get('/api/central-multas/ranking-veiculos', { limite: 10 });
            if (!result.success) return;

            const rankingBody = document.getElementById('rankingBody');
            if (!result.data || result.data.length === 0) {
                rankingBody.innerHTML = '<tr><td colspan="6" class="table-cell text-center text-slate-500">' + i18n.rankingNoData + '</td></tr>';
                return;
            }

            let rows = '';
            result.data.forEach((item, idx) => {
                rows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell text-center font-medium text-slate-500">${idx + 1}</td>
                    <td class="table-cell"><span class="font-mono text-sm bg-slate-100 px-2 py-0.5 rounded">${escapeHtml(item.placa || '')}</span></td>
                    <td class="table-cell hidden sm:table-cell text-sm text-slate-600">${escapeHtml(item.marca || '')} ${escapeHtml(item.modelo || '')}</td>
                    <td class="table-cell text-center font-medium">${item.total_multas}</td>
                    <td class="table-cell text-center ${item.pendentes > 0 ? 'text-red-600 font-medium' : 'text-slate-500'}">${item.pendentes}</td>
                    <td class="table-cell text-right font-medium">${Currency.format(item.valor_pendente || 0)}</td>
                </tr>`;
            });
            rankingBody.innerHTML = rows;
        } catch (e) {
            console.error('Erro ranking:', e);
        }
    }

    // =================================================================
    // CONSULTA ONLINE (modal no parent via postMessage)
    // =================================================================

    function abrirModalConsulta() {
        window.parent.postMessage({ action: 'openConsultaMultasModal' }, '*');
    }

    // =================================================================
    // CONSULTA LOTE (modal no parent via postMessage)
    // =================================================================

    function abrirModalLote() {
        window.parent.postMessage({
            action: 'openConsultaLoteModal',
            saldo: Currency.format(Number(dashboardData?.saldo || 0)),
            precoConsulta: Currency.format(dashboardData?.precos?.consulta || 0)
        }, '*');
    }

    // =================================================================
    // PAGINACAO
    // =================================================================

    function atualizarPaginacao(pagination) {
        const c = document.getElementById('paginationContainer');
        if (!c || !pagination) return;
        const { page, totalPages, hasPrev, hasNext } = pagination;
        let html = `<li><button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}" ${!hasPrev ? 'disabled' : ''} onclick="irParaPagina(${page - 1})"><i class="fas fa-chevron-left"></i></button></li>`;
        const max = 5;
        let s = Math.max(1, page - Math.floor(max / 2));
        let e = Math.min(totalPages || 1, s + max - 1);
        if (e - s < max - 1) s = Math.max(1, e - max + 1);
        for (let i = s; i <= e; i++) {
            html += `<li><button class="pagination-button numbered ${i === page ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button></li>`;
        }
        html += `<li><button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}" ${!hasNext ? 'disabled' : ''} onclick="irParaPagina(${page + 1})"><i class="fas fa-chevron-right"></i></button></li>`;
        c.innerHTML = html;
    }

    window.irParaPagina = function(p) {
        currentPage = p;
        carregarMultas(currentPage);
    };

    // =================================================================
    // HELPERS
    // =================================================================

    function formatarData(dt) {
        if (!dt) return '';
        const d = new Date(dt);
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function formatarDataCurta(dt) {
        if (!dt) return '-';
        const d = new Date(dt + (dt.includes('T') ? '' : 'T00:00:00'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('pt-BR');
    }

    function escapeHtml(t) {
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    // =================================================================
    // EVENT LISTENERS
    // =================================================================

    // Botao Adicionar Multa
    document.getElementById('btnNovo')?.addEventListener('click', function() {
        navegarPara('/pages/multas/adicionar');
    });

    // Modais (abrem no parent via postMessage)
    document.getElementById('btnConsultarOnline')?.addEventListener('click', abrirModalConsulta);
    document.getElementById('btnConsultarLote')?.addEventListener('click', abrirModalLote);

    // Ranking toggle
    document.getElementById('btnToggleRanking')?.addEventListener('click', function() {
        const content = document.getElementById('rankingContent');
        const icon = document.getElementById('rankingIcon');
        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    });

    // Filtros
    document.getElementById('filtroSearch')?.addEventListener('input', function() {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filtroSearch = this.value.trim();
            currentPage = 1;
            carregarMultas(1);
        }, 400);
    });

    document.getElementById('filtroTipo')?.addEventListener('change', function() {
        filtroTipo = this.value;
        currentPage = 1;
        carregarMultas(1);
    });

    document.getElementById('filtroPago')?.addEventListener('change', function() {
        filtroPago = this.value;
        currentPage = 1;
        carregarMultas(1);
    });

    document.getElementById('filtroVencimento')?.addEventListener('change', function() {
        filtroVencimento = this.value;
        currentPage = 1;
        carregarMultas(1);
    });

    document.getElementById('filtroOrigem')?.addEventListener('change', function() {
        filtroOrigem = this.value;
        currentPage = 1;
        carregarMultas(1);
    });

    document.getElementById('filtroStatus')?.addEventListener('change', function() {
        filtroStatus = this.value;
        currentPage = 1;
        carregarMultas(1);
    });

    document.getElementById('rowsPerPage')?.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        carregarMultas(1);
    });

    // =================================================================
    // AUTOMACOES TOGGLES
    // =================================================================

    async function toggleAutomacao(campo, valor, extras = {}) {
        try {
            const data = { campo, valor, ...extras };
            const result = await API.post('/multas-online/configuracao/toggle', data);

            if (result.success) {
                window.parent.postMessage({ action: 'showToast', type: 'success', message: result.message }, '*');
                carregarDashboard();
            } else {
                // Reverter checkbox
                if (campo === 'auto_consulta_ativo') {
                    document.getElementById('toggleAutoConsulta').checked = !valor;
                    if (!valor) document.getElementById('intervaloWrapper').classList.add('hidden');
                } else {
                    document.getElementById('toggleAutoEventos').checked = !valor;
                }
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.configError }, '*');
            }
        } catch (e) {
            // Reverter checkbox em caso de erro
            if (campo === 'auto_consulta_ativo') {
                document.getElementById('toggleAutoConsulta').checked = !valor;
                if (!valor) document.getElementById('intervaloWrapper').classList.add('hidden');
            } else {
                document.getElementById('toggleAutoEventos').checked = !valor;
            }
            window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
        }
    }

    document.getElementById('toggleAutoConsulta')?.addEventListener('change', function() {
        const ativar = this.checked ? 1 : 0;

        if (ativar) {
            // Reverter ate confirmar
            this.checked = false;
            window._pendingAutoConsulta = true;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.activateAutoQueryTitle,
                message: i18n.activateAutoQueryMessage,
                confirmText: i18n.confirmActivate
            }, '*');
        } else {
            document.getElementById('intervaloWrapper').classList.add('hidden');
            toggleAutomacao('auto_consulta_ativo', 0);
        }
    });

    document.getElementById('toggleAutoEventos')?.addEventListener('change', function() {
        const ativar = this.checked ? 1 : 0;

        if (ativar) {
            // Reverter ate confirmar
            this.checked = false;
            window._pendingAutoEventos = true;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.activateAutoEventsTitle,
                message: i18n.activateAutoEventsMessage,
                confirmText: i18n.confirmActivate
            }, '*');
        } else {
            toggleAutomacao('auto_eventos_ativo', 0);
        }
    });

    document.getElementById('intervaloConsulta')?.addEventListener('change', function() {
        if (document.getElementById('toggleAutoConsulta').checked) {
            toggleAutomacao('auto_consulta_ativo', 1, { intervalo_dias_consulta: parseInt(this.value) });
        }
    });

    // Resultados dos modais no parent + confirm modal
    window.addEventListener('message', function(event) {
        // Resultado da consulta individual
        if (event.data?.action === 'consultaMultasResult' && event.data.success) {
            carregarDashboard();
            carregarMultas(1);
        }
        // Resultado da consulta em lote
        if (event.data?.action === 'consultaLoteResult' && event.data.success) {
            carregarDashboard();
            carregarMultas(1);
        }
        // Confirmar exclusao
        if (event.data?.action === 'confirmDelete') {
            (async function() {
                try {
                    const result = await API.post(`/multas/${event.data.recordId}/excluir`);
                    if (result.success) {
                        window.parent.postMessage({ action: 'showToast', type: 'success', message: i18n.fineDeleted }, '*');
                        carregarMultas(currentPage);
                        carregarDashboard();
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                    }
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
                }
            })();
        }
        // Confirmar marcar pago ou reverter pagamento
        if (event.data?.action === 'genericConfirmed') {
            if (window._pendingPagarId) {
                (async function() {
                    try {
                        const result = await API.post(`/multas/${window._pendingPagarId}/marcar-pago`);
                        if (result.success) {
                            window.parent.postMessage({ action: 'showToast', type: 'success', message: i18n.fineMarkedPaid }, '*');
                            carregarMultas(currentPage);
                            carregarDashboard();
                        } else {
                            window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                        }
                    } catch (e) {
                        window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
                    }
                    window._pendingPagarId = null;
                })();
            }
            if (window._pendingNaoPagoId) {
                (async function() {
                    try {
                        const result = await API.post(`/multas/${window._pendingNaoPagoId}/marcar-nao-pago`);
                        if (result.success) {
                            window.parent.postMessage({ action: 'showToast', type: 'success', message: i18n.paymentReverted }, '*');
                            carregarMultas(currentPage);
                            carregarDashboard();
                        } else {
                            window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                        }
                    } catch (e) {
                        window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
                    }
                    window._pendingNaoPagoId = null;
                })();
            }
            if (window._pendingAutoConsulta) {
                document.getElementById('toggleAutoConsulta').checked = true;
                document.getElementById('intervaloWrapper').classList.remove('hidden');
                const intervalo = parseInt(document.getElementById('intervaloConsulta').value);
                toggleAutomacao('auto_consulta_ativo', 1, { intervalo_dias_consulta: intervalo });
                window._pendingAutoConsulta = null;
            }
            if (window._pendingAutoEventos) {
                document.getElementById('toggleAutoEventos').checked = true;
                toggleAutomacao('auto_eventos_ativo', 1);
                window._pendingAutoEventos = null;
            }
        }
    });

    // =================================================================
    // INIT
    // =================================================================

    carregarDashboard();
    carregarMultas(1);
    carregarRanking();
})();
</script>
@endsection
