@extends('layouts.iframe')

@section('title', '<?= t("modules.contas_bancarias.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.contas_bancarias.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.contas_bancarias.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovaConta" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.contas_bancarias.table.name') ?></th>
                    <th class="table-header hidden sm:table-cell text-center"><?= t('modules.contas_bancarias.table.type') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.contas_bancarias.table.bank') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.contas_bancarias.table.branch') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.contas_bancarias.table.account') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.contas_bancarias.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.contas_bancarias.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="contasTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.contas_bancarias.pagination.rows_per_page') ?></label>
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
        loading: '<?= addslashes(t('common.labels.loading')) ?>',
        loadError: '<?= addslashes(t('modules.contas_bancarias.messages.load_error')) ?>',
        serverError: '<?= addslashes(t('modules.contas_bancarias.messages.server_error')) ?>',
        noRecords: '<?= addslashes(t('modules.contas_bancarias.messages.no_records')) ?>',
        noName: '<?= addslashes(t('modules.contas_bancarias.messages.no_name')) ?>',
        typeBank: '<?= addslashes(t('modules.contas_bancarias.badges.type_bank')) ?>',
        typeCash: '<?= addslashes(t('modules.contas_bancarias.badges.type_cash')) ?>',
        statusActive: '<?= addslashes(t('modules.contas_bancarias.badges.status_active')) ?>',
        statusInactive: '<?= addslashes(t('modules.contas_bancarias.badges.status_inactive')) ?>',
        actionEdit: '<?= addslashes(t('common.buttons.edit')) ?>',
        actionDelete: '<?= addslashes(t('common.buttons.delete')) ?>',
        thisRecord: '<?= addslashes(t('modules.contas_bancarias.messages.this_record')) ?>',
        recordType: '<?= addslashes(t('modules.contas_bancarias.record_type')) ?>',
        deleteError: '<?= addslashes(t('modules.contas_bancarias.messages.delete_error')) ?>',
        showingPagination: '<?= addslashes(t('modules.contas_bancarias.pagination.showing')) ?>',
    };

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;

    // Elementos
    const tbody = document.getElementById('contasTableBody');

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

    async function carregarContas(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const result = await API.get('/api/contas-bancarias', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderContas(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar contas:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderContas(contas) {
        if (!contas || contas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        contas.forEach(c => {
            const nome = c.nome || i18n.noName;
            const nomeEscapado = escapeHtml(nome);
            const banco = c.banco || '-';
            const agencia = c.agencia || '-';
            const conta = c.conta || '-';

            // Tipo
            const tipoBadge = c.e_conta_bancaria === 'S'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-university mr-1"></i>${i18n.typeBank}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-cash-register mr-1"></i>${i18n.typeCash}</span>`;

            // Status
            const statusBadge = c.status === 'A'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.statusActive}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-times mr-1"></i>${i18n.statusInactive}</span>`;

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                        <div class="sm:hidden text-xs text-slate-500 mt-1">${tipoBadge}</div>
                    </td>
                    <td class="table-cell hidden sm:table-cell text-center">${tipoBadge}</td>
                    <td class="table-cell hidden md:table-cell text-slate-600 text-sm">${escapeHtml(banco)}</td>
                    <td class="table-cell hidden lg:table-cell text-slate-600 text-sm">${escapeHtml(agencia)}</td>
                    <td class="table-cell hidden lg:table-cell text-slate-600 text-sm">${escapeHtml(conta)}</td>
                    <td class="table-cell hidden md:table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${c.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${c.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/contas-bancarias/adicionar?id=' + id);
            });
        });

        // Event listeners para botoes de excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || i18n.thisRecord;

                window.parent.postMessage({
                    action: 'openDeleteModal',
                    recordId: id,
                    recordName: name,
                    recordType: i18n.recordType,
                    confirmType: 'none'
                }, '*');
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
        carregarContas(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarContas(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarContas(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Botao Nova Conta - Navega para pagina de adicionar
    document.getElementById('btnNovaConta')?.addEventListener('click', function () {
        navegarPara('/pages/contas-bancarias/adicionar');
    });

    // ===== EXCLUSAO =====

    async function excluirConta(id) {
        try {
            const result = await API.post(`/contas-bancarias/${id}/excluir`);

            if (result.success) {
                carregarContas(currentPage, perPage, searchTerm);
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
            excluirConta(event.data.recordId);
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
    carregarContas(currentPage, perPage, searchTerm);
})();
</script>
@endsection
