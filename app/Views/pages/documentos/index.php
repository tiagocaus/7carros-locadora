@extends('layouts.iframe')

@section('title', '<?= t("modules.documentos.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.documentos.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filtroTipo" class="form-input-focus w-32">
                <option value=""><?= t('modules.documentos.filters.all') ?></option>
                <option value="0"><?= t('modules.documentos.filters.both') ?></option>
                <option value="1"><?= t('modules.documentos.filters.contract') ?></option>
                <option value="2"><?= t('modules.documentos.filters.rental') ?></option>
                <option value="3"><?= t('modules.documentos.filters.fine') ?></option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.documentos.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovoDocumento" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.documentos.table.title') ?></th>
                    <th class="table-header w-28 text-center"><?= t('modules.documentos.table.type') ?></th>
                    <th class="table-header w-24 text-center"><?= t('modules.documentos.table.status') ?></th>
                    <th class="table-header hidden md:table-cell w-40"><?= t('modules.documentos.table.updated_at') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.documentos.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="documentosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.documentos.pagination.rows_per_page') ?></label>
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
        loadError: <?= json_encode(t('modules.documentos.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.documentos.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.documentos.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noTitle: <?= json_encode(t('modules.documentos.messages.no_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeBoth: <?= json_encode(t('modules.documentos.badges.type_both'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeContract: <?= json_encode(t('modules.documentos.badges.type_contract'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeRental: <?= json_encode(t('modules.documentos.badges.type_rental'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        typeFine: <?= json_encode(t('modules.documentos.badges.type_fine'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusActive: <?= json_encode(t('modules.documentos.badges.status_active'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusInactive: <?= json_encode(t('modules.documentos.badges.status_inactive'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('common.buttons.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionDelete: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        thisRecord: <?= json_encode(t('modules.documentos.messages.this_record'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        recordType: <?= json_encode(t('modules.documentos.record_type'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.documentos.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.documentos.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let filtroTipo = '';
    let searchTimeout = null;

    // Elementos
    const tbody = document.getElementById('documentosTableBody');

    // Labels de tipo
    const tipoLabels = {
        0: { text: i18n.typeBoth, class: 'bg-purple-100 text-purple-700' },
        1: { text: i18n.typeContract, class: 'bg-blue-100 text-blue-700' },
        2: { text: i18n.typeRental, class: 'bg-green-100 text-green-700' },
        3: { text: i18n.typeFine, class: 'bg-red-100 text-red-700' }
    };

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

    async function carregarDocumentos(page = 1, recordsPerPage = 10, search = '', tipo = '') {
        try {
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search
            };

            if (tipo !== '') {
                params.tipo = tipo;
            }

            const result = await API.get('/api/documentos', params);

            if (result.success) {
                renderDocumentos(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar documentos:', error);
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

    function renderDocumentos(documentos) {
        if (!documentos || documentos.length === 0) {
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
        documentos.forEach(doc => {
            const titulo = doc.titulo || i18n.noTitle;
            const tituloEscapado = escapeHtml(titulo);
            const tipo = parseInt(doc.tipo) || 0;
            const tipoInfo = tipoLabels[tipo] || tipoLabels[0];
            const status = parseInt(doc.status) || 0;
            const updatedAt = doc.updated_at ? formatarData(doc.updated_at) : '-';
            const isGlobal = doc.chave === '0';
            const deleteButton = isGlobal
                ? ''
                : `<button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${doc.id}" data-name="${tituloEscapado}"><i class="fas fa-trash"></i></button>`;

            // Badge de tipo
            const tipoBadge = `<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${tipoInfo.class}">${tipoInfo.text}</span>`;

            // Badge de status
            const statusBadge = status === 1
                ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">' + i18n.statusActive + '</span>'
                : '<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">' + i18n.statusInactive + '</span>';

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${tituloEscapado}</div>
                    </td>
                    <td class="table-cell text-center">${tipoBadge}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell hidden md:table-cell text-slate-500 text-sm">${updatedAt}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${doc.id}"><i class="fas fa-edit"></i></button>
                        ${deleteButton}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/documentos/' + id + '/editar');
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
                    excluirDocumento(id);
                }
            });
        });
    }

    // ===== FORMATACAO =====

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        try {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dataStr;
        }
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
        carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
        }, 300);
    });

    document.getElementById('filtroTipo')?.addEventListener('change', function (e) {
        filtroTipo = e.target.value;
        currentPage = 1;
        carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
    });

    // Botao Novo Documento - Navega para pagina de adicionar
    document.getElementById('btnNovoDocumento')?.addEventListener('click', function () {
        navegarPara('/pages/documentos/adicionar');
    });

    // ===== EXCLUSAO =====

    async function excluirDocumento(id) {
        try {
            const result = await API.post(`/documentos/${id}/excluir`);

            if (result.success) {
                carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
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
            excluirDocumento(event.data.recordId);
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
    carregarDocumentos(currentPage, perPage, searchTerm, filtroTipo);
})();
</script>
@endsection
