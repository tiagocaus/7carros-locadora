@extends('layouts.iframe')

@section('title', '<?= t("modules.feature_requests.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.feature_requests.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.feature_requests.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovoPedido" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.feature_requests.new_request') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
        <div class="flex flex-wrap gap-4 items-center">
            <div>
                <label class="text-xs text-slate-500 block mb-1"><?= t('modules.feature_requests.filters.status') ?></label>
                <select id="filtroStatus" class="form-input-focus text-sm">
                    <option value=""><?= t('modules.feature_requests.filters.all') ?></option>
                    <option value="pendente"><?= t('modules.feature_requests.status.pending') ?></option>
                    <option value="em_analise"><?= t('modules.feature_requests.status.in_review') ?></option>
                    <option value="em_desenvolvimento"><?= t('modules.feature_requests.status.in_development') ?></option>
                    <option value="concluido"><?= t('modules.feature_requests.status.completed') ?></option>
                    <option value="recusado"><?= t('modules.feature_requests.status.rejected') ?></option>
                    <option value="aguardando_info"><?= t('modules.feature_requests.status.awaiting_info') ?></option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 block mb-1"><?= t('modules.feature_requests.filters.module') ?></label>
                <select id="filtroModulo" class="form-input-focus text-sm">
                    <option value=""><?= t('modules.feature_requests.filters.all') ?></option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 block mb-1"><?= t('modules.feature_requests.filters.sort') ?></label>
                <select id="filtroOrdenar" class="form-input-focus text-sm">
                    <option value="recentes"><?= t('modules.feature_requests.filters.sort_recent') ?></option>
                    <option value="votos"><?= t('modules.feature_requests.filters.sort_votes') ?></option>
                    <option value="antigos"><?= t('modules.feature_requests.filters.sort_oldest') ?></option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="filtroMeusPedidos" class="mr-2">
                    <span class="text-sm"><?= t('modules.feature_requests.filters.my_requests') ?></span>
                </label>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full table-fixed divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.feature_requests.table.title') ?></th>
                    <th class="table-header hidden sm:table-cell w-48"><?= t('modules.feature_requests.table.module') ?></th>
                    <th class="table-header w-32"><?= t('modules.feature_requests.table.status') ?></th>
                    <th class="table-header hidden md:table-cell w-24 text-center"><?= t('modules.feature_requests.table.votes') ?></th>
                    <th class="table-header px-2 w-40 text-center"><?= t('modules.feature_requests.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="pedidosTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.feature_requests.pagination.rows_per_page') ?></label>
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
            </ul>
        </nav>
    </div>

    <!-- Legenda -->
    <div class="mt-4 text-xs text-slate-500">
        <span class="mr-4"><i class="fas fa-thumbs-up text-blue-600 mr-1"></i> <?= t('modules.feature_requests.info.vote_priority') ?></span> <br>
        <span><i class="fas fa-bell text-green-600 mr-1"></i> <?= t('modules.feature_requests.info.follow_updates') ?></span>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.feature_requests.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.feature_requests.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.feature_requests.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noTitle: <?= json_encode(t('modules.feature_requests.messages.no_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        otherModule: <?= json_encode(t('modules.feature_requests.messages.other_module'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        removeVote: <?= json_encode(t('modules.feature_requests.actions.remove_vote'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        vote: <?= json_encode(t('modules.feature_requests.actions.vote'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        follow: <?= json_encode(t('modules.feature_requests.actions.follow'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        unfollow: <?= json_encode(t('modules.feature_requests.actions.unfollow'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        viewDetails: <?= json_encode(t('modules.feature_requests.actions.view_details'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        voteError: <?= json_encode(t('modules.feature_requests.messages.vote_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        processError: <?= json_encode(t('modules.feature_requests.messages.process_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.feature_requests.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let filtros = {
        status: '',
        modulo_id: '',
        ordenar: 'recentes',
        meus_pedidos: false
    };

    const tbody = document.getElementById('pedidosTableBody');

    // ===== NAVEGACAO =====
    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAMENTO DE DADOS =====
    async function carregarModulos() {
        try {
            const result = await API.get('/api/feature-requests/modulos');
            if (result.success) {
                const select = document.getElementById('filtroModulo');
                result.data.forEach(m => {
                    const option = document.createElement('option');
                    option.value = m.id;
                    option.textContent = m.nome;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar modulos:', error);
        }
    }

    async function carregarPedidos(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search,
                ...filtros
            };

            const result = await API.get('/api/feature-requests', params);

            if (result.success) {
                renderPedidos(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar pedidos:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderPedidos(pedidos) {
        if (!pedidos || pedidos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        pedidos.forEach(p => {
            const titulo = escapeHtml(p.titulo || i18n.noTitle);
            const modulo = escapeHtml(p.modulo_nome || i18n.otherModule);
            const moduloIcone = p.modulo_icone || 'fas fa-ellipsis-h';
            const statusLabel = p.status_label || p.status;
            const statusCor = p.status_cor || 'bg-gray-100 text-gray-800';
            const votos = p.total_votos || 0;
            const data = formatarData(p.created_at);
            const votei = p.votei;
            const sigo = p.sigo;

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50 cursor-pointer" data-id="${p.id}">
                    <td class="table-cell overflow-hidden">
                        <div class="font-medium truncate" title="${titulo}">${titulo}</div>
                        <div class="sm:hidden text-xs text-slate-500 mt-1">
                            ${modulo}
                        </div>
                    </td>
                    <td class="table-cell hidden sm:table-cell">
                        <span class="inline-flex items-center text-sm">
                            ${modulo}
                        </span>
                    </td>
                    <td class="table-cell">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusCor}">
                            ${statusLabel}
                        </span>
                    </td>
                    <td class="table-cell hidden md:table-cell text-center">
                        <span class="inline-flex items-center ${votei ? 'text-blue-600 font-medium' : 'text-slate-600'}">
                            <i class="fas fa-thumbs-up mr-1"></i>${votos}
                        </span>
                    </td>
                    <td class="table-cell px-2 text-right" onclick="event.stopPropagation()">
                        <button title="${votei ? i18n.removeVote : i18n.vote}"
                                class="btn-icon ${votei ? 'text-blue-600' : 'text-slate-400 hover:text-blue-600'} btn-votar"
                                data-id="${p.id}" data-votei="${votei ? '1' : '0'}">
                            <i class="fas fa-thumbs-up"></i>
                        </button>
                        <button title="${sigo ? i18n.unfollow : i18n.follow}"
                                class="btn-icon ${sigo ? 'text-green-600' : 'text-slate-400 hover:text-green-600'} btn-seguir"
                                data-id="${p.id}" data-sigo="${sigo ? '1' : '0'}">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button title="${i18n.viewDetails}" class="btn-icon text-slate-600 hover:text-slate-800 btn-detalhes" data-id="${p.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners
        tbody.querySelectorAll('tr[data-id]').forEach(row => {
            row.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/feature-requests/detalhes?id=' + id);
            });
        });

        tbody.querySelectorAll('.btn-votar').forEach(button => {
            button.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const votei = this.getAttribute('data-votei') === '1';
                await toggleVoto(id, votei);
            });
        });

        tbody.querySelectorAll('.btn-seguir').forEach(button => {
            button.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const sigo = this.getAttribute('data-sigo') === '1';
                await toggleSeguir(id, sigo);
            });
        });

        tbody.querySelectorAll('.btn-detalhes').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/feature-requests/detalhes?id=' + id);
            });
        });
    }

    // ===== VOTAR / SEGUIR =====
    async function toggleVoto(id, jaVotou) {
        try {
            const endpoint = jaVotou
                ? `/feature-requests/${id}/remover-voto`
                : `/feature-requests/${id}/votar`;

            const result = await API.post(endpoint);

            if (result.success) {
                carregarPedidos(currentPage, perPage, searchTerm);
            } else {
                showToast(result.message || i18n.voteError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.voteError, 'error');
        }
    }

    async function toggleSeguir(id, jaSigo) {
        try {
            const endpoint = jaSigo
                ? `/feature-requests/${id}/deixar-de-seguir`
                : `/feature-requests/${id}/seguir`;

            const result = await API.post(endpoint);

            if (result.success) {
                showToast(result.message, 'success');
                carregarPedidos(currentPage, perPage, searchTerm);
            } else {
                showToast(result.message || i18n.processError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.processError, 'error');
        }
    }

    // ===== FORMATACAO =====
    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR');
    }

    // ===== PAGINACAO =====
    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement || !pagination) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
    }

    function atualizarPaginacao(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container || !pagination) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        let buttons = '';

        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages || 1, startPage + maxButtons - 1);

        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `
                <li>
                    <button class="pagination-button numbered ${i === page ? 'active' : ''}"
                            onclick="irParaPagina(${i})">
                        ${i}
                    </button>
                </li>
            `;
        }

        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasNext ? 'disabled' : ''}
                        onclick="irParaPagina(${page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

        container.innerHTML = buttons;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarPedidos(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====
    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarPedidos(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarPedidos(currentPage, perPage, searchTerm);
        }, 300);
    });

    document.getElementById('filtroStatus')?.addEventListener('change', function (e) {
        filtros.status = e.target.value;
        currentPage = 1;
        carregarPedidos(currentPage, perPage, searchTerm);
    });

    document.getElementById('filtroModulo')?.addEventListener('change', function (e) {
        filtros.modulo_id = e.target.value;
        currentPage = 1;
        carregarPedidos(currentPage, perPage, searchTerm);
    });

    document.getElementById('filtroOrdenar')?.addEventListener('change', function (e) {
        filtros.ordenar = e.target.value;
        currentPage = 1;
        carregarPedidos(currentPage, perPage, searchTerm);
    });

    document.getElementById('filtroMeusPedidos')?.addEventListener('change', function (e) {
        filtros.meus_pedidos = e.target.checked;
        currentPage = 1;
        carregarPedidos(currentPage, perPage, searchTerm);
    });

    document.getElementById('btnNovoPedido')?.addEventListener('click', function () {
        navegarPara('/pages/feature-requests/adicionar');
    });

    // ===== HELPERS =====
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        if (type === 'error') {
            window.parent.postMessage({ action: 'openAlert', message: message }, '*');
        } else {
            window.parent.postMessage({ action: 'showToast', message: message }, '*');
        }
    }

    // ===== INICIALIZACAO =====
    carregarModulos();
    carregarPedidos(currentPage, perPage, searchTerm);
})();
</script>
@endsection
