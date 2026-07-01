@extends('layouts.iframe')

@section('title', t('modules.logs.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="logs-header flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.logs.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.logs.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
        </div>
    </div>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="inline-flex rounded-md border border-slate-200 bg-white p-1 w-full sm:w-auto">
            <button type="button" id="tabAuditoria" class="px-3 py-2 text-sm font-medium rounded bg-sky-600 text-white flex-1 sm:flex-none">
                <?= t('modules.logs.tabs.audit') ?>
            </button>
            <button type="button" id="tabEnvios" class="px-3 py-2 text-sm font-medium rounded text-slate-600 hover:bg-slate-100 flex-1 sm:flex-none">
                <?= t('modules.logs.tabs.messages') ?>
            </button>
        </div>

        <div id="enviosFilters" class="hidden flex flex-col gap-2 sm:flex-row sm:items-center">
            <select id="typeFilter" class="form-input-focus select-pagination">
                <option value=""><?= t('modules.logs.filters.all_channels') ?></option>
                <option value="email"><?= t('modules.logs.channels.email') ?></option>
                <option value="whatsapp"><?= t('modules.logs.channels.whatsapp') ?></option>
                <option value="sms"><?= t('modules.logs.channels.sms') ?></option>
            </select>
            <select id="statusFilter" class="form-input-focus select-pagination">
                <option value=""><?= t('modules.logs.filters.all_statuses') ?></option>
                <option value="pending"><?= t('modules.logs.status.pending') ?></option>
                <option value="processing"><?= t('modules.logs.status.processing') ?></option>
                <option value="sent"><?= t('modules.logs.status.sent') ?></option>
                <option value="failed"><?= t('modules.logs.status.failed') ?></option>
            </select>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr id="tableHeaderRow">
                    <th class="table-header"><?= t('modules.logs.table.date') ?></th>
                    <th class="table-header hidden md:table-cell"><?= t('modules.logs.table.user') ?></th>
                    <th class="table-header"><?= t('modules.logs.table.message') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.logs.table.ip') ?></th>
                    <th class="table-header px-2 w-20 text-center"><?= t('modules.logs.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="logsTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.logs.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= str_replace([':start', ':end', ':total'], ['0', '0', '0'], t('modules.logs.pagination.showing')) ?></span>
        </div>
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px" id="paginationNav">
                <li><button class="pagination-button arrow-button rounded-l-md" disabled><i class="fas fa-chevron-left"></i></button></li>
                <li><button class="pagination-button numbered active">1</button></li>
                <li><button class="pagination-button arrow-button rounded-r-md" disabled><i class="fas fa-chevron-right"></i></button></li>
            </ul>
        </nav>
    </div>

    <div id="loadingOverlay" class="hidden fixed inset-0 bg-white/80 flex items-center justify-center z-50">
        <div class="flex flex-col items-center">
            <i class="fas fa-spinner fa-spin text-3xl text-sky-600"></i>
            <span class="mt-2 text-sm text-slate-600"><?= t('common.labels.loading') ?></span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            noRecords: <?= json_encode(t('modules.logs.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            loadError: <?= json_encode(t('modules.logs.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            serverError: <?= json_encode(t('modules.logs.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            detailsTitle: <?= json_encode(t('modules.logs.details_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            emptyValue: <?= json_encode(t('modules.logs.empty_value'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            unrecognizedFormat: <?= json_encode(t('modules.logs.unrecognized_format'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            viewDetails: <?= json_encode(t('modules.logs.view_details'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            noDetails: <?= json_encode(t('modules.logs.no_details'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            showingPagination: <?= json_encode(t('modules.logs.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            showingLazy: <?= json_encode(t('modules.logs.pagination.showing_lazy'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            sentHint: <?= json_encode(t('modules.logs.messages.sent_hint'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            payloadTitle: <?= json_encode(t('modules.logs.payload_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            auditTab: <?= json_encode(t('modules.logs.tabs.audit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            messagesTab: <?= json_encode(t('modules.logs.tabs.messages'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            table: {
                date: <?= json_encode(t('modules.logs.table.date'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                user: <?= json_encode(t('modules.logs.table.user'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                message: <?= json_encode(t('modules.logs.table.message'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                ip: <?= json_encode(t('modules.logs.table.ip'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                actions: <?= json_encode(t('modules.logs.table.actions'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                channel: <?= json_encode(t('modules.logs.table.channel'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                recipient: <?= json_encode(t('modules.logs.table.recipient'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                status: <?= json_encode(t('modules.logs.table.status'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                error: <?= json_encode(t('modules.logs.table.error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                processedAt: <?= json_encode(t('modules.logs.table.processed_at'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            },
            channels: {
                email: <?= json_encode(t('modules.logs.channels.email'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                whatsapp: <?= json_encode(t('modules.logs.channels.whatsapp'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                sms: <?= json_encode(t('modules.logs.channels.sms'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            },
            status: {
                pending: <?= json_encode(t('modules.logs.status.pending'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                processing: <?= json_encode(t('modules.logs.status.processing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                sent: <?= json_encode(t('modules.logs.status.sent'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                failed: <?= json_encode(t('modules.logs.status.failed'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                skipped: <?= json_encode(t('modules.logs.status.skipped'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            },
        };

        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let searchTimeout = null;
        let activeView = 'audit';
        let typeFilter = '';
        let statusFilter = '';
        let enviosDetalhes = new Map();

        function showLoading() {
            document.getElementById('loadingOverlay')?.classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay')?.classList.add('hidden');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function atualizarCabecalhoTabela() {
            const row = document.getElementById('tableHeaderRow');
            if (!row) return;

            if (activeView === 'messages') {
                row.innerHTML = `
                    <th class="table-header">${i18n.table.date}</th>
                    <th class="table-header">${i18n.table.channel}</th>
                    <th class="table-header">${i18n.table.recipient}</th>
                    <th class="table-header">${i18n.table.message}</th>
                    <th class="table-header hidden lg:table-cell">${i18n.table.status}</th>
                    <th class="table-header hidden xl:table-cell">${i18n.table.processedAt}</th>
                    <th class="table-header px-2 w-20 text-center">${i18n.table.actions}</th>
                `;
                return;
            }

            row.innerHTML = `
                <th class="table-header">${i18n.table.date}</th>
                <th class="table-header hidden md:table-cell">${i18n.table.user}</th>
                <th class="table-header">${i18n.table.message}</th>
                <th class="table-header hidden lg:table-cell">${i18n.table.ip}</th>
                <th class="table-header px-2 w-20 text-center">${i18n.table.actions}</th>
            `;
        }

        function carregarAtual(page = currentPage) {
            if (activeView === 'messages') {
                carregarEnvios(page, perPage, searchTerm);
                return;
            }

            carregarLogs(page, perPage, searchTerm);
        }

        async function carregarLogs(page = 1, recordsPerPage = 10, search = '') {
            showLoading();

            try {
                const result = await API.get('/api/logs', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search
                });

                if (result.success) {
                    renderLogs(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    console.error('Erro ao carregar logs:', result.message);
                    mostrarMensagemErro(i18n.loadError);
                }
            } catch (error) {
                console.error('Erro ao buscar logs:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            } finally {
                hideLoading();
            }
        }

        async function carregarEnvios(page = 1, recordsPerPage = 10, search = '') {
            showLoading();

            try {
                const result = await API.get('/api/logs/envios', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search,
                    type: typeFilter,
                    status: statusFilter
                });

                if (result.success) {
                    renderEnvios(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    console.error('Erro ao carregar envios:', result.message);
                    mostrarMensagemErro(i18n.loadError);
                }
            } catch (error) {
                console.error('Erro ao buscar envios:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            } finally {
                hideLoading();
            }
        }

        function mostrarMensagemErro(mensagem) {
            const tbody = document.querySelector('#logsTableBody');
            if (!tbody) return;
            const colspan = activeView === 'messages' ? 7 : 5;
            tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${escapeHtml(mensagem)}
                </td>
            </tr>
        `;
        }

        function formatarData(dataString) {
            if (!dataString) return '-';
            return DateHelper.formatDateTime(dataString);
        }

        function renderLogs(logs) {
            const tbody = document.querySelector('#logsTableBody');
            if (!tbody) return;

            if (logs.length === 0) {
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
            logs.forEach(log => {
                const data = formatarData(log.data);
                const usuario = log.usuario_nome || '-';
                const mensagem = log.mensagem || '-';
                const ip = log.ip || '-';
                const temDetalhes = log.campos_alterados && log.campos_alterados.trim() !== '';

                const mensagemEscapada = mensagem.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const camposAlterados = temDetalhes ? log.campos_alterados.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell whitespace-nowrap">${data}</td>
                    <td class="table-cell hidden md:table-cell">${usuario}</td>
                    <td class="table-cell">${mensagemEscapada}</td>
                    <td class="table-cell hidden lg:table-cell">${ip}</td>
                    <td class="table-cell px-2 w-20 text-right">
                        ${temDetalhes ? `
                            <button title="${i18n.viewDetails}" class="btn-icon text-sky-600 hover:text-sky-800 btn-ver-detalhes" data-detalhes="${camposAlterados}">
                                <i class="fas fa-eye"></i>
                            </button>
                        ` : `
                            <span class="text-slate-300" title="${i18n.noDetails}"><i class="fas fa-minus"></i></span>
                        `}
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            tbody.querySelectorAll('.btn-ver-detalhes').forEach(button => {
                button.addEventListener('click', function() {
                    const detalhes = this.getAttribute('data-detalhes');
                    abrirPainel(detalhes);
                });
            });
        }

        function resumoEnvio(envio) {
            return envio.assunto || envio.mensagem_texto || envio.legenda || '-';
        }

        function limitarTexto(texto, limite = 180) {
            texto = String(texto || '');
            return texto.length > limite ? texto.substring(0, limite - 1) + '...' : texto;
        }

        function statusClass(status) {
            const classes = {
                sent: 'bg-green-100 text-green-700',
                failed: 'bg-red-100 text-red-700',
                pending: 'bg-amber-100 text-amber-700',
                processing: 'bg-sky-100 text-sky-700',
                skipped: 'bg-slate-100 text-slate-700',
            };

            return classes[status] || 'bg-slate-100 text-slate-700';
        }

        function renderEnvios(envios) {
            const tbody = document.querySelector('#logsTableBody');
            if (!tbody) return;

            if (envios.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            enviosDetalhes.clear();
            let tableRows = '';
            envios.forEach(envio => {
                const status = envio.status || '-';
                const canal = envio.type || '-';
                const resumo = resumoEnvio(envio);
                const erro = envio.error_message || '';
                let payloadDetalhado = null;
                try {
                    payloadDetalhado = envio.payload ? JSON.parse(envio.payload) : null;
                } catch (e) {
                    payloadDetalhado = envio.payload || null;
                }

                const detalhes = {
                    id: envio.id,
                    canal: canal,
                    status: status,
                    destinatario: envio.destinatario || '',
                    destinatario_nome: envio.destinatario_nome || '',
                    resumo: resumo,
                    erro: erro,
                    tentativas: envio.attempts,
                    criado_em: envio.created_at,
                    processado_em: envio.processed_at,
                    lote: envio.batch_id || '',
                    observacao: canal === 'whatsapp' && status === 'sent' ? i18n.sentHint : '',
                    payload: payloadDetalhado,
                };
                enviosDetalhes.set(String(envio.id), detalhes);
                const resumoSeguro = escapeHtml(limitarTexto(resumo));
                const erroSeguro = escapeHtml(erro);
                const destinatario = envio.destinatario_nome
                    ? `${escapeHtml(envio.destinatario_nome)}<br><span class="text-xs text-slate-500">${escapeHtml(envio.destinatario || '-')}</span>`
                    : escapeHtml(envio.destinatario || '-');

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell whitespace-nowrap">${formatarData(envio.created_at)}</td>
                    <td class="table-cell">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
                            ${escapeHtml(i18n.channels[canal] || canal)}
                        </span>
                    </td>
                    <td class="table-cell">${destinatario}</td>
                    <td class="table-cell">
                        <div>${resumoSeguro}</div>
                        ${erro ? `<div class="mt-1 text-xs text-red-600">${erroSeguro}</div>` : ''}
                    </td>
                    <td class="table-cell hidden lg:table-cell">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusClass(status)}">
                            ${escapeHtml(i18n.status[status] || status)}
                        </span>
                    </td>
                    <td class="table-cell hidden xl:table-cell whitespace-nowrap">${formatarData(envio.processed_at)}</td>
                    <td class="table-cell px-2 w-20 text-right">
                        <button title="${i18n.viewDetails}" class="btn-icon text-sky-600 hover:text-sky-800 btn-ver-envio" data-id="${escapeHtml(envio.id)}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            tbody.querySelectorAll('.btn-ver-envio').forEach(button => {
                button.addEventListener('click', function() {
                    abrirPainelEnvio(enviosDetalhes.get(this.getAttribute('data-id')));
                });
            });
        }

        function formatarValorLog(valor) {
            if (valor === null || valor === undefined) {
                return i18n.emptyValue;
            }

            if (Array.isArray(valor)) {
                if (valor.length === 0) return i18n.emptyValue;

                let html = '<ul class="list-disc list-inside text-xs space-y-2">';
                valor.forEach(item => {
                    if (typeof item === 'object' && item !== null) {
                        // Tentar campo descritivo primeiro, senao listar todas as chaves
                        const desc = item.descricao || item.item;
                        if (desc) {
                            html += `<li>${desc}</li>`;
                        } else {
                            // Renderizar objeto como lista de chave: valor
                            const entries = Object.entries(item).filter(([, v]) => v !== null && v !== undefined && v !== '');
                            if (entries.length > 0) {
                                html += '<li><div class="ml-1 space-y-0.5">';
                                entries.forEach(([key, val]) => {
                                    html += `<div><span class="font-medium text-slate-600">${key}:</span> ${val}</div>`;
                                });
                                html += '</div></li>';
                            } else {
                                html += `<li>${JSON.stringify(item)}</li>`;
                            }
                        }
                    } else {
                        html += `<li>${item}</li>`;
                    }
                });
                html += '</ul>';
                return html;
            }

            if (typeof valor === 'object') {
                return '<pre class="text-xs">' + JSON.stringify(valor, null, 2) + '</pre>';
            }

            return String(valor);
        }

        function renderizarCampo(item) {
            const campo = item.label || item.campo || 'Campo';
            const de = formatarValorLog(item.de);
            const para = formatarValorLog(item.para);
            const isCriacao = item.de === null || item.de === undefined;

            if (item.txt !== undefined) {
                return `
                <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <div class="font-medium text-slate-800 mb-1">${campo}</div>
                    <div class="text-sm text-slate-600">${item.txt}</div>
                </li>`;
            }

            if (isCriacao) {
                return `
                <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                    <div class="font-medium text-slate-800 mb-2">${campo}</div>
                    <div class="text-sm text-green-600 bg-green-50 px-2 py-1 rounded">${para}</div>
                </li>`;
            }

            return `
            <li class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                <div class="font-medium text-slate-800 mb-2">${campo}</div>
                <div class="flex items-center text-sm">
                    <span class="text-red-600 bg-red-50 px-2 py-1 rounded">${de}</span>
                    <i class="fas fa-arrow-right mx-2 text-slate-400"></i>
                    <span class="text-green-600 bg-green-50 px-2 py-1 rounded">${para}</span>
                </div>
            </li>`;
        }

        function abrirPainel(detalhesJson) {
            let html = '';

            try {
                const detalhes = JSON.parse(detalhesJson);

                if (typeof detalhes === 'object' && !Array.isArray(detalhes)) {
                    const abas = Object.keys(detalhes);
                    if (abas.length > 0 && Array.isArray(detalhes[abas[0]])) {
                        html = '<div class="space-y-4">';
                        abas.forEach(nomeAba => {
                            const campos = detalhes[nomeAba];
                            if (!Array.isArray(campos) || campos.length === 0) return;

                            html += `
                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                <div class="bg-slate-100 px-3 py-2 font-medium text-slate-700 text-sm border-b border-slate-200">
                                    <i class="fas fa-folder-open mr-2 text-slate-400"></i>${nomeAba}
                                </div>
                                <ul class="p-3 space-y-2">
                            `;
                            campos.forEach(item => {
                                html += renderizarCampo(item);
                            });
                            html += '</ul></div>';
                        });
                        html += '</div>';
                    } else {
                        html = '<div class="bg-slate-50 p-3 rounded-lg border border-slate-200">';
                        html += '<pre class="text-sm text-slate-700 whitespace-pre-wrap">' + JSON.stringify(detalhes, null, 2) + '</pre>';
                        html += '</div>';
                    }
                }
                else if (Array.isArray(detalhes) && detalhes.length > 0) {
                    html = '<ul class="space-y-3">';
                    detalhes.forEach(item => {
                        if (item.aba) {
                            html += `<li class="text-xs text-slate-400 -mb-2 mt-2">${item.aba}</li>`;
                        }
                        html += renderizarCampo(item);
                    });
                    html += '</ul>';
                } else {
                    html = '<p class="text-slate-500">' + i18n.unrecognizedFormat + '</p>';
                }
            } catch (e) {
                html = '<div class="bg-slate-50 p-3 rounded-lg border border-slate-200">';
                html += '<pre class="text-sm text-slate-700 whitespace-pre-wrap">' + detalhesJson + '</pre>';
                html += '</div>';
            }

            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openOffcanvasContent',
                    content: html,
                    title: i18n.detailsTitle,
                    width: '40%'
                }, '*');
            }
        }

        function abrirPainelEnvio(detalhesInput) {
            let detalhes = detalhesInput || {};

            if (typeof detalhesInput === 'string') {
                try {
                    detalhes = JSON.parse(detalhesInput || '{}');
                } catch (e) {
                    detalhes = { erro: detalhesInput || '' };
                }
            }

            const rows = [
                [i18n.table.channel, i18n.channels[detalhes.canal] || detalhes.canal || '-'],
                [i18n.table.status, i18n.status[detalhes.status] || detalhes.status || '-'],
                [i18n.table.recipient, detalhes.destinatario_nome ? `${detalhes.destinatario_nome} (${detalhes.destinatario})` : (detalhes.destinatario || '-')],
                [i18n.table.message, detalhes.resumo || '-'],
                [i18n.table.error, detalhes.erro || '-'],
                [i18n.table.date, detalhes.criado_em ? formatarData(detalhes.criado_em) : '-'],
                [i18n.table.processedAt, detalhes.processado_em ? formatarData(detalhes.processado_em) : '-'],
                ['ID', detalhes.id || '-'],
                ['Batch', detalhes.lote || '-'],
                ['Tentativas', detalhes.tentativas ?? '-'],
            ];

            let html = '<div class="space-y-4">';
            if (detalhes.observacao) {
                html += `<div class="rounded border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">${escapeHtml(detalhes.observacao)}</div>`;
            }

            html += '<div class="border border-slate-200 rounded-lg overflow-hidden">';
            rows.forEach(([label, value]) => {
                html += `
                    <div class="grid grid-cols-3 border-b border-slate-100 last:border-b-0">
                        <div class="bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">${escapeHtml(label)}</div>
                        <div class="col-span-2 px-3 py-2 text-sm text-slate-800 break-words">${escapeHtml(value)}</div>
                    </div>
                `;
            });
            html += '</div>';

            if (detalhes.payload) {
                html += '<div class="border border-slate-200 rounded-lg overflow-hidden">';
                html += `<div class="bg-slate-100 px-3 py-2 font-medium text-slate-700 text-sm">${escapeHtml(i18n.payloadTitle)}</div>`;
                html += '<pre class="p-3 text-xs text-slate-700 whitespace-pre-wrap overflow-auto">' + escapeHtml(JSON.stringify(detalhes.payload, null, 2)) + '</pre>';
                html += '</div>';
            }
            html += '</div>';

            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openOffcanvasContent',
                    content: html,
                    title: i18n.payloadTitle,
                    width: '40%'
                }, '*');
            }
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement) return;

            const { page, perPage, total } = pagination;

            if (total === null) {
                const start = ((page - 1) * perPage) + 1;
                const end = start + perPage - 1;
                infoElement.textContent = i18n.showingLazy.replace(':start', start).replace(':end', end);
            } else {
                const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
                const end = Math.min(page * perPage, total);
                infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
            }
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.getElementById('paginationNav');
            if (!paginationNav) return;

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

            if (totalPages === null) {
                buttons += `
                <li>
                    <button class="pagination-button numbered active">${page}</button>
                </li>
            `;
            } else {
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
            carregarAtual(currentPage);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarAtual(currentPage);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarAtual(currentPage);
            }, 300);
        });

        function ativarAba(view) {
            activeView = view;
            currentPage = 1;
            const isMessages = view === 'messages';
            const tabAuditoria = document.getElementById('tabAuditoria');
            const tabEnvios = document.getElementById('tabEnvios');
            const enviosFilters = document.getElementById('enviosFilters');

            tabAuditoria?.classList.toggle('bg-sky-600', !isMessages);
            tabAuditoria?.classList.toggle('text-white', !isMessages);
            tabAuditoria?.classList.toggle('text-slate-600', isMessages);
            tabAuditoria?.classList.toggle('hover:bg-slate-100', isMessages);

            tabEnvios?.classList.toggle('bg-sky-600', isMessages);
            tabEnvios?.classList.toggle('text-white', isMessages);
            tabEnvios?.classList.toggle('text-slate-600', !isMessages);
            tabEnvios?.classList.toggle('hover:bg-slate-100', !isMessages);

            enviosFilters?.classList.toggle('hidden', !isMessages);
            atualizarCabecalhoTabela();
            carregarAtual(currentPage);
        }

        document.getElementById('tabAuditoria')?.addEventListener('click', function() {
            ativarAba('audit');
        });

        document.getElementById('tabEnvios')?.addEventListener('click', function() {
            ativarAba('messages');
        });

        document.getElementById('typeFilter')?.addEventListener('change', function(e) {
            typeFilter = e.target.value;
            currentPage = 1;
            carregarAtual(currentPage);
        });

        document.getElementById('statusFilter')?.addEventListener('change', function(e) {
            statusFilter = e.target.value;
            currentPage = 1;
            carregarAtual(currentPage);
        });

        atualizarCabecalhoTabela();
        carregarAtual(currentPage);
    })();
</script>
@endsection
