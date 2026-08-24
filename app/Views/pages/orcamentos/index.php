@extends('layouts.iframe')

@section('title', 'Orçamentos')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0">Orçamentos</h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="statusFilter" class="form-input-focus w-40">
                <option value="">Todos os status</option>
                <option value="R">Rascunho</option><option value="E">Enviado</option>
                <option value="A">Aceito</option><option value="N">Recusado</option>
                <option value="C">Convertido</option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0"><input id="searchInput" class="form-input-focus sm:w-72 pr-8" placeholder="Código, cliente ou grupo"><i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i></div>
            <?php if (\App\Core\Auth::can('orcamentos.criar')): ?>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap"><i class="fas fa-plus mr-2"></i>Novo orçamento</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom"><tr>
                <th class="table-header">Código</th><th class="table-header">Cliente</th>
                <th class="table-header hidden lg:table-cell">Período</th><th class="table-header hidden xl:table-cell">Grupo</th>
                <th class="table-header hidden lg:table-cell text-right">Total</th><th class="table-header hidden xl:table-cell text-center">Validade</th>
                <th class="table-header hidden md:table-cell text-center">Status</th><th class="table-header px-2 w-40 text-center">Ações</th>
            </tr></thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2">Registros por página</label>
            <select id="rowsPerPage" class="form-input-focus select-pagination"><option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="50">50</option></select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0"><span id="registrosInfo">Mostrando 0-0 de 0 registros</span></div>
        <nav id="paginationNav" aria-label="Navegação de páginas" class="mt-2 sm:mt-0">
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
(function () {
    let page = 1, searchTimer = null, pendingAction = null;
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
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function load() {
        tbody.innerHTML = '<tr><td colspan="8" class="table-cell text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>';
        try {
            const result = await API.get('/api/orcamentos', {page, perPage:document.getElementById('rowsPerPage').value, search:document.getElementById('searchInput').value, status:document.getElementById('statusFilter').value});
            if (!result.success) throw new Error(result.message || 'Erro ao carregar.');
            render(result.data);
            updateRecordsInfo(result.pagination);
            updatePagination(result.pagination);
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
                <td class="table-cell hidden lg:table-cell text-sm">${datetime(item.data_saida)}<br><span class="text-slate-500">até ${datetime(item.data_prevista)}</span></td>
                <td class="table-cell hidden xl:table-cell">${escapeHtml(item.grupo_nome || item.grupo_nome_atual || '-')}</td>
                <td class="table-cell hidden lg:table-cell text-right font-semibold">${money(item.total_pagar)}</td>
                <td class="table-cell hidden xl:table-cell text-center">${date(item.validade)}</td>
                <td class="table-cell hidden md:table-cell text-center"><span class="px-2 py-1 rounded text-xs font-medium ${statusClasses[status] || statusClasses.R}">${statusLabels[status] || status}</span></td>
                <td class="table-cell text-center whitespace-nowrap">
                    ${editable ? `<button class="btn-icon text-amber-600" data-action="edit" data-id="${item.id}" title="Editar"><i class="fas fa-edit"></i></button>` : ''}
                    ${canPrint ? `<button class="btn-icon text-blue-600" data-action="print" data-id="${item.id}" data-code="${escapeHtml(item.codigo)}" title="PDF"><i class="fas fa-file-pdf"></i></button>` : ''}
                    ${canCreate ? `<button class="btn-icon text-slate-600" data-action="duplicate" data-id="${item.id}" title="Duplicar"><i class="fas fa-copy"></i></button>` : ''}
                    ${convertible ? `<button class="btn-icon text-green-600" data-action="convert" data-id="${item.id}" title="Converter em reserva"><i class="fas fa-calendar-check"></i></button>` : ''}
                </td></tr>`;
        }).join('');
    }

    function updateRecordsInfo(pagination) {
        const {page:currentPage, perPage, total} = pagination;
        const start = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, total);
        document.getElementById('registrosInfo').textContent = `Mostrando ${start}-${end} de ${total} registros`;
    }

    function updatePagination(pagination) {
        const nav = document.querySelector('#paginationNav ul');
        const {page:currentPage, totalPages, hasPrev, hasNext} = pagination;
        const maxButtons = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages || 1, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) startPage = Math.max(1, endPage - maxButtons + 1);

        let buttons = `<li><button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}" data-page="${currentPage - 1}" ${!hasPrev ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button></li>`;
        for (let number = startPage; number <= endPage; number++) {
            buttons += `<li><button class="pagination-button numbered ${number === currentPage ? 'active' : ''}" data-page="${number}">${number}</button></li>`;
        }
        buttons += `<li><button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}" data-page="${currentPage + 1}" ${!hasNext ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button></li>`;
        nav.innerHTML = buttons;
        nav.querySelectorAll('button[data-page]:not([disabled])').forEach(button => button.addEventListener('click', () => {
            page = Number(button.dataset.page);
            load();
        }));
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
    load();
})();
</script>
@endsection
