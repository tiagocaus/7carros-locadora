@extends('layouts.iframe')

@section('title', t('modules.funcionarios.list_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="funcionarios-header flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.funcionarios.list_title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.funcionarios.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnAdicionarFuncionario" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.funcionarios.actions.add') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.funcionarios.table.name') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.funcionarios.table.username') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.funcionarios.table.email') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.funcionarios.table.role') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.funcionarios.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.funcionarios.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="funcionariosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serão inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.funcionarios.pagination.rows_per_page') ?></label>
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
        loadError: <?= json_encode(t('modules.funcionarios.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.funcionarios.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noRecords: <?= json_encode(t('modules.funcionarios.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        unnamed: <?= json_encode(t('modules.funcionarios.messages.unnamed'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        thisEmployee: <?= json_encode(t('modules.funcionarios.messages.this_employee'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        idNotFound: <?= json_encode(t('modules.funcionarios.messages.id_not_found'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.funcionarios.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusActive: <?= json_encode(t('modules.funcionarios.status_options.active'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        statusInactive: <?= json_encode(t('modules.funcionarios.status_options.inactive'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionView: <?= json_encode(t('modules.funcionarios.actions.view'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('modules.funcionarios.actions.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionDelete: <?= json_encode(t('modules.funcionarios.actions.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        recordType: <?= json_encode(t('modules.funcionarios.record_type'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        showingPagination: <?= json_encode(t('modules.funcionarios.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteModalTitle: <?= json_encode(t('modules.funcionarios.delete_modal.title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteModalConfirmText: <?= json_encode(t('modules.funcionarios.delete_modal.confirm_text'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteModalThisRecord: <?= json_encode(t('modules.funcionarios.delete_modal.this_record'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteModalMessage: <?= json_encode(t('modules.funcionarios.delete_modal.message'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteModalTypePlaceholder: <?= json_encode(t('modules.funcionarios.delete_modal.type_placeholder'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    // Estado da paginação
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;

    // Variáveis globais para o modal de exclusão
    let currentRecordId = null;
    let currentRecordName = null;
    const CONFIRM_TEXT = i18n.deleteModalConfirmText;
    let currentConfirmType = 'text';
    let currentExpectedText = '';

    // Comunicação com o iframe pai - Navegar para adicionar funcionário
    document.getElementById('btnAdicionarFuncionario')?.addEventListener('click', function () {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: '/pages/funcionarios/adicionar'
            }, '*');
        }
    });

    // Carregar funcionários do banco de dados via API (com paginação)
    async function carregarFuncionarios(page = 1, recordsPerPage = 10, search = '') {
        try {
            const result = await API.get('/api/funcionarios', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderFuncionarios(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                console.error('Erro ao carregar funcionários:', result.message);
                mostrarMensagemErro(i18n.loadError);
            }
        } catch (error) {
            console.error('Erro ao buscar funcionários:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    // Mostrar mensagem de erro na tabela
    function mostrarMensagemErro(mensagem) {
        const tbody = document.querySelector('#funcionariosTableBody');
        if (!tbody) return;
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    // Preencher tabela
    function renderFuncionarios(funcionarios) {
        const tbody = document.querySelector('#funcionariosTableBody');
        if (!tbody) return;

        if (funcionarios.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        funcionarios.forEach(funcionario => {
            const nome = funcionario.nome || i18n.unnamed;
            const usuario = funcionario.usuario || '-';
            const email = funcionario.email || '-';
            const funcao = funcionario.role_name || funcionario.funcao || '-';
            const status = funcionario.status === 'A' ? i18n.statusActive : i18n.statusInactive;
            const statusClass = funcionario.status === 'A' ? 'text-green-600' : 'text-red-600';
            // Garantir que o ID seja um número válido ou string vazia
            const funcionarioId = (funcionario.id !== undefined && funcionario.id !== null) ? String(funcionario.id) : '';

            // Escapar o nome para evitar problemas com aspas no HTML
            const nomeEscapado = nome.replace(/"/g, '&quot;');

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">${nome}</td>
                    <td class="table-cell hidden md:table-cell">${usuario}</td>
                    <td class="table-cell hidden md:table-cell">${email}</td>
                    <td class="table-cell hidden lg:table-cell">${funcao}</td>
                    <td class="table-cell hidden lg:table-cell"><span class="${statusClass}">${status}</span></td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionView}" class="btn-icon text-sky-600 hover:text-sky-800 btn-view-funcionario" data-funcionario-id="${funcionarioId}"><i class="fas fa-eye"></i></button>
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit-funcionario" data-funcionario-id="${funcionarioId}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete-funcionario" data-funcionario-id="${funcionarioId}" data-funcionario-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botões de visualizar
        tbody.querySelectorAll('.btn-view-funcionario').forEach(button => {
            button.addEventListener('click', function () {
                const funcionarioId = this.getAttribute('data-funcionario-id');
                if (!funcionarioId || funcionarioId === 'undefined' || funcionarioId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'navigate',
                        page: `/pages/funcionarios/adicionar?id=${funcionarioId}&mode=view`
                    }, '*');
                } else {
                    window.location.href = `/pages/funcionarios/adicionar?id=${funcionarioId}&mode=view`;
                }
            });
        });

        // Event listeners para botões de editar
        tbody.querySelectorAll('.btn-edit-funcionario').forEach(button => {
            button.addEventListener('click', function () {
                const funcionarioId = this.getAttribute('data-funcionario-id');
                if (!funcionarioId || funcionarioId === 'undefined' || funcionarioId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'navigate',
                        page: `/pages/funcionarios/adicionar?id=${funcionarioId}`
                    }, '*');
                } else {
                    window.location.href = `/pages/funcionarios/adicionar?id=${funcionarioId}`;
                }
            });
        });

        // Event listeners para botões de excluir
        tbody.querySelectorAll('.btn-delete-funcionario').forEach(button => {
            button.addEventListener('click', function () {
                const funcionarioId = this.getAttribute('data-funcionario-id');
                const funcionarioName = this.getAttribute('data-funcionario-name') || i18n.thisEmployee;

                // Validar se temos os dados necessários
                if (!funcionarioId || funcionarioId === 'undefined' || funcionarioId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }

                // Garantir que temos um nome válido
                const displayName = (funcionarioName && funcionarioName !== 'undefined' && funcionarioName !== '')
                    ? funcionarioName
                    : i18n.thisEmployee;

                // Enviar mensagem para o parent abrir o modal global
                if (window.parent !== window) {
                    const message = {
                        action: 'openDeleteModal',
                        recordId: funcionarioId,
                        recordName: displayName,
                        recordType: i18n.recordType,
                        confirmType: 'text'
                    };
                    window.parent.postMessage(message, '*');
                } else {
                    // Fallback: abrir modal local se não estiver em iframe
                    openDeleteModal(funcionarioId, displayName, i18n.recordType, 'text');
                }
            });
        });
    }

    // Atualizar informações de registros
    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
    }

    // Atualizar botões de paginação
    function atualizarPaginacao(pagination) {
        const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
        if (!paginationNav) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        // Gerar botões de paginação
        let buttons = '';

        // Botão anterior
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Botões de páginas
        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);

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

        // Botão próximo
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

    // Ir para uma página específica
    window.irParaPagina = function(page) {
        currentPage = page;
        carregarFuncionarios(currentPage, perPage, searchTerm);
    };

    // Event listener para seletor de registros por página
    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1; // Voltar para primeira página
        carregarFuncionarios(currentPage, perPage, searchTerm);
    });

    // Event listener para busca (com debounce)
    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        // Limpar timeout anterior
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Aguardar 300ms após parar de digitar
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1; // Voltar para primeira página ao buscar
            carregarFuncionarios(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Carregar funcionários ao inicializar
    carregarFuncionarios(currentPage, perPage, searchTerm);

    // Escutar mensagens do parent para confirmar exclusão
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'confirmDelete') {
            const funcionarioId = event.data.recordId;
            // Executar a exclusão
            excluirFuncionarioViaAPI(funcionarioId);
        }
    });

    /**
     * Executa a exclusão do funcionário via API
     */
    async function excluirFuncionarioViaAPI(funcionarioId) {
        try {
            const result = await API.post(`/funcionarios/${funcionarioId}/excluir`);

            if (result.success) {
                // Recarregar página atual
                carregarFuncionarios(currentPage, perPage, searchTerm);
            } else {
                console.error('Erro ao excluir funcionário:', result.message);
                mostrarMensagemErro(i18n.deleteError.replace(':message', result.message));
            }
        } catch (error) {
            console.error('Erro ao excluir funcionário:', error);
            mostrarMensagemErro(i18n.serverError);
        }
    }

    // ========================================
    // FUNÇÕES DO MODAL DE EXCLUSÃO (FALLBACK)
    // ========================================

    /**
     * Abre o modal de exclusão
     */
    window.openDeleteModal = function(recordId, recordName, recordType = i18n.recordType, confirmType = 'text') {
        currentRecordId = recordId;
        currentRecordName = recordName;
        currentConfirmType = confirmType;

        const modal = document.getElementById('meuModalExclusao');
        const modalTitle = document.getElementById('deleteModalTitle');
        const modalMessage = document.getElementById('deleteModalMessage');
        const confirmSection = document.getElementById('confirmDeleteSection');
        const confirmInput = document.getElementById('confirmDeleteInput');
        const confirmText = document.getElementById('confirmDeleteText');
        const confirmButton = document.getElementById('confirmDeleteButton');

        modalTitle.textContent = i18n.deleteModalTitle;
        // Garantir que temos um nome válido
        const displayName = recordName && recordName !== 'undefined' ? recordName : i18n.deleteModalThisRecord;
        modalMessage.textContent = i18n.deleteModalMessage.replace(':type', recordType).replace(':name', displayName);

        // Modo sem confirmação
        if (confirmType === 'none') {
            confirmSection.style.display = 'none';
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
            confirmInput.removeEventListener('input', validateDeleteConfirmation);
            modal.classList.add('open');
            return;
        }

        // Modo com confirmação de texto (EXCLUIR)
        confirmSection.style.display = 'block';
        currentExpectedText = CONFIRM_TEXT;
        confirmText.textContent = CONFIRM_TEXT;
        confirmInput.placeholder = i18n.deleteModalTypePlaceholder.replace(':text', CONFIRM_TEXT);

        // Resetar campo e botão
        confirmInput.value = '';
        confirmButton.disabled = true;
        confirmButton.style.opacity = '0.5';
        confirmButton.style.cursor = 'not-allowed';

        // Remover listener anterior se existir
        confirmInput.removeEventListener('input', validateDeleteConfirmation);

        // Focar no campo de confirmação
        modal.classList.add('open');
        setTimeout(() => confirmInput.focus(), 100);

        // Validar enquanto digita
        confirmInput.addEventListener('input', validateDeleteConfirmation);
    };

    /**
     * Fecha o modal de exclusão
     */
    window.closeDeleteModal = function() {
        const modal = document.getElementById('meuModalExclusao');
        const confirmSection = document.getElementById('confirmDeleteSection');
        const confirmInput = document.getElementById('confirmDeleteInput');

        if (confirmInput) {
            confirmInput.removeEventListener('input', validateDeleteConfirmation);
            confirmInput.value = '';
        }
        if (confirmSection) {
            confirmSection.style.display = 'none';
        }

        modal.classList.remove('open');
        currentRecordId = null;
        currentRecordName = null;
        currentConfirmType = 'text';
        currentExpectedText = '';
    };

    /**
     * Valida o texto digitado no campo de confirmação
     */
    function validateDeleteConfirmation() {
        const confirmInput = document.getElementById('confirmDeleteInput');
        const confirmButton = document.getElementById('confirmDeleteButton');
        const inputValue = confirmInput.value.trim();

        // Comparação case-insensitive
        const matches = inputValue.toLowerCase() === currentExpectedText.toLowerCase();

        if (matches) {
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
        } else {
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
        }
    }

    /**
     * Confirma e executa a exclusão
     */
    window.confirmDelete = async function() {
        if (!currentRecordId) return;

        try {
            const result = await API.post(`/funcionarios/${currentRecordId}/excluir`);

            if (result.success) {
                closeDeleteModal();
                // Recarregar página atual
                carregarFuncionarios(currentPage, perPage, searchTerm);
            } else {
                closeDeleteModal();
                console.error('Erro ao excluir funcionário:', result.message);
                mostrarMensagemErro(i18n.deleteError.replace(':message', result.message));
            }
        } catch (error) {
            console.error('Erro ao excluir funcionário:', error);
            closeDeleteModal();
            mostrarMensagemErro(i18n.serverError);
        }
    };
})();
</script>
@endsection
