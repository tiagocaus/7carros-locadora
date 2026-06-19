@extends('layouts.iframe')

@section('title', '<?= t("modules.fornecedores.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.fornecedores.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filtroTipo" class="form-input-focus w-32">
                <option value="todos"><?= t('modules.fornecedores.filters.all') ?></option>
                <option value="fornecedor"><?= t('modules.fornecedores.filters.suppliers') ?></option>
                <option value="investidor"><?= t('modules.fornecedores.filters.investors') ?></option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.fornecedores.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
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
                    <th class="table-header"><?= t('modules.fornecedores.table.name') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.fornecedores.table.cpf_cnpj') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.fornecedores.table.phone') ?></th>
                    <th class="table-header hidden lg:table-cell text-center"><?= t('modules.fornecedores.table.investor') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.fornecedores.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.fornecedores.pagination.rows_per_page') ?></label>
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
        loadError: <?= json_encode(t('modules.fornecedores.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.fornecedores.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.fornecedores.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noName: <?= json_encode(t('modules.fornecedores.messages.no_name'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        investorYes: <?= json_encode(t('modules.fornecedores.badges.investor_yes'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        investorNo: <?= json_encode(t('modules.fornecedores.badges.investor_no'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('common.buttons.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionDelete: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        thisRecord: <?= json_encode(t('modules.fornecedores.messages.this_record'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        recordType: <?= json_encode(t('modules.fornecedores.record_type'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.fornecedores.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.fornecedores.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let tipoFiltro = 'todos';
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

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarDados(page = 1, recordsPerPage = 10, search = '', tipo = 'todos') {
        try {
            mostrarLoading();

            const result = await API.get('/api/fornecedores', {
                page: page,
                perPage: recordsPerPage,
                search: search,
                tipo: tipo
            });

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

    function renderDados(items) {
        if (!items || items.length === 0) {
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
        items.forEach(item => {
            const nome = item.nome_rsocial || i18n.noName;
            const nomeFantasia = item.nome_fantasia || '';
            const cpfCnpj = item.cpf_cnpj || '-';
            const telefone = item.tel1 || '-';
            const isInvestidor = item.investidor == 1;
            const nomeEscapado = escapeHtml(nome);
            const nomeFantasiaEscapado = escapeHtml(nomeFantasia);

            // Badge investidor
            const investidorBadge = isInvestidor
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-chart-line mr-1"></i>' + i18n.investorYes + '</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">' + i18n.investorNo + '</span>';

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                        ${nomeFantasiaEscapado ? `<div class="text-xs text-slate-500">${nomeFantasiaEscapado}</div>` : ''}
                    </td>
                    <td class="table-cell hidden sm:table-cell text-slate-600">${cpfCnpj}</td>
                    <td class="table-cell hidden md:table-cell text-slate-600">${telefone}</td>
                    <td class="table-cell hidden lg:table-cell text-center">${investidorBadge}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/fornecedores/adicionar?id=' + id);
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
        carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
        }, 300);
    });

    document.getElementById('filtroTipo')?.addEventListener('change', function (e) {
        tipoFiltro = e.target.value;
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
    });

    // Botao Novo - Navega para pagina de adicionar
    document.getElementById('btnNovo')?.addEventListener('click', function () {
        navegarPara('/pages/fornecedores/adicionar');
    });

    // ===== EXCLUSAO =====

    async function excluirRegistro(id) {
        try {
            const result = await API.post(`/fornecedores/${id}/excluir`);

            if (result.success) {
                carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.deleteError }, '*');
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

    // Inicializacao
    carregarDados(currentPage, perPage, searchTerm, tipoFiltro);
})();
</script>
@endsection
