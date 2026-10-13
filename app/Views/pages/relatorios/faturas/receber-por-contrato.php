@extends('layouts.iframe')

@section('title', t('modules.relatorios.faturas.receber_por_contrato.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.faturas.receber_por_contrato.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.faturas.receber_por_contrato.description') ?></p>

    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataInicio" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterDataFim" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches'), ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterCliente" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.client') ?></label>
            <select id="filterCliente" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_clients'), ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_clients') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterContrato" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.faturas.receber_por_contrato.filter_contract') ?></label>
            <select id="filterContrato" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contratos/buscar-select" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.faturas.receber_por_contrato.all_contracts'), ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.faturas.receber_por_contrato.all_contracts') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[220px] max-w-[300px]">
            <label for="filterVeiculo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.vehicle') ?></label>
            <select id="filterVeiculo" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/veiculos/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_vehicles'), ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_vehicles') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[160px] max-w-[200px]">
            <label for="filterSituacao" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.faturas.receber_por_contrato.filter_status') ?></label>
            <select id="filterSituacao" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="vencidas"><?= t('modules.relatorios.faturas.receber_por_contrato.overdue') ?></option>
                <option value="a_vencer"><?= t('modules.relatorios.faturas.receber_por_contrato.upcoming') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('modules.relatorios.common.clear') ?>">
                <i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?>
            </button>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display:none;">
        <table class="w-full min-w-[1100px] divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.faturas.receber_por_contrato.col_contract') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.faturas.receber_por_contrato.col_client') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.faturas.receber_por_contrato.col_vehicle') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.receber_por_contrato.total_overdue') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.receber_por_contrato.total_upcoming') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.faturas.receber_por_contrato.total_open') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.faturas.receber_por_contrato.col_deadline') ?></th>
                    <th class="table-header text-center"><?= t('common.labels.actions') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/faturas/receber-por-contrato';
    const PDF_URL = '/relatorios/faturas/receber-por-contrato/pdf';
    const i18n = {
        title: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        total_open: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.total_open'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        total_overdue: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.total_overdue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        total_upcoming: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.total_upcoming'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        total_contracts: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.total_contracts'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        total_clients: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.total_clients'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        contract_finalized_open: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.contract_finalized_open'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        contract_active: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.contract_active'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        days_overdue: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.days_overdue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        next_due: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.next_due'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        open_installments: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.open_installments'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        view_installments: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.view_installments'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        view_contract: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.view_contract'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        overdue: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.overdue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        upcoming: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.upcoming'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        payment_link: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.payment_link'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        payment_link_error: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.payment_link_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_invoice: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_invoice'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_description: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_description'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_due_date: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_due_date'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_original_value: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_original_value'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_fees: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_fees'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_total_value: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_total_value'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_status: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_status'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        col_action: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.col_action'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        contract_label: <?= json_encode(t('modules.relatorios.faturas.receber_por_contrato.contract_label'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    const totalsConfig = [
        { key: 'total_aberto', label: i18n.total_open, icon: 'fa-wallet', format: 'currency' },
        { key: 'total_vencido', label: i18n.total_overdue, icon: 'fa-exclamation-triangle', format: 'currency', color: 'red' },
        { key: 'total_a_vencer', label: i18n.total_upcoming, icon: 'fa-clock', format: 'currency', color: 'green' },
        { key: 'total_contratos', label: i18n.total_contracts, icon: 'fa-file-contract', format: 'number' },
        { key: 'total_clientes', label: i18n.total_clients, icon: 'fa-users', format: 'number' },
    ];

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);

    function setDefaultPeriod() {
        const hoje = DateHelper.todayISO();
        document.getElementById('filterDataInicio').value = DateHelper.addDays(hoje, -365);
        document.getElementById('filterDataFim').value = DateHelper.addDays(hoje, 365);
    }

    function buildParams() {
        return new URLSearchParams({
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            cliente: document.getElementById('filterCliente').value,
            contrato: document.getElementById('filterContrato').value,
            veiculo: document.getElementById('filterVeiculo').value,
            situacao: document.getElementById('filterSituacao').value,
        });
    }

    async function loadReport() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, Object.fromEntries(buildParams().entries()));
            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }

            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            renderTable(result.data || []);
            ReportUtils.showContent();
            document.getElementById('reportTableContainer').style.display = 'block';
        } catch (error) {
            console.error('Erro ao carregar contas a receber por contrato:', error);
            ReportUtils.showError('<?= t('modules.relatorios.messages.connection_error') ?>');
        }
    }

    function renderTable(rows) {
        const tbody = document.getElementById('reportTableBody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="table-cell text-center text-slate-400 py-8">${escapeHtml('<?= t('modules.relatorios.common.no_data') ?>')}</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const finalizado = row.contrato_status === 'finalizado';
            const statusClass = finalizado ? 'bg-slate-100 text-slate-700' : 'bg-green-100 text-green-700';
            const statusLabel = finalizado ? i18n.contract_finalized_open : i18n.contract_active;
            const prazo = Number(row.maior_atraso || 0) > 0
                ? `<span class="text-red-600 font-medium">${Number(row.maior_atraso)} ${escapeHtml(i18n.days_overdue)}</span>`
                : (row.proximo_vencimento ? `${escapeHtml(i18n.next_due)} ${DateHelper.format(row.proximo_vencimento)}` : '-');
            const detailId = `contract-details-${Number(row.id_contrato)}`;

            return `<tr class="hover:bg-slate-50">
                <td class="table-cell">
                    <div class="font-semibold text-slate-800">${escapeHtml(row.contrato)}</div>
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusClass}">${escapeHtml(statusLabel)}</span>
                </td>
                <td class="table-cell">${escapeHtml(row.cliente || '-')}</td>
                <td class="table-cell text-sm">${escapeHtml(row.veiculo_resumo || '-')}</td>
                <td class="table-cell text-right font-semibold text-red-600">${Currency.format(row.total_vencido, true)}</td>
                <td class="table-cell text-right font-semibold text-green-600">${Currency.format(row.total_a_vencer, true)}</td>
                <td class="table-cell text-right font-bold">${Currency.format(row.total_aberto, true)}</td>
                <td class="table-cell text-center text-sm">${prazo}<div class="text-xs text-slate-400 mt-1">${Number(row.parcelas_abertas)} ${escapeHtml(i18n.open_installments)}</div></td>
                <td class="table-cell text-center whitespace-nowrap">
                    <button type="button" class="btn-icon text-sky-600 btn-toggle-details" data-target="${detailId}" title="${escapeHtml(i18n.view_installments)}"><i class="fas fa-chevron-down"></i></button>
                    <button type="button" class="btn-icon text-blue-600 btn-open-contract" data-id="${Number(row.id_contrato)}" data-code="${escapeHtml(row.contrato)}" title="${escapeHtml(i18n.view_contract)}"><i class="fas fa-file-contract"></i></button>
                </td>
            </tr>
            <tr id="${detailId}" class="bg-slate-50" style="display:none;">
                <td colspan="8" class="p-3">${renderInstallments(row.parcelas || [])}</td>
            </tr>`;
        }).join('');
    }

    function renderInstallments(installments) {
        const body = installments.map(item => {
            const overdue = item.situacao === 'vencida';
            const badgeClass = overdue ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700';
            const badgeLabel = overdue
                ? `${i18n.overdue} ${Number(item.dias)}d`
                : `${i18n.upcoming} ${Number(item.dias)}d`;

            return `<tr>
                <td class="table-cell">${escapeHtml(item.fatura)}${item.parcela_label !== '-' ? ` <span class="text-xs text-slate-400">(${escapeHtml(item.parcela_label)})</span>` : ''}</td>
                <td class="table-cell">${escapeHtml(item.descricao || '-')}</td>
                <td class="table-cell text-center">${DateHelper.format(item.data_venci)}</td>
                <td class="table-cell text-right">${Currency.format(item.valor_subtotal, true)}</td>
                <td class="table-cell text-right">${Currency.format(item.juros_multa, true)}</td>
                <td class="table-cell text-right font-semibold">${Currency.format(item.valor_total, true)}</td>
                <td class="table-cell text-center"><span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${badgeClass}">${escapeHtml(badgeLabel)}</span></td>
                <td class="table-cell text-center"><button type="button" class="btn-icon text-blue-600 btn-payment-link" data-id="${Number(item.id)}" title="${escapeHtml(i18n.payment_link)}"><i class="fas fa-external-link-alt"></i></button></td>
            </tr>`;
        }).join('');

        return `<div class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full min-w-[900px]">
                <thead class="table-header-custom"><tr>
                    <th class="table-header">${escapeHtml(i18n.col_invoice)}</th>
                    <th class="table-header">${escapeHtml(i18n.col_description)}</th>
                    <th class="table-header text-center">${escapeHtml(i18n.col_due_date)}</th>
                    <th class="table-header text-right">${escapeHtml(i18n.col_original_value)}</th>
                    <th class="table-header text-right">${escapeHtml(i18n.col_fees)}</th>
                    <th class="table-header text-right">${escapeHtml(i18n.col_total_value)}</th>
                    <th class="table-header text-center">${escapeHtml(i18n.col_status)}</th>
                    <th class="table-header text-center">${escapeHtml(i18n.col_action)}</th>
                </tr></thead><tbody class="divide-y divide-slate-200">${body}</tbody>
            </table>
        </div>`;
    }

    async function openPaymentLink(id, button) {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const result = await API.get(`/api/financeiro/${id}/link-pagamento`);
            if (!result.success || !result.url) throw new Error(result.message || i18n.payment_link_error);
            window.parent.postMessage({ action: 'openLinkModal', url: result.url }, '*');
        } catch (error) {
            window.parent.postMessage({ action: 'openAlert', message: error.message || i18n.payment_link_error }, '*');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    }

    function clearChosen(id) {
        const select = document.getElementById(id);
        if (select?.chosenSelect?.clear) select.chosenSelect.clear();
        else if (select) select.value = '';
    }

    document.getElementById('reportTableBody').addEventListener('click', event => {
        const toggle = event.target.closest('.btn-toggle-details');
        if (toggle) {
            const detail = document.getElementById(toggle.dataset.target);
            const opening = detail.style.display === 'none';
            detail.style.display = opening ? 'table-row' : 'none';
            toggle.querySelector('i').className = opening ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            return;
        }

        const contract = event.target.closest('.btn-open-contract');
        if (contract) {
            window.parent.openOrSwitchToTab?.(`/pages/contratos/editar/${contract.dataset.id}`, `${i18n.contract_label} ${contract.dataset.code}`, 'fas fa-file-signature', `contrato-${contract.dataset.id}`);
            return;
        }

        const payment = event.target.closest('.btn-payment-link');
        if (payment) openPaymentLink(payment.dataset.id, payment);
    });

    document.getElementById('btnAplicar').addEventListener('click', loadReport);
    document.getElementById('btnExportPdf').addEventListener('click', () => {
        ReportUtils.exportPdf(`${PDF_URL}?${buildParams().toString()}`, i18n.title);
    });
    document.getElementById('btnLimpar').addEventListener('click', () => {
        setDefaultPeriod();
        ['filterFilial', 'filterCliente', 'filterContrato', 'filterVeiculo'].forEach(clearChosen);
        document.getElementById('filterSituacao').value = '';
        loadReport();
    });

    ReportUtils.initFilters();
    setDefaultPeriod();
    loadReport();
})();
</script>
@endsection
