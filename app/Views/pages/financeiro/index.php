@extends('layouts.iframe')

@section('title', t('modules.financeiro.title'))

@section('content')
<?php $canDeleteFinanceiro = \App\Core\Auth::can('financeiro.excluir'); ?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.financeiro.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.financeiro.filters.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <?php if ($canDeleteFinanceiro): ?>
            <button id="btnExcluirSelecionados" class="hidden bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md text-sm font-medium items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-trash mr-2"></i><span id="btnExcluirSelecionadosTexto"><?= t('modules.financeiro.buttons.delete_selected_count', ['count' => 0]) ?></span>
            </button>
            <?php endif; ?>
            <button id="btnNovoLancamento" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg">
        <div class="flex-1 min-w-[180px] max-w-[250px]">
            <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.financeiro.filters.branch') ?></label>
            <select id="filterFilial" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.financeiro.filters.all_branches') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[120px] max-w-[150px]">
            <label for="filterAno" class="block text-xs text-slate-500 mb-1"><?= t('modules.financeiro.filters.year') ?></label>
            <select id="filterAno" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.financeiro.filters.all_years') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[140px] max-w-[180px]">
            <label for="filterMes" class="block text-xs text-slate-500 mb-1"><?= t('modules.financeiro.filters.month') ?></label>
            <select id="filterMes" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.financeiro.filters.all_months') ?></option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>"><?= t('common.months.' . $m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[180px]">
            <label for="filterStatus" class="block text-xs text-slate-500 mb-1"><?= t('modules.financeiro.filters.status') ?></label>
            <select id="filterStatus" class="form-input-focus w-full text-sm">
                <option value=""><?= t('modules.financeiro.filters.all_statuses') ?></option>
                <option value="paid"><?= t('modules.financeiro.filters.status_paid') ?></option>
                <option value="due_today"><?= t('modules.financeiro.filters.status_due_today') ?></option>
                <option value="open"><?= t('modules.financeiro.filters.status_open') ?></option>
                <option value="overdue"><?= t('modules.financeiro.filters.status_overdue') ?></option>
            </select>
        </div>
        <div class="flex-1 min-w-[150px] max-w-[180px]">
            <label for="filterTipo" class="block text-xs text-slate-500 mb-1"><?= t('modules.financeiro.fields.type') ?></label>
            <select id="filterTipo" class="form-input-focus w-full text-sm">
                <option value=""><?= t('common.labels.all') ?></option>
                <option value="R"><?= t('modules.financeiro.fields.type_revenue') ?></option>
                <option value="D"><?= t('modules.financeiro.fields.type_expense') ?></option>
            </select>
        </div>
        <div class="flex items-end">
            <button id="btnLimparFiltros" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('modules.financeiro.filters.clear_title') ?>">
                <i class="fas fa-times mr-1"></i><?= t('common.buttons.clear') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <?php if ($canDeleteFinanceiro): ?>
                    <th class="table-header w-12 text-center">
                        <input type="checkbox" id="checkTodosLancamentos" class="rounded border-slate-300 text-red-600 focus:ring-red-500" title="<?= t('modules.financeiro.buttons.select_all_visible') ?>">
                    </th>
                    <?php endif; ?>
                    <th class="table-header w-16 text-center"><?= t('modules.financeiro.table.seq') ?></th>
                    <th class="table-header"><?= t('modules.financeiro.table.description') ?></th>
                    <th class="table-header hidden sm:table-cell" title="<?= t('modules.financeiro.table.client_supplier_employee_full') ?>"><?= t('modules.financeiro.table.client_supplier_employee') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.financeiro.table.due_date') ?></th>
                    <th class="table-header hidden lg:table-cell text-right"><?= t('modules.financeiro.table.value') ?></th>
                    <th class="table-header w-36 text-center"><?= t('common.labels.status') ?></th>
                    <th class="table-header px-2 w-48 text-center"><?= t('common.labels.actions') ?></th>
                </tr>
            </thead>
            <tbody id="financeirosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.financeiro.pagination.rows_per_page') ?></label>
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
<?php
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$i18nFinanceiro = [
    'loading' => t('common.labels.loading'),
    'noRecords' => t('modules.financeiro.messages.no_records'),
    'noDescription' => t('modules.financeiro.messages.no_description'),
    'loadError' => t('modules.financeiro.messages.load_error'),
    'connectionError' => t('modules.financeiro.messages.connection_error'),
    'deleteConfirm' => t('modules.financeiro.messages.delete_confirm'),
    'deleteError' => t('modules.financeiro.messages.delete_error'),
    'thisEntry' => t('modules.financeiro.messages.this_entry'),
    'recordType' => t('modules.financeiro.record_types.entry'),
    'statusPaid' => t('modules.financeiro.status.paid'),
    'statusDueIn' => t('modules.financeiro.status.due_in'),
    'statusDueToday' => t('modules.financeiro.status.due_today'),
    'statusOverdue' => t('modules.financeiro.status.overdue'),
    'daySingular' => t('modules.financeiro.status.day_singular'),
    'daysPlural' => t('modules.financeiro.status.days_plural'),
    'paymentLink' => t('modules.financeiro.buttons.payment_link'),
    'printSend' => t('modules.financeiro.buttons.print_send'),
    'printSendTitle' => t('modules.financeiro.print.title'),
    'emitNfse' => 'Emitir NFS-e',
    'resendNfse' => 'Reenviar NFS-e',
    'viewNfse' => 'Visualizar NFS-e',
    'resendNfseTitle' => 'Reenviar NFS-e',
    'resendNfseConfirm' => 'Esta NFS-e foi rejeitada. Deseja reenviar a nota agora?',
    'resendNfseSuccess' => 'NFS-e reenviada com sucesso.',
    'resendNfseError' => 'Erro ao reenviar NFS-e.',
    'edit' => t('common.buttons.edit'),
    'delete' => t('common.buttons.delete'),
    'deleteSelected' => t('modules.financeiro.buttons.delete_selected_count'),
    'selectedEntries' => t('modules.financeiro.messages.selected_entries'),
    'batchDeleteType' => t('modules.financeiro.record_types.entries'),
    'batchDeleteError' => t('modules.financeiro.messages.batch_delete_error'),
    'batchDeletePartialTitle' => t('modules.financeiro.messages.batch_delete_partial_title'),
    'paymentLinkError' => t('modules.financeiro.messages.payment_link_error'),
    'paginationShowing' => t('modules.financeiro.pagination.showing'),
    'vehiclePlatesLabel' => t('modules.financeiro.table.vehicle_plates_label'),
];
?>
<script>
(function () {
    const i18n = <?= json_encode($i18nFinanceiro, $jsFlags) ?>;
    const canDeleteFinanceiro = <?= $canDeleteFinanceiro ? 'true' : 'false' ?>;

    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let nfseReenvioPendente = null;
    let lancamentosSelecionados = new Set();

    // Estado dos filtros
    let filterFilial = '';
    let filterAno = DateHelper.todayISO().substring(0, 4);
    let filterMes = String(parseInt(DateHelper.todayISO().substring(5, 7), 10));
    let filterStatus = '';
    let filterTipo = '';

    // Elementos
    const tbody = document.getElementById('financeirosTableBody');

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

    function montarBotaoNfse(lancamento, pago, isReceita) {
        if (!pago || !isReceita) {
            return '';
        }

        const nfseId = parseInt(lancamento.nfse_id || 0, 10);
        const nfseStatus = String(lancamento.nfse_status || '').toLowerCase();

        if (!nfseId) {
            return `<button title="${i18n.emitNfse}" class="btn-icon text-purple-600 hover:text-purple-800 btn-nfse-emit" data-id="${lancamento.id}"><i class="fas fa-file-invoice"></i></button>`;
        }

        if (nfseStatus === 'rejeitada') {
            return `<button title="${i18n.resendNfse}" class="btn-icon text-red-600 hover:text-red-800 btn-nfse-resend" data-nfse-id="${nfseId}"><i class="fas fa-redo"></i></button>`;
        }

        if (nfseStatus === 'pendente' || nfseStatus === 'processando') {
            return `<button title="${i18n.viewNfse}" class="btn-icon text-purple-600 hover:text-purple-800 btn-nfse-view" data-nfse-id="${nfseId}"><i class="fas fa-file-invoice"></i></button>`;
        }

        return '';
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

    function popularAnos() {
        const select = document.getElementById('filterAno');
        if (!select) return;

        const anoAtual = DateHelper.currentYear();

        // Gerar ultimos 5 anos + proximo ano
        for (let ano = anoAtual + 1; ano >= anoAtual - 4; ano--) {
            const option = document.createElement('option');
            option.value = ano;
            option.textContent = ano;
            option.selected = (ano === anoAtual);
            select.appendChild(option);
        }

        // Selecionar mês atual
        document.getElementById('filterMes').value = filterMes;
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarLancamentos(page = 1, recordsPerPage = 10, search = '') {
        try {
            limparSelecaoLancamentos();
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search
            };

            // Adicionar filtros se preenchidos
            if (filterFilial) params.filial = filterFilial;
            if (filterAno) params.ano = filterAno;
            if (filterMes) params.mes = filterMes;
            if (filterStatus) params.status = filterStatus;
            if (filterTipo) params.tipo = filterTipo;

            const result = await API.get('/api/financeiro', params);

            if (result.success) {
                renderLancamentos(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError.replace(':message', result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar lancamentos:', error);
            mostrarMensagemErro(error.message || i18n.connectionError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="${canDeleteFinanceiro ? 8 : 7}" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${canDeleteFinanceiro ? 8 : 7}" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderLancamentos(lancamentos) {
        if (!lancamentos || lancamentos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${canDeleteFinanceiro ? 8 : 7}" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        const hoje = DateHelper.todayISO();

        lancamentos.forEach(l => {
            const tipo = l.tipo || 'D';
            const isReceita = tipo === 'R';

            const descricaoCompleta = l.descricao || i18n.noDescription;
            const descricao = escapeHtml(
                descricaoCompleta.length > 40
                    ? descricaoCompleta.substring(0, 40) + '...'
                    : descricaoCompleta
            );
            const descricaoTitle = escapeHtml(descricaoCompleta);
            const placasCompleta = (l.veiculo_placas || '').trim();
            const placas = escapeHtml(
                placasCompleta.length > 60
                    ? placasCompleta.substring(0, 60) + '...'
                    : placasCompleta
            );
            const placasTitle = escapeHtml(placasCompleta);
            const placasHtml = placasCompleta
                ? `<div class="mt-0.5 text-xs text-slate-500" title="${placasTitle}">${i18n.vehiclePlatesLabel}: ${placas}</div>`
                : '';
            const documento = l.documento ? `<span class="text-xs text-slate-400">${escapeHtml(l.documento)}</span>` : '';

            // Cliente, Fornecedor ou Funcionario (prioridade: cliente > fornecedor > funcionario)
            const clienteFornecedor = escapeHtml(
                l.cliente_nome || l.fornecedor_nome || l.funcionario_nome || '-'
            );

            // Data de vencimento
            const dataVenci = formatarData(l.data_venci);

            // Valor total com cor baseada no tipo — moeda da filial do lançamento
            const valorNum = l.valor_total || 0;
            const valorFormatado = formatarMoeda(valorNum, l.filial_currency_code, l.filial_locale);
            const valorDisplay = isReceita ? valorFormatado : '-' + valorFormatado;
            const corValor = isReceita ? 'text-green-600' : 'text-red-600';

            // Status baseado em pago e vencimento
            let statusBadge;
            const pago = l.pago === 'S';

            if (pago) {
                statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>${i18n.statusPaid}</span>`;
            } else {
                // Calcular diferenca de dias
                const diffDays = DateHelper.diffDays(hoje, l.data_venci);

                if (diffDays > 0) {
                    // Vence no futuro
                    const diasTexto = diffDays === 1 ? i18n.daySingular : i18n.daysPlural.replace(':count', diffDays);
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">${i18n.statusDueIn.replace(':days', diasTexto)}</span>`;
                } else if (diffDays === 0) {
                    // Vence hoje
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-600">${i18n.statusDueToday}</span>`;
                } else {
                    // Ja venceu
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-900">${i18n.statusOverdue}</span>`;
                }
            }

            const botaoNfse = montarBotaoNfse(l, pago, isReceita);

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    ${canDeleteFinanceiro ? `<td class="table-cell w-12 text-center"><input type="checkbox" class="lancamento-check rounded border-slate-300 text-red-600 focus:ring-red-500" value="${l.id}"></td>` : ''}
                    <td class="table-cell w-16 text-center text-slate-500">${l.sequencia || '-'}</td>
                    <td class="table-cell">
                        <div class="font-medium" title="${descricaoTitle}">${descricao}</div>
                        ${placasHtml}
                        ${documento ? `<div class="mt-0.5">${documento}</div>` : ''}
                    </td>
                    <td class="table-cell hidden sm:table-cell text-slate-600">${clienteFornecedor}</td>
                    <td class="table-cell hidden md:table-cell text-center">${dataVenci}</td>
                    <td class="table-cell hidden lg:table-cell text-right font-medium ${corValor}">${valorDisplay}</td>
                    <td class="table-cell w-36 text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-48 text-right">
                        ${!pago && isReceita ? `<button title="${i18n.paymentLink}" class="btn-icon text-blue-600 hover:text-blue-800 btn-payment-link" data-id="${l.id}"><i class="fas fa-external-link-alt"></i></button>` : ''}
                        ${botaoNfse}
                        <button title="${i18n.printSend}" class="btn-icon text-blue-600 hover:text-blue-800 btn-imprimir-fatura" data-id="${l.id}"><i class="fas fa-print"></i></button>
                        <button title="${i18n.edit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${l.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.delete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${l.id}" data-name="${descricao}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        configurarSelecaoLancamentos();

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/financeiro/adicionar?id=' + id);
            });
        });

        // Event listeners para botoes de excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || i18n.thisEntry;

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: i18n.recordType,
                        confirmType: 'none'
                    }, '*');
                }
            });
        });

        // Event listeners para botoes de link de pagamento
        tbody.querySelectorAll('.btn-payment-link').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirLinkPagamento(id, this);
            });
        });

        // Event listeners para botoes de NFS-e
        tbody.querySelectorAll('.btn-nfse-emit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/nfse/emitir?id_financeiro=' + id);
            });
        });

        tbody.querySelectorAll('.btn-nfse-view').forEach(button => {
            button.addEventListener('click', function () {
                const nfseId = this.getAttribute('data-nfse-id');
                navegarPara('/pages/nfse/' + nfseId + '/visualizar');
            });
        });

        tbody.querySelectorAll('.btn-nfse-resend').forEach(button => {
            button.addEventListener('click', function () {
                nfseReenvioPendente = this.getAttribute('data-nfse-id');
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.resendNfseTitle,
                    message: i18n.resendNfseConfirm,
                    confirmText: i18n.resendNfse
                }, '*');
            });
        });

        // Event listeners para botoes de imprimir/enviar fatura
        tbody.querySelectorAll('.btn-imprimir-fatura').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openOffcanvasIframe',
                        url: '/pages/financeiro/offcanvas-impressao?id=' + id,
                        title: i18n.printSendTitle,
                        width: '500px'
                    }, '*');
                }
            });
        });
    }

    // ===== FORMATACAO =====

    /**
     * Formata valor na moeda da filial do lançamento.
     * Se currency_code/locale não vierem (legado), cai pro padrão pt-BR/BRL.
     */
    function formatarMoeda(valor, currencyCode, locale) {
        const cur = currencyCode || 'BRL';
        const loc = (locale || 'pt_BR').replace('_', '-');
        try {
            return new Intl.NumberFormat(loc, { style: 'currency', currency: cur }).format(valor);
        } catch (e) {
            // Locale/currency inválido (fallback)
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
        }
    }

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        return DateHelper.format(dataStr);
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
        carregarLancamentos(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarLancamentos(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Botao Novo Lancamento - Navega para pagina de adicionar
    document.getElementById('btnNovoLancamento')?.addEventListener('click', function () {
        navegarPara('/pages/financeiro/adicionar');
    });

    // Filtro de Filial
    document.getElementById('filterFilial')?.addEventListener('change', function (e) {
        filterFilial = e.target.value;
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    // Filtro de Ano
    document.getElementById('filterAno')?.addEventListener('change', function (e) {
        filterAno = e.target.value;
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    // Filtro de Mes
    document.getElementById('filterMes')?.addEventListener('change', function (e) {
        filterMes = e.target.value;
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    document.getElementById('filterStatus')?.addEventListener('change', function (e) {
        filterStatus = e.target.value;
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    document.getElementById('filterTipo')?.addEventListener('change', function (e) {
        filterTipo = e.target.value;
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    // Botao Limpar Filtros
    document.getElementById('btnLimparFiltros')?.addEventListener('click', function () {
        document.getElementById('filterFilial').value = '';
        document.getElementById('filterAno').value = '';
        document.getElementById('filterMes').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterTipo').value = '';
        filterFilial = '';
        filterAno = '';
        filterMes = '';
        filterStatus = '';
        filterTipo = '';
        currentPage = 1;
        carregarLancamentos(currentPage, perPage, searchTerm);
    });

    // ===== EXCLUSAO =====

    function configurarSelecaoLancamentos() {
        if (!canDeleteFinanceiro) return;

        const checkTodos = document.getElementById('checkTodosLancamentos');
        const checks = Array.from(tbody.querySelectorAll('.lancamento-check'));

        checks.forEach(check => {
            check.addEventListener('change', function () {
                const id = parseInt(this.value, 10);
                if (this.checked) {
                    lancamentosSelecionados.add(id);
                } else {
                    lancamentosSelecionados.delete(id);
                }
                atualizarControlesSelecaoLancamentos();
            });
        });

        if (checkTodos) {
            checkTodos.checked = false;
            checkTodos.indeterminate = false;
            checkTodos.onchange = function () {
                checks.forEach(check => {
                    check.checked = this.checked;
                    const id = parseInt(check.value, 10);
                    if (this.checked) {
                        lancamentosSelecionados.add(id);
                    } else {
                        lancamentosSelecionados.delete(id);
                    }
                });
                atualizarControlesSelecaoLancamentos();
            };
        }
    }

    function atualizarControlesSelecaoLancamentos() {
        if (!canDeleteFinanceiro) return;

        const quantidade = lancamentosSelecionados.size;
        const checks = Array.from(tbody.querySelectorAll('.lancamento-check'));
        const marcados = checks.filter(check => check.checked).length;
        const checkTodos = document.getElementById('checkTodosLancamentos');
        const botao = document.getElementById('btnExcluirSelecionados');
        const texto = document.getElementById('btnExcluirSelecionadosTexto');

        if (checkTodos) {
            checkTodos.checked = checks.length > 0 && marcados === checks.length;
            checkTodos.indeterminate = marcados > 0 && marcados < checks.length;
        }
        if (botao) {
            botao.classList.toggle('hidden', quantidade === 0);
            botao.classList.toggle('flex', quantidade > 0);
        }
        if (texto) {
            texto.textContent = i18n.deleteSelected.replace(':count', quantidade);
        }
    }

    function limparSelecaoLancamentos() {
        lancamentosSelecionados.clear();
        atualizarControlesSelecaoLancamentos();
    }

    document.getElementById('btnExcluirSelecionados')?.addEventListener('click', function () {
        if (lancamentosSelecionados.size === 0) return;

        window.parent.postMessage({
            action: 'openDeleteModal',
            recordId: Array.from(lancamentosSelecionados).join(','),
            recordName: i18n.selectedEntries.replace(':count', lancamentosSelecionados.size),
            recordType: i18n.batchDeleteType,
            confirmType: 'text',
            customAction: 'excluirLancamentosLote'
        }, '*');
    });

    async function excluirLancamentosLote() {
        const ids = Array.from(lancamentosSelecionados);
        if (ids.length === 0) return;

        try {
            const result = await API.post('/financeiro/excluir-lote', { ids });
            const ignorados = result.data?.ignorados || [];
            const pendencias = [...ignorados, ...(result.data?.avisos || [])];

            if ((result.data?.excluidos || 0) > 0) {
                await carregarLancamentos(currentPage, perPage, searchTerm);
            }

            if (pendencias.length > 0) {
                window.parent.postMessage({
                    action: 'openValidationModal',
                    errors: [{
                        tabName: i18n.batchDeletePartialTitle,
                        fields: pendencias.map(item => `#${item.id} - ${item.identificador}: ${item.motivo}`)
                    }]
                }, '*');
            } else {
                openAlert(result.message || i18n.batchDeleteError);
            }
        } catch (error) {
            console.error('Erro ao excluir lancamentos em lote:', error);
            openAlert(i18n.batchDeleteError);
        }
    }

    async function excluirLancamento(id) {
        try {
            const result = await API.post(`/financeiro/${id}/excluir`);

            if (result.success) {
                carregarLancamentos(currentPage, perPage, searchTerm);
            } else {
                openAlert(result.message || i18n.deleteError);
            }
        } catch (error) {
            console.error('Erro:', error);
            openAlert(i18n.deleteError);
        }
    }

    // ===== NFS-E =====

    async function reenviarNfse(id) {
        if (!id) return;

        try {
            const result = await API.post(`/nfse/${id}/reenviar`, {});
            const message = result.success
                ? (result.message || i18n.resendNfseSuccess)
                : (result.message || i18n.resendNfseError);

            window.parent.postMessage({ action: 'openAlert', message }, '*');

            if (result.success) {
                carregarLancamentos(currentPage, perPage, searchTerm);
            }
        } catch (error) {
            console.error('Erro ao reenviar NFS-e:', error);
            if (await confirmarReenvioNfseAutorizado(id)) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.resendNfseSuccess }, '*');
                carregarLancamentos(currentPage, perPage, searchTerm);
                return;
            }
            window.parent.postMessage({ action: 'openAlert', message: i18n.resendNfseError }, '*');
        }
    }

    async function confirmarReenvioNfseAutorizado(id) {
        try {
            const result = await API.get(`/api/nfse/${id}`);
            return result?.success && result?.data?.status === 'autorizada';
        } catch (error) {
            return false;
        }
    }

    window.addEventListener('message', function (event) {
        if (event.data && event.data.action === 'genericConfirmed' && nfseReenvioPendente) {
            const nfseId = nfseReenvioPendente;
            nfseReenvioPendente = null;
            reenviarNfse(nfseId);
        }

        if (event.data && event.data.action === 'genericModalClosed') {
            nfseReenvioPendente = null;
        }
    });

    // ===== LINK DE PAGAMENTO =====

    async function abrirLinkPagamento(id, button) {
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        try {
            const result = await API.get('/api/financeiro/' + id + '/link-pagamento');

            if (result.success && result.url) {
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openLinkModal',
                        url: result.url
                    }, '*');
                } else {
                    window.open(result.url, '_blank');
                }
            } else {
                openAlert(result.message || i18n.paymentLinkError);
            }
        } catch (error) {
            console.error('Erro:', error);
            openAlert(i18n.paymentLinkError);
        } finally {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    function openAlert(message) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'openAlert', message }, '*');
        } else {
            console.error(message);
        }
    }

    // ===== LISTENER DE MENSAGENS =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmacao de exclusao do parent
        if (event.data.action === 'confirmDelete' && event.data.customAction === 'excluirLancamentosLote') {
            excluirLancamentosLote();
        } else if (event.data.action === 'confirmDelete' && !event.data.customAction) {
            excluirLancamento(event.data.recordId);
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
    carregarFiliais();
    popularAnos();
    carregarLancamentos(currentPage, perPage, searchTerm);
})();
</script>
@endsection
