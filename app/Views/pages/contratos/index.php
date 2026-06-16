@extends('layouts.iframe')

@section('title', t('modules.contratos.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.contratos.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select id="filterStatus" class="form-input-focus w-32">
                <option value=""><?= t('modules.contratos.filters.all') ?></option>
                <option value="A" selected><?= t('modules.contratos.filters.active') ?></option>
                <option value="F"><?= t('modules.contratos.filters.finalized') ?></option>
            </select>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.contratos.filters.search_placeholder') ?>" class="form-input-focus sm:w-72 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovoContrato" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header px-2 w-10 text-center"></th>
                    <th class="table-header"><?= t('modules.contratos.table.seq') ?></th>
                    <th class="table-header"><?= t('modules.contratos.table.code') ?></th>
                    <th class="table-header"><?= t('modules.contratos.table.client') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.contratos.table.vehicle') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.contratos.table.start') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.contratos.table.end_renewal') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.contratos.table.info') ?></th>
                    <th class="table-header px-2 w-64 text-center"><?= t('common.labels.actions') ?></th>
                </tr>
            </thead>
            <tbody id="contratosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.contratos.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= t('modules.contratos.pagination.showing', ['start' => 0, 'end' => 0, 'total' => 0]) ?></span>
        </div>
        <nav id="paginationNav" aria-label="<?= htmlspecialchars(t('modules.contratos.pagination.page_navigation'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 sm:mt-0">
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
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsT = static fn(string $key, array $replace = []): string => $jsText(t($key, $replace));
?>
<script>
    (function() {
        const i18n = <?= json_encode([
            'loading' => t('common.labels.loading'),
            'loadError' => t('modules.contratos.messages.load_error'),
            'connectionError' => t('modules.contratos.messages.connection_error'),
            'noContracts' => t('modules.contratos.messages.no_contracts'),
            'statusDelivered' => t('modules.contratos.status.delivered'),
            'statusExpired' => t('modules.contratos.status.expired'),
            'statusRenewalIn' => t('modules.contratos.status.renewal_in'),
            'statusAuto' => t('modules.contratos.status.auto'),
            'statusEnding' => t('modules.contratos.status.ending'),
            'btnReturn' => t('modules.contratos.buttons.return'),
            'btnReplace' => t('modules.contratos.buttons.replace_vehicle'),
            'btnSyncRenewal' => t('modules.contratos.buttons.regularize_renewal'),
            'btnPrint' => t('common.buttons.print'),
            'btnOdometer' => 'Registrar odômetro',
            'btnOdometerDisabled' => 'Disponível apenas para contratos ativos com veículo',
            'btnOdometerNoPermission' => 'Sem permissão para registrar odômetro',
            'odometerTitle' => 'Registrar odômetro',
            'btnSignature' => t('modules.contratos.buttons.signature'),
            'btnEdit' => t('common.buttons.edit'),
            'btnDelete' => t('common.buttons.delete'),
            'recordType' => t('modules.contratos.record_type'),
            'deleteWarning' => t('modules.contratos.messages.delete_warning'),
            'thisContract' => t('modules.contratos.messages.this_contract'),
            'deleteError' => t('modules.contratos.messages.delete_error'),
            'signatureRemoved' => t('modules.contratos.messages.signature_removed'),
            'signatureClearError' => t('modules.contratos.messages.signature_clear_error'),
            'signatureCopied' => t('modules.contratos.messages.signature_copied'),
            'signatureLoadError' => t('modules.contratos.messages.signature_load_error'),
            'loadContractError' => t('modules.contratos.messages.load_contract_error'),
            'printTitle' => t('modules.contratos.print.title'),
            'paginationShowing' => t('modules.contratos.pagination.showing'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        // Estado da paginacao
        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let statusFilter = 'A';
        let searchTimeout = null;
        const canRegistrarOdometro = <?= !empty($permissions['registrar_odometro']) ? 'true' : 'false' ?>;

        // Elementos
        const tbody = document.getElementById('contratosTableBody');

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

        async function carregarContratos(page = 1, recordsPerPage = 10, search = '', status = '') {
            try {
                mostrarLoading();

                const result = await API.get('/api/contratos', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search,
                    status: status
                });

                if (result.success) {
                    renderContratos(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    mostrarMensagemErro(i18n.loadError.replace(':message', result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao buscar contratos:', error);
                mostrarMensagemErro(error.message || i18n.connectionError);
            }
        }

        function mostrarLoading() {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
        }

        function mostrarMensagemErro(mensagem) {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function renderContratos(contratos) {
            if (!contratos || contratos.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="table-cell text-center text-slate-500">
                        <i class="fas fa-file-contract mr-2"></i>${i18n.noContracts}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            contratos.forEach(c => {
                const sequencia = c.sequencia || '-';
                const codigo = escapeHtml(c.codigo || '-');
                const cliente = escapeHtml(c.cliente_nome || '-');
                const veiculo = escapeHtml(c.veiculo_resumo || c.veiculo_ativo || '-');
                const qtdVeiculos = parseInt(c.qtd_veiculos) || parseInt(c.qtd_veiculos_ativos) || 0;
                const qtdVeiculosAtivos = parseInt(c.qtd_veiculos_ativos) || 0;
                const dataIni = formatarData(c.data_ini);

                // Determinar data final (renovacao ou fim)
                let dataFinal = '';
                if (c.auto_renovacao && c.data_renovacao) {
                    dataFinal = formatarData(c.data_renovacao);
                } else {
                    dataFinal = formatarData(c.data_fim);
                }

                // Badges de informacoes
                let infoBadges = '';
                let autorenovacaoVencida = false;

                // Status finalizado
                if (c.status === 'F') {
                    infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 mr-1">${i18n.statusDelivered}</span>`;
                } else {
                    // Verificar vencimento (apenas para ativos com autorenovacao)
                    if (c.auto_renovacao === 'auto' && c.data_renovacao) {
                        const dataRenov = new Date(c.data_renovacao);
                        dataRenov.setHours(0, 0, 0, 0);
                        const diffDays = Math.ceil((dataRenov - hoje) / (1000 * 60 * 60 * 24));

                        if (diffDays < 0) {
                            autorenovacaoVencida = true;
                            infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 mr-1">${i18n.statusExpired}</span>`;
                        } else if (diffDays <= 7) {
                            infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700 mr-1">${i18n.statusRenewalIn.replace(':days', diffDays + 'd')}</span>`;
                        }
                    }

                    // Badge de autorenovacao
                    if (c.auto_renovacao === 'auto') {
                        infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 mr-1">${i18n.statusAuto}</span>`;
                    } else if (c.auto_renovacao === 'fim') {
                        infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 mr-1">${i18n.statusEnding}</span>`;
                    } else if (c.auto_renovacao && !isNaN(c.auto_renovacao)) {
                        infoBadges += `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 mr-1">${c.auto_renovacao}x</span>`;
                    }
                }

                // Indicador de multiplos veiculos
                let veiculoInfo = veiculo;
                if (qtdVeiculos > 1) {
                    veiculoInfo += ` <span class="text-xs text-slate-500">(+${qtdVeiculos - 1})</span>`;
                }

                // Icone de assinatura (verifica se tem id_assinatura valido)
                const temAssinatura = !!c.id_assinatura;
                const assinaturaClass = temAssinatura ? 'text-green-600' : 'text-slate-400';

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell px-2 text-center">
                        ${canRegistrarOdometro && c.status === 'A' && qtdVeiculosAtivos > 0 ? `
                            <button title="${i18n.btnOdometer}" class="btn-icon text-cyan-600 hover:text-cyan-800 btn-odometro" data-id="${c.id}"><i class="fas fa-gauge-high"></i></button>
                        ` : `
                            <button title="${canRegistrarOdometro ? i18n.btnOdometerDisabled : i18n.btnOdometerNoPermission}" class="btn-icon text-slate-300 cursor-not-allowed" disabled><i class="fas fa-gauge-high"></i></button>
                        `}
                    </td>
                    <td class="table-cell text-slate-500">${sequencia}</td>
                    <td class="table-cell">
                        <span class="font-medium">${codigo}</span>
                    </td>
                    <td class="table-cell">
                        <div class="font-medium">${cliente}</div>
                        <div class="text-sm text-slate-500 md:hidden">${veiculoInfo}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell">${veiculoInfo}</td>
                    <td class="table-cell hidden lg:table-cell">${dataIni}</td>
                    <td class="table-cell hidden lg:table-cell">${dataFinal}</td>
                    <td class="table-cell hidden md:table-cell">${infoBadges || '-'}</td>
                    <td class="table-cell px-2 w-64 text-right">
                        ${c.status === 'A' ? `
                            <button title="${i18n.btnReturn}" class="btn-icon text-green-600 hover:text-green-800 btn-devolver" data-id="${c.id}"><i class="fa-solid fa-arrow-turn-down"></i></button>
                            <button title="${i18n.btnReplace}" class="btn-icon text-amber-600 hover:text-amber-800 btn-substituir" data-id="${c.id}"><i class="fa-solid fa-up-down"></i></button>
                            ${autorenovacaoVencida ? `<button title="${i18n.btnSyncRenewal}" class="btn-icon text-purple-600 hover:text-purple-800 btn-regularizar-renovacao" data-id="${c.id}"><i class="fas fa-sync-alt"></i></button>` : ''}
                        ` : ''}
                        <button title="${i18n.btnPrint}" class="btn-icon text-blue-600 hover:text-blue-800 btn-print" data-id="${c.id}"><i class="fas fa-print"></i></button>
                        <button title="${i18n.btnSignature}" class="btn-icon ${assinaturaClass} btn-assinatura" data-id="${c.id}" data-codigo="${codigo}" data-tem="${temAssinatura ? '1' : '0'}"><i class="fas fa-signature"></i></button>
                        <button title="${i18n.btnEdit}" class="btn-icon text-slate-600 hover:text-slate-800 btn-edit" data-id="${c.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.btnDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${c.id}" data-name="${codigo}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            // Event listeners
            bindEventListeners();
        }

        function bindEventListeners() {
            // Editar
            tbody.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/contratos/editar/' + id);
                });
            });

            // Excluir
            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisContract;

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

            // Devolucao (navegar para tela dedicada)
            tbody.querySelectorAll('.btn-devolver').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/contratos/devolver/' + id);
                });
            });

            // Substituir (navegar para tela dedicada)
            tbody.querySelectorAll('.btn-substituir').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    navegarPara('/pages/contratos/substituir/' + id);
                });
            });

            // Imprimir
            tbody.querySelectorAll('.btn-print').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    abrirModalImpressao(id);
                });
            });

            // Odometro rapido
            tbody.querySelectorAll('.btn-odometro').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    abrirOffcanvasOdometro(id);
                });
            });

            // Regularizar autorenovacao vencida
            tbody.querySelectorAll('.btn-regularizar-renovacao').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    window.parent.postMessage({
                        action: 'openContratoRenovacaoSyncModal',
                        contratoId: id
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
                        // Abre modal para visualizar assinatura
                        abrirModalAssinatura(id, codigo);
                    } else {
                        // Gerar link de assinatura
                        const linkAssinatura = window.location.origin + '/assinar/' + codigo;
                        window.parent.postMessage({
                            action: 'openSignatureLinkModal',
                            tipo: 'contrato',
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

            const {
                page,
                perPage,
                total
            } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = i18n.paginationShowing.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.querySelector('#paginationNav ul');
            if (!paginationNav || !pagination) return;

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
            carregarContratos(currentPage, perPage, searchTerm, statusFilter);
        };

        // ===== EVENT LISTENERS =====

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarContratos(currentPage, perPage, searchTerm, statusFilter);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarContratos(currentPage, perPage, searchTerm, statusFilter);
            }, 300);
        });

        document.getElementById('filterStatus')?.addEventListener('change', function(e) {
            statusFilter = e.target.value;
            currentPage = 1;
            carregarContratos(currentPage, perPage, searchTerm, statusFilter);
        });

        document.getElementById('btnNovoContrato')?.addEventListener('click', function() {
            navegarPara('/pages/contratos/adicionar');
        });

        // ===== ACOES =====

        async function excluirContrato(id) {
            try {
                const result = await API.post(`/contratos/${id}/excluir`);

                if (result.success) {
                    carregarContratos(currentPage, perPage, searchTerm, statusFilter);
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
                const result = await API.post(`/contratos/${id}/limpar-assinatura`);

                if (result.success) {
                    carregarContratos(currentPage, perPage, searchTerm, statusFilter);
                    window.parent.postMessage({ action: 'openAlert', message: i18n.signatureRemoved }, '*');
                } else {
                    window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.signatureClearError }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.signatureClearError }, '*');
            }
        }

        // ===== MODAL ASSINATURA =====

        async function abrirModalAssinatura(id, codigo) {
            try {
                const result = await API.get(`/api/contratos/${id}/assinatura`);
                if (!result.success) {
                    window.parent.postMessage({ action: 'openAlert', message: i18n.signatureLoadError.replace(':message', result.message || '') }, '*');
                    return;
                }

                // Enviar para o parent abrir o modal global
                window.parent.postMessage({
                    action: 'openAssinaturaModal',
                    tipo: 'contrato',
                    contratoId: id,
                    codigo: codigo,
                    data_assinatura: result.data.data_assinatura || '-',
                    ip: result.data.ip || '-',
                    url: result.data.url || ''
                }, '*');
            } catch (error) {
                console.error('Erro ao carregar assinatura:', error);
                window.parent.postMessage({ action: 'openAlert', message: i18n.signatureLoadError.replace(':message', error.message) }, '*');
            }
        }

        // Escutar mensagem do parent para resetar assinatura
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'resetarAssinatura') {
                if (event.data.contratoId) {
                    limparAssinatura(event.data.contratoId);
                }
            }
        });

        // ===== IMPRESSAO (OFFCANVAS) =====

        function abrirModalImpressao(id) {
            window.parent.postMessage({
                action: 'openOffcanvasIframe',
                url: '/pages/contratos/offcanvas-impressao?id=' + id,
                title: i18n.printTitle,
                width: '420px'
            }, '*');
        }

        function abrirOffcanvasOdometro(id) {
            window.parent.postMessage({
                action: 'openOffcanvasIframe',
                url: '/pages/contratos/offcanvas-odometro?id=' + id,
                title: i18n.odometerTitle,
                width: '520px'
            }, '*');
        }

        // ===== LISTENER DE MENSAGENS =====

        window.addEventListener('message', function(event) {
            if (!event.data || !event.data.action) return;

            if (event.data.action === 'confirmDelete') {
                excluirContrato(event.data.recordId);
            } else if (event.data.action === 'contratoRenovacaoRegularizada') {
                carregarContratos(currentPage, perPage, searchTerm, statusFilter);
            } else if (event.data.action === 'contratoOdometroRegistrado') {
                carregarContratos(currentPage, perPage, searchTerm, statusFilter);
            }
        });

        // ===== HELPERS =====

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatarData(data) {
            if (!data) return '-';
            try {
                return DateHelper.format(data);
            } catch {
                return data;
            }
        }

        // Inicializacao
        carregarContratos(currentPage, perPage, searchTerm, statusFilter);
    })();
</script>
@endsection
