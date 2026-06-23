@extends('layouts.iframe')

@section('title', '<?= t("modules.formas_pagamento.commands.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.formas_pagamento.commands.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.formas_pagamento.commands.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
            </button>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.formas_pagamento.commands.actions.new') ?>
            </button>
        </div>
    </div>

    <!-- Formulario inline (add/edit) -->
    <div id="formSection" class="bg-white shadow-md rounded-lg p-5 mb-6" style="display: none;">
        <div class="flex justify-between items-center mb-4">
            <h3 id="formTitle" class="form-section-title"><?= t('modules.formas_pagamento.commands.new_title') ?></h3>
            <button id="btnFecharForm" class="btn-icon text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form id="comandoForm" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <input type="hidden" id="comandoId" value="">

            <div class="md:col-span-4 form-input-group">
                <label for="comando" class="form-label-group">
                    <?= t('modules.formas_pagamento.commands.fields.command') ?> <span class="text-red-500">*</span> {!! aviso(t('modules.formas_pagamento.commands.fields.command_hint')) !!}
                </label>
                <input type="text" id="comando" name="comando" class="form-input-group-field" maxlength="255" placeholder="<?= t('modules.formas_pagamento.commands.placeholders.command') ?>" required>
            </div>

            <div class="md:col-span-5 form-input-group">
                <label for="descricao" class="form-label-group">
                    <?= t('modules.formas_pagamento.commands.fields.description') ?>
                </label>
                <textarea id="descricao" name="descricao" class="form-input-group-field" rows="1" maxlength="500" placeholder="<?= t('modules.formas_pagamento.commands.placeholders.description') ?>"></textarea>
            </div>

            <div class="md:col-span-1 form-input-group flex flex-col items-center justify-center">
                <label for="status" class="form-label-group"><?= t('modules.formas_pagamento.commands.fields.active') ?></label>
                <label class="switch mt-1">
                    <input type="checkbox" id="status" name="status" checked>
                    <span class="slider round"></span>
                </label>
            </div>

            <div class="md:col-span-2 form-input-group flex items-end">
                <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow w-full justify-center">
                    <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.formas_pagamento.commands.table.command') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.formas_pagamento.commands.table.description') ?></th>
                    <th class="table-header text-center"><?= t('modules.formas_pagamento.commands.table.origin') ?></th>
                    <th class="table-header text-center"><?= t('modules.formas_pagamento.commands.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.formas_pagamento.commands.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.formas_pagamento.commands.pagination.rows_per_page') ?></label>
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
(function() {
    const i18n = {
        loading: '<?= addslashes(t('common.labels.loading')) ?>',
        loadError: '<?= addslashes(t('modules.formas_pagamento.commands.messages.load_error')) ?>',
        serverError: '<?= addslashes(t('modules.formas_pagamento.commands.messages.server_error')) ?>',
        noRecords: '<?= addslashes(t('modules.formas_pagamento.commands.messages.no_records')) ?>',
        newTitle: '<?= addslashes(t('modules.formas_pagamento.commands.new_title')) ?>',
        editTitle: '<?= addslashes(t('modules.formas_pagamento.commands.edit_title')) ?>',
        commandRequired: '<?= addslashes(t('modules.formas_pagamento.commands.messages.command_required')) ?>',
        saveSuccess: '<?= addslashes(t('modules.formas_pagamento.commands.messages.save_success')) ?>',
        saveError: '<?= addslashes(t('modules.formas_pagamento.commands.messages.save_error')) ?>',
        loadCommandError: '<?= addslashes(t('modules.formas_pagamento.commands.messages.load_command_error')) ?>',
        notFound: '<?= addslashes(t('modules.formas_pagamento.commands.messages.not_found')) ?>',
        deleteError: <?= js_t('modules.formas_pagamento.commands.messages.delete_error') ?>,
        deleteConfirm: <?= js_t('modules.formas_pagamento.commands.messages.delete_confirm') ?>,
        thisRecord: '<?= addslashes(t('modules.formas_pagamento.commands.messages.this_record')) ?>',
        badgeSystem: '<?= addslashes(t('modules.formas_pagamento.commands.badges.system')) ?>',
        badgeCustom: '<?= addslashes(t('modules.formas_pagamento.commands.badges.custom')) ?>',
        badgeSystemCommand: '<?= addslashes(t('modules.formas_pagamento.commands.badges.system_command')) ?>',
        badgeActive: '<?= addslashes(t('modules.formas_pagamento.badges.active')) ?>',
        badgeInactive: '<?= addslashes(t('modules.formas_pagamento.badges.inactive')) ?>',
        actionEdit: '<?= addslashes(t('modules.formas_pagamento.commands.actions.edit')) ?>',
        actionDelete: '<?= addslashes(t('modules.formas_pagamento.commands.actions.delete')) ?>',
        showingPagination: '<?= addslashes(t('modules.formas_pagamento.commands.pagination.showing')) ?>',
    };

    // ===== ESTADO =====
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let editandoId = null;

    // ===== ELEMENTOS =====
    const tbody = document.getElementById('tableBody');
    const formSection = document.getElementById('formSection');
    const formTitle = document.getElementById('formTitle');
    const comandoForm = document.getElementById('comandoForm');
    const comandoIdInput = document.getElementById('comandoId');
    const comandoInput = document.getElementById('comando');
    const descricaoInput = document.getElementById('descricao');
    const statusInput = document.getElementById('status');

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

    // ===== ALERTA (postMessage) =====

    function mostrarAlerta(mensagem, callbackAction = null) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openAlert',
                message: mensagem,
                callbackAction: callbackAction ? 'callback' : null
            }, '*');
            if (callbackAction) {
                const handler = function(event) {
                    if (event.data && event.data.action === 'alertModalClosed') {
                        window.removeEventListener('message', handler);
                        callbackAction();
                    }
                };
                window.addEventListener('message', handler);
            }
        } else {
            alert(mensagem);
            if (callbackAction) callbackAction();
        }
    }

    // ===== FORMULARIO =====

    function abrirFormNovo() {
        editandoId = null;
        comandoIdInput.value = '';
        comandoInput.value = '';
        descricaoInput.value = '';
        statusInput.checked = true;
        formTitle.textContent = i18n.newTitle;
        formSection.style.display = 'block';
        comandoInput.focus();
    }

    function abrirFormEditar(id) {
        editandoId = id;
        formTitle.textContent = i18n.editTitle;
        formSection.style.display = 'block';
        carregarComando(id);
    }

    function fecharForm() {
        formSection.style.display = 'none';
        editandoId = null;
        comandoIdInput.value = '';
        comandoInput.value = '';
        descricaoInput.value = '';
        statusInput.checked = true;
    }

    async function carregarComando(id) {
        try {
            const result = await API.get('/api/comandos-parcelas/' + id);

            if (result.success && result.data) {
                const item = result.data;
                comandoIdInput.value = item.id;
                comandoInput.value = item.comando || '';
                descricaoInput.value = item.descricao || '';
                statusInput.checked = item.status === 'A';
                comandoInput.focus();
            } else {
                mostrarAlerta(i18n.loadCommandError + ': ' + (result.message || i18n.notFound));
                fecharForm();
            }
        } catch (error) {
            console.error('Erro ao carregar comando:', error);
            mostrarAlerta(i18n.serverError);
            fecharForm();
        }
    }

    async function salvarComando(e) {
        e.preventDefault();

        const comando = comandoInput.value.trim();
        if (!comando) {
            mostrarAlerta(i18n.commandRequired);
            comandoInput.focus();
            return;
        }

        const dados = {
            comando: comando,
            descricao: descricaoInput.value.trim(),
            status: statusInput.checked ? 'A' : 'I'
        };

        try {
            let result;
            if (editandoId) {
                result = await API.post('/comandos-parcelas/' + editandoId + '/atualizar', dados);
            } else {
                result = await API.post('/comandos-parcelas/salvar', dados);
            }

            if (result.success) {
                mostrarAlerta(result.message || i18n.saveSuccess);
                fecharForm();
                carregarDados(currentPage, perPage, searchTerm);
            } else {
                mostrarAlerta(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            mostrarAlerta(error.message || i18n.serverError);
        }
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarDados(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const result = await API.get('/api/comandos-parcelas', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderTabela(result.data);
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

    // ===== RENDERIZACAO DA TABELA =====

    function renderTabela(dados) {
        if (!dados || dados.length === 0) {
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
        dados.forEach(item => {
            const comando = escapeHtml(item.comando || '');
            const descricao = escapeHtml(item.descricao || '');
            const isSistema = item.chave === '0' || item.chave === 0;

            // Badge Origem
            const origemBadge = isSistema
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-lock mr-1"></i>${i18n.badgeSystem}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700"><i class="fas fa-user mr-1"></i>${i18n.badgeCustom}</span>`;

            // Badge Status
            const statusBadge = item.status === 'A'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.badgeActive}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-times mr-1"></i>${i18n.badgeInactive}</span>`;

            // Botoes de acao - desabilitados para comandos do sistema
            let acoesBtns = '';
            if (isSistema) {
                acoesBtns = `
                    <button title="${i18n.badgeSystemCommand}" class="btn-icon text-slate-300 cursor-not-allowed" disabled><i class="fas fa-edit"></i></button>
                    <button title="${i18n.badgeSystemCommand}" class="btn-icon text-slate-300 cursor-not-allowed" disabled><i class="fas fa-trash"></i></button>
                `;
            } else {
                acoesBtns = `
                    <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                    <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${comando}"><i class="fas fa-trash"></i></button>
                `;
            }

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium font-mono text-sm">${comando}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell">
                        <div class="text-sm text-slate-600">${descricao || '<span class="text-slate-400">-</span>'}</div>
                    </td>
                    <td class="table-cell text-center">${origemBadge}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-32 text-center">${acoesBtns}</td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                abrirFormEditar(id);
            });
        });

        // Event listeners para excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || i18n.thisRecord;

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: 'comando_parcela',
                        confirmType: 'none'
                    }, '*');
                } else {
                    if (confirm(i18n.deleteConfirm.replace(':name', name))) {
                        excluirRegistro(id);
                    }
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

        paginationNav.innerHTML = buttons;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarDados(currentPage, perPage, searchTerm);
    };

    // ===== EXCLUSAO =====

    async function excluirRegistro(id) {
        try {
            const result = await API.post('/comandos-parcelas/' + id + '/excluir');

            if (result.success) {
                carregarDados(currentPage, perPage, searchTerm);
            } else {
                mostrarAlerta(result.message || i18n.deleteError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarAlerta(error.message || i18n.deleteError);
        }
    }

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarDados(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm);
        }, 300);
    });

    document.getElementById('btnVoltar')?.addEventListener('click', function() {
        navegarPara('/pages/formas-pagamento');
    });

    document.getElementById('btnNovo')?.addEventListener('click', function() {
        abrirFormNovo();
    });

    document.getElementById('btnFecharForm')?.addEventListener('click', function() {
        fecharForm();
    });

    comandoForm?.addEventListener('submit', salvarComando);

    // Listener de mensagens do parent (confirmacao de exclusao)
    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

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

    // ===== INICIALIZACAO =====
    carregarDados(currentPage, perPage, searchTerm);
})();
</script>
@endsection
