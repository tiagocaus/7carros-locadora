@extends('layouts.iframe')

@section('title', t('modules.mensageria.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0">
            <?= t('modules.mensageria.subtitle') ?>
        </h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto flex-wrap gap-2">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.mensageria.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovaConexaoWhatsApp" class="btn-green py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fab fa-whatsapp mr-2"></i><?= t('modules.mensageria.buttons.new_whatsapp') ?>
            </button>
            <button id="btnNovaConexaoSMS" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-sms mr-2"></i><?= t('modules.mensageria.buttons.new_sms') ?>
            </button>
            <button id="btnNovaConexaoSMTP" class="btn-purple py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-envelope mr-2"></i><?= t('modules.mensageria.buttons.new_smtp') ?>
            </button>
        </div>
    </div>

    <!-- Tabela de Conexoes -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header w-16 text-center"><?= t('modules.mensageria.table.type') ?></th>
                    <th class="table-header"><?= t('modules.mensageria.table.linked_branches') ?></th>
                    <th class="table-header hidden md:table-cell w-40"><?= t('modules.mensageria.table.identifier') ?></th>
                    <th class="table-header text-center w-28"><?= t('modules.mensageria.table.status') ?></th>
                    <th class="table-header px-2 w-56 text-center"><?= t('modules.mensageria.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="conexoesTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.mensageria.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"><?= str_replace([':start', ':end', ':total'], ['0', '0', '0'], t('modules.mensageria.pagination.showing')) ?></span>
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
        loading: '<?= addslashes(t("common.labels.loading")) ?>',
        noRecords: '<?= addslashes(t("modules.mensageria.table.no_records")) ?>',
        loadErrorBranches: '<?= addslashes(t("modules.mensageria.table.load_error_branches")) ?>',
        none: '<?= addslashes(t("modules.mensageria.common.none")) ?>',
        selectBranch: '<?= addslashes(t("modules.mensageria.common.select_branch")) ?>',
        showingPagination: '<?= addslashes(t("modules.mensageria.pagination.showing")) ?>',
        // Status badges
        connected: '<?= addslashes(t("modules.mensageria.status.connected")) ?>',
        connecting: '<?= addslashes(t("modules.mensageria.status.connecting")) ?>',
        disconnected: '<?= addslashes(t("modules.mensageria.status.disconnected")) ?>',
        validated: '<?= addslashes(t("modules.mensageria.status.validated")) ?>',
        pending: '<?= addslashes(t("modules.mensageria.status.pending")) ?>',
        invalid: '<?= addslashes(t("modules.mensageria.status.invalid")) ?>',
        unknown: '<?= addslashes(t("modules.mensageria.status.unknown")) ?>',
        // Action titles
        actionTest: '<?= addslashes(t("modules.mensageria.actions.test")) ?>',
        actionRestart: '<?= addslashes(t("modules.mensageria.actions.restart")) ?>',
        actionDisconnect: '<?= addslashes(t("modules.mensageria.actions.disconnect")) ?>',
        actionConnect: '<?= addslashes(t("modules.mensageria.actions.connect")) ?>',
        actionRecreate: '<?= addslashes(t("modules.mensageria.actions.recreate")) ?>',
        actionEdit: '<?= addslashes(t("common.buttons.edit")) ?>',
        actionDelete: '<?= addslashes(t("common.buttons.delete")) ?>',
        actionTestSms: '<?= addslashes(t("modules.mensageria.actions.test_sms")) ?>',
        actionCheckBalance: '<?= addslashes(t("modules.mensageria.actions.check_balance")) ?>',
        actionValidateCredentials: '<?= addslashes(t("modules.mensageria.actions.validate_credentials")) ?>',
        actionTestEmail: '<?= addslashes(t("modules.mensageria.actions.test_email")) ?>',
        actionValidateConnection: '<?= addslashes(t("modules.mensageria.actions.validate_connection")) ?>',
        // Offcanvas titles
        newWhatsapp: '<?= addslashes(t("modules.mensageria.offcanvas.new_whatsapp")) ?>',
        editWhatsapp: '<?= addslashes(t("modules.mensageria.offcanvas.edit_whatsapp")) ?>',
        connectWhatsapp: '<?= addslashes(t("modules.mensageria.offcanvas.connect_whatsapp")) ?>',
        testWhatsapp: '<?= addslashes(t("modules.mensageria.offcanvas.test_whatsapp")) ?>',
        newSms: '<?= addslashes(t("modules.mensageria.offcanvas.new_sms")) ?>',
        editSms: '<?= addslashes(t("modules.mensageria.offcanvas.edit_sms")) ?>',
        testSms: '<?= addslashes(t("modules.mensageria.offcanvas.test_sms")) ?>',
        newSmtp: '<?= addslashes(t("modules.mensageria.offcanvas.new_smtp")) ?>',
        editSmtp: '<?= addslashes(t("modules.mensageria.offcanvas.edit_smtp")) ?>',
        testSmtp: '<?= addslashes(t("modules.mensageria.offcanvas.test_smtp")) ?>',
        // Confirms
        confirmDelete: '<?= addslashes(t("modules.mensageria.confirms.delete")) ?>',
        confirmDisconnect: '<?= addslashes(t("modules.mensageria.confirms.disconnect")) ?>',
        confirmRestart: '<?= addslashes(t("modules.mensageria.confirms.restart")) ?>',
        // Messages
        smtpValidated: '<?= addslashes(t("modules.mensageria.messages.smtp_validated")) ?>',
        smtpValidationFailed: '<?= addslashes(t("modules.mensageria.messages.smtp_validation_failed")) ?>',
        smtpValidateError: '<?= addslashes(t("modules.mensageria.messages.smtp_validate_error")) ?>',
        smtpDeleted: '<?= addslashes(t("modules.mensageria.messages.smtp_deleted")) ?>',
        smtpDeleteError: '<?= addslashes(t("modules.mensageria.messages.smtp_delete_error")) ?>',
        smtpCreated: '<?= addslashes(t("modules.mensageria.messages.smtp_created")) ?>',
        smtpCreateError: '<?= addslashes(t("modules.mensageria.messages.smtp_create_error")) ?>',
        smtpUpdated: '<?= addslashes(t("modules.mensageria.messages.smtp_updated")) ?>',
        smtpUpdateError: '<?= addslashes(t("modules.mensageria.messages.smtp_update_error")) ?>',
        whatsappCreatedShort: '<?= addslashes(t("modules.mensageria.messages.whatsapp_created_short")) ?>',
        whatsappCreateError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_create_error")) ?>',
        whatsappUpdated: '<?= addslashes(t("modules.mensageria.messages.whatsapp_updated")) ?>',
        whatsappUpdateError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_update_error")) ?>',
        whatsappDeleted: '<?= addslashes(t("modules.mensageria.messages.whatsapp_deleted")) ?>',
        whatsappDeleteError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_delete_error")) ?>',
        whatsappDisconnected: '<?= addslashes(t("modules.mensageria.messages.whatsapp_disconnected")) ?>',
        whatsappDisconnectError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_disconnect_error")) ?>',
        whatsappRestarted: '<?= addslashes(t("modules.mensageria.messages.whatsapp_restarted")) ?>',
        whatsappRestartError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_restart_error")) ?>',
        whatsappRecreated: '<?= addslashes(t("modules.mensageria.messages.whatsapp_recreated")) ?>',
        whatsappRecreateError: '<?= addslashes(t("modules.mensageria.messages.whatsapp_recreate_error")) ?>',
        smsCreated: '<?= addslashes(t("modules.mensageria.messages.sms_created")) ?>',
        smsCreateError: '<?= addslashes(t("modules.mensageria.messages.sms_create_error")) ?>',
        smsUpdated: '<?= addslashes(t("modules.mensageria.messages.sms_updated")) ?>',
        smsUpdateError: '<?= addslashes(t("modules.mensageria.messages.sms_update_error")) ?>',
        smsDeleted: '<?= addslashes(t("modules.mensageria.messages.sms_deleted")) ?>',
        smsDeleteError: '<?= addslashes(t("modules.mensageria.messages.sms_delete_error")) ?>',
        smsValidated: '<?= addslashes(t("modules.mensageria.messages.sms_validated")) ?>',
        smsValidationFailed: '<?= addslashes(t("modules.mensageria.messages.sms_validation_failed")) ?>',
        smsValidateError: '<?= addslashes(t("modules.mensageria.messages.sms_validate_error")) ?>',
        smsBalance: '<?= addslashes(t("modules.mensageria.messages.sms_balance")) ?>',
        smsBalanceError: '<?= addslashes(t("modules.mensageria.messages.sms_balance_error")) ?>',
        testSuccess: '<?= addslashes(t("modules.mensageria.messages.test_success")) ?>',
        testError: '<?= addslashes(t("modules.mensageria.messages.test_error")) ?>',
        provideEmail: '<?= addslashes(t("modules.mensageria.messages.provide_email")) ?>',
        sendingEmail: '<?= addslashes(t("modules.mensageria.messages.sending_email")) ?>',
        emailSuccess: '<?= addslashes(t("modules.mensageria.messages.email_test_success")) ?>',
        emailError: '<?= addslashes(t("modules.mensageria.messages.email_test_send_error")) ?>',
        providePhone: '<?= addslashes(t("modules.mensageria.messages.provide_phone")) ?>',
        sendingSms: '<?= addslashes(t("modules.mensageria.messages.sending_sms")) ?>',
        smsTestSuccess: '<?= addslashes(t("modules.mensageria.messages.sms_test_success")) ?>',
        smsTestError: '<?= addslashes(t("modules.mensageria.messages.sms_test_send_error")) ?>',
        // SMTP handler
        passwordHintCustom: '<?= addslashes(t("modules.mensageria.smtp.password_hint_custom")) ?>',
        passwordHintDefault: '<?= addslashes(t("modules.mensageria.smtp.password_hint_default")) ?>',
        smtpCreateValidate: '<?= addslashes(t("modules.mensageria.smtp.create_validate")) ?>',
        createWhatsapp: '<?= addslashes(t("modules.mensageria.whatsapp.create_connection")) ?>',
        smsCreateValidate: '<?= addslashes(t("modules.mensageria.sms.create_validate")) ?>',
        validating: '<?= addslashes(t("common.labels.validating")) ?>',
        creating: '<?= addslashes(t("common.labels.creating")) ?>',
        sending: '<?= addslashes(t("common.labels.sending")) ?>',
        serverError: '<?= addslashes(t("modules.mensageria.messages.server_error")) ?>',
    };

    // Estado
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let pollingInterval = null;
    let currentConexaoId = null;
    let currentConexaoTipo = null;
    let filiaisDisponiveis = [];
    let isCreating = false;

    // ===== FUNCAO GLOBAL PARA HANDLER SMTP =====
    // Precisa estar fora do template literal para evitar problemas com HTML no onchange
    window.handleSmtpProviderChange = function(sel) {
        var opt = sel.options[sel.selectedIndex];
        var isCustom = opt.dataset.custom === 'true' || opt.dataset.custom === '1';
        var container = document.getElementById('smtpCustomFields');
        var hostHidden = document.getElementById('smtpHostHidden');
        var portHidden = document.getElementById('smtpPortHidden');
        var encHidden = document.getElementById('smtpEncryptionHidden');
        var hostVisible = document.getElementById('smtpHostVisible');
        var portVisible = document.getElementById('smtpPortVisible');
        var encVisible = document.getElementById('smtpEncryptionVisible');
        var helpText = document.getElementById('smtpPasswordHelp');

        if (isCustom) {
            if (container) container.classList.remove('hidden');
            if (hostHidden && hostVisible) hostHidden.value = hostVisible.value || '';
            if (portHidden && portVisible) portHidden.value = portVisible.value || '587';
            if (encHidden && encVisible) encHidden.value = encVisible.value || 'tls';
            if (helpText) helpText.textContent = i18n.passwordHintCustom;
        } else {
            if (container) container.classList.add('hidden');
            if (hostHidden) hostHidden.value = opt.dataset.host || '';
            if (portHidden) portHidden.value = opt.dataset.port || '587';
            if (encHidden) encHidden.value = opt.dataset.encryption || 'tls';
            if (helpText) helpText.textContent = i18n.passwordHintDefault;
        }
    };

    // Elementos
    const tbody = document.getElementById('conexoesTableBody');

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarConexoes(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            // Carregar WhatsApp, SMS e SMTP em paralelo
            const [whatsappResult, smsResult, smtpResult] = await Promise.all([
                API.get('/api/whatsapp', { page: page, perPage: recordsPerPage, search: search }),
                API.get('/api/sms', { page: page, perPage: recordsPerPage, search: search }),
                API.get('/api/smtp', { page: page, perPage: recordsPerPage, search: search })
            ]);

            // Mesclar resultados
            const whatsappConexoes = (whatsappResult.success ? whatsappResult.data : []).map(c => ({ ...c, tipo: 'whatsapp' }));
            const smsConexoes = (smsResult.success ? smsResult.data : []).map(c => ({ ...c, tipo: 'sms' }));
            const smtpConexoes = (smtpResult.success ? smtpResult.data : []).map(c => ({ ...c, tipo: 'smtp' }));
            const conexoes = [...whatsappConexoes, ...smsConexoes, ...smtpConexoes];

            // Calcular paginacao combinada
            const totalWhatsapp = whatsappResult.success ? whatsappResult.pagination.total : 0;
            const totalSms = smsResult.success ? smsResult.pagination.total : 0;
            const totalSmtp = smtpResult.success ? smtpResult.pagination.total : 0;
            const total = totalWhatsapp + totalSms + totalSmtp;
            const totalPages = total > 0 ? Math.ceil(total / recordsPerPage) : 1;

            const pagination = {
                page: page,
                perPage: recordsPerPage,
                total: total,
                totalPages: totalPages,
                hasNext: page < totalPages,
                hasPrev: page > 1
            };

            renderConexoes(conexoes);
            atualizarPaginacao(pagination);
            atualizarInfoRegistros(pagination);

        } catch (error) {
            console.error('Erro ao buscar conexoes:', error);
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

    function renderConexoes(conexoes) {
        if (!conexoes || conexoes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="table-cell text-center text-slate-500">
                        ${i18n.noRecords}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        conexoes.forEach(c => {
            const isWhatsApp = c.tipo === 'whatsapp';
            const isSms = c.tipo === 'sms';
            const isSmtp = c.tipo === 'smtp';

            // Icone de tipo
            let tipoIcon = '';
            if (isWhatsApp) {
                tipoIcon = '<i class="fab fa-whatsapp text-green-500 text-xl" title="WhatsApp"></i>';
            } else if (isSms) {
                tipoIcon = '<i class="fas fa-sms text-blue-500 text-xl" title="SMS"></i>';
            } else if (isSmtp) {
                tipoIcon = '<i class="fas fa-envelope text-purple-500 text-xl" title="SMTP"></i>';
            }

            // Empresas vinculadas (carregado via data-id para lazy load)
            const empresasTexto = `<span class="text-slate-600 empresas-texto" data-id="${c.id}" data-tipo="${c.tipo}"><i class="fas fa-spinner fa-spin text-slate-400"></i></span>`;

            // Identificador (numero para WhatsApp, sender_id para SMS, nome para SMTP)
            let identificador = '-';
            if (isWhatsApp) {
                identificador = c.remoteJid ? formatarTelefone(c.remoteJid) : '-';
            } else if (isSms) {
                identificador = c.sender_id || '-';
            } else if (isSmtp) {
                identificador = c.nome || c.from_email || '-';
            }

            // Status
            const status = (c.status || '').toLowerCase();
            let statusBadge = '';

            if (isWhatsApp) {
                switch (status) {
                    case 'connected':
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-circle text-green-500 mr-1"></i>${i18n.connected}</span>`;
                        break;
                    case 'connecting':
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-spinner fa-spin mr-1"></i>${i18n.connecting}</span>`;
                        break;
                    default:
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-circle text-slate-400 mr-1"></i>${i18n.disconnected}</span>`;
                }
            } else if (isSms || isSmtp) {
                // SMS e SMTP usam mesmo status
                switch (status) {
                    case 'validated':
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check-circle text-green-500 mr-1"></i>${i18n.validated}</span>`;
                        break;
                    case 'pending':
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-clock text-amber-500 mr-1"></i>${i18n.pending}</span>`;
                        break;
                    case 'invalid':
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><i class="fas fa-times-circle text-red-500 mr-1"></i>${i18n.invalid}</span>`;
                        break;
                    default:
                        statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"><i class="fas fa-question-circle text-slate-400 mr-1"></i>${i18n.unknown}</span>`;
                }
            }

            // Botoes de acao baseados no tipo e status
            let actionButtons = '';

            if (isWhatsApp) {
                if (status === 'connected') {
                    actionButtons = `
                        <button title="${i18n.actionTest}" class="btn-icon text-blue-600 hover:text-blue-800 btn-test-whatsapp" data-id="${c.id}" data-name="${escapeHtml(c.instanceName || '')}"><i class="fas fa-vial"></i></button>
                        <button title="${i18n.actionRestart}" class="btn-icon text-cyan-600 hover:text-cyan-800 btn-restart" data-id="${c.id}"><i class="fas fa-sync-alt"></i></button>
                        <button title="${i18n.actionDisconnect}" class="btn-icon text-amber-600 hover:text-amber-800 btn-disconnect" data-id="${c.id}"><i class="fas fa-plug"></i></button>
                    `;
                } else if (status === 'connecting') {
                    actionButtons = `
                        <button title="${i18n.actionConnect}" class="btn-icon text-green-600 hover:text-green-800 btn-connect" data-id="${c.id}"><i class="fas fa-qrcode"></i></button>
                    `;
                } else {
                    actionButtons = `
                        <button title="${i18n.actionRecreate}" class="btn-icon text-green-600 hover:text-green-800 btn-recreate" data-id="${c.id}"><i class="fas fa-play"></i></button>
                    `;
                }
                actionButtons += `
                    <button title="${i18n.actionEdit}" class="btn-icon text-slate-600 hover:text-slate-800 btn-edit-whatsapp" data-id="${c.id}"><i class="fas fa-edit"></i></button>
                    <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${c.id}" data-tipo="whatsapp" data-name="${identificador}"><i class="fas fa-trash"></i></button>
                `;
            } else if (isSms) {
                // SMS
                if (status === 'validated') {
                    actionButtons = `
                        <button title="${i18n.actionTestSms}" class="btn-icon text-blue-600 hover:text-blue-800 btn-test-sms" data-id="${c.id}" data-name="${escapeHtml(c.sender_id || '')}"><i class="fas fa-vial"></i></button>
                        <button title="${i18n.actionCheckBalance}" class="btn-icon text-green-600 hover:text-green-800 btn-balance" data-id="${c.id}"><i class="fas fa-dollar-sign"></i></button>
                    `;
                } else if (status === 'pending' || status === 'invalid') {
                    actionButtons = `
                        <button title="${i18n.actionValidateCredentials}" class="btn-icon text-amber-600 hover:text-amber-800 btn-validate" data-id="${c.id}"><i class="fas fa-check-double"></i></button>
                    `;
                }
                actionButtons += `
                    <button title="${i18n.actionEdit}" class="btn-icon text-slate-600 hover:text-slate-800 btn-edit-sms" data-id="${c.id}"><i class="fas fa-edit"></i></button>
                    <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${c.id}" data-tipo="sms" data-name="${identificador}"><i class="fas fa-trash"></i></button>
                `;
            } else if (isSmtp) {
                // SMTP
                if (status === 'validated') {
                    actionButtons = `
                        <button title="${i18n.actionTestEmail}" class="btn-icon text-blue-600 hover:text-blue-800 btn-test-smtp" data-id="${c.id}" data-name="${escapeHtml(c.nome || '')}"><i class="fas fa-vial"></i></button>
                    `;
                } else if (status === 'pending' || status === 'invalid') {
                    actionButtons = `
                        <button title="${i18n.actionValidateConnection}" class="btn-icon text-amber-600 hover:text-amber-800 btn-validate-smtp" data-id="${c.id}"><i class="fas fa-check-double"></i></button>
                    `;
                }
                actionButtons += `
                    <button title="${i18n.actionEdit}" class="btn-icon text-slate-600 hover:text-slate-800 btn-edit-smtp" data-id="${c.id}"><i class="fas fa-edit"></i></button>
                    <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${c.id}" data-tipo="smtp" data-name="${identificador}"><i class="fas fa-trash"></i></button>
                `;
            }

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell text-center">${tipoIcon}</td>
                    <td class="table-cell text-sm">${empresasTexto}</td>
                    <td class="table-cell hidden md:table-cell text-slate-600 text-sm">${identificador}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-56 text-right">
                        ${actionButtons}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;
        bindActionButtons();
        carregarEmpresasVinculadas(conexoes);
    }

    // Carrega as empresas vinculadas para cada conexao
    async function carregarEmpresasVinculadas(conexoes) {
        for (const c of conexoes) {
            const elemento = tbody.querySelector(`.empresas-texto[data-id="${c.id}"][data-tipo="${c.tipo}"]`);
            if (!elemento) continue;

            try {
                let endpoint = '';
                if (c.tipo === 'whatsapp') {
                    endpoint = `/api/whatsapp/${c.id}`;
                } else if (c.tipo === 'sms') {
                    endpoint = `/api/sms/${c.id}`;
                } else if (c.tipo === 'smtp') {
                    endpoint = `/api/smtp/${c.id}`;
                }
                const result = await API.get(endpoint);
                if (result.success && result.data.filiais) {
                    const filiais = result.data.filiais;
                    if (filiais.length === 0) {
                        elemento.innerHTML = `<span class="text-slate-400">${i18n.none}</span>`;
                    } else {
                        const nomes = filiais.map(f => escapeHtml(f.razao_social || f.nome_fantasia)).join(', ');
                        elemento.innerHTML = nomes;
                    }
                } else {
                    elemento.innerHTML = `<span class="text-red-500">${i18n.loadErrorBranches}</span>`;
                }
            } catch (error) {
                elemento.innerHTML = `<span class="text-red-500">${i18n.loadErrorBranches}</span>`;
            }
        }
    }

    function bindActionButtons() {
        // ===== WHATSAPP =====

        // Botoes de conectar
        tbody.querySelectorAll('.btn-connect').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirOffcanvasQR(id);
            });
        });

        // Botoes de desconectar
        tbody.querySelectorAll('.btn-disconnect').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                desconectar(id);
            });
        });

        // Botoes de testar WhatsApp
        tbody.querySelectorAll('.btn-test-whatsapp').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                abrirOffcanvasTestesWhatsApp(id, name);
            });
        });

        // Botoes de reiniciar
        tbody.querySelectorAll('.btn-restart').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                reiniciarConexao(id);
            });
        });

        // Botoes de recriar
        tbody.querySelectorAll('.btn-recreate').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                recriarConexao(id);
            });
        });

        // Botoes de editar WhatsApp
        tbody.querySelectorAll('.btn-edit-whatsapp').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirOffcanvasEditarWhatsApp(id);
            });
        });

        // ===== SMS =====

        // Botoes de testar SMS
        tbody.querySelectorAll('.btn-test-sms').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                abrirOffcanvasTestesSMS(id, name);
            });
        });

        // Botoes de validar SMS
        tbody.querySelectorAll('.btn-validate').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                validarCredenciaisSMS(id);
            });
        });

        // Botoes de consultar saldo
        tbody.querySelectorAll('.btn-balance').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                consultarSaldoSMS(id);
            });
        });

        // Botoes de editar SMS
        tbody.querySelectorAll('.btn-edit-sms').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirOffcanvasEditarSMS(id);
            });
        });

        // ===== SMTP =====

        // Botoes de testar SMTP
        tbody.querySelectorAll('.btn-test-smtp').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                abrirOffcanvasTestesSMTP(id, name);
            });
        });

        // Botoes de validar SMTP
        tbody.querySelectorAll('.btn-validate-smtp').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                validarConexaoSMTP(id);
            });
        });

        // Botoes de editar SMTP
        tbody.querySelectorAll('.btn-edit-smtp').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                abrirOffcanvasEditarSMTP(id);
            });
        });

        // ===== COMUM =====

        // Botoes de excluir (WhatsApp e SMS)
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const tipo = this.getAttribute('data-tipo');
                const name = this.getAttribute('data-name') || 'esta conexao';

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: tipo,
                        confirmType: 'none'
                    }, '*');
                }
            });
        });
    }

    // ===== OFFCANVAS WHATSAPP =====

    async function abrirOffcanvasNovaConexaoWhatsApp() {
        // Verificar limite do plano
        const limiteResult = await API.get('/api/plano/verificar-limite', { recurso: 'whatsapp' });
        if (limiteResult && !limiteResult.pode_adicionar) {
            if (window.parent !== window && limiteResult.redirect_url) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: limiteResult.redirect_url
                }, '*');
            }
            return;
        }

        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: '/pages/mensageria/whatsapp/adicionar',
            title: i18n.newWhatsapp,
            width: '400px'
        }, '*');
    }

    function abrirOffcanvasEditarWhatsApp(id) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/whatsapp/editar?id=${id}`,
            title: i18n.editWhatsapp,
            width: '400px'
        }, '*');
    }

    function abrirOffcanvasQR(id) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/whatsapp/qrcode?id=${id}`,
            title: i18n.connectWhatsapp,
            width: '400px'
        }, '*');
    }

    function abrirOffcanvasTestesWhatsApp(id, name) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/whatsapp/testar?id=${id}&nome=${encodeURIComponent(name)}`,
            title: i18n.testWhatsapp,
            width: '400px'
        }, '*');
    }

    // ===== OFFCANVAS SMS =====

    async function abrirOffcanvasNovaConexaoSMS() {
        // Verificar limite do plano
        const limiteResult = await API.get('/api/plano/verificar-limite', { recurso: 'sms' });
        if (limiteResult && !limiteResult.pode_adicionar) {
            if (window.parent !== window && limiteResult.redirect_url) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: limiteResult.redirect_url
                }, '*');
            }
            return;
        }

        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: '/pages/mensageria/sms/adicionar',
            title: i18n.newSms,
            width: '400px'
        }, '*');
    }

    function abrirOffcanvasEditarSMS(id) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/sms/editar?id=${id}`,
            title: i18n.editSms,
            width: '400px'
        }, '*');
    }

    function abrirOffcanvasTestesSMS(id, name) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/sms/testar?id=${id}&nome=${encodeURIComponent(name)}`,
            title: i18n.testSms,
            width: '400px'
        }, '*');
    }

    // ===== OFFCANVAS SMTP =====

    async function abrirOffcanvasNovaConexaoSMTP() {
        // Verificar limite do plano
        const limiteResult = await API.get('/api/plano/verificar-limite', { recurso: 'smtp' });
        if (limiteResult && !limiteResult.pode_adicionar) {
            if (window.parent !== window && limiteResult.redirect_url) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: limiteResult.redirect_url
                }, '*');
            }
            return;
        }

        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: '/pages/mensageria/smtp/adicionar',
            title: i18n.newSmtp,
            width: '450px'
        }, '*');
    }

    function abrirOffcanvasEditarSMTP(id) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/smtp/editar?id=${id}`,
            title: i18n.editSmtp,
            width: '450px'
        }, '*');
    }

    function abrirOffcanvasTestesSMTP(id, name) {
        window.parent.postMessage({
            action: 'openOffcanvasIframe',
            url: `/pages/mensageria/smtp/testar?id=${id}&nome=${encodeURIComponent(name)}`,
            title: i18n.testSmtp,
            width: '400px'
        }, '*');
    }

    // ===== ACOES SMTP =====

    async function validarConexaoSMTP(id) {
        const btnValidate = tbody.querySelector(`.btn-validate-smtp[data-id="${id}"]`);
        if (btnValidate) {
            btnValidate.disabled = true;
            btnValidate.classList.add('cursor-not-allowed', 'opacity-70');
            btnValidate.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/smtp/${id}/validate`);

            if (result.success) {
                mostrarToast(i18n.smtpValidated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smtpValidationFailed, 'error');
                if (btnValidate) {
                    btnValidate.disabled = false;
                    btnValidate.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnValidate.innerHTML = '<i class="fas fa-check-double"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smtpValidateError, 'error');
            if (btnValidate) {
                btnValidate.disabled = false;
                btnValidate.classList.remove('cursor-not-allowed', 'opacity-70');
                btnValidate.innerHTML = '<i class="fas fa-check-double"></i>';
            }
        }
    }

    async function excluirConexaoSMTP(id) {
        const btnDelete = tbody.querySelector(`.btn-delete[data-id="${id}"][data-tipo="smtp"]`);
        if (btnDelete) {
            btnDelete.disabled = true;
            btnDelete.classList.add('cursor-not-allowed', 'opacity-70');
            btnDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/smtp/${id}/excluir`);

            if (result.success) {
                mostrarToast(i18n.smtpDeleted, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smtpDeleteError, 'error');
                if (btnDelete) {
                    btnDelete.disabled = false;
                    btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smtpDeleteError, 'error');
            if (btnDelete) {
                btnDelete.disabled = false;
                btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
            }
        }
    }

    async function criarNovaConexaoSMTP(dados) {
        if (!dados.filiais_ids || dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        if (isCreating) return;
        isCreating = true;

        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#formNovaConexaoSMTP button[type="submit"]',
            outerHtml: '<button type="submit" class="btn-purple w-full py-2 opacity-50 cursor-not-allowed" disabled><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.validating + '</button>'
        }, '*');

        try {
            const result = await API.post('/smtp/salvar', {
                provider: dados.provider,
                nome: dados.nome,
                host: dados.host,
                port: dados.port,
                encryption: dados.encryption,
                username: dados.username,
                password: dados.password,
                from_email: dados.from_email,
                from_name: dados.from_name,
                reply_to_email: dados.reply_to_email,
                daily_limit: dados.daily_limit,
                filiais_ids: JSON.stringify(dados.filiais_ids)
            });

            if (result.success) {
                isCreating = false;
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(result.message || i18n.smtpCreated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                isCreating = false;
                window.parent.postMessage({
                    action: 'updateOffcanvasContent',
                    selector: '#formNovaConexaoSMTP button[type="submit"]',
                    outerHtml: '<button type="submit" class="btn-purple w-full py-2"><i class="fas fa-envelope mr-2"></i>' + i18n.smtpCreateValidate + '</button>'
                }, '*');
                mostrarToast(result.message || i18n.smtpCreateError, 'error');
            }
        } catch (error) {
            isCreating = false;
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#formNovaConexaoSMTP button[type="submit"]',
                outerHtml: '<button type="submit" class="btn-purple w-full py-2"><i class="fas fa-envelope mr-2"></i>' + i18n.smtpCreateValidate + '</button>'
            }, '*');
            mostrarToast(error.message || i18n.smtpCreateError, 'error');
        }
    }

    async function atualizarConexaoSMTP(id, dados) {
        if (!dados.filiais_ids || dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        try {
            const result = await API.post(`/smtp/${id}/atualizar`, {
                nome: dados.nome,
                host: dados.host,
                port: dados.port,
                encryption: dados.encryption,
                username: dados.username,
                password: dados.password,
                from_email: dados.from_email,
                from_name: dados.from_name,
                reply_to_email: dados.reply_to_email,
                daily_limit: dados.daily_limit,
                filiais_ids: JSON.stringify(dados.filiais_ids)
            });

            if (result.success) {
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(i18n.smtpUpdated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smtpUpdateError, 'error');
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smtpUpdateError, 'error');
        }
    }

    async function enviarTesteSMTP(email) {
        if (!email) {
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + i18n.provideEmail + '</div>',
                show: true
            }, '*');
            return;
        }

        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#testeResultadoOffcanvas',
            html: '<div class="p-3 rounded-lg text-sm bg-slate-100 text-slate-600"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.sendingEmail + '</div>',
            show: true
        }, '*');

        try {
            const result = await API.post('/smtp/test', { id: currentConexaoId, email: email });

            let resultHtml = '';
            if (result.success) {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-green-100 text-green-700"><i class="fas fa-check-circle mr-2"></i>' + (result.message || i18n.emailSuccess) + '</div>';
            } else {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (result.message || i18n.emailError) + '</div>';
            }

            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: resultHtml,
                show: true
            }, '*');
        } catch (error) {
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (error.message || i18n.emailError) + '</div>',
                show: true
            }, '*');
        }
    }

    // ===== ACOES WHATSAPP =====

    function pararPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    async function desconectar(id) {
        if (!confirm(i18n.confirmDisconnect)) return;

        try {
            const result = await API.post(`/whatsapp/${id}/disconnect`);

            if (result.success) {
                mostrarToast(i18n.whatsappDisconnected, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.whatsappDisconnectError, 'error');
            }
        } catch (error) {
            mostrarToast(error.message || i18n.whatsappDisconnectError, 'error');
        }
    }

    async function reiniciarConexao(id) {
        if (!confirm(i18n.confirmRestart)) return;

        const btnRestart = tbody.querySelector(`.btn-restart[data-id="${id}"]`);
        if (btnRestart) {
            btnRestart.disabled = true;
            btnRestart.classList.add('cursor-not-allowed', 'opacity-70');
            btnRestart.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/whatsapp/${id}/restart`);

            if (result.success) {
                mostrarToast(i18n.whatsappRestarted, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.whatsappRestartError, 'error');
                if (btnRestart) {
                    btnRestart.disabled = false;
                    btnRestart.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnRestart.innerHTML = '<i class="fas fa-sync-alt"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.whatsappRestartError, 'error');
            if (btnRestart) {
                btnRestart.disabled = false;
                btnRestart.classList.remove('cursor-not-allowed', 'opacity-70');
                btnRestart.innerHTML = '<i class="fas fa-sync-alt"></i>';
            }
        }
    }

    async function recriarConexao(id) {
        const btnRecreate = tbody.querySelector(`.btn-recreate[data-id="${id}"]`);
        if (btnRecreate) {
            btnRecreate.disabled = true;
            btnRecreate.classList.add('cursor-not-allowed', 'opacity-70');
            btnRecreate.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/whatsapp/${id}/recreate`);

            if (result.success) {
                mostrarToast(i18n.whatsappRecreated, 'success');
                abrirOffcanvasQR(id);
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.whatsappRecreateError, 'error');
                if (btnRecreate) {
                    btnRecreate.disabled = false;
                    btnRecreate.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnRecreate.innerHTML = '<i class="fas fa-play"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.whatsappRecreateError, 'error');
            if (btnRecreate) {
                btnRecreate.disabled = false;
                btnRecreate.classList.remove('cursor-not-allowed', 'opacity-70');
                btnRecreate.innerHTML = '<i class="fas fa-play"></i>';
            }
        }
    }

    async function excluirConexaoWhatsApp(id) {
        const btnDelete = tbody.querySelector(`.btn-delete[data-id="${id}"][data-tipo="whatsapp"]`);
        if (btnDelete) {
            btnDelete.disabled = true;
            btnDelete.classList.add('cursor-not-allowed', 'opacity-70');
            btnDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/whatsapp/${id}/excluir`);

            if (result.success) {
                mostrarToast(i18n.whatsappDeleted, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.whatsappDeleteError, 'error');
                if (btnDelete) {
                    btnDelete.disabled = false;
                    btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.whatsappDeleteError, 'error');
            if (btnDelete) {
                btnDelete.disabled = false;
                btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
            }
        }
    }

    // ===== ACOES SMS =====

    async function validarCredenciaisSMS(id) {
        const btnValidate = tbody.querySelector(`.btn-validate[data-id="${id}"]`);
        if (btnValidate) {
            btnValidate.disabled = true;
            btnValidate.classList.add('cursor-not-allowed', 'opacity-70');
            btnValidate.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/sms/${id}/validate`);

            if (result.success) {
                mostrarToast(i18n.smsValidated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smsValidationFailed, 'error');
                if (btnValidate) {
                    btnValidate.disabled = false;
                    btnValidate.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnValidate.innerHTML = '<i class="fas fa-check-double"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smtpValidateError, 'error');
            if (btnValidate) {
                btnValidate.disabled = false;
                btnValidate.classList.remove('cursor-not-allowed', 'opacity-70');
                btnValidate.innerHTML = '<i class="fas fa-check-double"></i>';
            }
        }
    }

    async function consultarSaldoSMS(id) {
        try {
            const result = await API.get(`/api/sms/${id}/balance`);

            if (result.success) {
                const balance = result.data.balance;
                const currency = result.data.currency || 'USD';
                mostrarToast(i18n.smsBalance.replace(':currency', currency).replace(':balance', balance.toFixed(2)), 'success');
            } else {
                mostrarToast(result.message || i18n.smsBalanceError, 'error');
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smsBalanceError, 'error');
        }
    }

    async function excluirConexaoSMS(id) {
        const btnDelete = tbody.querySelector(`.btn-delete[data-id="${id}"][data-tipo="sms"]`);
        if (btnDelete) {
            btnDelete.disabled = true;
            btnDelete.classList.add('cursor-not-allowed', 'opacity-70');
            btnDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.post(`/sms/${id}/excluir`);

            if (result.success) {
                mostrarToast(i18n.smsDeleted, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smsDeleteError, 'error');
                if (btnDelete) {
                    btnDelete.disabled = false;
                    btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                    btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
                }
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smsDeleteError, 'error');
            if (btnDelete) {
                btnDelete.disabled = false;
                btnDelete.classList.remove('cursor-not-allowed', 'opacity-70');
                btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
            }
        }
    }

    // ===== CRIAR/ATUALIZAR CONEXOES =====

    async function criarNovaConexaoWhatsApp(filiaisIds) {
        if (!filiaisIds || filiaisIds.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        if (isCreating) return;
        isCreating = true;

        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#formNovaConexaoWhatsApp button[type="submit"]',
            outerHtml: '<button type="submit" class="btn-green w-full py-2 opacity-50 cursor-not-allowed" disabled><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.creating + '</button>'
        }, '*');

        try {
            const result = await API.post('/whatsapp/salvar', {
                filiais_ids: JSON.stringify(filiaisIds)
            });

            if (result.success) {
                isCreating = false;
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(i18n.whatsappCreatedShort, 'success');
                const novoId = result.data.id;
                abrirOffcanvasQR(novoId);
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                isCreating = false;
                window.parent.postMessage({
                    action: 'updateOffcanvasContent',
                    selector: '#formNovaConexaoWhatsApp button[type="submit"]',
                    outerHtml: '<button type="submit" class="btn-green w-full py-2"><i class="fab fa-whatsapp mr-2"></i>' + i18n.createWhatsapp + '</button>'
                }, '*');
                mostrarToast(result.message || i18n.whatsappCreateError, 'error');
            }
        } catch (error) {
            isCreating = false;
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#formNovaConexaoWhatsApp button[type="submit"]',
                outerHtml: '<button type="submit" class="btn-green w-full py-2"><i class="fab fa-whatsapp mr-2"></i>' + i18n.createWhatsapp + '</button>'
            }, '*');
            mostrarToast(error.message || i18n.whatsappCreateError, 'error');
        }
    }

    async function atualizarConexaoWhatsApp(id, filiaisIds) {
        if (!filiaisIds || filiaisIds.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        try {
            const result = await API.post(`/whatsapp/${id}/atualizar`, {
                filiais_ids: JSON.stringify(filiaisIds)
            });

            if (result.success) {
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(i18n.whatsappUpdated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.whatsappUpdateError, 'error');
            }
        } catch (error) {
            mostrarToast(error.message || i18n.whatsappUpdateError, 'error');
        }
    }

    async function criarNovaConexaoSMS(dados) {
        if (!dados.filiais_ids || dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        if (isCreating) return;
        isCreating = true;

        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#formNovaConexaoSMS button[type="submit"]',
            outerHtml: '<button type="submit" class="btn-blue w-full py-2 opacity-50 cursor-not-allowed" disabled><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.validating + '</button>'
        }, '*');

        try {
            const result = await API.post('/sms/salvar', {
                provider: dados.provider,
                sender_id: dados.sender_id,
                username: dados.username,
                api_key: dados.api_key,
                filiais_ids: JSON.stringify(dados.filiais_ids)
            });

            if (result.success) {
                isCreating = false;
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(result.message || i18n.smsCreated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                isCreating = false;
                window.parent.postMessage({
                    action: 'updateOffcanvasContent',
                    selector: '#formNovaConexaoSMS button[type="submit"]',
                    outerHtml: '<button type="submit" class="btn-blue w-full py-2"><i class="fas fa-sms mr-2"></i>' + i18n.smsCreateValidate + '</button>'
                }, '*');
                mostrarToast(result.message || i18n.smsCreateError, 'error');
            }
        } catch (error) {
            isCreating = false;
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#formNovaConexaoSMS button[type="submit"]',
                outerHtml: '<button type="submit" class="btn-blue w-full py-2"><i class="fas fa-sms mr-2"></i>' + i18n.smsCreateValidate + '</button>'
            }, '*');
            mostrarToast(error.message || i18n.smsCreateError, 'error');
        }
    }

    async function atualizarConexaoSMS(id, dados) {
        if (!dados.filiais_ids || dados.filiais_ids.length === 0) {
            mostrarToast(i18n.selectBranch, 'error');
            return;
        }

        try {
            const result = await API.post(`/sms/${id}/atualizar`, {
                sender_id: dados.sender_id,
                username: dados.username,
                api_key: dados.api_key,
                filiais_ids: JSON.stringify(dados.filiais_ids)
            });

            if (result.success) {
                window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
                mostrarToast(i18n.smsUpdated, 'success');
                carregarConexoes(currentPage, perPage, searchTerm);
            } else {
                mostrarToast(result.message || i18n.smsUpdateError, 'error');
            }
        } catch (error) {
            mostrarToast(error.message || i18n.smsUpdateError, 'error');
        }
    }

    // ===== TESTES =====

    async function enviarTesteWhatsApp(tipo) {
        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#testeResultadoOffcanvas',
            html: '<div class="p-3 rounded-lg text-sm bg-slate-100 text-slate-600"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.sending + '</div>',
            show: true
        }, '*');

        try {
            const result = await API.post(`/whatsapp/test/${tipo}`, { id: currentConexaoId });

            let resultHtml = '';
            if (result.success) {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-green-100 text-green-700"><i class="fas fa-check-circle mr-2"></i>' + (result.message || i18n.testSuccess) + '</div>';
            } else {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (result.message || i18n.testError) + '</div>';
            }

            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: resultHtml,
                show: true
            }, '*');
        } catch (error) {
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (error.message || i18n.testError) + '</div>',
                show: true
            }, '*');
        }
    }

    async function enviarTesteSMS(telefone) {
        if (!telefone) {
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + i18n.providePhone + '</div>',
                show: true
            }, '*');
            return;
        }

        window.parent.postMessage({
            action: 'updateOffcanvasContent',
            selector: '#testeResultadoOffcanvas',
            html: '<div class="p-3 rounded-lg text-sm bg-slate-100 text-slate-600"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.sendingSms + '</div>',
            show: true
        }, '*');

        try {
            const result = await API.post('/sms/test', { id: currentConexaoId, telefone: telefone });

            let resultHtml = '';
            if (result.success) {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-green-100 text-green-700"><i class="fas fa-check-circle mr-2"></i>' + (result.message || i18n.smsTestSuccess) + '</div>';
            } else {
                resultHtml = '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (result.message || i18n.smsTestError) + '</div>';
            }

            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: resultHtml,
                show: true
            }, '*');
        } catch (error) {
            window.parent.postMessage({
                action: 'updateOffcanvasContent',
                selector: '#testeResultadoOffcanvas',
                html: '<div class="p-3 rounded-lg text-sm bg-red-100 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i>' + (error.message || i18n.smsTestError) + '</div>',
                show: true
            }, '*');
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
        carregarConexoes(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarConexoes(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarConexoes(currentPage, perPage, searchTerm);
        }, 300);
    });

    document.getElementById('btnNovaConexaoWhatsApp')?.addEventListener('click', abrirOffcanvasNovaConexaoWhatsApp);
    document.getElementById('btnNovaConexaoSMS')?.addEventListener('click', abrirOffcanvasNovaConexaoSMS);
    document.getElementById('btnNovaConexaoSMTP')?.addEventListener('click', abrirOffcanvasNovaConexaoSMTP);

    // Listener de mensagens do parent e iframes filhos
    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Recarregar dados quando iframe filho solicitar
        if (event.data.action === 'reloadMensageriaData') {
            carregarConexoes(currentPage, perPage, searchTerm);
            return;
        }

        if (event.data.action === 'confirmDelete') {
            if (event.data.recordType === 'whatsapp') {
                excluirConexaoWhatsApp(event.data.recordId);
            } else if (event.data.recordType === 'sms') {
                excluirConexaoSMS(event.data.recordId);
            } else if (event.data.recordType === 'smtp') {
                excluirConexaoSMTP(event.data.recordId);
            }
        } else if (event.data.action === 'offcanvasFormSubmit') {
            if (event.data.formId === 'formNovaConexaoWhatsApp') {
                criarNovaConexaoWhatsApp(event.data.filiais_ids);
            } else if (event.data.formId === 'formEditarConexaoWhatsApp') {
                atualizarConexaoWhatsApp(event.data.id, event.data.filiais_ids);
            } else if (event.data.formId === 'formNovaConexaoSMS') {
                criarNovaConexaoSMS(event.data);
            } else if (event.data.formId === 'formEditarConexaoSMS') {
                atualizarConexaoSMS(event.data.id, event.data);
            } else if (event.data.formId === 'formNovaConexaoSMTP') {
                criarNovaConexaoSMTP(event.data);
            } else if (event.data.formId === 'formEditarConexaoSMTP') {
                atualizarConexaoSMTP(event.data.id, event.data);
            }
        } else if (event.data.action === 'offcanvasButtonClick') {
            if (event.data.buttonId === 'btnTestTextOffcanvas') {
                enviarTesteWhatsApp('text');
            } else if (event.data.buttonId === 'btnTestImageOffcanvas') {
                enviarTesteWhatsApp('image');
            } else if (event.data.buttonId === 'btnTestDocumentOffcanvas') {
                enviarTesteWhatsApp('document');
            } else if (event.data.buttonId === 'btnTestSMSOffcanvas') {
                const telefone = event.data.smsTestPhone || '';
                enviarTesteSMS(telefone);
            } else if (event.data.buttonId === 'btnTestSMTPOffcanvas') {
                const email = event.data.smtpTestEmail || '';
                enviarTesteSMTP(email);
            }
        } else if (event.data.action === 'offcanvasClosed') {
            pararPolling();
            currentConexaoId = null;
            currentConexaoTipo = null;
        }
    });

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatarTelefone(telefone) {
        if (!telefone) return '-';
        telefone = telefone.replace('@s.whatsapp.net', '');
        telefone = telefone.replace(/[^0-9]/g, '');

        if (telefone.length === 13 && telefone.startsWith('55')) {
            telefone = telefone.substring(2);
        }

        if (telefone.length === 11) {
            return '(' + telefone.substring(0, 2) + ') ' + telefone.substring(2, 7) + '-' + telefone.substring(7);
        }

        return telefone;
    }

    function mostrarToast(mensagem, tipo = 'info') {
        if (typeof window.toast !== 'undefined') {
            if (tipo === 'error') {
                window.toast.error(mensagem);
            } else if (tipo === 'success') {
                window.toast.success(mensagem);
            } else if (tipo === 'warning') {
                window.toast.warning(mensagem);
            } else {
                window.toast.info(mensagem);
            }
        } else if (window.parent !== window && typeof window.parent.toast !== 'undefined') {
            window.parent.toast.show(mensagem, tipo);
        }
    }

    // Inicializacao
    carregarConexoes(currentPage, perPage, searchTerm);
})();
</script>
@endsection
