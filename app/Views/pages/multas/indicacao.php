@extends('layouts.iframe')

@section('title', t('modules.multas.indicacoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.multas.indicacoes.title') ?></h2>
        <div class="flex items-center gap-2">
            <button id="btnInstrucoes" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-info-circle mr-2"></i> <?= t('modules.online_indicacoes.instructions.button') ?>
            </button>
            <button id="btnNovaIndicacao" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-user-plus mr-2"></i> <?= t('modules.multas.indicacoes.new_nomination') ?>
            </button>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white shadow rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-slate-700" id="resumoTotal">-</div>
            <div class="text-xs text-slate-500"><?= t('modules.multas.indicacoes.summary.total') ?></div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-amber-600" id="resumoEnviadas">-</div>
            <div class="text-xs text-slate-500"><?= t('modules.multas.indicacoes.summary.sent') ?></div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-blue-600" id="resumoPendentes">-</div>
            <div class="text-xs text-slate-500"><?= t('modules.multas.indicacoes.summary.pending') ?></div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-green-600" id="resumoAceitas">-</div>
            <div class="text-xs text-slate-500"><?= t('modules.multas.indicacoes.summary.accepted') ?></div>
        </div>
        <div class="bg-white shadow rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-red-500" id="resumoRejeitadas">-</div>
            <div class="text-xs text-slate-500"><?= t('modules.multas.indicacoes.summary.rejected') ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <select id="filtroTipo" class="form-input-focus text-sm w-44">
            <option value=""><?= t('modules.multas.indicacoes.filters.all_types') ?></option>
            <option value="real_infrator"><?= t('modules.multas.indicacoes.filters.real_offender') ?></option>
            <option value="principal_condutor"><?= t('modules.multas.indicacoes.filters.main_driver') ?></option>
        </select>
        <select id="filtroStatus" class="form-input-focus text-sm w-40">
            <option value=""><?= t('modules.multas.indicacoes.filters.all_status') ?></option>
            <option value="enviado"><?= t('modules.multas.indicacoes.filters.sent') ?></option>
            <option value="pendente"><?= t('modules.multas.indicacoes.filters.pending') ?></option>
            <option value="processando"><?= t('modules.multas.indicacoes.filters.processing') ?></option>
            <option value="aceito"><?= t('modules.multas.indicacoes.filters.accepted') ?></option>
            <option value="rejeitado"><?= t('modules.multas.indicacoes.filters.rejected') ?></option>
            <option value="cancelado"><?= t('modules.multas.indicacoes.filters.cancelled') ?></option>
            <option value="excluido"><?= t('modules.multas.indicacoes.filters.deleted') ?></option>
            <option value="expirado"><?= t('modules.multas.indicacoes.filters.expired') ?></option>
        </select>
        <input type="text" id="filtroPlaca" class="form-input-focus text-sm w-32" placeholder="<?= t('modules.multas.indicacoes.filters.plate') ?>">
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.multas.indicacoes.table.date') ?></th>
                    <th class="table-header"><?= t('modules.multas.indicacoes.table.type') ?></th>
                    <th class="table-header"><?= t('modules.multas.indicacoes.table.plate') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.multas.indicacoes.table.nominee') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.multas.indicacoes.table.ait') ?></th>
                    <th class="table-header text-center"><?= t('modules.multas.indicacoes.table.status') ?></th>
                    <th class="table-header text-center w-28"><?= t('modules.multas.indicacoes.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label class="text-sm text-slate-600 mr-2"><?= t('modules.multas.indicacoes.pagination.rows') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="30">30</option>
            </select>
        </div>
        <div class="text-sm text-slate-600"><span id="registrosInfo"></span></div>
        <nav><ul class="inline-flex items-center -space-x-px" id="paginationContainer"></ul></nav>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: '<?= t('common.labels.loading') ?>',
        noNominations: '<?= t('modules.multas.indicacoes.messages.no_nominations') ?>',
        paginationShowing: '<?= t('modules.multas.indicacoes.pagination.showing') ?>',
        badgeRealOffender: '<?= t('modules.multas.indicacoes.badges.real_offender') ?>',
        badgeMainDriver: '<?= t('modules.multas.indicacoes.badges.main_driver') ?>',
        actionCheckStatus: '<?= t('modules.multas.indicacoes.actions.check_status') ?>',
        actionCancel: '<?= t('modules.multas.indicacoes.actions.cancel') ?>',
        confirmCancelTitle: '<?= t('modules.multas.indicacoes.confirm.cancel_title') ?>',
        confirmCancelMessage: '<?= t('modules.multas.indicacoes.confirm.cancel_message') ?>',
    };

    let currentPage = 1;
    let perPage = 15;
    let filtroTipo = '';
    let filtroStatus = '';
    let filtroPlaca = '';
    let searchTimeout = null;

    const tbody = document.getElementById('tableBody');

    // =================================================================
    // RESUMO
    // =================================================================

    async function carregarResumo() {
        try {
            const result = await API.get('/api/multas-online/indicacoes/resumo');
            if (!result.success) return;
            document.getElementById('resumoTotal').textContent = result.data.total || 0;
            document.getElementById('resumoEnviadas').textContent = result.data.enviadas || 0;
            document.getElementById('resumoPendentes').textContent = result.data.pendentes || 0;
            document.getElementById('resumoAceitas').textContent = result.data.aceitas || 0;
            document.getElementById('resumoRejeitadas').textContent = result.data.rejeitadas || 0;
        } catch (e) { console.error(e); }
    }

    // =================================================================
    // TABELA
    // =================================================================

    async function carregarDados(page = 1) {
        try {
            tbody.innerHTML = '<tr><td colspan="7" class="table-cell text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.loading + '</td></tr>';
            const params = { page, perPage };
            if (filtroTipo) params.tipo = filtroTipo;
            if (filtroStatus) params.status = filtroStatus;
            if (filtroPlaca) params.placa = filtroPlaca;

            const result = await API.get('/api/multas-online/indicacoes', params);
            if (result.success) {
                renderTabela(result.data);
                atualizarPaginacao(result.pagination);
                const p = result.pagination;
                const start = p.total === 0 ? 0 : ((p.page-1)*p.perPage)+1;
                const end = Math.min(p.page*p.perPage, p.total);
                document.getElementById('registrosInfo').textContent = i18n.paginationShowing.replace(':start', start).replace(':end', end).replace(':total', p.total);
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="7" class="table-cell text-center text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>' + escapeHtml(e.message) + '</td></tr>';
        }
    }

    function getTipoBadge(tipo) {
        if (tipo === 'real_infrator') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700"><i class="fas fa-user-shield mr-1"></i>' + escapeHtml(i18n.badgeRealOffender) + '</span>';
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700"><i class="fas fa-id-card mr-1"></i>' + escapeHtml(i18n.badgeMainDriver) + '</span>';
    }

    function getStatusBadge(status) {
        const map = {
            'enviado': 'bg-amber-100 text-amber-700',
            'pendente': 'bg-blue-100 text-blue-700',
            'processando': 'bg-blue-100 text-blue-700',
            'aceito': 'bg-green-100 text-green-700',
            'rejeitado': 'bg-red-100 text-red-700',
            'cancelado': 'bg-slate-100 text-slate-600',
            'excluido': 'bg-slate-100 text-slate-600',
            'expirado': 'bg-red-50 text-red-500',
        };
        const cls = map[status] || 'bg-slate-100 text-slate-600';
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}">${escapeHtml(status)}</span>`;
    }

    function renderTabela(dados) {
        if (!dados || dados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="table-cell text-center text-slate-500"><i class="fas fa-inbox mr-2"></i>' + i18n.noNominations + '</td></tr>';
            return;
        }
        let rows = '';
        dados.forEach(item => {
            const dt = item.created_at ? DateHelper.formatDateTime(item.created_at) : '';
            rows += `
            <tr class="border-b border-slate-200 hover:bg-slate-50">
                <td class="table-cell text-sm">${dt}</td>
                <td class="table-cell">${getTipoBadge(item.tipo)}</td>
                <td class="table-cell"><span class="font-mono text-sm bg-slate-100 px-2 py-0.5 rounded">${escapeHtml(item.placa || '')}</span></td>
                <td class="table-cell hidden sm:table-cell text-sm">
                    ${escapeHtml(item.nome_indicado || item.cpf_indicado || '')}
                    ${item.cliente_nome ? '<div class="text-xs text-slate-400">' + escapeHtml(item.cliente_nome) + '</div>' : ''}
                </td>
                <td class="table-cell hidden md:table-cell text-xs font-mono text-slate-500">${escapeHtml(item.numero_ait || '-')}</td>
                <td class="table-cell text-center">${getStatusBadge(item.status_online)}</td>
                <td class="table-cell text-center w-28">
                    <button title="${escapeHtml(i18n.actionCheckStatus)}" class="btn-icon text-blue-600 hover:text-blue-800 btn-status" data-id="${item.id}"><i class="fas fa-sync-alt"></i></button>
                    ${['enviado','pendente','processando'].includes(item.status_online) ? `<button title="${escapeHtml(i18n.actionCancel)}" class="btn-icon text-red-600 hover:text-red-800 btn-cancelar" data-id="${item.id}"><i class="fas fa-times-circle"></i></button>` : ''}
                </td>
            </tr>`;
        });
        tbody.innerHTML = rows;

        // Event listeners
        tbody.querySelectorAll('.btn-status').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                try {
                    const result = await API.get(`/api/multas-online/indicacoes/${id}/status`);
                    if (result.success) {
                        window.parent.postMessage({ action: 'showToast', type: 'success', message: 'Status: ' + (result.data.status_local || 'atualizado') }, '*');
                        carregarDados(currentPage);
                        carregarResumo();
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                    }
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
                }
                this.innerHTML = '<i class="fas fa-sync-alt"></i>';
            });
        });

        tbody.querySelectorAll('.btn-cancelar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.confirmCancelTitle,
                    message: i18n.confirmCancelMessage
                }, '*');
                window._pendingCancelId = id;
            });
        });
    }

    function atualizarPaginacao(pagination) {
        const c = document.getElementById('paginationContainer');
        if (!c || !pagination) return;
        const { page, totalPages, hasPrev, hasNext } = pagination;
        let html = `<li><button class="pagination-button arrow-button rounded-l-md ${!hasPrev?'opacity-50 cursor-not-allowed':''}" ${!hasPrev?'disabled':''} onclick="irParaPagina(${page-1})"><i class="fas fa-chevron-left"></i></button></li>`;
        const max = 5; let s = Math.max(1,page-Math.floor(max/2)); let e = Math.min(totalPages||1,s+max-1);
        if(e-s<max-1) s=Math.max(1,e-max+1);
        for(let i=s;i<=e;i++) html += `<li><button class="pagination-button numbered ${i===page?'active':''}" onclick="irParaPagina(${i})">${i}</button></li>`;
        html += `<li><button class="pagination-button arrow-button rounded-r-md ${!hasNext?'opacity-50 cursor-not-allowed':''}" ${!hasNext?'disabled':''} onclick="irParaPagina(${page+1})"><i class="fas fa-chevron-right"></i></button></li>`;
        c.innerHTML = html;
    }

    window.irParaPagina = function(p) { currentPage=p; carregarDados(currentPage); };

    // =================================================================
    // MODAL (abre no parent via postMessage)
    // =================================================================

    function abrirModal() {
        window.parent.postMessage({ action: 'openIndicacaoModal' }, '*');
    }

    // =================================================================
    // EVENT LISTENERS
    // =================================================================

    document.getElementById('btnNovaIndicacao')?.addEventListener('click', abrirModal);
    document.getElementById('btnInstrucoes')?.addEventListener('click', function() {
        window.parent.postMessage({ action: 'openIndicacaoInstrucoes' }, '*');
    });

    document.getElementById('filtroTipo')?.addEventListener('change', function() { filtroTipo=this.value; currentPage=1; carregarDados(1); });
    document.getElementById('filtroStatus')?.addEventListener('change', function() { filtroStatus=this.value; currentPage=1; carregarDados(1); });
    document.getElementById('filtroPlaca')?.addEventListener('input', function() {
        if(searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { filtroPlaca=this.value.toUpperCase(); currentPage=1; carregarDados(1); }, 400);
    });
    document.getElementById('rowsPerPage')?.addEventListener('change', function() { perPage=parseInt(this.value); currentPage=1; carregarDados(1); });

    window.addEventListener('message', function(event) {
        // Resultado do modal de indicacao no parent
        if (event.data?.action === 'indicacaoResult' && event.data.success) {
            currentPage = 1;
            carregarDados(1);
            carregarResumo();
        }
        if (event.data?.action === 'genericConfirmed' && window._pendingCancelId) {
            (async function() {
                try {
                    const result = await API.post(`/multas-online/indicacoes/${window._pendingCancelId}/cancelar`);
                    if (result.success) {
                        window.parent.postMessage({ action: 'showToast', type: 'success', message: result.message }, '*');
                        carregarDados(currentPage);
                        carregarResumo();
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                    }
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: e.message }, '*');
                }
                window._pendingCancelId = null;
            })();
        }
    });

    function escapeHtml(t) { if(!t) return ''; const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

    // Init
    carregarResumo();
    carregarDados(1);
})();
</script>
@endsection
