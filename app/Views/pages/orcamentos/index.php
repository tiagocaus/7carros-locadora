@extends('layouts.iframe')

@section('title', 'Orçamentos')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col lg:flex-row justify-between gap-3 mb-6">
        <h2 class="title-section">Orçamentos</h2>
        <div class="flex flex-wrap gap-2">
            <select id="statusFilter" class="form-input-focus">
                <option value="">Todos os status</option>
                <option value="R">Rascunho</option><option value="E">Enviado</option>
                <option value="A">Aceito</option><option value="N">Recusado</option>
                <option value="C">Convertido</option>
            </select>
            <div class="relative"><input id="searchInput" class="form-input-focus pr-8" placeholder="Código, cliente ou grupo"><i class="fas fa-search absolute right-3 top-3 text-slate-400"></i></div>
            <?php if (\App\Core\Auth::can('orcamentos.criar')): ?>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium"><i class="fas fa-plus mr-2"></i>Novo orçamento</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-[900px] divide-y divide-slate-200">
            <thead class="table-header-custom"><tr>
                <th class="table-header">Código</th><th class="table-header">Cliente</th>
                <th class="table-header">Período</th><th class="table-header">Grupo</th>
                <th class="table-header text-right">Total</th><th class="table-header text-center">Validade</th>
                <th class="table-header text-center">Status</th><th class="table-header text-right">Ações</th>
            </tr></thead>
            <tbody id="tableBody" class="divide-y divide-slate-200"></tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap justify-between items-center gap-3">
        <select id="rowsPerPage" class="form-input-focus select-pagination"><option>10</option><option>20</option><option>50</option></select>
        <span id="registrosInfo" class="text-sm text-slate-600"></span>
        <div class="flex gap-1"><button id="prevPage" class="pagination-button rounded-md"><i class="fas fa-chevron-left"></i></button><span id="pageInfo" class="px-3 py-2 text-sm"></span><button id="nextPage" class="pagination-button rounded-md"><i class="fas fa-chevron-right"></i></button></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    let page = 1, totalPages = 1, searchTimer = null, pendingAction = null;
    const tbody = document.getElementById('tableBody');
    const statusLabels = {R:'Rascunho',E:'Enviado',A:'Aceito',N:'Recusado',X:'Expirado',C:'Convertido'};
    const statusClasses = {R:'bg-slate-100 text-slate-700',E:'bg-blue-100 text-blue-700',A:'bg-green-100 text-green-700',N:'bg-red-100 text-red-700',X:'bg-amber-100 text-amber-700',C:'bg-purple-100 text-purple-700'};
    const canEdit = <?= \App\Core\Auth::can('orcamentos.editar') ? 'true' : 'false' ?>;
    const canConvert = <?= \App\Core\Auth::can('orcamentos.converter') ? 'true' : 'false' ?>;
    const canPrint = <?= \App\Core\Auth::can('orcamentos.imprimir') ? 'true' : 'false' ?>;
    const canCreate = <?= \App\Core\Auth::can('orcamentos.criar') ? 'true' : 'false' ?>;

    function navigate(url) { window.parent !== window ? window.parent.postMessage({action:'navigate',page:url}, '*') : window.location.href = url; }
    function notify(message) { window.parent.postMessage({action:'openAlert', message}, '*'); }
    function money(value) { return Currency.format(Number(value || 0), true); }
    function date(value) { return value ? DateHelper.format(value) : '-'; }
    function datetime(value) { return value ? DateHelper.formatDateTime(value) : '-'; }

    async function load() {
        tbody.innerHTML = '<tr><td colspan="8" class="table-cell text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>';
        try {
            const result = await API.get('/api/orcamentos', {page, perPage:document.getElementById('rowsPerPage').value, search:document.getElementById('searchInput').value, status:document.getElementById('statusFilter').value});
            if (!result.success) throw new Error(result.message || 'Erro ao carregar.');
            totalPages = result.pagination.totalPages;
            render(result.data);
            document.getElementById('registrosInfo').textContent = result.pagination.total + ' registro(s)';
            document.getElementById('pageInfo').textContent = page + ' / ' + totalPages;
            document.getElementById('prevPage').disabled = page <= 1;
            document.getElementById('nextPage').disabled = page >= totalPages;
        } catch (error) { tbody.innerHTML = '<tr><td colspan="8" class="table-cell text-center text-red-600">' + escapeHtml(error.message) + '</td></tr>'; }
    }

    function render(items) {
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="8" class="table-cell text-center text-slate-500">Nenhum orçamento encontrado.</td></tr>'; return; }
        tbody.innerHTML = items.map(item => {
            const status = item.status_exibicao || item.status;
            const editable = canEdit && item.status !== 'C';
            const convertible = canConvert && !['C','N','X'].includes(status);
            return `<tr class="hover:bg-slate-50">
                <td class="table-cell font-mono font-medium">${escapeHtml(item.codigo)}</td>
                <td class="table-cell"><div class="font-medium">${escapeHtml(item.cliente_nome)}</div><div class="text-xs text-slate-500">${escapeHtml(item.filial_retirada_nome || '')}</div></td>
                <td class="table-cell text-sm">${datetime(item.data_saida)}<br><span class="text-slate-500">até ${datetime(item.data_prevista)}</span></td>
                <td class="table-cell">${escapeHtml(item.grupo_nome || item.grupo_nome_atual || '-')}</td>
                <td class="table-cell text-right font-semibold">${money(item.total_pagar)}</td>
                <td class="table-cell text-center">${date(item.validade)}</td>
                <td class="table-cell text-center"><span class="px-2 py-1 rounded text-xs font-medium ${statusClasses[status] || statusClasses.R}">${statusLabels[status] || status}</span></td>
                <td class="table-cell text-right whitespace-nowrap">
                    ${editable ? `<button class="btn-icon text-amber-600" data-action="edit" data-id="${item.id}" title="Editar"><i class="fas fa-edit"></i></button>` : ''}
                    ${canPrint ? `<button class="btn-icon text-blue-600" data-action="print" data-id="${item.id}" data-code="${escapeHtml(item.codigo)}" title="PDF"><i class="fas fa-file-pdf"></i></button>` : ''}
                    ${canCreate ? `<button class="btn-icon text-slate-600" data-action="duplicate" data-id="${item.id}" title="Duplicar"><i class="fas fa-copy"></i></button>` : ''}
                    ${convertible ? `<button class="btn-icon text-green-600" data-action="convert" data-id="${item.id}" title="Converter em reserva"><i class="fas fa-calendar-check"></i></button>` : ''}
                </td></tr>`;
        }).join('');
    }

    tbody.addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]'); if (!button) return;
        const id = button.dataset.id, action = button.dataset.action;
        if (action === 'edit') return navigate('/pages/orcamentos/editar/' + id);
        if (action === 'print') return window.parent.postMessage({action:'openPrintModal',url:'/orcamentos/' + id + '/imprimir',title:'Orçamento ' + button.dataset.code}, '*');
        if (action === 'duplicate') {
            const result = await API.post('/api/orcamentos/' + id + '/duplicar');
            if (result.success) navigate('/pages/orcamentos/editar/' + result.data.id); else notify(result.message);
            return;
        }
        if (action === 'convert') {
            pendingAction = {action, id};
            window.parent.postMessage({action:'openGenericConfirmModal',title:'Converter em reserva',message:'Os valores do orçamento serão copiados para uma nova reserva. Deseja continuar?',confirmText:'Converter'}, '*');
        }
    });

    window.addEventListener('message', async event => {
        if (event.data?.action !== 'genericConfirmed' || !pendingAction) return;
        const action = pendingAction; pendingAction = null;
        const result = await API.post('/api/orcamentos/' + action.id + '/converter');
        if (result.success) { notify(result.message); navigate('/pages/locacoes/editar/' + result.data.id_locacao); }
        else notify(result.message || 'Não foi possível converter o orçamento.');
    });

    document.getElementById('btnNovo')?.addEventListener('click', () => navigate('/pages/orcamentos/adicionar'));
    document.getElementById('searchInput').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer=setTimeout(()=>{page=1;load();},350); });
    document.getElementById('statusFilter').addEventListener('change', () => {page=1;load();});
    document.getElementById('rowsPerPage').addEventListener('change', () => {page=1;load();});
    document.getElementById('prevPage').addEventListener('click', () => {if(page>1){page--;load();}});
    document.getElementById('nextPage').addEventListener('click', () => {if(page<totalPages){page++;load();}});
    load();
})();
</script>
@endsection
