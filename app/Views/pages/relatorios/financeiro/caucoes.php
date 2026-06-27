@extends('layouts.iframe')

@section('title', t('modules.relatorios.financeiro.caucoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.financeiro.caucoes.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.financeiro.caucoes.description') ?></p>

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
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.caucoes.col_status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value="notificacao"><?= t('modules.relatorios.financeiro.caucoes.status_notification') ?></option>
                <option value="ativa"><?= t('modules.relatorios.financeiro.caucoes.status_active') ?></option>
                <option value="vencida"><?= t('modules.relatorios.financeiro.caucoes.status_overdue') ?></option>
                <option value="proxima"><?= t('modules.relatorios.financeiro.caucoes.status_due_soon') ?></option>
                <option value="devolvida"><?= t('modules.relatorios.financeiro.caucoes.status_returned') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[200px]">
            <label for="filterOrigem" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.caucoes.col_origin') ?></label>
            <select id="filterOrigem" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.relatorios.common.all') ?></option>
                <option value="locacao"><?= t('modules.relatorios.financeiro.caucoes.origin_rental') ?></option>
                <option value="contrato"><?= t('modules.relatorios.financeiro.caucoes.origin_contract') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
            </button>
        </div>
    </div>

    <div id="returnPanel" class="hidden mb-4 p-3 bg-white border border-blue-200 rounded-lg shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3">
            <div class="min-w-[180px]">
                <div class="text-xs text-slate-500"><?= t('modules.relatorios.financeiro.caucoes.returning') ?></div>
                <div id="returnTitle" class="font-medium text-slate-800"></div>
            </div>
            <div class="min-w-[150px]">
                <label for="returnDate" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.caucoes.return_date') ?></label>
                <input type="date" id="returnDate" class="form-input-focus w-full text-sm">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="returnConta" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.caucoes.return_account') ?></label>
                <select id="returnConta" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="returnForma" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.financeiro.caucoes.return_payment_method') ?></label>
                <select id="returnForma" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="flex gap-2">
                <button id="btnConfirmReturn" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-check mr-1"></i><?= t('modules.relatorios.financeiro.caucoes.confirm_return') ?>
                </button>
                <button id="btnCancelReturn" class="btn-secondary py-2 px-3 rounded-md text-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.caucoes.col_origin') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.caucoes.col_code') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.financeiro.caucoes.col_client') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.financeiro.caucoes.col_value') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.caucoes.col_expected_return') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.caucoes.col_status') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.financeiro.caucoes.col_financial') ?></th>
                    <th class="table-header text-right"><?= t('common.labels.actions') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    @include('pages.relatorios._partials.pagination')
</div>
@endsection

@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/financeiro/caucoes';
    let currentPage = 1;
    let perPage = 25;
    let selected = null;

    const totalsConfig = [
        { key: 'valor_total', label: '<?= t("modules.relatorios.financeiro.caucoes.total_value") ?>', icon: 'fa-shield-alt', format: 'currency', color: 'blue' },
        { key: 'ativas', label: '<?= t("modules.relatorios.financeiro.caucoes.status_active") ?>', icon: 'fa-clock', format: 'number', color: 'amber' },
        { key: 'vencidas', label: '<?= t("modules.relatorios.financeiro.caucoes.status_overdue") ?>', icon: 'fa-exclamation-triangle', format: 'number', color: 'red' },
        { key: 'proximas', label: '<?= t("modules.relatorios.financeiro.caucoes.status_due_soon") ?>', icon: 'fa-calendar-day', format: 'number', color: 'green' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        ReportUtils.setDefaultPeriod();
        document.getElementById('returnDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('btnAplicar')?.addEventListener('click', () => { currentPage = 1; carregarRelatorio(); });
        document.getElementById('btnCancelReturn')?.addEventListener('click', fecharPainel);
        document.getElementById('btnConfirmReturn')?.addEventListener('click', registrarDevolucao);
        carregarRelatorio();
    }

    async function carregarRelatorio() {
        try {
            ReportUtils.showLoading();
            const params = {
                data_inicio: document.getElementById('filterDataInicio').value,
                data_fim: document.getElementById('filterDataFim').value,
                filial: document.getElementById('filterFilial').value,
                status: document.getElementById('filterStatus').value,
                origem: document.getElementById('filterOrigem').value,
                page: currentPage,
                perPage: perPage,
            };
            const result = await API.get(API_URL, params);
            if (!result.success) {
                ReportUtils.showError(result.message);
                return;
            }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            renderTable(result.data || []);
            ReportUtils.renderPagination(result.pagination, (page, pp) => { currentPage = page; if (pp) perPage = pp; carregarRelatorio(); });
            ReportUtils.showContent();
        } catch (error) {
            console.error(error);
            ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>');
        }
    }

    function renderTable(rows) {
        const body = document.getElementById('reportTableBody');
        if (!rows.length) {
            body.innerHTML = '';
            document.getElementById('reportTableContainer').style.display = 'none';
            return;
        }
        document.getElementById('reportTableContainer').style.display = 'block';
        body.innerHTML = rows.map(row => {
            const canReturn = row.status === 'ativa';
            const action = canReturn
                ? `<button class="btn-return btn-icon text-blue-600 hover:text-blue-800" data-row='${escapeAttr(JSON.stringify(row))}' title="<?= t('modules.relatorios.financeiro.caucoes.confirm_return') ?>"><i class="fas fa-undo"></i></button>`
                : '';
            return `<tr class="hover:bg-slate-50">
                <td class="px-4 py-2 text-sm">${originBadge(row.origem)}</td>
                <td class="px-4 py-2 text-sm font-mono">${escapeHtml(row.codigo || '-')}</td>
                <td class="px-4 py-2 text-sm">${escapeHtml(row.cliente_nome || '-')}</td>
                <td class="px-4 py-2 text-sm text-right">${Currency.format(row.valor || 0)}</td>
                <td class="px-4 py-2 text-sm text-center">${DateHelper.format(row.data_prevista_devolucao)}</td>
                <td class="px-4 py-2 text-sm text-center">${statusBadge(row.situacao)}</td>
                <td class="px-4 py-2 text-sm text-center">${financialBadge(row)}</td>
                <td class="px-4 py-2 text-sm text-right">${action}</td>
            </tr>`;
        }).join('');
        document.querySelectorAll('.btn-return').forEach(btn => {
            btn.addEventListener('click', function () {
                selected = JSON.parse(this.dataset.row);
                abrirPainel();
            });
        });
    }

    function abrirPainel() {
        if (!selected) return;
        document.getElementById('returnTitle').textContent = `${selected.origem === 'contrato' ? 'Contrato' : 'Locação'} ${selected.codigo}`;
        document.getElementById('returnPanel').classList.remove('hidden');
        document.getElementById('returnDate').focus();
    }

    function fecharPainel() {
        selected = null;
        document.getElementById('returnPanel').classList.add('hidden');
    }

    async function registrarDevolucao() {
        if (!selected) return;
        const payload = {
            data_devolucao: document.getElementById('returnDate').value,
            id_conta: document.getElementById('returnConta').value,
            id_forma_pagamento: document.getElementById('returnForma').value,
        };
        try {
            const result = await API.post(`/api/caucoes/${selected.origem}/${selected.id_origem}/devolver`, payload);
            if (!result.success) {
                openAlert(result.message || '<?= t("modules.relatorios.financeiro.caucoes.return_error") ?>');
                return;
            }
            fecharPainel();
            await carregarRelatorio();
        } catch (error) {
            openAlert('<?= t("modules.relatorios.financeiro.caucoes.return_error") ?>');
        }
    }

    function openAlert(message) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message }, '*');
        }
    }

    function originBadge(origin) {
        const label = origin === 'contrato'
            ? '<?= t("modules.relatorios.financeiro.caucoes.origin_contract") ?>'
            : '<?= t("modules.relatorios.financeiro.caucoes.origin_rental") ?>';
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">${label}</span>`;
    }

    function financialBadge(row) {
        if (row.id_financeiro_entrada) {
            return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><?= t("modules.relatorios.financeiro.caucoes.financial_yes") ?></span>`;
        }
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600"><?= t("modules.relatorios.financeiro.caucoes.financial_no") ?></span>`;
    }

    function statusBadge(status) {
        const map = {
            ativa: ['bg-slate-100 text-slate-700', '<?= t("modules.relatorios.financeiro.caucoes.status_active") ?>'],
            proxima: ['bg-green-100 text-green-700', '<?= t("modules.relatorios.financeiro.caucoes.status_due_soon") ?>'],
            vencida: ['bg-red-100 text-red-700', '<?= t("modules.relatorios.financeiro.caucoes.status_overdue") ?>'],
            devolvida: ['bg-blue-100 text-blue-700', '<?= t("modules.relatorios.financeiro.caucoes.status_returned") ?>'],
            cancelada: ['bg-slate-100 text-slate-500', '<?= t("modules.relatorios.financeiro.caucoes.status_canceled") ?>'],
        };
        const item = map[status] || map.ativa;
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${item[0]}">${item[1]}</span>`;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return String(value).replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    init();
})();
</script>
@endsection
