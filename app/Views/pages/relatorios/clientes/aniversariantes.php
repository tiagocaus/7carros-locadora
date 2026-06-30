@extends('layouts.iframe')

@section('title', t('modules.relatorios.clientes.aniversariantes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t('modules.relatorios.clientes.aniversariantes.title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.clientes.aniversariantes.description') ?></p>

    <!-- Filtros customizados -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
        <div class="flex-1 min-w-[120px] max-w-[180px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.aniversariantes.mes') ?></label>
            <select id="filterMes" class="form-input-focus w-full text-sm">
                <?php $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro']; ?>
                <?php foreach ($meses as $n => $nome): ?>
                <option value="<?= $n ?>" <?= $n === (int) \App\Helpers\DateHelper::todayForDatabase('n') ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[100px] max-w-[140px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.clientes.aniversariantes.dia') ?></label>
            <input type="number" id="filterDia" min="1" max="31" placeholder="<?= t('modules.relatorios.common.all') ?>" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow whitespace-nowrap"><i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?></button>
            <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2"><i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?></button>
        </div>
    </div>

    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')

    <div id="reportChartContainer" class="bg-white shadow-md rounded-lg p-4 mb-4" style="display: none;">
        <canvas id="reportChart" height="200"></canvas>
    </div>

    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200 text-sm">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.clientes.aniversariantes.col_cliente') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.aniversariantes.col_cpf_cnpj') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.aniversariantes.col_nascimento') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.aniversariantes.col_idade') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.aniversariantes.col_telefone') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.clientes.aniversariantes.col_email') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.aniversariantes.col_ultima') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.clientes.aniversariantes.col_total_locacoes') ?></th>
                </tr>
            </thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="/assets/js/report-utils.min.js"></script>
<script>
(function () {
    const API_URL = '/api/relatorios/clientes/aniversariantes';
    const PDF_URL = '/relatorios/clientes/aniversariantes/pdf';
    let chartInstance = null;

    const totalsConfig = [
        { key: 'qtd_aniversariantes', label: '<?= t("modules.relatorios.clientes.aniversariantes.qtd_aniversariantes") ?>', icon: 'fa-birthday-cake', format: 'number' },
        { key: 'idade_media', label: '<?= t("modules.relatorios.clientes.aniversariantes.idade_media") ?>', icon: 'fa-user-clock', format: 'number' },
    ];

    async function init() {
        await ReportUtils.loadFiliais('filterFilial');
        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams({ mes: document.getElementById('filterMes').value, dia: document.getElementById('filterDia').value || '', filial: document.getElementById('filterFilial').value }).toString();
            ReportUtils.exportPdf(`${PDF_URL}?${qs}`, '<?= t("modules.relatorios.clientes.aniversariantes.title") ?>');
        });
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const r = await API.get(API_URL, { mes: document.getElementById('filterMes').value, dia: document.getElementById('filterDia').value, filial: document.getElementById('filterFilial').value });
            if (!r.success) { ReportUtils.showError(r.message); return; }
            document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(r.totals, totalsConfig);
            renderChart(r.chart);
            renderTable(r.data && r.data.lista ? r.data.lista : []);
            ReportUtils.showContent();
        } catch (e) { console.error(e); ReportUtils.showError('<?= t("modules.relatorios.messages.connection_error") ?>'); }
    }

    function renderChart(c) {
        const cont = document.getElementById('reportChartContainer');
        if (!c || !c.labels || c.labels.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        const ctx = document.getElementById('reportChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels: c.labels, datasets: [{ label: '<?= t("modules.relatorios.clientes.aniversariantes.qtd_aniversariantes") ?>', data: c.data || [], backgroundColor: 'rgba(168, 85, 247, .7)', borderColor: 'rgb(168, 85, 247)', borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    function renderTable(lista) {
        const cont = document.getElementById('reportTableContainer');
        const tb = document.getElementById('reportTableBody');
        if (!lista || lista.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tb.innerHTML = lista.map(r => `<tr class="hover:bg-slate-50">
            <td class="table-cell font-medium">${r.nome || '-'}</td>
            <td class="table-cell text-slate-600 text-xs">${r.cpf_cnpj || ''}</td>
            <td class="table-cell text-center">${r.nascimento ? DateHelper.format(r.nascimento) : '-'}</td>
            <td class="table-cell text-center">${r.idade || 0}</td>
            <td class="table-cell text-slate-600 text-xs">${r.telefone || '-'}</td>
            <td class="table-cell text-slate-600 text-xs">${r.email || '-'}</td>
            <td class="table-cell text-center">${r.ultima_locacao ? DateHelper.format(r.ultima_locacao) : '-'}</td>
            <td class="table-cell text-center">${r.total_locacoes || 0}</td>
        </tr>`).join('');
    }

    function limpar() { document.getElementById('filterMes').value = DateHelper.currentMonth(); document.getElementById('filterDia').value = ''; document.getElementById('filterFilial').value = ''; ReportUtils.hideContent(); }
    init();
})();
</script>
@endsection
