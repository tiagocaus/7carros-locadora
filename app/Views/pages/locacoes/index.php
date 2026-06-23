@extends('layouts.iframe')

@section('title', t('modules.locacoes.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.locacoes.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filterStatus" class="form-input-focus w-50">
                <option value=""><?= t('modules.locacoes.filters.all') ?></option>
                <option value="R,A,P" selected><?= t('modules.locacoes.filters.reservations_and_open') ?></option>
                <option value="P"><?= t('modules.locacoes.filters.pending') ?></option>
                <option value="R"><?= t('modules.locacoes.filters.reservation') ?></option>
                <option value="A"><?= t('modules.locacoes.filters.open') ?></option>
                <option value="F"><?= t('modules.locacoes.filters.closed') ?></option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.locacoes.filters.search_placeholder') ?>" class="form-input-focus sm:w-72 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovaLocacao" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.locacoes.table.seq') ?></th>
                    <th class="table-header"><?= t('modules.locacoes.table.code') ?></th>
                    <th class="table-header"><?= t('modules.locacoes.table.client') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.locacoes.table.vehicle') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.locacoes.table.checkout') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.locacoes.table.expected_return') ?></th>
                    <th class="table-header hidden xl:table-cell"><?= t('modules.locacoes.table.status') ?></th>
                    <th class="table-header px-2 w-48 text-center"><?= t('common.labels.actions') ?></th>
                </tr>
            </thead>
            <tbody id="locacoesTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.locacoes.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= t('modules.locacoes.pagination.showing', ['start' => 0, 'end' => 0, 'total' => 0]) ?></span>
        </div>
        <nav id="paginationNav" aria-label="<?= htmlspecialchars(t('modules.locacoes.pagination.page_navigation'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 sm:mt-0">
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
        const i18n = <?= json_encode([
            'loading' => t('common.labels.loading'),
            'loadError' => t('modules.locacoes.messages.load_error'),
            'connectionError' => t('modules.locacoes.messages.connection_error'),
            'noRecords' => t('modules.locacoes.messages.no_records'),
            'statusReservation' => t('modules.locacoes.status.reservation'),
            'statusPending' => t('modules.locacoes.status.pending'),
            'statusOpen' => t('modules.locacoes.status.open'),
            'statusClosed' => t('modules.locacoes.status.closed'),
            'statusLate' => t('modules.locacoes.status.late'),
            'btnApprove' => t('modules.locacoes.buttons.approve'),
            'approveConfirm' => t('modules.locacoes.messages.approve_confirm'),
            'approveOk' => t('modules.locacoes.messages.approve_ok'),
            'approveError' => t('modules.locacoes.messages.approve_error'),
            'btnPrint' => t('common.buttons.print'),
            'btnSignature' => t('modules.locacoes.buttons.signature'),
            'btnReplace' => t('modules.locacoes.buttons.replace_vehicle'),
            'btnEdit' => t('common.buttons.edit'),
            'btnDelete' => t('common.buttons.delete'),
            'recordType' => t('modules.locacoes.record_type'),
            'deleteWarning' => t('modules.locacoes.messages.delete_warning'),
            'thisRental' => t('modules.locacoes.messages.this_rental'),
            'deleteError' => t('modules.locacoes.messages.delete_error'),
            'signatureRemoved' => t('modules.locacoes.messages.signature_removed'),
            'signatureClearError' => t('modules.locacoes.messages.signature_clear_error'),
            'signatureCopied' => t('modules.locacoes.messages.signature_copied'),
            'signatureLoadError' => t('modules.locacoes.messages.signature_load_error'),
            'printTitle' => t('modules.locacoes.print.title'),
            'paginationShowing' => t('modules.locacoes.pagination.showing'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        // Estado da paginacao
        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let statusFilter = 'R,A,P';
        let searchTimeout = null;
        const canSubstituir = <?= \App\Core\Auth::can('locacoes.substituir') ? 'true' : 'false' ?>;

        const tbody = document.getElementById('locacoesTableBody');

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

        async function carregarLocacoes(page = 1, recordsPerPage = 10, search = '', status = '') {
            try {
                mostrarLoading();

                const result = await API.get('/api/locacoes', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search,
                    status: status
                });

                if (result.success) {
                    renderLocacoes(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    mostrarMensagemErro(i18n.loadError.replace(':message', result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao buscar locacoes:', error);
                mostrarMensagemErro(error.message || i18n.connectionError);
            }
        }

        function mostrarLoading() {
            tbody.innerHTML = `
            <tr>
                <td colspan="8" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
        }

        function mostrarMensagemErro(mensagem) {
            tbody.innerHTML = `
            <tr>
                <td colspan="8" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function renderLocacoes(locacoes) {
            if (!locacoes || locacoes.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="table-cell text-center text-slate-500">
                        <i class="fas fa-file-invoice mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            locacoes.forEach(loc => {
                const sequencia = loc.sequencia || '-';
                const codigo = escapeHtml(loc.codigo || '-');
                const cliente = escapeHtml(loc.cliente_nome_completo || loc.cliente_nome || '-');
                const veiculo = escapeHtml(loc.veiculo_info || '-');
                const dataSaida = formatarDataHora(loc.data_saida);
                const dataPrevista = formatarDataHora(loc.data_prevista);

                // Badge de situacao
                let statusBadge = '';
                if (loc.status === 'P') {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">${i18n.statusPending}</span>`;
                } else if (loc.status === 'R') {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">${i18n.statusReservation}</span>`;
                } else if (loc.status === 'A') {
                    // Verificar se esta atrasado
                    if (loc.data_prevista) {
                        const dataPrev = new Date(loc.data_prevista);
                        dataPrev.setHours(0, 0, 0, 0);
                        if (dataPrev < hoje) {
                            statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">${i18n.statusLate}</span>`;
                        } else {
                            statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">${i18n.statusOpen}</span>`;
                        }
                    } else {
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">${i18n.statusOpen}</span>`;
                    }
                } else if (loc.status === 'F') {
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">${i18n.statusClosed}</span>`;
                }

                // Icone de assinatura
                const temAssinatura = !!loc.id_assinatura;
                const assinaturaClass = temAssinatura ? 'text-green-600' : 'text-slate-400';

                // Botoes de acao por status
                let actionButtons = '';

                // Aprovar (somente Pendente)
                if (loc.status === 'P') {
                    actionButtons += `<button title="${i18n.btnApprove}" class="btn-icon text-green-600 hover:text-green-800 btn-approve" data-id="${loc.id}" data-codigo="${codigo}"><i class="fas fa-check-circle"></i></button>`;
                }

                // Impressao (todos)
                actionButtons += `<button title="${i18n.btnPrint}" class="btn-icon text-blue-600 hover:text-blue-800 btn-print" data-id="${loc.id}"><i class="fas fa-print"></i></button>`;

                // Assinatura (Reserva confirmada, Aberto e Fechado)
                if (loc.status !== 'P') {
                    actionButtons += `<button title="${i18n.btnSignature}" class="btn-icon ${assinaturaClass} btn-assinatura" data-id="${loc.id}" data-codigo="${codigo}" data-tem="${temAssinatura ? '1' : '0'}"><i class="fas fa-signature"></i></button>`;
                }

                if (canSubstituir && loc.status === 'A') {
                    actionButtons += `<button title="${i18n.btnReplace}" class="btn-icon text-amber-600 hover:text-amber-800 btn-substituir" data-id="${loc.id}"><i class="fa-solid fa-up-down"></i></button>`;
                }

                // Editar e Excluir (todos)
                actionButtons += `<button title="${i18n.btnEdit}" class="btn-icon text-slate-600 hover:text-slate-800 btn-edit" data-id="${loc.id}"><i class="fas fa-edit"></i></button>`;
                actionButtons += `<button title="${i18n.btnDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${loc.id}" data-name="${codigo}"><i class="fas fa-trash"></i></button>`;

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell text-slate-500">${sequencia}</td>
                    <td class="table-cell">
                        <span class="font-medium">${codigo}</span>
                    </td>
                    <td class="table-cell">
                        <div class="font-medium">${cliente}</div>
                        <div class="text-sm text-slate-500 md:hidden">${veiculo}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell">${veiculo}</td>
                    <td class="table-cell hidden lg:table-cell">${dataSaida}</td>
                    <td class="table-cell hidden lg:table-cell">${dataPrevista}</td>
                    <td class="table-cell hidden xl:table-cell">${statusBadge || '-'}</td>
                    <td class="table-cell px-2 w-48 text-right">
                        ${actionButtons}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;
            bindEventListeners();
        }

        function bindEventListeners() {
            // Editar
            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/locacoes/editar/' + id);
                });
            });

            tbody.querySelectorAll('.btn-substituir').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/locacoes/substituir/' + id);
                });
            });

            // Excluir
            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisRental;

                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: i18n.recordType,
                        confirmType: 'text',
                        warningMessage: i18n.deleteWarning
                    }, '*');
                });
            });

            // Impressao
            tbody.querySelectorAll('.btn-print').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    abrirModalImpressao(id);
                });
            });

            // Aprovar reserva pendente (status P -> R) — confirma via modal global do parent
            tbody.querySelectorAll('.btn-approve').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const codigo = this.getAttribute('data-codigo');
                    const msg = i18n.approveConfirm.replace(':code', codigo);
                    window._pendingApproveId = id;
                    window.parent.postMessage({
                        action: 'openGenericConfirmModal',
                        title: i18n.btnApprove,
                        message: msg,
                        confirmText: i18n.btnApprove
                    }, '*');
                });
            });

            // Assinatura
            tbody.querySelectorAll('.btn-assinatura').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const codigo = this.getAttribute('data-codigo');
                    const temAssinatura = this.getAttribute('data-tem') === '1';

                    if (temAssinatura) {
                        abrirModalAssinatura(id, codigo);
                    } else {
                        const linkAssinatura = window.location.origin + '/assinar/' + codigo;
                        window.parent.postMessage({
                            action: 'openSignatureLinkModal',
                            tipo: 'locacao',
                            id: id,
                            codigo: codigo,
                            url: linkAssinatura
                        }, '*');
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

            infoElement.textContent = i18n.paginationShowing.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.querySelector('#paginationNav ul');
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
            carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
        };

        // ===== EVENT LISTENERS =====

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
            }, 300);
        });

        document.getElementById('filterStatus')?.addEventListener('change', function(e) {
            statusFilter = e.target.value;
            currentPage = 1;
            carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
        });

        document.getElementById('btnNovaLocacao')?.addEventListener('click', function() {
            navegarPara('/pages/locacoes/adicionar');
        });

        // ===== ACOES =====

        async function excluirLocacao(id) {
            try {
                const result = await API.post(`/locacoes/${id}/excluir`);

                if (result.success) {
                    carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.deleteError }, '*');
            }
        }

        async function limparAssinatura(id) {
            try {
                const result = await API.post(`/locacoes/${id}/limpar-assinatura`);

                if (result.success) {
                    carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
                    window.parent.postMessage({ action: 'openAlert', message: i18n.signatureRemoved }, '*');
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.signatureClearError }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.signatureClearError }, '*');
            }
        }

        // ===== IMPRESSAO =====

        function abrirModalImpressao(id) {
            window.parent.postMessage({
                action: 'openOffcanvasIframe',
                url: '/pages/locacoes/offcanvas-impressao?id=' + id,
                title: i18n.printTitle,
                width: '420px'
            }, '*');
        }

        // ===== MODAL ASSINATURA =====

        async function abrirModalAssinatura(id, codigo) {
            try {
                const result = await API.get(`/api/locacoes/${id}/assinatura`);
                if (!result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: i18n.signatureLoadError.replace(':message', result.message || '') }, '*');
                    return;
                }

                window.parent.postMessage({
                    action: 'openAssinaturaModal',
                    tipo: 'locacao',
                    locacaoId: id,
                    codigo: codigo,
                    data_assinatura: result.data?.data_assinatura || '-',
                    ip: result.data?.ip || '-',
                    url: result.data?.url || ''
                }, '*');
            } catch (error) {
                console.error('Erro ao carregar assinatura:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.signatureLoadError.replace(':message', error.message) }, '*');
            }
        }

        // Escutar mensagem do parent para resetar assinatura
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'resetarAssinatura') {
                if (event.data.locacaoId) {
                    limparAssinatura(event.data.locacaoId);
                }
            }
        });

        // ===== LISTENER DE MENSAGENS =====

        window.addEventListener('message', function(event) {
            if (!event.data || !event.data.action) return;

            if (event.data.action === 'confirmDelete') {
                excluirLocacao(event.data.recordId);
            }

            // Aprovacao de reserva confirmada via genericConfirmModal
            if (event.data.action === 'genericConfirmed' && window._pendingApproveId) {
                const id = window._pendingApproveId;
                window._pendingApproveId = null;
                (async function() {
                    try {
                        const resp = await API.post('/api/locacoes/' + id + '/confirmar-reserva', {});
                        if (resp && resp.success) {
                            if (window.toast) toast.success(i18n.approveOk);
                            carregarLocacoes(currentPage);
                        } else {
                            window.parent.postMessage({ action: 'openAlert', message: (resp && resp.message) || i18n.approveError }, '*');
                        }
                    } catch (err) {
                        console.error(err);
                        window.parent.postMessage({ action: 'openAlert', message: i18n.approveError + ': ' + err.message }, '*');
                    }
                })();
            }
        });

        // ===== HELPERS =====

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatarDataHora(data) {
            if (!data) return '-';
            try {
                return DateHelper.formatDateTime(data) || '-';
            } catch {
                return data;
            }
        }

        // Initialization
        carregarLocacoes(currentPage, perPage, searchTerm, statusFilter);
    })();
</script>
@endsection
