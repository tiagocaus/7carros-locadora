@extends('layouts.iframe')

@section('title', t('modules.nfse.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.nfse.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.nfse.filters.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
        </div>
    </div>

    <!-- Cards estatisticas -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 my-4" id="statsCards">
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-blue-500 cursor-pointer hover:bg-blue-50 transition" data-status="">
            <div class="text-xs text-slate-500"><?= t('modules.nfse.stats.total') ?></div>
            <div class="text-xl font-bold text-blue-600" id="statTotal">0</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-green-500 cursor-pointer hover:bg-green-50 transition" data-status="autorizada">
            <div class="text-xs text-slate-500"><?= t('modules.nfse.stats.autorizadas') ?></div>
            <div class="text-xl font-bold text-green-600" id="statAutorizadas">0</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-yellow-500 cursor-pointer hover:bg-yellow-50 transition" data-status="pendente">
            <div class="text-xs text-slate-500"><?= t('modules.nfse.stats.pendentes') ?></div>
            <div class="text-xl font-bold text-yellow-600" id="statPendentes">0</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-red-500 cursor-pointer hover:bg-red-50 transition" data-status="rejeitada">
            <div class="text-xs text-slate-500"><?= t('modules.nfse.stats.rejeitadas') ?></div>
            <div class="text-xl font-bold text-red-600" id="statRejeitadas">0</div>
        </div>
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-slate-400 cursor-pointer hover:bg-slate-50 transition" data-status="cancelada">
            <div class="text-xs text-slate-500"><?= t('modules.nfse.stats.canceladas') ?></div>
            <div class="text-xl font-bold text-slate-500" id="statCanceladas">0</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.nfse.fields.filial') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.nfse.filters.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[140px] max-w-[180px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('common.labels.status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.nfse.filters.all_status') ?></option>
                <option value="pendente"><?= t('modules.nfse.status.pendente') ?></option>
                <option value="processando"><?= t('modules.nfse.status.processando') ?></option>
                <option value="autorizada"><?= t('modules.nfse.status.autorizada') ?></option>
                <option value="rejeitada"><?= t('modules.nfse.status.rejeitada') ?></option>
                <option value="cancelada"><?= t('modules.nfse.status.cancelada') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[130px] max-w-[160px]">
            <label for="filterDataInicio" class="block text-xs text-slate-500 mb-1"><?= t('modules.nfse.filters.date_from') ?></label>
            <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex-1 min-w-[130px] max-w-[160px]">
            <label for="filterDataFim" class="block text-xs text-slate-500 mb-1"><?= t('modules.nfse.filters.date_to') ?></label>
            <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
        </div>
        <div class="flex items-end">
            <button id="btnLimparFiltros" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('modules.nfse.filters.clear_title') ?>">
                <i class="fas fa-times mr-1"></i><?= t('common.buttons.clear') ?>
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header w-20 text-center"><?= t('modules.nfse.table.numero') ?></th>
                    <th class="table-header"><?= t('modules.nfse.table.tomador') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.nfse.table.data') ?></th>
                    <th class="table-header hidden sm:table-cell text-right"><?= t('modules.nfse.table.valor') ?></th>
                    <th class="table-header hidden lg:table-cell text-center"><?= t('modules.nfse.table.tipo') ?></th>
                    <th class="table-header w-32 text-center"><?= t('modules.nfse.table.status') ?></th>
                    <th class="table-header px-2 w-44 text-center"><?= t('modules.nfse.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="nfseTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.nfse.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"></span>
        </div>
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px" id="paginationContainer">
                <li><button class="pagination-button arrow-button rounded-l-md" id="btnPrevPage" disabled><i class="fas fa-chevron-left"></i></button></li>
                <li><button class="pagination-button numbered active">1</button></li>
                <li><button class="pagination-button arrow-button rounded-r-md" id="btnNextPage" disabled><i class="fas fa-chevron-right"></i></button></li>
            </ul>
        </nav>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        loading: '<?= t("common.labels.loading") ?>',
        noRecords: '<?= t("modules.nfse.messages.no_records") ?>',
        loadError: '<?= t("modules.nfse.messages.load_error") ?>',
        paginationShowing: '<?= t("modules.nfse.pagination.showing") ?>',
    };

    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let filterStatus = '';
    let filterFilial = '';
    let filterDataInicio = '';
    let filterDataFim = '';
    let isFirstLoad = true;

    // Init
    window.pageLoading.start();
    carregarFiliais();
    carregarEstatisticas();
    carregarDados();
    setupEventListeners();

    function setupEventListeners() {
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value.trim();
                currentPage = 1;
                carregarDados();
            }, 400);
        });

        document.getElementById('filterFilial').addEventListener('change', (e) => {
            filterFilial = e.target.value;
            currentPage = 1;
            carregarDados();
            carregarEstatisticas();
        });

        document.getElementById('filterStatus').addEventListener('change', (e) => {
            filterStatus = e.target.value;
            currentPage = 1;
            carregarDados();
        });

        document.getElementById('filterDataInicio').addEventListener('change', (e) => {
            filterDataInicio = e.target.value;
            currentPage = 1;
            carregarDados();
        });

        document.getElementById('filterDataFim').addEventListener('change', (e) => {
            filterDataFim = e.target.value;
            currentPage = 1;
            carregarDados();
        });

        document.getElementById('rowsPerPage').addEventListener('change', (e) => {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarDados();
        });

        document.getElementById('btnLimparFiltros').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterFilial').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDataInicio').value = '';
            document.getElementById('filterDataFim').value = '';
            searchTerm = '';
            filterFilial = '';
            filterStatus = '';
            filterDataInicio = '';
            filterDataFim = '';
            currentPage = 1;
            carregarDados();
            carregarEstatisticas();
        });



        // Cards de estatistica como filtro
        document.querySelectorAll('#statsCards [data-status]').forEach(card => {
            card.addEventListener('click', () => {
                const status = card.dataset.status;
                filterStatus = status;
                document.getElementById('filterStatus').value = status;
                currentPage = 1;
                carregarDados();
            });
        });

        document.getElementById('btnPrevPage').addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; carregarDados(); }
        });
        document.getElementById('btnNextPage').addEventListener('click', () => {
            currentPage++;
            carregarDados();
        });
    }

    async function carregarFiliais() {
        try {
            const result = await API.get('/api/matrizes-filiais/buscar');
            if (result.success && result.data) {
                const select = document.getElementById('filterFilial');
                result.data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = f.nome || f.razao_social;
                    select.appendChild(opt);
                });
            }
        } catch (e) {}
    }

    async function carregarEstatisticas() {
        try {
            const result = await API.get('/api/nfse/estatisticas');
            if (result.success && result.data) {
                const d = result.data;
                document.getElementById('statTotal').textContent = d.total || 0;
                document.getElementById('statAutorizadas').textContent = d.autorizada || 0;
                document.getElementById('statPendentes').textContent = (parseInt(d.pendente || 0) + parseInt(d.processando || 0));
                document.getElementById('statRejeitadas').textContent = d.rejeitada || 0;
                document.getElementById('statCanceladas').textContent = d.cancelada || 0;
            }
        } catch (e) {}
    }

    async function carregarDados() {
        const tbody = document.getElementById('nfseTableBody');

        try {
            const params = { page: currentPage, perPage: perPage };
            if (searchTerm) params.search = searchTerm;
            if (filterFilial) params.filial = filterFilial;
            if (filterStatus) params.status = filterStatus;
            if (filterDataInicio) params.data_inicio = filterDataInicio;
            if (filterDataFim) params.data_fim = filterDataFim;

            const result = await API.get('/api/nfse', params);

            if (!result.success) {
                tbody.innerHTML = `<tr><td colspan="7" class="table-cell text-center text-slate-500 py-8">${i18n.loadError}</td></tr>`;
                return;
            }

            const dados = result.data || [];
            const pagination = result.pagination || {};

            if (dados.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="table-cell text-center text-slate-500 py-8"><i class="fas fa-file-invoice text-slate-300 text-3xl mb-2 block"></i>${i18n.noRecords}</td></tr>`;
            } else {
                let html = '';
                dados.forEach(n => {
                    html += renderRow(n);
                });
                tbody.innerHTML = html;
            }

            atualizarPaginacao(pagination);
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" class="table-cell text-center text-red-500 py-8">${i18n.loadError}</td></tr>`;
        } finally {
            if (isFirstLoad) {
                isFirstLoad = false;
                window.pageLoading.done();
            }
        }
    }

    function renderRow(n) {
        const numero = n.numero || '-';
        const tomador = escapeHtml(n.tomador_nome || '-');
        const cpfCnpj = n.tomador_cpf_cnpj || '';
        const dataEmissao = n.created_at ? n.created_at.substring(0, 10).split('-').reverse().join('/') : '-';
        const valor = formatarMoeda(parseFloat(n.valor_servicos || 0));
        const tiposEmissao = {
            nacional: 'Nacional',
            betha: 'Betha'
        };
        const tipo = tiposEmissao[n.tipo_emissao] || n.tipo_emissao || '-';
        const statusBadge = getStatusBadge(n.status);

        const acoes = getAcoes(n);

        return `<tr class="hover:bg-slate-50">
            <td class="table-cell text-center font-medium">${numero}</td>
            <td class="table-cell">
                <div class="text-sm font-medium text-slate-900">${tomador}</div>
                <div class="text-xs text-slate-400">${cpfCnpj}</div>
            </td>
            <td class="table-cell hidden md:table-cell text-center text-sm">${dataEmissao}</td>
            <td class="table-cell hidden sm:table-cell text-right text-sm font-medium">${valor}</td>
            <td class="table-cell hidden lg:table-cell text-center">
                <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600">${tipo}</span>
            </td>
            <td class="table-cell text-center">${statusBadge}</td>
            <td class="table-cell text-right">
                <div class="flex justify-end gap-1">${acoes}</div>
            </td>
        </tr>`;
    }

    function getStatusBadge(status) {
        const map = {
            pendente: { bg: 'bg-yellow-100', text: 'text-yellow-700', icon: 'fa-clock', label: '<?= t('modules.nfse.status.pendente') ?>' },
            processando: { bg: 'bg-blue-100', text: 'text-blue-700', icon: 'fa-spinner fa-spin', label: '<?= t('modules.nfse.status.processando') ?>' },
            autorizada: { bg: 'bg-green-100', text: 'text-green-700', icon: 'fa-check-circle', label: '<?= t('modules.nfse.status.autorizada') ?>' },
            rejeitada: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle', label: '<?= t('modules.nfse.status.rejeitada') ?>' },
            cancelada: { bg: 'bg-slate-100', text: 'text-slate-500', icon: 'fa-ban', label: '<?= t('modules.nfse.status.cancelada') ?>' },
        };
        const s = map[status] || map.pendente;
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${s.bg} ${s.text}">
            <i class="fas ${s.icon} mr-1"></i>${s.label}
        </span>`;
    }

    function getAcoes(n) {
        let html = '';

        // Visualizar (sempre)
        html += `<button onclick="navegarPara('/pages/nfse/${n.id}/visualizar')" title="<?= t('modules.nfse.view_title') ?>" class="text-blue-600 hover:text-blue-800 p-1"><i class="fas fa-eye"></i></button>`;

        // PDF (autorizada ou cancelada)
        if (n.status === 'autorizada' || n.status === 'cancelada') {
            html += `<a href="/nfse/${n.id}/pdf" target="_blank" title="<?= t('modules.nfse.buttons.download_pdf') ?>" class="text-purple-600 hover:text-purple-800 p-1"><i class="fas fa-file-pdf"></i></a>`;
        }

        // Email (autorizada)
        if (n.status === 'autorizada') {
            html += `<button onclick="enviarEmail(${n.id})" title="<?= t('modules.nfse.buttons.send_email') ?>" class="text-green-600 hover:text-green-800 p-1"><i class="fas fa-envelope"></i></button>`;
        }

        // Reenviar (rejeitada)
        if (n.status === 'rejeitada') {
            html += `<button onclick="reenviarNfse(${n.id})" title="<?= t('modules.nfse.buttons.resend') ?>" class="text-orange-600 hover:text-orange-800 p-1"><i class="fas fa-redo"></i></button>`;
        }

        // Cancelar (autorizada)
        if (n.status === 'autorizada') {
            html += `<button onclick="navegarPara('/pages/nfse/${n.id}/cancelar')" title="<?= t('modules.nfse.buttons.cancel_nfse') ?>" class="text-red-600 hover:text-red-800 p-1"><i class="fas fa-ban"></i></button>`;
        }

        return html;
    }

    function atualizarPaginacao(pagination) {
        const { page, perPage: pp, total, totalPages, hasNext, hasPrev } = pagination;

        // Info
        const from = total > 0 ? ((page - 1) * pp) + 1 : 0;
        const to = Math.min(page * pp, total);
        document.getElementById('registrosInfo').textContent =
            i18n.paginationShowing.replace('{from}', from).replace('{to}', to).replace('{total}', total);

        // Botoes
        document.getElementById('btnPrevPage').disabled = !hasPrev;
        document.getElementById('btnNextPage').disabled = !hasNext;

        // Numeros de pagina
        const container = document.getElementById('paginationContainer');
        const numbered = container.querySelectorAll('.numbered');
        numbered.forEach(el => el.parentElement.remove());

        const btnNext = document.getElementById('btnNextPage').parentElement;
        const maxPages = Math.min(totalPages, 5);
        let startPage = Math.max(1, page - 2);
        if (startPage + maxPages - 1 > totalPages) startPage = Math.max(1, totalPages - maxPages + 1);

        for (let i = startPage; i < startPage + maxPages; i++) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.className = 'pagination-button numbered' + (i === page ? ' active' : '');
            btn.textContent = i;
            btn.addEventListener('click', () => { currentPage = i; carregarDados(); });
            li.appendChild(btn);
            container.insertBefore(li, btnNext);
        }
    }

    // Funcoes globais para onclick
    window.navegarPara = function(page) {
        window.parent.postMessage({ action: 'navigate', page: page }, '*');
    };

    window.enviarEmail = async function(id) {
        try {
            const result = await API.post(`/nfse/${id}/email`, {});
            const msg = result.success ? '<?= t('modules.nfse.messages.email_success') ?>' : (result.message || '<?= t('modules.nfse.messages.email_error') ?>');
            window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.email_error') ?>' }, '*');
        }
    };

    window.reenviarNfse = async function(id) {
        try {
            const result = await API.post(`/nfse/${id}/reenviar`, {});
            const msg = result.success ? '<?= t('modules.nfse.messages.resend_success') ?>' : (result.message || '<?= t('modules.nfse.messages.resend_error') ?>');
            window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
            if (result.success) { carregarDados(); carregarEstatisticas(); }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.resend_error') ?>' }, '*');
        }
    };
})();
</script>
@endsection
