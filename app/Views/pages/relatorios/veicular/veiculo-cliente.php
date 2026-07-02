@extends('layouts.iframe')

@section('title', t('modules.relatorios.veicular.veiculo_cliente.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-0"><?= t('modules.relatorios.veicular.veiculo_cliente.title') ?></h2>
    </div>
    <p class="text-sm text-slate-500 mb-3"><?= t('modules.relatorios.veicular.veiculo_cliente.description') ?></p>

    <?php
        $statusFilterHtml = '
            <div class="flex-1 min-w-[160px] max-w-[220px]">
                <label for="filterStatus" class="block text-xs text-slate-500 mb-1">' . htmlspecialchars(t('modules.relatorios.veicular.veiculo_cliente.filter_status'), ENT_QUOTES, 'UTF-8') . '</label>
                <select id="filterStatus" class="form-input-focus w-full text-sm">
                    <option value="">' . htmlspecialchars(t('modules.relatorios.veicular.veiculo_cliente.status_all'), ENT_QUOTES, 'UTF-8') . '</option>
                    <option value="aberto">' . htmlspecialchars(t('modules.relatorios.veicular.veiculo_cliente.status_aberto'), ENT_QUOTES, 'UTF-8') . '</option>
                    <option value="fechado">' . htmlspecialchars(t('modules.relatorios.veicular.veiculo_cliente.status_fechado'), ENT_QUOTES, 'UTF-8') . '</option>
                </select>
            </div>
        ';
    ?>
    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true, 'extraFiltersAfterFilial' => $statusFilterHtml])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')

    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display: none;">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.relatorios.veicular.veiculo_cliente.col_tipo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.veiculo_cliente.col_codigo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.veiculo_cliente.col_placa') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.relatorios.veicular.veiculo_cliente.col_veiculo') ?></th>
                    <th class="table-header"><?= t('modules.relatorios.veicular.veiculo_cliente.col_cliente') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.relatorios.veicular.veiculo_cliente.col_data_inicio') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.relatorios.veicular.veiculo_cliente.col_data_fim') ?></th>
                    <th class="table-header text-center"><?= t('modules.relatorios.veicular.veiculo_cliente.col_dias') ?></th>
                    <th class="table-header text-center hidden md:table-cell"><?= t('modules.relatorios.veicular.veiculo_cliente.col_km_rodado') ?></th>
                    <th class="table-header text-right"><?= t('modules.relatorios.veicular.veiculo_cliente.col_valor') ?></th>
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
    const i18n = {
        loadError: '<?= t("modules.relatorios.messages.load_error") ?>',
        connectionError: '<?= t("modules.relatorios.messages.connection_error") ?>',
    };
    const API_URL = '/api/relatorios/veicular/veiculo-cliente';

    const totalsConfig = [
        { key: 'qtd_locacoes', label: '<?= t("modules.relatorios.veicular.veiculo_cliente.qtd_locacoes") ?>', icon: 'fa-list', format: 'number' },
        { key: 'qtd_clientes', label: '<?= t("modules.relatorios.veicular.veiculo_cliente.qtd_clientes") ?>', icon: 'fa-users', format: 'number' },
        { key: 'receita_total', label: '<?= t("modules.relatorios.veicular.veiculo_cliente.receita_total") ?>', icon: 'fa-dollar-sign', format: 'currency', color: 'green' },
        { key: 'dias_total', label: '<?= t("modules.relatorios.veicular.veiculo_cliente.dias_total") ?>', icon: 'fa-calendar', format: 'number' },
        { key: 'km_total', label: '<?= t("modules.relatorios.veicular.veiculo_cliente.km_total") ?>', icon: 'fa-road', format: 'number' },
    ];

    async function init() {
        ReportUtils.initFilters();
        await ReportUtils.loadFiliais('filterFilial');
        await ReportUtils.loadGrupos('filterGrupo');
        ReportUtils.setDefaultPeriod();

        document.getElementById('btnAplicar')?.addEventListener('click', carregar);
        document.getElementById('btnLimpar')?.addEventListener('click', limpar);
        document.getElementById('btnExportPdf')?.addEventListener('click', () => {
            const qs = new URLSearchParams(buildParams()).toString();
            ReportUtils.exportPdf('/relatorios/veicular/veiculo-cliente/pdf?' + qs, '<?= t("modules.relatorios.veicular.veiculo_cliente.title") ?>');
        });
    }

    function buildParams() {
        return {
            data_inicio: document.getElementById('filterDataInicio').value,
            data_fim: document.getElementById('filterDataFim').value,
            filial: document.getElementById('filterFilial').value,
            grupo: document.getElementById('filterGrupo')?.value || '',
            status: document.getElementById('filterStatus')?.value || '',
        };
    }

    async function carregar() {
        try {
            ReportUtils.showLoading();
            const result = await API.get(API_URL, buildParams());
            if (!result.success) { ReportUtils.showError(result.message || i18n.loadError); return; }
            renderTotals(result.totals);
            renderTable(result.data);
            ReportUtils.showContent();
        } catch (e) {
            console.error('Erro ao carregar relatório:', e);
            ReportUtils.showError(i18n.connectionError);
        }
    }

    function renderTotals(t) { document.getElementById('reportTotals').innerHTML = ReportUtils.buildTotalCards(t, totalsConfig); }

    function renderTable(data) {
        const cont = document.getElementById('reportTableContainer');
        const tbody = document.getElementById('reportTableBody');
        if (!data || data.length === 0) { cont.style.display = 'none'; return; }
        cont.style.display = 'block';
        tbody.innerHTML = data.map(row => {
            const tipoCls = row.tipo === 'Locação' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800';
            const veiculos = Array.isArray(row.veiculos) ? row.veiculos : [];
            const detalhesVeiculos = veiculos.length > 1 ? renderVeiculosContrato(veiculos) : '';
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${tipoCls}">${escapeHtml(row.tipo)}</span></td>
                <td class="table-cell font-medium">${escapeHtml(row.codigo || '-')}</td>
                <td class="table-cell">${escapeHtml(row.placa || '-')}</td>
                <td class="table-cell hidden md:table-cell">${escapeHtml(row.veiculo || '-')}</td>
                <td class="table-cell">${escapeHtml(row.cliente || '-')}</td>
                <td class="table-cell hidden sm:table-cell">${row.data_inicio ? DateHelper.format(row.data_inicio) : '-'}</td>
                <td class="table-cell hidden sm:table-cell">${row.data_fim ? DateHelper.format(row.data_fim) : '<span class="text-amber-600">Em uso</span>'}</td>
                <td class="table-cell text-center">${row.dias}</td>
                <td class="table-cell text-center hidden md:table-cell">${Number(row.km_rodado).toLocaleString('pt-BR')}</td>
                <td class="table-cell text-right font-medium">${Currency.format(row.valor, true)}</td>
            </tr>${detalhesVeiculos}`;
        }).join('');
    }

    function renderVeiculosContrato(veiculos) {
        const linhas = veiculos.map(v => `
            <tr class="bg-slate-50">
                <td class="table-cell"></td>
                <td class="table-cell text-xs text-slate-500">Veículo</td>
                <td class="table-cell">${escapeHtml(v.placa || '-')}</td>
                <td class="table-cell hidden md:table-cell">${escapeHtml(v.veiculo || '-')}</td>
                <td class="table-cell"></td>
                <td class="table-cell hidden sm:table-cell">${v.data_inicio ? DateHelper.format(v.data_inicio) : '-'}</td>
                <td class="table-cell hidden sm:table-cell">${v.data_fim ? DateHelper.format(v.data_fim) : '<span class="text-amber-600">Em uso</span>'}</td>
                <td class="table-cell text-center">-</td>
                <td class="table-cell text-center hidden md:table-cell">${Number(v.km_rodado || 0).toLocaleString('pt-BR')}</td>
                <td class="table-cell"></td>
            </tr>
        `).join('');

        return linhas;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function limpar() {
        ReportUtils.setDefaultPeriod();
        document.getElementById('filterFilial').value = '';
        const g = document.getElementById('filterGrupo'); if (g) g.value = '';
        const s = document.getElementById('filterStatus'); if (s) s.value = '';
        ReportUtils.hideContent();
    }

    init();
})();
</script>
@endsection
