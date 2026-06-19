@extends('layouts.iframe')

@section('title', '<?= t("modules.estoque.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.estoque.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filtroFilial" class="form-input-focus w-40">
                <option value=""><?= t('modules.estoque.filters.all_branches') ?></option>
            </select>
            <select id="filtroStatus" class="form-input-focus w-40">
                <option value=""><?= t('modules.estoque.filters.all_status') ?></option>
                <option value="A"><?= t('modules.estoque.status.active') ?></option>
                <option value="I"><?= t('modules.estoque.status.inactive') ?></option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.estoque.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.estoque.table.code') ?></th>
                    <th class="table-header"><?= t('modules.estoque.table.product') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.estoque.table.brand_model') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.estoque.table.unit') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.estoque.table.stock') ?></th>
                    <th class="table-header hidden lg:table-cell text-right"><?= t('modules.estoque.table.purchase_value') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.estoque.table.branch') ?></th>
                    <th class="table-header hidden sm:table-cell text-center"><?= t('modules.estoque.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.estoque.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.estoque.pagination.rows_per_page') ?></label>
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
    const i18n = {
        loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.estoque.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.estoque.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.estoque.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noName: <?= json_encode(t('modules.estoque.messages.no_name'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('common.buttons.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionDelete: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionReactivate: <?= json_encode(t('modules.estoque.messages.reactivated'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        thisRecord: <?= json_encode(t('modules.estoque.messages.this_record'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        recordType: <?= json_encode(t('modules.estoque.record_type'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.estoque.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        reactivateError: <?= json_encode(t('modules.estoque.messages.reactivate_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.estoque.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusActive: <?= json_encode(t('modules.estoque.status.active'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusInactive: <?= json_encode(t('modules.estoque.status.inactive'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let filialFiltro = '';
    let statusFiltro = '';
    let searchTimeout = null;

    // Elementos
    const tbody = document.getElementById('tableBody');

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: page
            }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAMENTO DE FILIAIS =====

    async function carregarFiliais() {
        try {
            const result = await API.get('/api/matrizes-filiais/buscar');
            if (result.success && result.data) {
                const select = document.getElementById('filtroFilial');
                result.data.forEach(filial => {
                    const option = document.createElement('option');
                    option.value = filial.id;
                    option.textContent = filial.nome;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar filiais:', error);
        }
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarDados(page = 1, recordsPerPage = 10, search = '', filial = '', status = '') {
        try {
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search
            };
            if (filial) {
                params.filial = filial;
            }
            if (status) {
                params.status = status;
            }

            const result = await API.get('/api/estoque', params);

            if (result.success) {
                renderDados(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderDados(items) {
        if (!items || items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        items.forEach(item => {
            const codigo = item.produto_codigo || '-';
            const nome = item.produto_nome || i18n.noName;
            const marca = item.produto_marca || '-';
            const modelo = item.produto_modelo || '-';
            const estoqueAtual = parseInt(item.produto_estoque_atual) || 0;
            const estoqueMinimo = parseInt(item.produto_estoque_minimo) || 0;
            const valorCompra = formatarMoeda(item.valor_compra);
            const filial = item.filial_nome || '-';
            const nomeEscapado = escapeHtml(nome);

            // Badge estoque
            let estoqueBadge;
            if (estoqueAtual <= 0) {
                estoqueBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${estoqueAtual}</span>`;
            } else if (estoqueMinimo > 0 && estoqueAtual <= estoqueMinimo) {
                estoqueBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${estoqueAtual}</span>`;
            } else {
                estoqueBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${estoqueAtual}</span>`;
            }

            const itemStatus = item.status || 'A';
            const statusBadge = itemStatus === 'A'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.statusActive}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${i18n.statusInactive}</span>`;

            let actionButtons = `<button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>`;
            if (itemStatus === 'I') {
                actionButtons += `<button title="Reativar" class="btn-icon text-green-600 hover:text-green-800 btn-reactivate" data-id="${item.id}" data-name="${nomeEscapado}"><i class="fas fa-redo"></i></button>`;
            } else {
                actionButtons += `<button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>`;
            }

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50${itemStatus === 'I' ? ' opacity-60' : ''}">
                    <td class="table-cell font-mono text-sm">${escapeHtml(codigo)}</td>
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                    </td>
                    <td class="table-cell hidden sm:table-cell text-slate-600">
                        <div>${escapeHtml(marca)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(modelo)}</div>
                    </td>
                    <td class="table-cell hidden sm:table-cell text-slate-600">${escapeHtml(item.produto_unidade || '-')}</td>
                    <td class="table-cell hidden md:table-cell text-center">${estoqueBadge}</td>
                    <td class="table-cell hidden lg:table-cell text-right text-slate-600">${valorCompra}</td>
                    <td class="table-cell hidden lg:table-cell text-slate-600">${escapeHtml(filial)}</td>
                    <td class="table-cell hidden sm:table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        ${actionButtons}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/estoque/adicionar?id=' + id);
            });
        });

        // Event listeners para botoes de excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || i18n.thisRecord;

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: i18n.recordType,
                        confirmType: 'none'
                    }, '*');
                } else {
                    excluirRegistro(id);
                }
            });
        });

        // Event listeners para botoes de reativar
        tbody.querySelectorAll('.btn-reactivate').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                reativarRegistro(id);
            });
        });
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
        const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
        if (!paginationNav || !pagination) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        let buttons = '';

        // Botao anterior
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Botoes de paginas
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

        // Botao proximo
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasNext ? 'disabled' : ''}
                        onclick="irParaPagina(${page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

        paginationNav.innerHTML = buttons;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
        }, 300);
    });

    document.getElementById('filtroFilial')?.addEventListener('change', function (e) {
        filialFiltro = e.target.value;
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
    });

    document.getElementById('filtroStatus')?.addEventListener('change', function (e) {
        statusFiltro = e.target.value;
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
    });

    // Botao Novo - Navega para pagina de adicionar
    document.getElementById('btnNovo')?.addEventListener('click', function () {
        navegarPara('/pages/estoque/adicionar');
    });

    // ===== EXCLUSAO / REATIVACAO =====

    async function excluirRegistro(id) {
        try {
            const result = await API.post(`/estoque/${id}/excluir`);

            if (result.success) {
                if (result.message) {
                    window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                }
                carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.deleteError }, '*');
        }
    }

    async function reativarRegistro(id) {
        try {
            const result = await API.post(`/estoque/${id}/reativar`);

            if (result.success) {
                if (result.message) {
                    window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                }
                carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.reactivateError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.reactivateError }, '*');
        }
    }

    // ===== LISTENER DE MENSAGENS =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmacao de exclusao do parent
        if (event.data.action === 'confirmDelete') {
            excluirRegistro(event.data.recordId);
        }
    });

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatarMoeda(valor) {
        if (valor === null || valor === undefined) return 'R$ 0,00';
        const numero = parseFloat(valor);
        if (isNaN(numero)) return 'R$ 0,00';
        return 'R$ ' + numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Inicializacao
    carregarFiliais();
    carregarDados(currentPage, perPage, searchTerm, filialFiltro, statusFiltro);
})();
</script>
@endsection
