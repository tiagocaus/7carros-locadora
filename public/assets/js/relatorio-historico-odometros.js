(() => {
    const i18n = window.odometrosReportI18n;
    const fields = {data_inicio: 'DataInicio', data_fim: 'DataFim', filial: 'Filial', grupo: 'Grupo', veiculo: 'Veiculo', cliente: 'Cliente', contrato: 'Contrato'};
    const element = id => document.getElementById(id);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char]));
    let applied = {};
    let requestId = 0;
    const totalsConfig = ['registros', 'veiculos', 'contratos'].map(key => ({key, label: i18n[key], icon: 'fa-list', format: 'number'}));

    async function load(page = 1) {
        const current = ++requestId;
        ReportUtils.showLoading();
        try {
            const result = await API.get('/api/relatorios/veicular/historico-odometros', {...applied, page, per_page: element('rowsPerPage').value});
            if (current !== requestId) return;
            if (!result.success) { ReportUtils.showError(escape(result.message || i18n.error)); return; }
            element('reportTotals').innerHTML = ReportUtils.buildTotalCards(result.totals, totalsConfig);
            const locale = (window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-');
            element('reportTableBody').innerHTML = result.data.length ? result.data.map(row => {
                const values = [
                    row.data_referencia ? DateHelper.formatOperationalDateTime(row.data_referencia) : DateHelper.format(row.data),
                    [row.placa, row.modelo].filter(Boolean).join(' — '), row.contrato ?? row.contrato_codigo,
                    row.cliente, row.filial, row.grupo, Number(row.odometro).toLocaleString(locale), row.obs,
                    DateHelper.formatDateTime(row.created_at),
                ];
                return '<tr class="hover:bg-slate-50">' + values.map(value => `<td class="table-cell whitespace-pre-wrap">${escape(value ?? '—')}</td>`).join('') + '</tr>';
            }).join('') : `<tr><td colspan="9" class="table-cell text-center">${escape(i18n.empty)}</td></tr>`;
            ReportUtils.showContent();
            element('reportTableContainer').style.display = 'block';
            ReportUtils.renderPagination(result.pagination, load);
        } catch (error) {
            if (current === requestId) ReportUtils.showError(escape(i18n.error));
        }
    }
    function apply() {
        applied = Object.fromEntries(Object.entries(fields).map(([key, suffix]) => [key, element('filter' + suffix).value]));
        load();
    }
    ReportUtils.initFilters();
    element('rowsPerPage').value = '50';
    element('rowsPerPage').addEventListener('change', () => load());
    element('btnAplicar').addEventListener('click', apply);
    element('btnLimpar').addEventListener('click', () => {
        Object.values(fields).forEach(suffix => {
            const field = element('filter' + suffix);
            field.value = '';
            field.chosenSelect?.clear();
        });
        apply();
    });
    element('btnExportPdf').addEventListener('click', () => ReportUtils.exportPdf('/relatorios/veicular/historico-odometros/pdf?' + new URLSearchParams(applied), i18n.title));
    apply();
})();
