@extends('layouts.iframe')

@section('title', t('modules.promissorias.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.promissorias.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <button id="btnModelos" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center" title="<?= t('modules.promissorias.tooltips.edit_templates') ?>">
                <i class="fas fa-file-alt mr-2"></i><?= t('modules.promissorias.buttons.templates') ?>
            </button>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.promissorias.messages.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovaPromissoria" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.promissorias.buttons.new') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.promissorias.filters.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.promissorias.filters.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[140px] max-w-[180px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('common.labels.status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.promissorias.filters.all_statuses') ?></option>
                <option value="pendente"><?= t('modules.promissorias.filters.pending') ?></option>
                <option value="quitado"><?= t('modules.promissorias.filters.paid_off') ?></option>
            </select>
        </div>
        <div class="flex items-end">
            <button id="btnLimparFiltros" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('common.buttons.clear') ?>">
                <i class="fas fa-times mr-1"></i><?= t('common.buttons.clear') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.promissorias.table.code') ?></th>
                    <th class="table-header"><?= t('modules.promissorias.table.client') ?></th>
                    <th class="table-header hidden lg:table-cell text-right"><?= t('modules.promissorias.table.total_value') ?></th>
                    <th class="table-header hidden sm:table-cell text-center" title="<?= htmlspecialchars(t('modules.promissorias.tooltips.installments_progress'), ENT_QUOTES, 'UTF-8') ?>"><?= t('modules.promissorias.table.installments') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.promissorias.table.due_date') ?></th>
                    <th class="table-header w-28 text-center"><?= t('modules.promissorias.table.status') ?></th>
                    <th class="table-header px-2 w-44 text-center"><?= t('modules.promissorias.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="promissoriasTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.promissorias.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= t('modules.promissorias.pagination.showing_empty') ?></span>
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
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$i18nPromissorias = [
    'loading' => t('common.labels.loading'),
    'noRecords' => t('modules.promissorias.messages.no_records'),
    'loadError' => t('modules.promissorias.messages.load_error'),
    'connectionError' => t('modules.promissorias.messages.connection_error'),
    'statusPaidOff' => t('modules.promissorias.status.paid_off'),
    'statusPending' => t('modules.promissorias.status.pending'),
    'tooltipEdit' => t('modules.promissorias.tooltips.edit'),
    'tooltipDelete' => t('modules.promissorias.tooltips.delete'),
    'tooltipPrint' => t('modules.promissorias.tooltips.print'),
    'tooltipSignature' => t('modules.promissorias.tooltips.signature'),
    'tooltipMarkAllPaid' => t('modules.promissorias.tooltips.mark_all_paid'),
    'statusPaidOffLabel' => t('modules.promissorias.status.paid_off'),
    'thisPromissory' => t('modules.promissorias.messages.this_promissory'),
    'confirmDelete' => t('modules.promissorias.messages.confirm_delete'),
    'deletedSuccess' => t('modules.promissorias.messages.deleted_success'),
    'deleteError' => t('modules.promissorias.messages.delete_error'),
    'printTitle' => t('modules.promissorias.messages.print_title'),
    'markPaidTitle' => t('modules.promissorias.messages.mark_paid_title'),
    'markPaidConfirm' => t('modules.promissorias.messages.mark_paid_confirm'),
    'markPaidBtn' => t('modules.promissorias.messages.mark_paid_btn'),
    'markedPaid' => t('modules.promissorias.messages.marked_paid'),
    'markPaidError' => t('modules.promissorias.messages.mark_paid_error'),
    'signatureLoadError' => t('modules.promissorias.messages.signature_load_error'),
    'signatureRemoved' => t('modules.promissorias.messages.signature_removed'),
    'signatureClearError' => t('modules.promissorias.messages.signature_clear_error'),
    'showing' => t('modules.promissorias.pagination.showing'),
    'showingEmpty' => t('modules.promissorias.pagination.showing_empty'),
    'recordType' => t('modules.promissorias.title_singular'),
];
?>
<script>
(function () {
    const i18n = <?= json_encode($i18nPromissorias, $jsFlags) ?>;

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;

    // Estado dos filtros
    let filterFilial = '';
    let filterStatus = '';

    // Estado para acao pendente (confirmacao via modal)
    let pendingAction = null;
    let pendingData = null;

    // Elementos
    const tbody = document.getElementById('promissoriasTableBody');

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

    // ===== INICIALIZACAO DOS FILTROS =====

    async function carregarFiliais() {
        try {
            const result = await API.get('/api/matrizes-filiais/buscar');
            if (result.success && result.data) {
                const select = document.getElementById('filterFilial');
                if (!select) return;

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

    async function carregarPromissorias(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search
            };

            // Adicionar filtros se preenchidos
            if (filterFilial) params.filial = filterFilial;
            if (filterStatus) params.status = filterStatus;

            const result = await API.get('/api/promissorias', params);

            if (result.success) {
                renderPromissorias(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError.replace(':message', result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar promissorias:', error);
            mostrarMensagemErro(error.message || i18n.connectionError);
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

    function renderPromissorias(promissorias) {
        if (!promissorias || promissorias.length === 0) {
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

        promissorias.forEach(p => {
            const codigoBase = escapeHtml(p.codigo_base || p.codigo || '-');
            const clienteNome = escapeHtml(p.cliente_nome || '-');
            const clienteCpf = p.cliente_cpf_cnpj ? `<span class="text-xs text-slate-400">${escapeHtml(p.cliente_cpf_cnpj)}</span>` : '';
            const valorTotal = formatarMoeda(p.valor_total || 0);

            // Informacao de parcelas
            const qtdParcelas = parseInt(p.qtd_parcelas) || 0;
            const qtdPagas = parseInt(p.qtd_pagas) || 0;
            const parcelasInfo = qtdParcelas > 0 ? `${qtdPagas}/${qtdParcelas}` : '-';
            const proximoVencimento = p.proximo_vencimento
                ? DateHelper.format(p.proximo_vencimento)
                : '-';

            // Status baseado nas parcelas
            const todasPagas = qtdParcelas > 0 && qtdPagas === qtdParcelas;
            let statusBadge;
            if (todasPagas) {
                statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.statusPaidOff}</span>`;
            } else {
                statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700"><i class="fas fa-clock mr-1"></i>${i18n.statusPending}</span>`;
            }

            // Botoes de acao - usando codigo_base
            const codigo = escapeHtml(p.codigo_base || p.codigo);
            const temAssinatura = !!p.id_assinatura;
            const assinaturaClass = temAssinatura ? 'text-green-600' : 'text-slate-400';
            const btnAssinatura = `<button onclick="assinaturaPromissoria('${codigo}', ${temAssinatura ? 'true' : 'false'})" class="${assinaturaClass} hover:text-green-700 p-1" title="${i18n.tooltipSignature}"><i class="fas fa-signature"></i></button>`;
            const btnEditar = `<button onclick="editarPromissoria('${codigo}')" class="text-blue-600 hover:text-blue-800 p-1" title="${i18n.tooltipEdit}"><i class="fas fa-edit"></i></button>`;
            const btnExcluir = `<button onclick="excluirPromissoria('${codigo}')" class="text-red-600 hover:text-red-800 p-1" title="${i18n.tooltipDelete}"><i class="fas fa-trash"></i></button>`;
            const btnImprimir = `<button onclick="imprimirPromissoria('${codigo}')" class="text-slate-600 hover:text-slate-800 p-1" title="${i18n.tooltipPrint}"><i class="fas fa-print"></i></button>`;
            const btnMarcarPago = todasPagas
                ? `<button class="text-green-600 p-1 cursor-default" title="${i18n.statusPaidOffLabel}"><i class="fas fa-check-circle"></i></button>`
                : `<button onclick="marcarComoPago('${codigo}')" class="text-slate-400 hover:text-green-600 p-1" title="${i18n.tooltipMarkAllPaid}"><i class="fas fa-check-circle"></i></button>`;

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50" data-codigo="${codigo}">
                    <td class="table-cell font-mono">${codigoBase}</td>
                    <td class="table-cell">
                        <div class="font-medium">${clienteNome}</div>
                        ${clienteCpf}
                    </td>
                    <td class="table-cell hidden lg:table-cell text-right font-medium">${valorTotal}</td>
                    <td class="table-cell hidden sm:table-cell text-center">${parcelasInfo}</td>
                    <td class="table-cell hidden md:table-cell text-center whitespace-nowrap">${proximoVencimento}</td>
                    <td class="table-cell w-28 text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-44 text-right">
                        <div class="flex items-center justify-center space-x-1">
                            ${btnMarcarPago}
                            ${btnAssinatura}
                            ${btnImprimir}
                            ${btnEditar}
                            ${btnExcluir}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;
    }

    // ===== PAGINACAO =====

    function atualizarPaginacao(pagination) {
        if (!pagination) return;

        const nav = document.querySelector('nav[aria-label="Page navigation"] ul');
        if (!nav) return;

        let paginationHtml = `
            <li>
                <button class="pagination-button arrow-button rounded-l-md" ${pagination.page <= 1 ? 'disabled' : ''} onclick="mudarPagina(${pagination.page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Determinar paginas a mostrar
        let startPage = Math.max(1, pagination.page - 2);
        let endPage = Math.min(pagination.totalPages, pagination.page + 2);

        if (startPage > 1) {
            paginationHtml += `<li><button class="pagination-button numbered" onclick="mudarPagina(1)">1</button></li>`;
            if (startPage > 2) {
                paginationHtml += `<li><span class="px-2 text-slate-400">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `
                <li>
                    <button class="pagination-button numbered ${i === pagination.page ? 'active' : ''}" onclick="mudarPagina(${i})">${i}</button>
                </li>
            `;
        }

        if (endPage < pagination.totalPages) {
            if (endPage < pagination.totalPages - 1) {
                paginationHtml += `<li><span class="px-2 text-slate-400">...</span></li>`;
            }
            paginationHtml += `<li><button class="pagination-button numbered" onclick="mudarPagina(${pagination.totalPages})">${pagination.totalPages}</button></li>`;
        }

        paginationHtml += `
            <li>
                <button class="pagination-button arrow-button rounded-r-md" ${pagination.page >= pagination.totalPages ? 'disabled' : ''} onclick="mudarPagina(${pagination.page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

        nav.innerHTML = paginationHtml;
    }

    function atualizarInfoRegistros(pagination) {
        if (!pagination) return;

        const info = document.getElementById('registrosInfo');
        if (!info) return;

        const inicio = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.perPage + 1;
        const fim = Math.min(pagination.page * pagination.perPage, pagination.total);

        info.textContent = i18n.showing.replace(':start', inicio).replace(':end', fim).replace(':total', pagination.total);
    }

    window.mudarPagina = function(page) {
        currentPage = page;
        carregarPromissorias(currentPage, perPage, searchTerm);
    };

    // ===== ACOES =====

    window.editarPromissoria = function(codigo) {
        navegarPara(`/pages/promissorias/editar/${codigo}`);
    };

    window.excluirPromissoria = function(codigo) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: codigo,
                recordName: i18n.thisPromissory,
                recordType: i18n.recordType,
                confirmType: 'text'
            }, '*');
        }
    };

    async function confirmarExclusao(codigo) {
        try {
            const result = await API.post(`/promissorias/${codigo}/excluir`);

            if (result.success) {
                toast.success(result.message || i18n.deletedSuccess);
                carregarPromissorias(currentPage, perPage, searchTerm);
            } else {
                toast.error(result.message || i18n.deleteError);
            }
        } catch (error) {
            console.error('Erro ao excluir:', error);
            toast.error(error.message || i18n.deleteError);
        }
    }

    window.imprimirPromissoria = function(codigo) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openPrintModal',
                url: `/promissorias/${codigo}/imprimir`,
                title: i18n.printTitle
            }, '*');
        } else {
            // Fallback se não estiver em iframe
            window.open(`/promissorias/${codigo}/imprimir`, '_blank');
        }
    };

    window.marcarComoPago = function(codigo) {
        if (window.parent !== window) {
            pendingAction = 'marcarPago';
            pendingData = codigo;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.markPaidTitle,
                message: i18n.markPaidConfirm,
                confirmText: i18n.markPaidBtn
            }, '*');
        }
    };

    window.assinaturaPromissoria = function(codigo, temAssinatura) {
        if (temAssinatura) {
            abrirModalAssinatura(codigo);
            return;
        }

        const linkAssinatura = window.location.origin + '/assinar/' + codigo;
        window.parent.postMessage({
            action: 'openSignatureLinkModal',
            tipo: 'promissoria',
            id: codigo,
            codigo: codigo,
            url: linkAssinatura
        }, '*');
    };

    async function abrirModalAssinatura(codigo) {
        try {
            const result = await API.get(`/api/promissorias/${codigo}/assinatura`);
            if (!result.success) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.signatureLoadError.replace(':message', result.message || '')
                }, '*');
                return;
            }

            window.parent.postMessage({
                action: 'openAssinaturaModal',
                tipo: 'promissoria',
                promissoriaCodigo: codigo,
                codigo: codigo,
                data_assinatura: result.data?.data_assinatura || '-',
                ip: result.data?.ip || '-',
                url: result.data?.url || ''
            }, '*');
        } catch (error) {
            console.error('Erro ao carregar assinatura:', error);
            window.parent.postMessage({
                action: 'openAlert',
                message: i18n.signatureLoadError.replace(':message', error.message || '')
            }, '*');
        }
    }

    async function limparAssinatura(codigo) {
        try {
            const result = await API.post(`/promissorias/${codigo}/limpar-assinatura`);

            if (result.success) {
                carregarPromissorias(currentPage, perPage, searchTerm);
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.signatureRemoved }, '*');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.signatureClearError }, '*');
            }
        } catch (error) {
            console.error('Erro ao limpar assinatura:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.signatureClearError }, '*');
        }
    }

    async function executarMarcarPago(codigo) {
        try {
            const result = await API.post(`/promissorias/${codigo}/marcar-pago`);

            if (result.success) {
                toast.success(result.message || i18n.markedPaid);
                carregarPromissorias(currentPage, perPage, searchTerm);
            } else {
                toast.error(result.message || i18n.markPaidError);
            }
        } catch (error) {
            console.error('Erro ao marcar como pago:', error);
            toast.error(error.message || i18n.markPaidError);
        }
    }

    // ===== EVENT LISTENERS =====

    document.addEventListener('DOMContentLoaded', function() {
        // Carregar dados iniciais
        carregarFiliais();
        carregarPromissorias(currentPage, perPage, searchTerm);

        // Botao Modelos
        document.getElementById('btnModelos')?.addEventListener('click', function() {
            navegarPara('/pages/promissorias/templates');
        });

        // Botao Nova Promissoria
        document.getElementById('btnNovaPromissoria')?.addEventListener('click', function() {
            navegarPara('/pages/promissorias/adicionar');
        });

        // Busca com debounce
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarPromissorias(currentPage, perPage, searchTerm);
            }, 300);
        });

        // Filtro de filial
        document.getElementById('filterFilial')?.addEventListener('change', function(e) {
            filterFilial = e.target.value;
            currentPage = 1;
            carregarPromissorias(currentPage, perPage, searchTerm);
        });

        // Filtro de status
        document.getElementById('filterStatus')?.addEventListener('change', function(e) {
            filterStatus = e.target.value;
            currentPage = 1;
            carregarPromissorias(currentPage, perPage, searchTerm);
        });

        // Registros por pagina
        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value) || 10;
            currentPage = 1;
            carregarPromissorias(currentPage, perPage, searchTerm);
        });

        // Limpar filtros
        document.getElementById('btnLimparFiltros')?.addEventListener('click', function() {
            document.getElementById('filterFilial').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('searchInput').value = '';
            filterFilial = '';
            filterStatus = '';
            searchTerm = '';
            currentPage = 1;
            carregarPromissorias(currentPage, perPage, searchTerm);
        });
    });

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    }

    // ===== LISTENER DE MENSAGENS =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmacao de exclusao do parent
        if (event.data.action === 'confirmDelete') {
            confirmarExclusao(event.data.recordId);
        }

        // Confirmacao generica do parent
        if (event.data.action === 'genericConfirmed' && pendingAction) {
            if (pendingAction === 'marcarPago') {
                executarMarcarPago(pendingData);
            }
            pendingAction = null;
            pendingData = null;
        }

        if (event.data.action === 'resetarAssinatura' && event.data.promissoriaCodigo) {
            limparAssinatura(event.data.promissoriaCodigo);
        }
    });
})();
</script>
@endsection
