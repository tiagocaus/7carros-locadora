@extends('layouts.iframe')

@section('title', '<?= t("modules.matrizes_filiais.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.matrizes_filiais.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('common.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnAdicionar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.matrizes_filiais.actions.add') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header w-16"><?= t('modules.matrizes_filiais.table.type') ?></th>
                    <th class="table-header"><?= t('modules.matrizes_filiais.table.trade_name') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.matrizes_filiais.table.company_name') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.matrizes_filiais.table.cpf_cnpj') ?></th>
                    <th class="table-header hidden xl:table-cell"><?= t('modules.matrizes_filiais.table.city_state') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.matrizes_filiais.table.status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.matrizes_filiais.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.matrizes_filiais.pagination.rows_per_page') ?></label>
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
            <ul class="inline-flex items-center -space-x-px" id="paginationNav">
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
            loadError: '<?= addslashes(t('modules.matrizes_filiais.messages.load_error')) ?>',
            serverError: '<?= addslashes(t('modules.matrizes_filiais.messages.server_error')) ?>',
            noRecords: '<?= addslashes(t('modules.matrizes_filiais.messages.no_records')) ?>',
            typeParent: '<?= addslashes(t('modules.matrizes_filiais.type_options.parent')) ?>',
            typeBranch: '<?= addslashes(t('modules.matrizes_filiais.type_options.branch')) ?>',
            statusActive: '<?= addslashes(t('modules.matrizes_filiais.status_options.active')) ?>',
            statusInactive: '<?= addslashes(t('modules.matrizes_filiais.status_options.inactive')) ?>',
            actionView: '<?= addslashes(t('modules.matrizes_filiais.actions.view')) ?>',
            actionEdit: '<?= addslashes(t('modules.matrizes_filiais.actions.edit')) ?>',
            actionDelete: '<?= addslashes(t('modules.matrizes_filiais.actions.delete')) ?>',
            thisRecord: '<?= addslashes(t('modules.matrizes_filiais.messages.this_record')) ?>',
            idNotFound: '<?= addslashes(t('modules.matrizes_filiais.messages.id_not_found')) ?>',
            recordType: '<?= addslashes(t('modules.matrizes_filiais.record_type')) ?>',
            deleteError: '<?= addslashes(t('modules.matrizes_filiais.messages.delete_error')) ?>',
            deleteHasLinksTitle: <?= json_encode(t('modules.matrizes_filiais.messages.delete_has_links_title')) ?>,
            deleteHasLinksConfirm: <?= json_encode(t('modules.matrizes_filiais.messages.delete_has_links_confirm')) ?>,
            deactivateButton: <?= json_encode(t('modules.matrizes_filiais.messages.deactivate_button')) ?>,
            deactivated: <?= json_encode(t('modules.matrizes_filiais.messages.deactivated')) ?>,
            deactivateError: <?= json_encode(t('modules.matrizes_filiais.messages.deactivate_error')) ?>,
            showingPagination: '<?= addslashes(t('modules.matrizes_filiais.pagination.showing')) ?>',
        };

        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let searchTimeout = null;
        let pendingDeactivateId = null;

        // Navegar para adicionar (com verificação de limite do plano)
        document.getElementById('btnAdicionar')?.addEventListener('click', async function() {
            // Verificar limite do plano
            const limiteResult = await API.get('/api/plano/verificar-limite', { recurso: 'matrizfilial' });
            if (limiteResult && !limiteResult.pode_adicionar) {
                if (window.parent !== window && limiteResult.redirect_url) {
                    window.parent.postMessage({
                        action: 'navigate',
                        page: limiteResult.redirect_url
                    }, '*');
                }
                return;
            }

            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/matrizes-filiais/adicionar'
                }, '*');
            }
        });

        // Carregar dados via API
        async function carregarDados(page = 1, recordsPerPage = 10, search = '') {
            try {
                const result = await API.get('/api/matrizes-filiais', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search
                });

                if (result.success) {
                    renderTabela(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    console.error('Erro ao carregar dados:', result.message);
                    mostrarMensagemErro(i18n.loadError);
                }
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            }
        }

        function mostrarMensagemErro(mensagem) {
            const tbody = document.querySelector('#tableBody');
            if (!tbody) return;
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function renderTabela(registros) {
            const tbody = document.querySelector('#tableBody');
            if (!tbody) return;

            if (registros.length === 0) {
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
            registros.forEach(item => {
                const id = item.id || '';
                const tipo = item.tipo === 'M' ? `<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">${i18n.typeParent}</span>` : `<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">${i18n.typeBranch}</span>`;
                const razaoSocial = item.razao_social || '-';
                const nomeFantasia = item.nome_fantasia || '-';
                const cpfCnpj = item.cpf_cnpj || '-';
                const cidadeUf = item.cidade && item.estado ? `${item.cidade}/${item.estado}` : '-';
                const ativo = (item.status || 'A') === 'A';
                const status = ativo
                    ? `<span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800">${i18n.statusActive}</span>`
                    : `<span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-200 text-slate-700">${i18n.statusInactive}</span>`;
                const nomeEscapado = nomeFantasia.replace(/"/g, '&quot;');

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell w-16">${tipo}</td>
                    <td class="table-cell">${nomeFantasia}</td>
                    <td class="table-cell hidden md:table-cell">${razaoSocial}</td>
                    <td class="table-cell hidden lg:table-cell">${cpfCnpj}</td>
                    <td class="table-cell hidden xl:table-cell">${cidadeUf}</td>
                    <td class="table-cell hidden md:table-cell">${status}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${i18n.actionView}" class="btn-icon text-sky-600 hover:text-sky-800 btn-view" data-id="${id}"><i class="fas fa-eye"></i></button>
                        <button title="${i18n.actionEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            // Event listeners para botoes
            tbody.querySelectorAll('.btn-view').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'navigate',
                            page: '/pages/matrizes-filiais/adicionar?id=' + id + '&mode=view'
                        }, '*');
                    }
                });
            });

            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'navigate',
                            page: '/pages/matrizes-filiais/adicionar?id=' + id
                        }, '*');
                    }
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisRecord;

                    if (!id || id === 'undefined' || id === '') {
                        mostrarMensagemErro(i18n.idNotFound);
                        return;
                    }

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: id,
                            recordName: name,
                            recordType: i18n.recordType,
                            confirmType: 'text'
                        }, '*');
                    }
                });
            });
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement) return;

            const {
                page,
                perPage,
                total
            } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.getElementById('paginationNav');
            if (!paginationNav) return;

            const {
                page,
                totalPages,
                hasPrev,
                hasNext
            } = pagination;

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

        // Carregar dados ao inicializar
        carregarDados(currentPage, perPage, searchTerm);

        // Escutar mensagens do parent para confirmar exclusao
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'confirmDelete') {
                const id = event.data.recordId;
                excluirRegistro(id);
            } else if (event.data && event.data.action === 'genericConfirmed' && pendingDeactivateId) {
                const id = pendingDeactivateId;
                pendingDeactivateId = null;
                desativarRegistro(id);
            } else if (event.data && event.data.action === 'genericModalClosed') {
                pendingDeactivateId = null;
            }
        });

        function mostrarAlerta(mensagem) {
            if (window.parent !== window) {
                window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
                return;
            }

            console.warn(mensagem);
        }

        function abrirConfirmacaoDesativacao(id) {
            const message = i18n.deleteHasLinksConfirm;
            pendingDeactivateId = id;

            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.deleteHasLinksTitle,
                    message: message,
                    confirmText: i18n.deactivateButton
                }, '*');
                return;
            }

            mostrarAlerta(message);
        }

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/matrizes-filiais/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else if (result.pode_desativar) {
                    abrirConfirmacaoDesativacao(id);
                } else {
                    console.error('Erro ao excluir:', result.message);
                    mostrarAlerta(i18n.deleteError.replace(':message', result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao excluir:', error);
                mostrarAlerta(i18n.serverError);
            }
        }

        async function desativarRegistro(id) {
            try {
                const result = await API.post(`/matrizes-filiais/${id}/desativar`);

                if (result.success) {
                    mostrarAlerta(result.message || i18n.deactivated);
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    mostrarAlerta(result.message || i18n.deactivateError);
                }
            } catch (error) {
                console.error('Erro ao desativar:', error);
                mostrarAlerta(i18n.deactivateError);
            }
        }
    })();
</script>
@endsection
