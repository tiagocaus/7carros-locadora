@extends('layouts.iframe')

@section('title', t('modules.notificacoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.notificacoes.title') ?></h2>
    </div>

    <!-- Chips de categoria -->
    <div id="chips" class="flex flex-wrap gap-2 mb-4"></div>

    <!-- Tabela -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr id="tableHead"></tr>
                </thead>
                <tbody id="tableBody" class="bg-white divide-y divide-slate-200"></tbody>
            </table>
        </div>

        <!-- Paginacao -->
        <div class="flex justify-between items-center px-4 py-3 border-t border-slate-200 bg-slate-50">
            <span id="paginationInfo" class="text-sm text-slate-600"></span>
            <div class="flex gap-2">
                <button id="btnPrev" class="btn-secondary py-1 px-3 rounded text-sm disabled:opacity-50" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button id="btnNext" class="btn-secondary py-1 px-3 rounded text-sm disabled:opacity-50" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        all: '<?= t('modules.notificacoes.chips.all') ?>',
        manutencao: '<?= t('modules.notificacoes.chips.manutencao') ?>',
        tarefa: '<?= t('modules.notificacoes.chips.tarefa') ?>',
        fatura: '<?= t('modules.notificacoes.chips.fatura') ?>',
        licenciamento: '<?= t('modules.notificacoes.chips.licenciamento') ?>',
        cnh: '<?= t('modules.notificacoes.chips.cnh') ?>',
        problema: '<?= t('modules.notificacoes.chips.problema') ?>',
        loading: '<?= t('common.labels.loading') ?>',
        noRecords: '<?= t('modules.notificacoes.no_records') ?>',
        loadError: '<?= addslashes(t('modules.notificacoes.load_error')) ?>',
        showing: '<?= addslashes(t('modules.notificacoes.showing')) ?>',
        receita: '<?= t('modules.notificacoes.fatura.receita') ?>',
        despesa: '<?= t('modules.notificacoes.fatura.despesa') ?>',
        action_open: '<?= t('common.buttons.edit') ?>',
        col_type: '<?= t('modules.notificacoes.cols.type') ?>',
        col_desc: '<?= t('modules.notificacoes.cols.description') ?>',
        col_detail: '<?= t('modules.notificacoes.cols.detail') ?>',
        col_date: '<?= t('modules.notificacoes.cols.date') ?>',
        col_actions: '<?= t('common.labels.actions') ?>',
        col_os: '<?= t('modules.notificacoes.cols.os') ?>',
        col_vehicle: '<?= t('modules.notificacoes.cols.vehicle') ?>',
        col_status: '<?= t('modules.notificacoes.cols.status') ?>',
        col_seq: '<?= t('modules.notificacoes.cols.seq') ?>',
        col_code: '<?= t('modules.notificacoes.cols.code') ?>',
        col_party: '<?= t('modules.notificacoes.cols.client_supplier') ?>',
        col_value: '<?= t('modules.notificacoes.cols.value') ?>',
        col_due: '<?= t('modules.notificacoes.cols.due_date') ?>',
        col_charge: '<?= t('modules.notificacoes.cols.charge') ?>',
        col_client: '<?= t('modules.notificacoes.cols.client') ?>',
        col_doc: '<?= t('modules.notificacoes.cols.doc') ?>',
        col_cnh: '<?= t('modules.notificacoes.cols.cnh') ?>',
        col_validity: '<?= t('modules.notificacoes.cols.validity') ?>',
    };

    const initialCategoria = '<?= htmlspecialchars($categoria ?? 'all') ?>';
    const state = {
        categoria: initialCategoria || 'all',
        page: 1,
        perPage: 25,
        counts: { manutencoes: 0, faturas_vencidas: 0, licenciamento: 0, cnh_vencidas: 0, total: 0 },
    };

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    function fmtDate(iso) {
        if (!iso) return '-';
        const [y, m, d] = String(iso).split(' ')[0].split('-');
        return `${d}/${m}/${y}`;
    }

    function fmtMoney(n) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);
    }

    function tipoBadge(tipo) {
        const map = {
            manutencao: { icon: 'fa-wrench', label: i18n.manutencao, color: 'bg-amber-100 text-amber-700' },
            fatura: { icon: 'fa-money-bill-wave', label: i18n.fatura, color: 'bg-rose-100 text-rose-700' },
            licenciamento: { icon: 'fa-id-card', label: i18n.licenciamento, color: 'bg-blue-100 text-blue-700' },
            cnh: { icon: 'fa-id-badge', label: i18n.cnh, color: 'bg-purple-100 text-purple-700' },
        };
        const m = map[tipo] || { icon: 'fa-bell', label: tipo, color: 'bg-slate-100 text-slate-700' };
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${m.color}"><i class="fas ${m.icon} mr-1"></i>${m.label}</span>`;
    }

    function faturaTipoBadge(t) {
        if (t === 'R') {
            return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.receita}</span>`;
        }
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${i18n.despesa}</span>`;
    }

    function renderChips() {
        const c = state.counts;
        const cats = [
            { id: 'all', label: i18n.all, count: c.total || 0 },
            { id: 'manutencao', label: i18n.manutencao, count: c.manutencoes || 0 },
            { id: 'tarefa', label: i18n.tarefa, count: c.tarefas || 0 },
            { id: 'fatura', label: i18n.fatura, count: c.faturas_vencidas || 0 },
            { id: 'licenciamento', label: i18n.licenciamento, count: c.licenciamento || 0 },
            { id: 'cnh', label: i18n.cnh, count: c.cnh_vencidas || 0 },
            { id: 'problema', label: i18n.problema, count: c.problemas || 0 },
        ];
        document.getElementById('chips').innerHTML = cats.map(cat => {
            const active = cat.id === state.categoria;
            const cls = active
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50';
            return `<button data-cat="${cat.id}" class="chip-cat px-3 py-1.5 rounded-full border text-sm font-medium ${cls} transition-colors">
                ${escapeHtml(cat.label)} <span class="ml-1 opacity-80">(${cat.count})</span>
            </button>`;
        }).join('');
        document.querySelectorAll('.chip-cat').forEach(b => {
            b.addEventListener('click', function () {
                state.categoria = this.dataset.cat;
                state.page = 1;
                renderChips();
                load();
            });
        });
    }

    // Config de colunas por categoria. align: 'left' | 'center' | 'right'.
    // render(item, action): retorna HTML interno do <td>. Extras de cell (ex: 'font-mono') vao em cellClass.
    function getColumns(cat, action) {
        if (cat === 'manutencao') {
            return [
                { label: i18n.col_os,      align: 'left',   render: i => escapeHtml(i.titulo) },
                { label: i18n.col_vehicle, align: 'left',   render: i => escapeHtml(i.detalhe) },
                { label: i18n.col_status,  align: 'center', render: i => escapeHtml(i.extra?.status || '-') },
                { label: i18n.col_date,    align: 'center', render: i => fmtDate(i.data) },
                { label: i18n.col_actions, align: 'right',  render: () => action },
            ];
        }
        if (cat === 'fatura') {
            return [
                { label: i18n.col_seq,     align: 'left',   render: i => escapeHtml(i.extra?.sequencia ?? '-'), cellClass: 'text-slate-500' },
                { label: i18n.col_code,    align: 'left',   render: i => escapeHtml(i.titulo), cellClass: 'font-mono' },
                { label: i18n.col_party,   align: 'left',   render: i => escapeHtml(i.detalhe) },
                { label: i18n.col_type,    align: 'center', render: i => faturaTipoBadge(i.extra?.tipo_lancamento) },
                { label: i18n.col_value,   align: 'right',  render: i => fmtMoney(i.extra?.valor) },
                { label: i18n.col_due,     align: 'center', render: i => fmtDate(i.data) },
                { label: i18n.col_actions, align: 'right',  render: () => action },
            ];
        }
        if (cat === 'licenciamento') {
            return [
                { label: i18n.col_charge,  align: 'left',   render: i => escapeHtml(i.titulo) },
                { label: i18n.col_vehicle, align: 'left',   render: i => escapeHtml(i.detalhe) },
                { label: i18n.col_due,     align: 'center', render: i => fmtDate(i.data) },
                { label: i18n.col_actions, align: 'right',  render: () => action },
            ];
        }
        if (cat === 'cnh') {
            return [
                { label: i18n.col_client,   align: 'left',   render: i => escapeHtml(i.titulo) },
                { label: i18n.col_doc,      align: 'left',   render: i => escapeHtml(i.extra?.cpf_cnpj || '-') },
                { label: i18n.col_cnh,      align: 'left',   render: i => escapeHtml(i.detalhe) },
                { label: i18n.col_validity, align: 'center', render: i => fmtDate(i.data) },
                { label: i18n.col_actions,  align: 'right',  render: () => action },
            ];
        }
        // 'all', 'tarefa', 'problema' — colunas genericas
        return [
            { label: i18n.col_type,    align: 'left',   render: i => tipoBadge(i.tipo) },
            { label: i18n.col_desc,    align: 'left',   render: i => escapeHtml(i.titulo) },
            { label: i18n.col_detail,  align: 'left',   render: i => escapeHtml(i.detalhe) },
            { label: i18n.col_date,    align: 'center', render: i => fmtDate(i.data) },
            { label: i18n.col_actions, align: 'right',  render: () => action },
        ];
    }

    function renderHead() {
        const cols = getColumns(state.categoria, '');
        document.getElementById('tableHead').innerHTML = cols.map(c => {
            return `<th class="px-4 py-2 text-${c.align} text-xs font-semibold text-slate-600 uppercase tracking-wider">${escapeHtml(c.label)}</th>`;
        }).join('');
    }

    function renderRow(item) {
        const action = `<button data-link="${escapeHtml(item.link || '')}" class="btn-open btn-icon text-blue-600 hover:text-blue-800" title="${i18n.action_open}"><i class="fas fa-edit"></i></button>`;
        const cols = getColumns(state.categoria, action);
        const cells = cols.map(c => {
            const extra = c.cellClass ? ' ' + c.cellClass : '';
            return `<td class="px-4 py-2 text-sm text-${c.align}${extra}">${c.render(item)}</td>`;
        }).join('');
        return `<tr class="hover:bg-slate-50">${cells}</tr>`;
    }

    function bindRowActions() {
        document.querySelectorAll('.btn-open').forEach(b => {
            b.addEventListener('click', function () {
                const url = this.dataset.link;
                if (!url) return;
                if (window.parent !== window) {
                    window.parent.postMessage({ action: 'navigate', page: url }, '*');
                } else {
                    window.location.href = url;
                }
            });
        });
    }

    async function loadCounts() {
        try {
            const r = await API.get('/api/notifications/counts');
            if (r.success) state.counts = r.data || state.counts;
        } catch (e) { /* silencioso */ }
    }

    async function load() {
        renderHead();
        document.getElementById('tableBody').innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}</td></tr>`;
        try {
            const r = await API.get('/api/notifications/list', {
                categoria: state.categoria,
                page: state.page,
                perPage: state.perPage,
            });
            if (!r.success) throw new Error(r.message || i18n.loadError);
            const items = r.data || [];
            const tbody = document.getElementById('tableBody');
            if (items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500"><i class="fas fa-inbox mr-2"></i>${i18n.noRecords}</td></tr>`;
            } else {
                tbody.innerHTML = items.map(renderRow).join('');
                bindRowActions();
            }
            const p = r.pagination || {};
            const start = ((p.page - 1) * p.perPage) + 1;
            const end = Math.min(p.page * p.perPage, p.total);
            const info = i18n.showing
                .replace(':start', items.length ? start : 0)
                .replace(':end', items.length ? end : 0)
                .replace(':total', p.total || 0);
            document.getElementById('paginationInfo').textContent = info;
            document.getElementById('btnPrev').disabled = (p.page || 1) <= 1;
            document.getElementById('btnNext').disabled = (p.page || 1) >= (p.totalPages || 1);
        } catch (e) {
            document.getElementById('tableBody').innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-red-600">${escapeHtml(e.message || i18n.loadError)}</td></tr>`;
        }
    }

    document.getElementById('btnPrev').addEventListener('click', () => { if (state.page > 1) { state.page--; load(); }});
    document.getElementById('btnNext').addEventListener('click', () => { state.page++; load(); });

    (async function init() {
        await loadCounts();
        renderChips();
        await load();
        if (typeof window.pageLoading !== 'undefined' && window.pageLoading.done) {
            window.pageLoading.done();
        }
    })();
})();
</script>
@endsection
