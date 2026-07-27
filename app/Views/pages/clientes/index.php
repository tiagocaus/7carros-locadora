@extends('layouts.iframe')

@section('title', t('modules.clientes.list_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="clientes-header flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0">{{ t('modules.clientes.list_title') }}</h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="{{ t('modules.clientes.placeholders.search_list') }}" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <?php if (\App\Core\Auth::can('clientes.criar')): ?>
                <div class="action-menu" data-action-menu>
                    <button type="button"
                        id="btnMenuImportacaoClientes"
                        class="action-menu-trigger"
                        title="{{ t('modules.clientes.tooltips.import_actions') }}"
                        aria-label="{{ t('modules.clientes.tooltips.import_actions') }}"
                        aria-haspopup="menu"
                        aria-expanded="false">
                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    </button>
                    <div class="action-menu-panel" role="menu" aria-label="{{ t('modules.clientes.tooltips.import_actions') }}">
                        <button type="button" id="btnImportarClientes" class="action-menu-item" role="menuitem">
                            <i class="fas fa-file-arrow-up" aria-hidden="true"></i>
                            <span>{{ t('modules.clientes.buttons.import_clients') }}</span>
                        </button>
                        <button type="button" id="btnBaixarModeloClientes" class="action-menu-item" role="menuitem">
                            <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                            <span>{{ t('modules.clientes.buttons.download_import_template') }}</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <button id="btnAdicionarCliente" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i>{{ t('modules.clientes.buttons.add_client') }}
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header">{{ t('modules.clientes.fields.name') }}</th>
                    <th class="table-header hidden md:table-cell">{{ t('modules.clientes.fields.email') }}</th>
                    <th class="table-header hidden lg:table-cell">{{ t('modules.clientes.fields.phone') }}</th>
                    <th class="table-header px-2 w-32 text-center">{{ t('common.labels.actions') }}</th>
                </tr>
            </thead>
            <tbody id="clientesTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2">{{ t('modules.clientes.pagination.rows_per_page') }}</label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo">{{ t('modules.clientes.pagination.showing_empty') }}</span>
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
<?php
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$jsText = static fn(string $value): string => json_encode($value, $jsonFlags);
?>
<script>
(function () {
    const i18n = {
        noClients: <?= $jsText(t("modules.clientes.messages.no_clients")) ?>,
        noName: <?= $jsText(t("modules.clientes.messages.no_name")) ?>,
        thisClient: <?= $jsText(t("modules.clientes.messages.this_client")) ?>,
        loadError: <?= $jsText(t("modules.clientes.messages.load_error")) ?>,
        connectionError: <?= $jsText(t("modules.clientes.messages.connection_error")) ?>,
        idNotFound: <?= $jsText(t("modules.clientes.messages.id_not_found")) ?>,
        deleteError: <?= $jsText(t("modules.clientes.messages.delete_error")) ?>,
        deleteBlockedTitle: <?= $jsText(t("modules.clientes.messages.delete_blocked_title")) ?>,
        confirmDeleteTitle: <?= $jsText(t("modules.clientes.messages.confirm_delete_title")) ?>,
        confirmDeleteMessage: <?= $jsText(t("modules.clientes.messages.confirm_delete_message")) ?>,
        confirmText: <?= $jsText(t("modules.clientes.messages.confirm_text")) ?>,
        confirmInputPlaceholder: <?= $jsText(t("modules.clientes.messages.confirm_input_placeholder")) ?>,
        thisRecord: <?= $jsText(t("modules.clientes.messages.this_record")) ?>,
        tooltipView: <?= $jsText(t("modules.clientes.tooltips.view")) ?>,
        tooltipEdit: <?= $jsText(t("modules.clientes.tooltips.edit")) ?>,
        tooltipDelete: <?= $jsText(t("modules.clientes.tooltips.delete")) ?>,
        showing: <?= $jsText(t("common.labels.showing")) ?>,
        of: <?= $jsText(t("common.labels.of")) ?>,
        records: <?= $jsText(t("common.labels.records")) ?>,
    };

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;

    // Variaveis globais para o modal de exclusao
    let currentRecordId = null;
    let currentRecordName = null;
    let currentConfirmType = 'text';
    let currentExpectedText = '';

    // Comunicacao com o iframe pai - Navegar para adicionar cliente
    document.getElementById('btnAdicionarCliente')?.addEventListener('click', function () {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: '/pages/clientes/adicionar'
            }, '*');
        }
    });

    document.getElementById('btnImportarClientes')?.addEventListener('click', function () {
        window.parent.postMessage({ action: 'openClienteImportacaoModal' }, '*');
    });

    document.getElementById('btnBaixarModeloClientes')?.addEventListener('click', function () {
        window.location.href = '/clientes/modelo-importacao';
    });

    window.addEventListener('message', function (event) {
        if (event.data?.action === 'clienteImportacaoConcluida') {
            currentPage = 1;
            carregarClientes(currentPage, perPage, searchTerm);
        } else if (event.data?.action === 'clienteImportacaoModalClosed') {
            document.getElementById('btnMenuImportacaoClientes')?.focus();
        }
    });

    // Carregar clientes do banco de dados via API (com paginacao)
    async function carregarClientes(page = 1, recordsPerPage = 10, search = '') {
        try {
            const result = await API.get('/api/clientes', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderClientes(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                console.error('Erro ao carregar clientes:', result.message);
                mostrarMensagemErro(i18n.loadError);
            }
        } catch (error) {
            console.error('Erro ao buscar clientes:', error);
            mostrarMensagemErro(error.message || i18n.connectionError);
        }
    }

    // Mostrar mensagem de erro na tabela
    function mostrarMensagemErro(mensagem) {
        const tbody = document.querySelector('#clientesTableBody');
        if (!tbody) return;
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    // Preencher tabela
    function renderClientes(clientes) {
        const tbody = document.querySelector('#clientesTableBody');
        if (!tbody) return;

        if (clientes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noClients}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        clientes.forEach(cliente => {
            const nome = cliente.nome_rsocial || cliente.nome || i18n.noName;
            const email = cliente.email || '-';
            const telefone = cliente.tel_cel || cliente.tel_com || cliente.tel_residenc || '-';
            // Garantir que o ID seja um numero valido ou string vazia
            const clienteId = (cliente.id !== undefined && cliente.id !== null) ? String(cliente.id) : '';

            // Escapar o nome para evitar problemas com aspas no HTML
            const nomeEscapado = nome.replace(/"/g, '&quot;');

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">${nome}</td>
                    <td class="table-cell hidden md:table-cell">${email}</td>
                    <td class="table-cell hidden lg:table-cell">${telefone}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.tooltipView}" class="btn-icon text-sky-600 hover:text-sky-800 btn-view-client" data-client-id="${clienteId}"><i class="fas fa-eye"></i></button>
                        <button title="${i18n.tooltipEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit-client" data-client-id="${clienteId}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.tooltipDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete-client" data-client-id="${clienteId}" data-client-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de excluir
        tbody.querySelectorAll('.btn-delete-client').forEach(button => {
            button.addEventListener('click', function () {
                const clientId = this.getAttribute('data-client-id');
                const clientName = this.getAttribute('data-client-name') || i18n.thisClient;

                // Validar se temos os dados necessarios
                if (!clientId || clientId === 'undefined' || clientId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }

                // Garantir que temos um nome valido
                const displayName = (clientName && clientName !== 'undefined' && clientName !== '')
                    ? clientName
                    : i18n.thisClient;

                // Enviar mensagem para o parent abrir o modal global
                if (window.parent !== window) {
                    const message = {
                        action: 'openDeleteModal',
                        recordId: clientId,
                        recordName: displayName,
                        recordType: 'cliente',
                        confirmType: 'text'
                    };
                    window.parent.postMessage(message, '*');
                } else {
                    // Fallback: abrir modal local se nao estiver em iframe
                    openDeleteModal(clientId, displayName, 'cliente', 'text');
                }
            });
        });

        // Event listeners para botoes de ver
        tbody.querySelectorAll('.btn-view-client').forEach(button => {
            button.addEventListener('click', function () {
                const clientId = this.getAttribute('data-client-id');
                if (!clientId || clientId === 'undefined' || clientId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'navigate',
                        page: '/pages/clientes/adicionar?id=' + clientId + '&mode=view'
                    }, '*');
                }
            });
        });

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit-client').forEach(button => {
            button.addEventListener('click', function () {
                const clientId = this.getAttribute('data-client-id');
                if (!clientId || clientId === 'undefined' || clientId === '') {
                    mostrarMensagemErro(i18n.idNotFound);
                    return;
                }
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'navigate',
                        page: '/pages/clientes/adicionar?id=' + clientId
                    }, '*');
                }
            });
        });
    }

    // Atualizar informacoes de registros
    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        infoElement.textContent = `${i18n.showing} ${start}-${end} ${i18n.of} ${total} ${i18n.records}`;
    }

    // Atualizar botoes de paginacao
    function atualizarPaginacao(pagination) {
        const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
        if (!paginationNav) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        // Gerar botoes de paginacao
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

    // Ir para uma pagina especifica
    window.irParaPagina = function(page) {
        currentPage = page;
        carregarClientes(currentPage, perPage, searchTerm);
    };

    // Event listener para seletor de registros por pagina
    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarClientes(currentPage, perPage, searchTerm);
    });

    // Event listener para busca (com debounce)
    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarClientes(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Carregar clientes ao inicializar
    carregarClientes(currentPage, perPage, searchTerm);

    // Escutar mensagens do parent para confirmar exclusao
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'confirmDelete') {
            const clientId = event.data.recordId;
            excluirClienteViaAPI(clientId);
        }
    });

    /**
     * Executa a exclusao do cliente via API
     */
    async function excluirClienteViaAPI(clientId) {
        try {
            const result = await API.post(`/clientes/${clientId}/excluir`);

            if (result.success) {
                carregarClientes(currentPage, perPage, searchTerm);
            } else {
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openValidationModal',
                        errors: [{
                            tabName: i18n.deleteBlockedTitle,
                            fields: [result.message]
                        }]
                    }, '*');
                } else {
                    openAlert(result.message, 'error');
                }
            }
        } catch (error) {
            console.error('Erro ao excluir cliente:', error);
            mostrarMensagemErro(i18n.connectionError);
        }
    }

    // ========================================
    // FUNCOES DO MODAL DE EXCLUSAO (FALLBACK)
    // ========================================

    /**
     * Abre o modal de exclusao
     */
    window.openDeleteModal = function(recordId, recordName, recordType = 'cliente', confirmType = 'text') {
        currentRecordId = recordId;
        currentRecordName = recordName;
        currentConfirmType = confirmType;

        const modal = document.getElementById('meuModalExclusao');
        const modalTitle = document.getElementById('deleteModalTitle');
        const modalMessage = document.getElementById('deleteModalMessage');
        const confirmSection = document.getElementById('confirmDeleteSection');
        const confirmInput = document.getElementById('confirmDeleteInput');
        const confirmTextEl = document.getElementById('confirmDeleteText');
        const confirmButton = document.getElementById('confirmDeleteButton');

        modalTitle.textContent = i18n.confirmDeleteTitle;
        const displayName = recordName && recordName !== 'undefined' ? recordName : i18n.thisRecord;
        modalMessage.textContent = i18n.confirmDeleteMessage.replace(':type', recordType).replace(':name', displayName);

        // Modo sem confirmacao
        if (confirmType === 'none') {
            confirmSection.style.display = 'none';
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
            confirmInput.removeEventListener('input', validateDeleteConfirmation);
            modal.classList.add('open');
            return;
        }

        // Modo com confirmacao de texto
        confirmSection.style.display = 'block';
        currentExpectedText = i18n.confirmText;
        confirmTextEl.textContent = i18n.confirmText;
        confirmInput.placeholder = i18n.confirmInputPlaceholder.replace(':text', i18n.confirmText);

        // Resetar campo e botao
        confirmInput.value = '';
        confirmButton.disabled = true;
        confirmButton.style.opacity = '0.5';
        confirmButton.style.cursor = 'not-allowed';

        // Remover listener anterior se existir
        confirmInput.removeEventListener('input', validateDeleteConfirmation);

        // Focar no campo de confirmacao
        modal.classList.add('open');
        setTimeout(() => confirmInput.focus(), 100);

        // Validar enquanto digita
        confirmInput.addEventListener('input', validateDeleteConfirmation);
    };

    /**
     * Fecha o modal de exclusao
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
     * Valida o texto digitado no campo de confirmacao
     */
    function validateDeleteConfirmation() {
        const confirmInput = document.getElementById('confirmDeleteInput');
        const confirmButton = document.getElementById('confirmDeleteButton');
        const inputValue = confirmInput.value.trim();

        // Comparacao case-insensitive
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
     * Confirma e executa a exclusao
     */
    window.confirmDelete = async function() {
        if (!currentRecordId) return;

        try {
            const result = await API.post(`/clientes/${currentRecordId}/excluir`);

            if (result.success) {
                closeDeleteModal();
                carregarClientes(currentPage, perPage, searchTerm);
            } else {
                closeDeleteModal();
                console.error('Erro ao excluir cliente:', result.message);
                mostrarMensagemErro(i18n.deleteError + ': ' + result.message);
            }
        } catch (error) {
            console.error('Erro ao excluir cliente:', error);
            closeDeleteModal();
            mostrarMensagemErro(i18n.connectionError);
        }
    };
})();
</script>
@endsection
