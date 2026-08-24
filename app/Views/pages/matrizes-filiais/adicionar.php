@extends('layouts.iframe')

@section('title', '<?= t("modules.matrizes_filiais.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.matrizes_filiais.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Abas -->
    <div class="mb-4 border-b border-slate-300">
        <nav class="flex -mb-px" id="formTabsNav">
            <button data-form-tab-target="#tabDados" class="form-tab-button active"><?= t('modules.matrizes_filiais.tabs.company_data') ?></button>
            <button data-form-tab-target="#tabConfiguracoes" class="form-tab-button"><?= t('modules.matrizes_filiais.tabs.settings') ?></button>
            <?php if (\App\Core\Auth::can('nfse.configurar')): ?>
            <button data-form-tab-target="#tabNfse" class="form-tab-button"><?= t('modules.matrizes_filiais.tabs.nfse') ?></button>
            <?php endif; ?>
            <button data-form-tab-target="#tabLocais" class="form-tab-button"><?= t('modules.matrizes_filiais.tabs.locations') ?></button>
        </nav>
    </div>

    <!-- Formulario -->
    <form id="formMatrizFilial" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id" value="">

        <!-- Aba 1: Dados da Empresa -->
        <div id="tabDados" class="form-tab-content active">
            <!-- Secao: Dados Basicos -->
            <div class="form-section mb-6 relative">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.company_data') ?></h3>

                <!-- Container do Logo -->
                <div class="absolute top-0 right-0 w-40 h-30 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="logoContainer">
                    <img id="logoPreview"
                        src="<?= image('assets/img/logo_padrao.png') ?>"
                        alt="<?= t('modules.matrizes_filiais.logo.alt') ?>"
                        class="w-full h-full object-cover">
                    <input type="file" id="logoInput" name="logo" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                    <input type="hidden" id="logoBase64" name="logo_base64">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                        <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <?= t('modules.matrizes_filiais.logo.change') ?>
                        </div>
                    </div>
                </div>

                <!-- Grid: Tipo + Razao Social -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-2 form-input-group">
                        <label for="tipo" class="form-label-group"><?= t('modules.matrizes_filiais.fields.type') ?> *</label>
                        <select id="tipo" name="tipo" class="form-input-group-field" required>
                            <option value="M"><?= t('modules.matrizes_filiais.type_options.parent') ?></option>
                            <option value="F"><?= t('modules.matrizes_filiais.type_options.branch') ?></option>
                        </select>
                    </div>

                    <div class="md:col-span-5 form-input-group">
                        <label for="nome_fantasia" class="form-label-group"><?= t('modules.matrizes_filiais.fields.trade_name') ?> *</label>
                        <input type="text" id="nome_fantasia" name="nome_fantasia" class="form-input-group-field" required>
                    </div>

                    <div class="md:col-span-3 form-input-group">
                        <label for="razao_social" class="form-label-group"><?= t('modules.matrizes_filiais.fields.company_name') ?></label>
                        <input type="text" id="razao_social" name="razao_social" class="form-input-group-field">
                    </div>
                </div>

                <!-- Grid: CPF/CNPJ + Inscricoes -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="cpf_cnpj" class="form-label-group"><?= t('modules.matrizes_filiais.fields.cpf_cnpj') ?></label>
                        <input type="text" id="cpf_cnpj" name="cpf_cnpj" class="form-input-group-field">
                    </div>

                    <div class="form-input-group">
                        <label for="inscricao_municipal" class="form-label-group"><?= t('modules.matrizes_filiais.fields.municipal_registration') ?></label>
                        <input type="text" id="inscricao_municipal" name="inscricao_municipal" class="form-input-group-field">
                    </div>

                    <div class="form-input-group">
                        <label for="inscricao_estadual" class="form-label-group"><?= t('modules.matrizes_filiais.fields.state_registration') ?></label>
                        <input type="text" id="inscricao_estadual" name="inscricao_estadual" class="form-input-group-field">
                    </div>

                    <div class="form-input-group">
                        <label for="status" class="form-label-group"><?= t('modules.matrizes_filiais.fields.status') ?> *</label>
                        <select id="status" name="status" class="form-input-group-field" required>
                            <option value="A"><?= t('modules.matrizes_filiais.status_options.active') ?></option>
                            <option value="I"><?= t('modules.matrizes_filiais.status_options.inactive') ?></option>
                        </select>
                    </div>
                </div>

            </div>

            <!-- Secao: Endereco -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.address') ?></h3>

                <!-- Linha 1: CEP, Rua, Número -->
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                        <label for="cep" class="form-label-group"><?= t('modules.matrizes_filiais.fields.zip_code') ?></label>
                        <input type="text" id="cep" name="cep" class="form-input-group-field cep" placeholder="00000-000">
                    </div>
                    <div class="col-span-12 sm:col-span-6 lg:col-span-8 form-input-group">
                        <label for="rua" class="form-label-group"><?= t('modules.matrizes_filiais.fields.street') ?></label>
                        <input type="text" id="rua" name="rua" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                        <label for="numero" class="form-label-group"><?= t('modules.matrizes_filiais.fields.number') ?></label>
                        <input type="text" id="numero" name="numero" class="form-input-group-field">
                    </div>
                </div>

                <!-- Linha 2: Complemento, Bairro, Cidade -->
                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="complemento" class="form-label-group"><?= t('modules.matrizes_filiais.fields.complement') ?></label>
                        <input type="text" id="complemento" name="complemento" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="bairro" class="form-label-group"><?= t('modules.matrizes_filiais.fields.neighborhood') ?></label>
                        <input type="text" id="bairro" name="bairro" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="cidade" class="form-label-group"><?= t('modules.matrizes_filiais.fields.city') ?></label>
                        <input type="text" id="cidade" name="cidade" class="form-input-group-field">
                    </div>
                </div>

                <!-- Linha 3: Estado, País -->
                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-6 form-input-group">
                        <label for="estado" class="form-label-group"><?= t('modules.matrizes_filiais.fields.state') ?></label>
                        <input type="text" id="estado" name="estado" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-6 form-input-group">
                        <label for="pais" class="form-label-group"><?= t('modules.matrizes_filiais.fields.country') ?></label>
                        <select id="pais" name="pais" class="form-input-group-field chosen-select"
                                data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                            <option value=""><?= t('common.labels.select') ?>...</option>
                            <?php foreach ($paises ?? [] as $p): ?>
                                <option value="<?= $p['codigo'] ?>" <?= ($p['codigo'] === 'BR') ? 'selected' : '' ?>>
                                    <?= \App\Models\Pais::getNome($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Secao: Contato -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.contact') ?></h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Coluna: E-mails -->
                    <div class="border rounded-lg p-4 bg-slate-50">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-medium text-slate-700">
                                <i class="fas fa-envelope mr-2 text-blue-500"></i><?= t('modules.matrizes_filiais.contact.emails_title') ?>
                            </h4>
                            <button type="button" id="btnAddEmail" class="btn-secondary text-sm py-1 px-3 rounded">
                                <i class="fas fa-plus mr-1"></i><?= t('modules.matrizes_filiais.contact.add') ?>
                            </button>
                        </div>
                        <div id="emailsContainer" class="space-y-3">
                            <!-- Emails serao renderizados aqui pelo JavaScript -->
                        </div>
                    </div>

                    <!-- Coluna: Telefones -->
                    <div class="border rounded-lg p-4 bg-slate-50">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-medium text-slate-700">
                                <i class="fas fa-phone mr-2 text-green-500"></i><?= t('modules.matrizes_filiais.contact.phones_title') ?>
                            </h4>
                            <button type="button" id="btnAddTelefone" class="btn-secondary text-sm py-1 px-3 rounded">
                                <i class="fas fa-plus mr-1"></i><?= t('modules.matrizes_filiais.contact.add') ?>
                            </button>
                        </div>
                        <div id="telefonesContainer" class="space-y-3">
                            <!-- Telefones serao renderizados aqui pelo JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Campo Site (mantido separado) -->
                <div class="mt-4">
                    <div class="form-input-group lg:col-span-2">
                        <label for="site" class="form-label-group"><?= t('modules.matrizes_filiais.fields.site') ?></label>
                        <input type="url" id="site" name="site" class="form-input-group-field" placeholder="https://">
                    </div>
                </div>
            </div>

            <!-- Secao: Horarios de Funcionamento -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.business_hours') ?></h3>

                <div class="space-y-3" id="horariosContainer">
                    <!-- Dias da semana serao renderizados aqui pelo JavaScript -->
                </div>

                <div class="mt-4 flex items-center gap-4">
                    <button type="button" id="btnCopiarSegSex" class="btn-secondary text-sm py-1 px-3 rounded">
                        <i class="fas fa-copy mr-1"></i> <?= t('modules.matrizes_filiais.hours.copy_mon_to_weekdays') ?>
                    </button>
                    <button type="button" id="btnLimparHorarios" class="btn-secondary text-sm py-1 px-3 rounded text-red-600 border-red-300 hover:bg-red-50">
                        <i class="fas fa-trash mr-1"></i> <?= t('modules.matrizes_filiais.hours.clear_all') ?>
                    </button>
                </div>
            </div>

            <!-- Secao: Excecoes de Horario -->
            <div class="form-section mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="form-section-title mb-0"><?= t('modules.matrizes_filiais.sections.schedule_exceptions') ?></h3>
                    <button type="button" id="btnAddExcecao" class="btn-blue text-sm py-1 px-3 rounded">
                        <i class="fas fa-plus mr-1"></i> <?= t('modules.matrizes_filiais.exceptions.new_exception') ?>
                    </button>
                </div>

                <p class="text-sm text-slate-500 mb-4"><?= t('modules.matrizes_filiais.exceptions.description_text') ?></p>

                <div id="excecoesContainer">
                    <!-- Excecoes serao renderizadas aqui pelo JavaScript -->
                </div>

                <div id="excecoesVazio" class="text-center py-6 text-slate-400 border-2 border-dashed border-slate-200 rounded-lg">
                    <i class="fas fa-calendar-times text-3xl mb-2"></i>
                    <p><?= t('modules.matrizes_filiais.exceptions.no_exceptions') ?></p>
                </div>

                <!-- Proximos Feriados (Sugestoes) -->
                <div id="feriadosContainer" class="mt-6 hidden">
                    <h4 class="text-sm font-semibold text-slate-600 mb-3">
                        <i class="fas fa-calendar-alt mr-2 text-blue-500"></i><?= t('modules.matrizes_filiais.sections.holidays_suggestions') ?>
                    </h4>
                    <div id="feriadosList" class="space-y-2">
                        <!-- Feriados serao renderizados aqui pelo JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba 2: Configuracoes -->
        <div id="tabConfiguracoes" class="form-tab-content">
            <!-- Secao: Localizacao -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.locale_formatting') ?></h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="form-input-group">
                        <label for="locale" class="form-label-group"><?= t('modules.matrizes_filiais.fields.locale') ?></label>
                        <select id="locale" name="locale" class="form-input-group-field">
                            <?php foreach (supported_locales() as $code => $info): ?>
                                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($info['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-input-group">
                        <label for="currency_code" class="form-label-group"><?= t('modules.matrizes_filiais.fields.currency') ?></label>
                        <select id="currency_code" name="currency_code" class="form-input-group-field">
                            <option value="BRL"><?= t('modules.matrizes_filiais.format_options.currency_brl') ?></option>
                            <option value="USD"><?= t('modules.matrizes_filiais.format_options.currency_usd') ?></option>
                            <option value="EUR"><?= t('modules.matrizes_filiais.format_options.currency_eur') ?></option>
                        </select>
                    </div>

                    <div class="form-input-group">
                        <label for="date_format" class="form-label-group"><?= t('modules.matrizes_filiais.fields.date_format') ?></label>
                        <select id="date_format" name="date_format" class="form-input-group-field">
                            <option value="d/m/Y"><?= t('modules.matrizes_filiais.format_options.date_dmy') ?></option>
                            <option value="m/d/Y"><?= t('modules.matrizes_filiais.format_options.date_mdy') ?></option>
                            <option value="Y-m-d"><?= t('modules.matrizes_filiais.format_options.date_ymd') ?></option>
                        </select>
                    </div>

                    <div class="form-input-group">
                        <label for="datetime_format" class="form-label-group"><?= t('modules.matrizes_filiais.fields.datetime_format') ?></label>
                        <select id="datetime_format" name="datetime_format" class="form-input-group-field">
                            <option value="d/m/Y H:i:s"><?= t('modules.matrizes_filiais.format_options.datetime_dmy') ?></option>
                            <option value="m/d/Y H:i:s"><?= t('modules.matrizes_filiais.format_options.datetime_mdy') ?></option>
                            <option value="Y-m-d H:i:s"><?= t('modules.matrizes_filiais.format_options.datetime_ymd') ?></option>
                        </select>
                    </div>

                    <div class="form-input-group">
                        <label for="timezone" class="form-label-group"><?= t('modules.matrizes_filiais.fields.timezone') ?></label>
                        <select id="timezone" name="timezone" class="form-input-group-field">
                            <?php
                            $timezones = [
                                'America/Sao_Paulo',
                                'America/Manaus',
                                'America/Cuiaba',
                                'America/Campo_Grande',
                                'America/Belem',
                                'America/Fortaleza',
                                'America/Recife',
                                'America/Bahia',
                                'America/Rio_Branco',
                                'America/New_York',
                                'America/Mexico_City',
                                'Europe/Lisbon',
                                'Europe/Madrid',
                                'Europe/Rome',
                                'UTC',
                            ];
                            ?>
                            <?php foreach ($timezones as $timezone): ?>
                                <option value="<?= htmlspecialchars($timezone) ?>"><?= htmlspecialchars($timezone) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Secao: Sequencias -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.numbering_sequences') ?></h3>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.matrizes_filiais.sections.numbering_sequences_desc') ?></p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="form-input-group">
                        <label for="sequencia_locacoes" class="form-label-group"><?= t('modules.matrizes_filiais.fields.next_rental_number') ?> *</label>
                        <input type="number" id="sequencia_locacoes" name="sequencia_locacoes" class="form-input-group-field" min="1" value="1" required>
                    </div>

                    <div class="form-input-group">
                        <label for="sequencia_contratos" class="form-label-group"><?= t('modules.matrizes_filiais.fields.next_contract_number') ?> *</label>
                        <input type="number" id="sequencia_contratos" name="sequencia_contratos" class="form-input-group-field" min="1" value="1" required>
                    </div>

                    <div class="form-input-group">
                        <label for="sequencia_financeiro" class="form-label-group"><?= t('modules.matrizes_filiais.fields.next_financial_number') ?> *</label>
                        <input type="number" id="sequencia_financeiro" name="sequencia_financeiro" class="form-input-group-field" min="1" value="1" required>
                    </div>
                </div>
            </div>

            <!-- Secao: Notificacoes -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.notifications') ?></h3>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.notifications.sms_title') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.notifications.sms_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="notificacao_sms" name="notificacao_sms" value="S" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.notifications.email_title') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.notifications.email_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="notificacao_email" name="notificacao_email" value="S" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.notifications.whatsapp_title') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.notifications.whatsapp_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="notificacao_whatsapp" name="notificacao_whatsapp" value="S" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <div class="form-input-group mt-4">
                    <label for="notificacao_titulo" class="form-label-group"><?= t('modules.matrizes_filiais.fields.notification_title') ?></label>
                    <input type="text" id="notificacao_titulo" name="notificacao_titulo" class="form-input-group-field" placeholder="<?= t('modules.matrizes_filiais.fields.notification_title_placeholder') ?>">
                </div>

                <div class="mt-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
                        <?= t('modules.matrizes_filiais.notifications.financial_automation') ?>
                    </h4>
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.notifications.overdue_billing_title') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.notifications.overdue_billing_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="notificacao_cobranca_vencida" name="notificacao_cobranca_vencida" value="S" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Secao: Impressao -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.matrizes_filiais.sections.print_settings') ?></h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.print.bold_variables') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.print.bold_variables_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="impressao_variavel_negrito" name="impressao_variavel_negrito" value="S" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <div>
                            <h4 class="font-medium"><?= t('modules.matrizes_filiais.print.remove_yellow_stripe') ?></h4>
                            <p class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.print.remove_yellow_stripe_desc') ?></p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="impressao_remover_tarja_amarela" name="impressao_remover_tarja_amarela" value="S" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba: Locais de atendimento -->
        <div id="tabLocais" class="form-tab-content">
            <!-- Modo criacao: aviso para salvar primeiro -->
            <div id="locaisAvisoSalvar" class="hidden">
                <div class="p-8 text-center">
                    <i class="fas fa-info-circle text-blue-400 text-4xl mb-4"></i>
                    <p class="text-slate-600 text-lg"><?= t('modules.matrizes_filiais.messages.locations_save_first') ?></p>
                </div>
            </div>

            <div id="locaisConteudo" class="hidden">
                <div class="form-section mb-6">
                    <h3 class="form-section-title"><i class="fas fa-map-marker-alt mr-2"></i><?= t('modules.matrizes_filiais.sections.locations') ?></h3>
                    <p class="text-sm text-slate-500 mb-4"><?= t('modules.matrizes_filiais.descriptions.locations') ?></p>

                    <div id="locaisLista" class="space-y-2 mb-4">
                        <p id="locaisVazio" class="text-sm text-slate-400 italic"><?= t('modules.matrizes_filiais.messages.no_locations') ?></p>
                    </div>

                    <button type="button" id="btnAddLocal" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-plus mr-2"></i><?= t('modules.matrizes_filiais.buttons.add_location') ?>
                    </button>
                </div>
            </div>
            <input type="hidden" id="locaisJson" name="locais" value="[]">
        </div>

        <!-- Botoes de acao -->
        <div class="mt-6 flex justify-end space-x-3" id="mainFormButtons">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>

    <?php if (\App\Core\Auth::can('nfse.configurar')): ?>
    <!-- Aba 3: NFS-e (fora do form principal pois tem seus proprios forms) -->
    <div id="tabNfse" class="form-tab-content">
        <!-- Modo criacao: aviso para salvar primeiro -->
        <div id="nfseAvisoSalvar" class="hidden">
            <div class="p-8 text-center">
                <i class="fas fa-info-circle text-blue-400 text-4xl mb-4"></i>
                <p class="text-slate-600 text-lg"><?= t('modules.matrizes_filiais.messages.nfse_save_first') ?></p>
            </div>
        </div>

        <!-- Modo edicao: config completa -->
        <div id="nfseConteudo" class="hidden">

            <!-- Certificado Digital -->
            <div class="form-section mb-6">
                <h3 class="form-section-title">
                    <i class="fas fa-key mr-2"></i><?= t('modules.nfse.config.section_cert') ?>
                </h3>

                <div id="certInfo" class="mb-4">
                    <div id="certNaoConfigurado" class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?= t('modules.nfse.config.cert_nao_configurado') ?>
                    </div>
                    <div id="certConfigurado" class="hidden p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <span id="certValidadeLabel"></span>
                                </div>
                                <div class="text-xs text-green-600 mt-1" id="certDiasExpirar"></div>
                            </div>
                            <button type="button" id="btnRemoverCert" class="btn-red py-1 px-3 rounded text-xs">
                                <i class="fas fa-trash mr-1"></i><?= t('modules.nfse.buttons.remove_cert') ?>
                            </button>
                        </div>
                    </div>
                </div>

                <form id="formCertificado" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id_matriz_filial" id="certFilialId">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.cert_arquivo') ?> <span class="text-red-500">*</span></label>
                            <label for="inputCertificado" class="flex items-center gap-3 cursor-pointer border-slate-300 rounded-md px-3 py-2 hover:bg-slate-50">
                                <span class="inline-flex items-center px-3 py-1 bg-slate-100 border border-slate-300 rounded text-sm text-slate-700">
                                    <i class="fas fa-paperclip mr-1"></i> <?= t('modules.matrizes_filiais.nfse_ui.choose_file') ?>
                                </span>
                                <span id="nomeArquivoCert" class="text-sm text-slate-500"><?= t('modules.matrizes_filiais.nfse_ui.no_file_selected') ?></span>
                            </label>
                            <input type="file" name="certificado" id="inputCertificado" accept=".pfx,.p12" class="sr-only">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.cert_senha') ?> <span class="text-red-500">*</span></label>
                            <input type="password" name="certificado_senha" id="inputCertSenha" class="form-input-group-field" placeholder="<?= t('modules.matrizes_filiais.nfse_ui.certificate_password_placeholder') ?>">
                        </div>
                        <div class="md:col-span-2 form-input-group flex items-end">
                            <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm w-full">
                                <i class="fas fa-upload mr-1"></i><?= t('modules.nfse.buttons.upload_cert') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Configuracoes Gerais -->
            <form id="formConfiguracoes">
                @csrf
                <input type="hidden" name="id_matriz_filial" id="configFilialId">

                <div class="form-section mb-6">
                    <h3 class="form-section-title">
                        <i class="fas fa-cog mr-2"></i><?= t('modules.nfse.config.section_general') ?>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.ativo') ?></label>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" name="ativo" value="S" id="inputAtivo" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                <label for="inputAtivo" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.ambiente') ?></label>
                            <select name="ambiente" id="inputAmbiente" class="form-input-group-field">
                                <option value="2"><?= t('modules.nfse.ambiente.homologacao') ?></option>
                                <option value="1"><?= t('modules.nfse.ambiente.producao') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.tipo_emissao') ?></label>
                            <select name="tipo_emissao" id="inputTipoEmissao" class="form-input-group-field">
                                <option value="nacional"><?= t('modules.nfse.tipo_emissao.nacional') ?></option>
                                <option value="betha"><?= t('modules.nfse.tipo_emissao.betha') ?></option>
                                <option value="issnet"><?= t('modules.nfse.tipo_emissao.issnet') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.serie') ?> <span class="text-red-500">*</span></label>
                            <input type="text" name="serie" id="inputSerie" class="form-input-group-field" placeholder="1" maxlength="10" required>
                        </div>
                        <div class="md:col-span-3 form-input-group" id="fieldNumeroAtual">
                            <label class="form-label-group"><?= t('modules.nfse.config.numero_atual') ?></label>
                            <input type="number" name="numero_atual" id="inputNumeroAtual" class="form-input-group-field" min="0" placeholder="0">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.emissao_auto') ?> <?= aviso(t('modules.nfse.config.emissao_auto_hint')) ?></label>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" name="emissao_auto" value="S" id="inputEmissaoAuto" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                <label for="inputEmissaoAuto" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                            </div>
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.enviar_email') ?> <?= aviso(t('modules.nfse.config.enviar_email_hint')) ?></label>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" name="enviar_email" value="S" id="inputEnviarEmail" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                <label for="inputEnviarEmail" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados Fiscais -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title">
                        <i class="fas fa-file-invoice-dollar mr-2"></i><?= t('modules.nfse.config.section_fiscal') ?>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.codigo_municipio') ?></label>
                            <input type="text" name="codigo_municipio" id="inputCodigoMunicipio" class="form-input-group-field" placeholder="5300108" maxlength="7">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.codigo_servico') ?> <?= aviso(t('modules.nfse.config.codigo_servico_hint')) ?></label>
                            <input type="text" name="codigo_servico" id="inputCodigoServico" class="form-input-group-field" placeholder="1.1101.11" maxlength="20">
                        </div>
                        <div class="md:col-span-4 form-input-group field-issnet">
                            <label class="form-label-group"><?= t('modules.nfse.config.item_lista_servico') ?></label>
                            <input type="text" name="item_lista_servico" id="inputItemListaServico" class="form-input-group-field" placeholder="17.09" maxlength="10">
                        </div>
                        <div class="md:col-span-4 form-input-group field-issnet">
                            <label class="form-label-group"><?= t('modules.nfse.config.codigo_cnae') ?></label>
                            <input type="text" name="codigo_cnae" id="inputCodigoCnae" class="form-input-group-field" placeholder="7711000" maxlength="10">
                        </div>
                        <div class="md:col-span-4 form-input-group field-issnet">
                            <label class="form-label-group"><?= t('modules.nfse.config.codigo_tributacao_municipio') ?></label>
                            <input type="text" name="codigo_tributacao_municipio" id="inputCodigoTributacaoMunicipio" class="form-input-group-field" maxlength="30">
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.regime_tributario') ?></label>
                            <select name="regime_tributario" id="inputRegimeTributario" class="form-input-group-field">
                                <option value="1"><?= t('modules.nfse.config.regime_simples') ?></option>
                                <option value="4"><?= t('modules.nfse.config.regime_mei') ?></option>
                                <option value="2"><?= t('modules.nfse.config.regime_presumido') ?></option>
                                <option value="3"><?= t('modules.nfse.config.regime_real') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-4 form-input-group" id="fieldRegApuracaoSN">
                            <label class="form-label-group"><?= t('modules.nfse.config.reg_apuracao_sn') ?></label>
                            <select name="reg_apuracao_sn" id="inputRegApuracaoSN" class="form-input-group-field">
                                <option value="1"><?= t('modules.nfse.config.reg_apuracao_sn_1') ?></option>
                                <option value="2"><?= t('modules.nfse.config.reg_apuracao_sn_2') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-12 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.descricao_servico') ?></label>
                            <textarea name="descricao_servico" id="inputDescricaoServico" class="form-input-group-field" rows="2" placeholder="<?= t('modules.matrizes_filiais.nfse_ui.service_description_placeholder') ?>"></textarea>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.trib_issqn') ?></label>
                            <select name="trib_issqn" id="inputTribISSQN" class="form-input-group-field">
                                <option value="1"><?= t('modules.nfse.config.trib_normal') ?></option>
                                <option value="2"><?= t('modules.nfse.config.trib_imunidade') ?></option>
                                <option value="3"><?= t('modules.nfse.config.trib_exportacao') ?></option>
                                <option value="4"><?= t('modules.nfse.config.trib_nao_incide') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.aliquota_iss') ?></label>
                            <div class="relative">
                                <input type="text" name="aliquota_iss" id="inputAliquotaISS" class="form-input-group-field pr-10 input-percent" placeholder="0,00">
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-sm">%</span>
                            </div>
                        </div>
                        <div class="md:col-span-12 border-t border-slate-200 pt-4 mt-2" id="sectionIBSCBS">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3">IBS/CBS</h4>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div class="md:col-span-4 form-input-group" id="fieldPreencherIBSCBS">
                                    <label class="form-label-group"><?= t('modules.nfse.config.preencher_ibscbs') ?> <?= aviso(t('modules.nfse.config.preencher_ibscbs_hint')) ?></label>
                                    <div class="flex items-center mt-1">
                                        <input type="checkbox" name="preencher_ibscbs" value="S" id="inputPreencherIBSCBS" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                        <label for="inputPreencherIBSCBS" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                                    </div>
                                </div>
                                <div class="md:col-span-4 form-input-group field-ibscbs-code">
                                    <label class="form-label-group"><?= t('modules.nfse.config.c_ind_op_ibscbs') ?> <?= aviso(t('modules.nfse.config.c_ind_op_ibscbs_hint')) ?></label>
                                    <input type="text" name="c_ind_op_ibscbs" id="inputCIndOpIBSCBS" class="form-input-group-field" inputmode="numeric" maxlength="6">
                                </div>
                                <div class="md:col-span-4 form-input-group field-ibscbs-code">
                                    <label class="form-label-group"><?= t('modules.nfse.config.cst_ibscbs') ?> <?= aviso(t('modules.nfse.config.cst_ibscbs_hint')) ?></label>
                                    <input type="text" name="cst_ibscbs" id="inputCstIBSCBS" class="form-input-group-field" inputmode="numeric" maxlength="3">
                                </div>
                                <div class="md:col-span-4 form-input-group field-ibscbs-code">
                                    <label class="form-label-group"><?= t('modules.nfse.config.c_class_trib_ibscbs') ?> <?= aviso(t('modules.nfse.config.c_class_trib_ibscbs_hint')) ?></label>
                                    <input type="text" name="c_class_trib_ibscbs" id="inputCClassTribIBSCBS" class="form-input-group-field" inputmode="numeric" maxlength="6">
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.exigibilidade_iss') ?></label>
                            <select name="exigibilidade_iss" id="inputExigibilidadeISS" class="form-input-group-field">
                                <option value="1"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_1') ?></option>
                                <option value="2"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_2') ?></option>
                                <option value="3"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_3') ?></option>
                                <option value="4"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_4') ?></option>
                                <option value="5"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_5') ?></option>
                                <option value="6"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_6') ?></option>
                                <option value="7"><?= t('modules.matrizes_filiais.nfse_ui.iss_due_7') ?></option>
                            </select>
                        </div>
                        <div class="md:col-span-3 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.config.incentivo_fiscal') ?></label>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" name="incentivo_fiscal" value="S" id="inputIncentivoFiscal" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                <label for="inputIncentivoFiscal" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                            </div>
                        </div>
                        <div class="md:col-span-3 form-input-group" id="fieldEnviarIM">
                            <label class="form-label-group"><?= t('modules.nfse.config.enviar_im') ?> <?= aviso(t('modules.nfse.config.enviar_im_hint')) ?></label>
                            <div class="flex items-center mt-1">
                                <input type="checkbox" name="enviar_im" value="S" id="inputEnviarIM" class="w-4 h-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded">
                                <label for="inputEnviarIM" class="ml-2 text-sm text-slate-700 cursor-pointer"><?= t('modules.matrizes_filiais.nfse_ui.activate') ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botoes NFS-e -->
                <div class="flex flex-wrap justify-between gap-3 mt-6">
                    <button type="button" id="btnTestarConexao" class="btn-secondary py-2 px-4 rounded-md text-sm">
                        <i class="fas fa-plug mr-2"></i><?= t('modules.nfse.buttons.test_connection') ?>
                    </button>
                    <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            // Títulos
            newTitle: '<?= addslashes(t('modules.matrizes_filiais.new_title')) ?>',
            editTitle: '<?= addslashes(t('modules.matrizes_filiais.edit_title')) ?>',
            viewTitle: '<?= addslashes(t('modules.matrizes_filiais.view_title')) ?>',
            // Logo
            formatNotSupported: '<?= addslashes(t('modules.matrizes_filiais.messages.format_not_supported')) ?>',
            imageTooLarge: '<?= addslashes(t('modules.matrizes_filiais.messages.image_too_large')) ?>',
            // Horários
            closed: '<?= addslashes(t('modules.matrizes_filiais.hours.closed')) ?>',
            addPeriod: '<?= addslashes(t('modules.matrizes_filiais.hours.add_period')) ?>',
            removePeriod: '<?= addslashes(t('modules.matrizes_filiais.hours.remove_period')) ?>',
            configureMondayFirst: '<?= addslashes(t('modules.matrizes_filiais.hours.configure_monday_first')) ?>',
            confirmClearAll: '<?= addslashes(t('modules.matrizes_filiais.hours.confirm_clear_all')) ?>',
            // Contatos
            noEmails: '<?= addslashes(t('modules.matrizes_filiais.contact.no_emails')) ?>',
            noPhones: '<?= addslashes(t('modules.matrizes_filiais.contact.no_phones')) ?>',
            emailPlaceholder: '<?= addslashes(t('modules.matrizes_filiais.contact.email_placeholder')) ?>',
            descriptionPlaceholder: '<?= addslashes(t('modules.matrizes_filiais.contact.description_placeholder')) ?>',
            descriptionShort: '<?= addslashes(t('modules.matrizes_filiais.contact.description_short')) ?>',
            mainEmail: '<?= addslashes(t('modules.matrizes_filiais.contact.main_email')) ?>',
            mainPhone: '<?= addslashes(t('modules.matrizes_filiais.contact.main_phone')) ?>',
            remove: '<?= addslashes(t('modules.matrizes_filiais.contact.remove')) ?>',
            // Exceções
            exceptionClosed: '<?= addslashes(t('modules.matrizes_filiais.exceptions.closed')) ?>',
            specialHours: '<?= addslashes(t('modules.matrizes_filiais.exceptions.special_hours')) ?>',
            exceptionDescPlaceholder: '<?= addslashes(t('modules.matrizes_filiais.exceptions.description_placeholder')) ?>',
            alreadyRegistered: '<?= addslashes(t('modules.matrizes_filiais.exceptions.already_registered')) ?>',
            exceptionAdded: '<?= addslashes(t('modules.matrizes_filiais.exceptions.exception_added')) ?>',
            alreadyExists: <?= js_t('modules.matrizes_filiais.exceptions.already_exists') ?>,
            // Feriados
            holidayClosed: '<?= addslashes(t('modules.matrizes_filiais.holidays.closed_btn')) ?>',
            holidaySpecial: '<?= addslashes(t('modules.matrizes_filiais.holidays.special_hours_btn')) ?>',
            // Salvar
            saveError: '<?= addslashes(t('modules.matrizes_filiais.messages.save_error')) ?>',
            serverError: '<?= addslashes(t('modules.matrizes_filiais.messages.server_error')) ?>',
            saving: '<?= addslashes(t('common.labels.saving')) ?>',
            noFileSelected: '<?= addslashes(t('modules.matrizes_filiais.nfse_ui.no_file_selected')) ?>',
            testing: '<?= addslashes(t('modules.matrizes_filiais.nfse_ui.testing')) ?>',
            confirmTitle: '<?= addslashes(t('modules.matrizes_filiais.confirm.title')) ?>',
            editAction: '<?= addslashes(t('modules.matrizes_filiais.actions.edit')) ?>',
            noAddress: '<?= addslashes(t('modules.matrizes_filiais.locations.no_address')) ?>',
            // Dias da semana
            days: [
                { id: 0, nome: '<?= addslashes(t('common.days.sunday')) ?>', abrev: '<?= addslashes(t('common.days_short.sun')) ?>' },
                { id: 1, nome: '<?= addslashes(t('common.days.monday')) ?>', abrev: '<?= addslashes(t('common.days_short.mon')) ?>' },
                { id: 2, nome: '<?= addslashes(t('common.days.tuesday')) ?>', abrev: '<?= addslashes(t('common.days_short.tue')) ?>' },
                { id: 3, nome: '<?= addslashes(t('common.days.wednesday')) ?>', abrev: '<?= addslashes(t('common.days_short.wed')) ?>' },
                { id: 4, nome: '<?= addslashes(t('common.days.thursday')) ?>', abrev: '<?= addslashes(t('common.days_short.thu')) ?>' },
                { id: 5, nome: '<?= addslashes(t('common.days.friday')) ?>', abrev: '<?= addslashes(t('common.days_short.fri')) ?>' },
                { id: 6, nome: '<?= addslashes(t('common.days.saturday')) ?>', abrev: '<?= addslashes(t('common.days_short.sat')) ?>' }
            ],
        };

        // Estado
        let editMode = false;
        let viewMode = false;
        let registroId = null;

        // Estado dos horarios de funcionamento
        const DIAS_SEMANA = i18n.days;

        // Estado dos horarios (sera preenchido)
        let horariosFuncionamento = {};
        let excecoesHorario = [];
        let proximosFeriados = [];

        // Estado dos contatos
        let emails = [];
        let telefones = [];

        // Estado dos locais de atendimento (aliases)
        let locais = [];
        let pendingClearHours = false;
        let salvandoFormulario = false;

        function showAlert(message) {
            window.parent.postMessage({
                action: 'openAlert',
                message: message
            }, '*');
        }

        function clearAllHours() {
            DIAS_SEMANA.forEach(dia => {
                horariosFuncionamento[dia.id] = { ativo: false, periodos: [] };
            });
            renderizarHorarios();
        }

        // Inicializar horarios vazios
        DIAS_SEMANA.forEach(dia => {
            horariosFuncionamento[dia.id] = {
                ativo: false,
                periodos: []
            };
        });

        // Verificar parametros da URL
        const urlParams = new URLSearchParams(window.location.search);
        registroId = urlParams.get('id');
        viewMode = urlParams.get('mode') === 'view';

        if (registroId) {
            editMode = true;
            document.getElementById('registroId').value = registroId;
            document.getElementById('pageTitle').textContent = viewMode ? i18n.viewTitle : i18n.editTitle;
            carregarDados(registroId);
            // Aba Locais habilitada so em edit (igual NFS-e)
            document.getElementById('locaisConteudo')?.classList.remove('hidden');
        } else {
            // Novo registro: inicializar UI de horarios e contatos
            renderizarHorarios();
            renderizarEmails();
            renderizarTelefones();
            // Aba Locais: mostra aviso "salve primeiro"
            document.getElementById('locaisAvisoSalvar')?.classList.remove('hidden');
        }

        // Carregar feriados quando pais, estado ou cidade mudar
        ['pais', 'estado', 'cidade'].forEach(field => {
            const el = document.getElementById(field);
            if (el) {
                el.addEventListener('change', carregarProximosFeriados);
                el.addEventListener('blur', carregarProximosFeriados);
            }
        });

        // Se modo adicionar e pais ja tem valor, carregar feriados
        if (!editMode) {
            carregarProximosFeriados();
        }

        if (viewMode) {
            // Desabilitar todos os campos
            document.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = true;
            });
            document.getElementById('btnSalvar').style.display = 'none';
            document.getElementById('btnAddExcecao')?.setAttribute('disabled', 'disabled');
        }

        // Gerenciamento de abas
        const formTabButtons = document.querySelectorAll('#formTabsNav .form-tab-button');
        const formTabContents = document.querySelectorAll('.form-tab-content');

        formTabButtons.forEach(button => {
            button.addEventListener('click', () => {
                formTabButtons.forEach(btn => btn.classList.remove('active'));
                formTabContents.forEach(content => content.classList.remove('active'));

                button.classList.add('active');
                const targetId = button.dataset.formTabTarget;
                document.querySelector(targetId).classList.add('active');

                // Esconder botoes do form principal quando aba NFS-e ativa,
                // ou na aba Locais quando estamos em modo criacao (so aviso — nada a salvar aqui)
                const mainFormButtons = document.getElementById('mainFormButtons');
                if (mainFormButtons) {
                    const esconder = targetId === '#tabNfse'
                        || (targetId === '#tabLocais' && !editMode);
                    mainFormButtons.style.display = esconder ? 'none' : 'flex';
                }
            });
        });

        // Upload de logo
        const logoContainer = document.getElementById('logoContainer');
        const logoInput = document.getElementById('logoInput');
        const logoPreview = document.getElementById('logoPreview');
        const logoBase64Input = document.getElementById('logoBase64');

        logoContainer.addEventListener('click', function() {
            if (!viewMode) {
                logoInput.click();
            }
        });

        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && validateFile(file)) {
                processImage(file);
            }
        });

        function validateFile(file) {
            const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!acceptedTypes.includes(file.type)) {
                showAlert(i18n.formatNotSupported);
                return false;
            }
            if (file.size > 5 * 1024 * 1024) {
                showAlert(i18n.imageTooLarge);
                return false;
            }
            return true;
        }

        function processImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const maxWidth = 400;
                    const maxHeight = 400;
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const base64 = canvas.toDataURL('image/jpeg', 0.9);
                    logoPreview.src = base64;
                    logoBase64Input.value = base64;
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Voltar para lista
        document.getElementById('btnVoltar')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/matrizes-filiais'
                }, '*');
            }
        });

        document.getElementById('btnCancelar')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/matrizes-filiais'
                }, '*');
            }
        });

        // Carregar dados para edicao
        async function carregarDados(id) {
            try {
                const result = await API.get('/api/matrizes-filiais/' + id);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    console.error('Erro ao carregar dados:', result.message);
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
            }
        }

        function preencherFormulario(data) {
            // Dados basicos
            if (data.tipo) document.getElementById('tipo').value = data.tipo;
            if (data.status) document.getElementById('status').value = data.status;
            if (data.razao_social) document.getElementById('razao_social').value = data.razao_social;
            if (data.nome_fantasia) document.getElementById('nome_fantasia').value = data.nome_fantasia;
            if (data.cpf_cnpj) document.getElementById('cpf_cnpj').value = data.cpf_cnpj;
            if (data.ins_muni) document.getElementById('inscricao_municipal').value = data.ins_muni;
            if (data.ins_esta) document.getElementById('inscricao_estadual').value = data.ins_esta;

            // Endereco
            if (data.cep) document.getElementById('cep').value = data.cep;
            if (data.rua) document.getElementById('rua').value = data.rua;
            if (data.num) document.getElementById('numero').value = data.num;
            if (data.compl) document.getElementById('complemento').value = data.compl;
            if (data.bairro) document.getElementById('bairro').value = data.bairro;
            if (data.cidade) document.getElementById('cidade').value = data.cidade;
            if (data.estado) document.getElementById('estado').value = data.estado;
            if (data.pais) {
                const paisSelect = document.getElementById('pais');
                paisSelect.value = data.pais;
                paisSelect.dispatchEvent(new Event('change'));
                if (typeof jQuery !== 'undefined') $(paisSelect).trigger('chosen:updated');
            }

            // Contato (Site apenas)
            if (data.site) document.getElementById('site').value = data.site;

            // Emails de contato
            if (data.emails && Array.isArray(data.emails)) {
                emails = data.emails.map(e => ({
                    email: e.email || '',
                    descricao: e.descricao || '',
                    principal: e.principal || 'N'
                }));
            }
            renderizarEmails();

            // Telefones de contato
            if (data.telefones && Array.isArray(data.telefones)) {
                telefones = data.telefones.map(t => ({
                    telefone: t.telefone || '',
                    descricao: t.descricao || '',
                    whatsapp: t.whatsapp || 'N',
                    telegram: t.telegram || 'N',
                    sms: t.sms || 'N',
                    principal: t.principal || 'N'
                }));
            }
            renderizarTelefones();

            // Locais de atendimento (aliases)
            if (data.locais && Array.isArray(data.locais)) {
                locais = data.locais.map(l => ({
                    id: l.id ? parseInt(l.id) : null,
                    nome: l.nome || '',
                    cep: l.cep || '',
                    rua: l.rua || '',
                    numero: l.numero || '',
                    complemento: l.complemento || '',
                    bairro: l.bairro || '',
                    cidade: l.cidade || '',
                    estado: l.estado || '',
                    pais: l.pais || 'BR',
                }));
            }
            renderizarLocais();

            // Horarios de funcionamento (novo modelo)
            if (data.horarios_funcionamento) {
                // Processar horarios recebidos do servidor
                Object.keys(data.horarios_funcionamento).forEach(diaKey => {
                    const diaData = data.horarios_funcionamento[diaKey];
                    const diaSemana = parseInt(diaKey);

                    if (diaData.periodos && diaData.periodos.length > 0) {
                        horariosFuncionamento[diaSemana] = {
                            ativo: true,
                            periodos: diaData.periodos.map(p => ({
                                abertura: p.abertura,
                                fechamento: p.fechamento
                            }))
                        };
                    } else {
                        horariosFuncionamento[diaSemana] = {
                            ativo: false,
                            periodos: []
                        };
                    }
                });
            }
            renderizarHorarios();

            // Excecoes de horario
            if (data.horarios_excecoes && Array.isArray(data.horarios_excecoes)) {
                excecoesHorario = data.horarios_excecoes.map(e => ({
                    data: e.data,
                    tipo: e.tipo,
                    abertura: e.abertura,
                    fechamento: e.fechamento,
                    descricao: e.descricao
                }));
            }
            renderizarExcecoes();

            // Proximos feriados
            if (data.proximos_feriados && Array.isArray(data.proximos_feriados)) {
                proximosFeriados = data.proximos_feriados;
            }
            renderizarFeriados();

            // Configuracoes
            if (data.locale) document.getElementById('locale').value = data.locale;
            if (data.currency_code) document.getElementById('currency_code').value = data.currency_code;
            if (data.date_format) document.getElementById('date_format').value = data.date_format;
            if (data.datetime_format) document.getElementById('datetime_format').value = data.datetime_format;
            if (data.timezone) {
                const timezoneSelect = document.getElementById('timezone');
                if (![...timezoneSelect.options].some(option => option.value === data.timezone)) {
                    timezoneSelect.add(new Option(data.timezone, data.timezone));
                }
                timezoneSelect.value = data.timezone;
            }
            if (data.sequencia_locacoes) document.getElementById('sequencia_locacoes').value = data.sequencia_locacoes;
            if (data.sequencia_contratos) document.getElementById('sequencia_contratos').value = data.sequencia_contratos;
            if (data.sequencia_financeiro) document.getElementById('sequencia_financeiro').value = data.sequencia_financeiro;

            // Notificacoes
            if (data.notificacao_sms === 'S') document.getElementById('notificacao_sms').checked = true;
            if (data.notificacao_email === 'S') document.getElementById('notificacao_email').checked = true;
            if (data.notificacao_whatsapp === 'S') document.getElementById('notificacao_whatsapp').checked = true;
            document.getElementById('notificacao_cobranca_vencida').checked = data.notificacao_cobranca_vencida !== 'N';
            if (data.notificacao_titulo) document.getElementById('notificacao_titulo').value = data.notificacao_titulo;

            // Impressao
            if (data.impressao_variavel_negrito === 'S') document.getElementById('impressao_variavel_negrito').checked = true;
            if (data.impressao_remover_tarja_amarela === 'S') document.getElementById('impressao_remover_tarja_amarela').checked = true;

            // Logo
            if (data.logo_url) {
                logoPreview.src = data.logo_url;
            }

            // Recapturar estado inicial para auditoria (modo edicao)
            FormAudit.recapture(document.getElementById('formMatrizFilial'));
        }

        // ========== FUNCOES DE HORARIOS DE FUNCIONAMENTO ==========

        function renderizarHorarios() {
            const container = document.getElementById('horariosContainer');
            container.innerHTML = '';

            DIAS_SEMANA.forEach(dia => {
                const horarioDia = horariosFuncionamento[dia.id];
                const diaEl = document.createElement('div');
                diaEl.className = 'flex items-center gap-4 p-3 bg-slate-50 rounded-lg';
                diaEl.dataset.diaSemana = dia.id;

                // Checkbox ativo
                const checkboxHtml = `
                    <label class="inline-flex items-center min-w-[140px]">
                        <input type="checkbox" class="form-checkbox dia-ativo-check" data-dia="${dia.id}"
                            ${horarioDia.ativo ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                        <span class="ml-2 font-medium">${dia.nome}</span>
                    </label>
                `;

                // Container de periodos
                let periodosHtml = '<div class="flex-1 flex flex-wrap items-center gap-2 periodos-container">';

                if (horarioDia.ativo && horarioDia.periodos.length > 0) {
                    horarioDia.periodos.forEach((periodo, idx) => {
                        periodosHtml += renderizarPeriodo(dia.id, idx, periodo);
                    });
                } else if (horarioDia.ativo) {
                    // Dia ativo mas sem periodos - adicionar um vazio
                    periodosHtml += renderizarPeriodo(dia.id, 0, { abertura: '', fechamento: '' });
                } else {
                    periodosHtml += `<span class="text-slate-400 text-sm">${i18n.closed}</span>`;
                }

                periodosHtml += '</div>';

                // Botao adicionar periodo
                const btnAddHtml = horarioDia.ativo && !viewMode ? `
                    <button type="button" class="btn-add-periodo text-blue-600 hover:text-blue-800 text-sm"
                        data-dia="${dia.id}" title="${i18n.addPeriod}">
                        <i class="fas fa-plus-circle"></i>
                    </button>
                ` : '';

                diaEl.innerHTML = checkboxHtml + periodosHtml + btnAddHtml;
                container.appendChild(diaEl);
            });

            // Event listeners para checkboxes
            container.querySelectorAll('.dia-ativo-check').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const dia = parseInt(this.dataset.dia);
                    horariosFuncionamento[dia].ativo = this.checked;
                    if (this.checked && horariosFuncionamento[dia].periodos.length === 0) {
                        horariosFuncionamento[dia].periodos.push({ abertura: '08:00', fechamento: '18:00' });
                    }
                    renderizarHorarios();
                });
            });

            // Event listeners para inputs de horario
            container.querySelectorAll('.horario-input').forEach(input => {
                input.addEventListener('change', function() {
                    const dia = parseInt(this.dataset.dia);
                    const periodo = parseInt(this.dataset.periodo);
                    const tipo = this.dataset.tipo; // abertura ou fechamento

                    if (!horariosFuncionamento[dia].periodos[periodo]) {
                        horariosFuncionamento[dia].periodos[periodo] = {};
                    }
                    horariosFuncionamento[dia].periodos[periodo][tipo] = this.value;
                });
            });

            // Event listeners para adicionar periodo
            container.querySelectorAll('.btn-add-periodo').forEach(btn => {
                btn.addEventListener('click', function() {
                    const dia = parseInt(this.dataset.dia);
                    horariosFuncionamento[dia].periodos.push({ abertura: '', fechamento: '' });
                    renderizarHorarios();
                });
            });

            // Event listeners para remover periodo
            container.querySelectorAll('.btn-remove-periodo').forEach(btn => {
                btn.addEventListener('click', function() {
                    const dia = parseInt(this.dataset.dia);
                    const periodo = parseInt(this.dataset.periodo);
                    horariosFuncionamento[dia].periodos.splice(periodo, 1);
                    if (horariosFuncionamento[dia].periodos.length === 0) {
                        horariosFuncionamento[dia].ativo = false;
                    }
                    renderizarHorarios();
                });
            });
        }

        function renderizarPeriodo(dia, idx, periodo) {
            return `
                <div class="flex items-center gap-1 bg-white px-2 py-1 rounded border">
                    <input type="time" class="horario-input text-sm border-0 p-1 w-24"
                        data-dia="${dia}" data-periodo="${idx}" data-tipo="abertura"
                        value="${periodo.abertura || ''}" ${viewMode ? 'disabled' : ''}>
                    <span class="text-slate-400">-</span>
                    <input type="time" class="horario-input text-sm border-0 p-1 w-24"
                        data-dia="${dia}" data-periodo="${idx}" data-tipo="fechamento"
                        value="${periodo.fechamento || ''}" ${viewMode ? 'disabled' : ''}>
                    ${!viewMode && idx > 0 ? `
                        <button type="button" class="btn-remove-periodo text-red-500 hover:text-red-700 ml-1"
                            data-dia="${dia}" data-periodo="${idx}" title="${i18n.removePeriod}">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            `;
        }

        // Botao copiar Seg para Seg-Sex
        document.getElementById('btnCopiarSegSex')?.addEventListener('click', function() {
            const segunda = horariosFuncionamento[1];
            if (!segunda.ativo || segunda.periodos.length === 0) {
                showAlert(i18n.configureMondayFirst);
                return;
            }

            [2, 3, 4, 5].forEach(dia => {
                horariosFuncionamento[dia] = {
                    ativo: true,
                    periodos: JSON.parse(JSON.stringify(segunda.periodos))
                };
            });
            renderizarHorarios();
        });

        // Botao limpar todos
        document.getElementById('btnLimparHorarios')?.addEventListener('click', function() {
            pendingClearHours = true;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.confirmTitle,
                message: i18n.confirmClearAll,
                confirmText: i18n.remove
            }, '*');
        });

        // ========== FUNCOES DE CONTATOS (EMAILS E TELEFONES) ==========

        function renderizarEmails() {
            const container = document.getElementById('emailsContainer');
            container.innerHTML = '';

            if (emails.length === 0) {
                container.innerHTML = `<p class="text-slate-400 text-sm text-center py-4">${i18n.noEmails}</p>`;
                return;
            }

            emails.forEach((email, idx) => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2 bg-white p-3 rounded border';
                div.innerHTML = `
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="email" class="email-input form-input-group-field text-sm"
                            data-idx="${idx}" data-field="email"
                            value="${email.email || ''}" placeholder="${i18n.emailPlaceholder}"
                            ${viewMode ? 'disabled' : ''}>
                        <input type="text" class="email-input form-input-group-field text-sm"
                            data-idx="${idx}" data-field="descricao"
                            value="${email.descricao || ''}" placeholder="${i18n.descriptionPlaceholder}"
                            ${viewMode ? 'disabled' : ''}>
                    </div>
                    <label class="flex items-center gap-1 text-sm whitespace-nowrap cursor-pointer" title="${i18n.mainEmail}">
                        <input type="radio" name="email_principal" class="email-principal-radio"
                            data-idx="${idx}" ${email.principal === 'S' ? 'checked' : ''}
                            ${viewMode ? 'disabled' : ''}>
                        <i class="fas fa-star ${email.principal === 'S' ? 'text-yellow-500' : 'text-slate-300'}"></i>
                    </label>
                    ${!viewMode ? `
                        <button type="button" class="btn-remove-email text-red-500 hover:text-red-700"
                            data-idx="${idx}" title="${i18n.remove}">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                `;
                container.appendChild(div);
            });

            // Event listeners
            container.querySelectorAll('.email-input').forEach(input => {
                input.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    const field = this.dataset.field;
                    emails[idx][field] = this.value;
                });
            });

            container.querySelectorAll('.email-principal-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    // Desmarcar todos
                    emails.forEach(e => e.principal = 'N');
                    // Marcar selecionado
                    emails[idx].principal = 'S';
                    renderizarEmails();
                });
            });

            container.querySelectorAll('.btn-remove-email').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.idx);
                    emails.splice(idx, 1);
                    // Se removeu o principal, definir o primeiro como principal
                    if (emails.length > 0 && !emails.some(e => e.principal === 'S')) {
                        emails[0].principal = 'S';
                    }
                    renderizarEmails();
                });
            });
        }

        function renderizarTelefones() {
            const container = document.getElementById('telefonesContainer');
            container.innerHTML = '';

            if (telefones.length === 0) {
                container.innerHTML = `<p class="text-slate-400 text-sm text-center py-4">${i18n.noPhones}</p>`;
                return;
            }

            telefones.forEach((tel, idx) => {
                const div = document.createElement('div');
                div.className = 'bg-white p-3 rounded border';
                div.innerHTML = `
                    <div class="flex items-center gap-2 mb-2">
                        <input type="tel" class="telefone-input intltel form-input-group-field text-sm flex-1"
                            data-idx="${idx}" data-field="telefone"
                            value="${tel.telefone || ''}"
                            ${viewMode ? 'disabled' : ''}>
                        <input type="text" class="telefone-input form-input-group-field text-sm w-32"
                            data-idx="${idx}" data-field="descricao"
                            value="${tel.descricao || ''}" placeholder="${i18n.descriptionShort}"
                            ${viewMode ? 'disabled' : ''}>
                        <label class="flex items-center gap-1 text-sm cursor-pointer" title="${i18n.mainPhone}">
                            <input type="radio" name="telefone_principal" class="telefone-principal-radio"
                                data-idx="${idx}" ${tel.principal === 'S' ? 'checked' : ''}
                                ${viewMode ? 'disabled' : ''}>
                            <i class="fas fa-star ${tel.principal === 'S' ? 'text-yellow-500' : 'text-slate-300'}"></i>
                        </label>
                        ${!viewMode ? `
                            <button type="button" class="btn-remove-telefone text-red-500 hover:text-red-700"
                                data-idx="${idx}" title="${i18n.remove}">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="whatsapp"
                                ${tel.whatsapp === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="fab fa-whatsapp text-green-500"></i> WhatsApp
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="telegram"
                                ${tel.telegram === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="fab fa-telegram text-blue-400"></i> Telegram
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="sms"
                                ${tel.sms === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="fas fa-sms text-purple-500"></i> SMS
                        </label>
                    </div>
                `;
                container.appendChild(div);
            });

            // Reinicializar IntlPhone nos novos campos
            if (typeof IntlPhone !== 'undefined') {
                container.querySelectorAll('.intltel:not(.intl-phone-initialized)').forEach(input => {
                    new IntlPhone(input);
                    input.classList.add('intl-phone-initialized');
                });
            }

            // Event listeners
            container.querySelectorAll('.telefone-input').forEach(input => {
                input.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    const field = this.dataset.field;
                    telefones[idx][field] = this.value;
                });
            });

            container.querySelectorAll('.telefone-principal-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    telefones.forEach(t => t.principal = 'N');
                    telefones[idx].principal = 'S';
                    renderizarTelefones();
                });
            });

            container.querySelectorAll('.telefone-flag').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    const flag = this.dataset.flag;
                    telefones[idx][flag] = this.checked ? 'S' : 'N';
                });
            });

            container.querySelectorAll('.btn-remove-telefone').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.idx);
                    telefones.splice(idx, 1);
                    if (telefones.length > 0 && !telefones.some(t => t.principal === 'S')) {
                        telefones[0].principal = 'S';
                    }
                    renderizarTelefones();
                });
            });
        }

        // ===== LOCAIS DE ATENDIMENTO =====

        function rotuloLocal(l) {
            if (l.nome && l.nome.trim()) return l.nome.trim();
            const bairro = (l.bairro || '').trim();
            const cidade = (l.cidade || '').trim();
            const estado = (l.estado || '').trim();
            return [bairro, cidade + (estado ? '/' + estado : '')].filter(Boolean).join(', ') || i18n.noAddress;
        }

        function enderecoLocal(l) {
            const partes = [];
            if (l.rua) partes.push(l.rua + (l.numero ? ', ' + l.numero : ''));
            const regiao = [l.bairro, (l.cidade || '') + (l.estado ? '/' + l.estado : '')].filter(Boolean).join(', ');
            if (regiao) partes.push(regiao);
            if (l.cep) partes.push(l.cep);
            return partes.join(' · ');
        }

        function renderizarLocais() {
            const lista = document.getElementById('locaisLista');
            const vazio = document.getElementById('locaisVazio');
            const hidden = document.getElementById('locaisJson');
            hidden.value = JSON.stringify(locais);

            if (!locais.length) {
                if (vazio) vazio.classList.remove('hidden');
                lista.querySelectorAll('.local-card').forEach(el => el.remove());
                return;
            }
            if (vazio) vazio.classList.add('hidden');
            lista.querySelectorAll('.local-card').forEach(el => el.remove());

            locais.forEach((l, idx) => {
                const card = document.createElement('div');
                card.className = 'local-card border border-slate-200 rounded-md p-3 flex items-start justify-between gap-3 bg-white';
                card.innerHTML = `
                    <div class="flex-1">
                        <div class="font-medium text-slate-700">${escapeHtml(rotuloLocal(l))}</div>
                        <div class="text-sm text-slate-500">${escapeHtml(enderecoLocal(l))}</div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-icon text-amber-600 hover:text-amber-800" data-action="edit" data-idx="${idx}" title="${i18n.editAction}"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn-icon text-red-600 hover:text-red-800" data-action="delete" data-idx="${idx}" title="${i18n.remove}"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                lista.appendChild(card);
            });

            lista.querySelectorAll('[data-action="edit"]').forEach(btn => {
                btn.addEventListener('click', () => abrirModalLocal(parseInt(btn.dataset.idx)));
            });
            lista.querySelectorAll('[data-action="delete"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.idx);
                    locais.splice(idx, 1);
                    renderizarLocais();
                });
            });
        }

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function abrirModalLocal(idx) {
            const editingIdx = (idx === undefined || idx === null || idx < 0) ? null : idx;
            const local = editingIdx !== null ? locais[editingIdx] : {};
            window.parent.postMessage({
                action: 'openLocalAtendimentoModal',
                idx: editingIdx,
                local: local,
            }, '*');
        }

        // Recebe callback do modal no parent
        window.addEventListener('message', function(e) {
            if (!e.data) return;

            if (pendingClearHours && e.data.action === 'genericConfirmed') {
                pendingClearHours = false;
                clearAllHours();
                return;
            }

            if (pendingClearHours && e.data.action === 'genericModalClosed') {
                pendingClearHours = false;
                return;
            }

            if (e.data.action !== 'localAtendimentoModalSaved') return;
            const payload = e.data.local || {};
            const idx = e.data.idx;
            if (idx !== null && idx !== undefined && idx >= 0) {
                payload.id = locais[idx]?.id ?? null;
                locais[idx] = payload;
            } else {
                payload.id = payload.id ?? null;
                locais.push(payload);
            }
            renderizarLocais();
        });

        document.getElementById('btnAddLocal')?.addEventListener('click', () => abrirModalLocal(null));

        // Botao adicionar email
        document.getElementById('btnAddEmail')?.addEventListener('click', function() {
            const isPrimeiro = emails.length === 0;
            emails.push({
                email: '',
                descricao: '',
                principal: isPrimeiro ? 'S' : 'N'
            });
            renderizarEmails();
            // Focar no novo campo
            setTimeout(() => {
                const inputs = document.querySelectorAll('#emailsContainer .email-input[data-field="email"]');
                if (inputs.length > 0) inputs[inputs.length - 1].focus();
            }, 100);
        });

        // Botao adicionar telefone
        document.getElementById('btnAddTelefone')?.addEventListener('click', function() {
            const isPrimeiro = telefones.length === 0;
            telefones.push({
                telefone: '',
                descricao: '',
                whatsapp: 'N',
                telegram: 'N',
                sms: 'N',
                principal: isPrimeiro ? 'S' : 'N'
            });
            renderizarTelefones();
        });

        // ========== FUNCOES DE EXCECOES ==========

        function renderizarExcecoes() {
            const container = document.getElementById('excecoesContainer');
            const vazio = document.getElementById('excecoesVazio');

            container.innerHTML = '';

            if (excecoesHorario.length === 0) {
                vazio.style.display = 'block';
                return;
            }

            vazio.style.display = 'none';

            excecoesHorario.forEach((excecao, idx) => {
                const el = document.createElement('div');
                el.className = 'flex items-center gap-4 p-3 bg-slate-50 rounded-lg mb-2';

                const dataFormatada = excecao.data ? formatarDataBR(excecao.data) : '';
                const horarioTexto = excecao.tipo === 'fechado'
                    ? `<span class="text-red-600 font-medium">${i18n.exceptionClosed}</span>`
                    : `<span class="text-green-600">${excecao.abertura || '--:--'} - ${excecao.fechamento || '--:--'}</span>`;

                el.innerHTML = `
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-3 items-center">
                        <input type="date" class="excecao-data form-input-group-field text-sm" data-idx="${idx}"
                            value="${excecao.data || ''}" ${viewMode ? 'disabled' : ''}>

                        <select class="excecao-tipo form-input-group-field text-sm" data-idx="${idx}" ${viewMode ? 'disabled' : ''}>
                            <option value="fechado" ${excecao.tipo === 'fechado' ? 'selected' : ''}>${i18n.exceptionClosed}</option>
                            <option value="especial" ${excecao.tipo === 'especial' ? 'selected' : ''}>${i18n.specialHours}</option>
                        </select>

                        <div class="flex items-center gap-1 excecao-horarios ${excecao.tipo === 'fechado' ? 'hidden' : ''}">
                            <input type="time" class="excecao-abertura form-input-group-field text-sm" data-idx="${idx}"
                                value="${excecao.abertura || ''}" ${viewMode ? 'disabled' : ''}>
                            <span>-</span>
                            <input type="time" class="excecao-fechamento form-input-group-field text-sm" data-idx="${idx}"
                                value="${excecao.fechamento || ''}" ${viewMode ? 'disabled' : ''}>
                        </div>

                        <input type="text" class="excecao-descricao form-input-group-field text-sm" data-idx="${idx}"
                            placeholder="${i18n.exceptionDescPlaceholder}" value="${excecao.descricao || ''}" ${viewMode ? 'disabled' : ''}>
                    </div>

                    ${!viewMode ? `
                        <button type="button" class="btn-remove-excecao text-red-500 hover:text-red-700" data-idx="${idx}">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                `;

                container.appendChild(el);
            });

            // Event listeners
            container.querySelectorAll('.excecao-data').forEach(input => {
                input.addEventListener('change', function() {
                    excecoesHorario[this.dataset.idx].data = this.value;
                });
            });

            container.querySelectorAll('.excecao-tipo').forEach(select => {
                select.addEventListener('change', function() {
                    const idx = this.dataset.idx;
                    excecoesHorario[idx].tipo = this.value;
                    const horariosDiv = this.closest('.flex-1').querySelector('.excecao-horarios');
                    if (this.value === 'fechado') {
                        horariosDiv.classList.add('hidden');
                    } else {
                        horariosDiv.classList.remove('hidden');
                    }
                });
            });

            container.querySelectorAll('.excecao-abertura').forEach(input => {
                input.addEventListener('change', function() {
                    excecoesHorario[this.dataset.idx].abertura = this.value;
                });
            });

            container.querySelectorAll('.excecao-fechamento').forEach(input => {
                input.addEventListener('change', function() {
                    excecoesHorario[this.dataset.idx].fechamento = this.value;
                });
            });

            container.querySelectorAll('.excecao-descricao').forEach(input => {
                input.addEventListener('change', function() {
                    excecoesHorario[this.dataset.idx].descricao = this.value;
                });
            });

            container.querySelectorAll('.btn-remove-excecao').forEach(btn => {
                btn.addEventListener('click', function() {
                    excecoesHorario.splice(parseInt(this.dataset.idx), 1);
                    renderizarExcecoes();
                    renderizarFeriados(); // Atualizar estado dos feriados
                });
            });
        }

        function formatarDataBR(data) {
            if (!data) return '';
            const [ano, mes, dia] = data.split('-');
            return `${dia}/${mes}/${ano}`;
        }

        // Botao adicionar excecao
        document.getElementById('btnAddExcecao')?.addEventListener('click', function() {
            excecoesHorario.push({
                data: '',
                tipo: 'fechado',
                abertura: null,
                fechamento: null,
                descricao: ''
            });
            renderizarExcecoes();
            renderizarFeriados(); // Atualizar estado dos feriados
        });

        // ========== FUNCOES DE FERIADOS ==========

        function renderizarFeriados() {
            const container = document.getElementById('feriadosContainer');
            const lista = document.getElementById('feriadosList');

            if (!proximosFeriados || proximosFeriados.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.classList.remove('hidden');
            lista.innerHTML = '';

            // Datas que ja tem excecao cadastrada
            const datasComExcecao = excecoesHorario.map(e => e.data);

            proximosFeriados.forEach(feriado => {
                const jaTemExcecao = datasComExcecao.includes(feriado.data);
                const el = document.createElement('div');
                el.className = `flex items-center justify-between p-2 rounded-lg ${jaTemExcecao ? 'bg-slate-100 opacity-50' : 'bg-blue-50 hover:bg-blue-100'}`;

                el.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-slate-700">${feriado.data_formatada}</span>
                        <span class="text-sm text-slate-600">${feriado.nome}</span>
                        ${jaTemExcecao ? `<span class="text-xs text-green-600"><i class="fas fa-check mr-1"></i>${i18n.alreadyRegistered}</span>` : ''}
                    </div>
                    ${!jaTemExcecao && !viewMode ? `
                        <div class="flex gap-2">
                            <button type="button" class="btn-feriado-fechado text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200"
                                data-data="${feriado.data}" data-nome="${feriado.nome}">
                                <i class="fas fa-times-circle mr-1"></i>${i18n.holidayClosed}
                            </button>
                            <button type="button" class="btn-feriado-especial text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200"
                                data-data="${feriado.data}" data-nome="${feriado.nome}">
                                <i class="fas fa-clock mr-1"></i>${i18n.holidaySpecial}
                            </button>
                        </div>
                    ` : ''}
                `;

                lista.appendChild(el);
            });

            // Event listeners para botoes de feriado
            lista.querySelectorAll('.btn-feriado-fechado').forEach(btn => {
                btn.addEventListener('click', function() {
                    const data = this.dataset.data;
                    const nome = this.dataset.nome;
                    criarExcecaoDoFeriado(data, nome, 'fechado');
                });
            });

            lista.querySelectorAll('.btn-feriado-especial').forEach(btn => {
                btn.addEventListener('click', function() {
                    const data = this.dataset.data;
                    const nome = this.dataset.nome;
                    criarExcecaoDoFeriado(data, nome, 'especial');
                });
            });
        }

        function criarExcecaoDoFeriado(data, descricao, tipo) {
            // Verificar se ja existe excecao para essa data
            const jaExiste = excecoesHorario.some(e => e.data === data);
            if (jaExiste) {
                toast.warning(i18n.alreadyExists);
                return;
            }

            excecoesHorario.push({
                data: data,
                tipo: tipo,
                abertura: tipo === 'especial' ? '08:00' : null,
                fechamento: tipo === 'especial' ? '18:00' : null,
                descricao: descricao
            });

            renderizarExcecoes();
            renderizarFeriados();
            toast.success(i18n.exceptionAdded.replace(':name', descricao));
        }

        // Funcao para carregar proximos feriados via API
        async function carregarProximosFeriados() {
            const pais = document.getElementById('pais').value.trim();
            const estado = document.getElementById('estado').value.trim();
            const cidade = document.getElementById('cidade').value.trim();

            // So busca se tiver pelo menos pais preenchido
            if (!pais) {
                proximosFeriados = [];
                renderizarFeriados();
                return;
            }

            try {
                const response = await API.get('/api/feriados/buscar', {
                    pais: pais,
                    estado: estado || '',
                    cidade: cidade || ''
                });

                if (response.success && response.data) {
                    proximosFeriados = response.data;
                    renderizarFeriados();
                }
            } catch (error) {
                console.error('Erro ao carregar feriados:', error);
            }
        }

        // Funcao para coletar horarios para envio
        function coletarHorariosFuncionamento() {
            const horarios = [];
            Object.keys(horariosFuncionamento).forEach(diaKey => {
                const dia = horariosFuncionamento[diaKey];
                if (dia.ativo && dia.periodos.length > 0) {
                    dia.periodos.forEach((periodo, idx) => {
                        if (periodo.abertura && periodo.fechamento) {
                            horarios.push({
                                dia_semana: parseInt(diaKey),
                                abertura: periodo.abertura,
                                fechamento: periodo.fechamento,
                                periodo: idx + 1
                            });
                        }
                    });
                }
            });
            return horarios;
        }

        function coletarExcecoes() {
            return excecoesHorario.filter(e => e.data).map(e => ({
                data: e.data,
                tipo: e.tipo,
                abertura: e.tipo === 'especial' ? e.abertura : null,
                fechamento: e.tipo === 'especial' ? e.fechamento : null,
                descricao: e.descricao || null
            }));
        }

        // Submeter formulario
        document.getElementById('formMatrizFilial')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (salvandoFormulario) {
                return;
            }

            salvandoFormulario = true;
            const btnSalvar = document.getElementById('btnSalvar');
            const btnSalvarHtmlOriginal = btnSalvar?.innerHTML || '';
            if (btnSalvar) {
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;
            }

            const formData = new FormData(this);
            const dados = {};
            let salvamentoConcluido = false;

            // Converter FormData para objeto
            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }

            // Converter checkboxes para S/N
            dados.notificacao_sms = document.getElementById('notificacao_sms').checked ? 'S' : 'N';
            dados.notificacao_email = document.getElementById('notificacao_email').checked ? 'S' : 'N';
            dados.notificacao_whatsapp = document.getElementById('notificacao_whatsapp').checked ? 'S' : 'N';
            dados.notificacao_cobranca_vencida = document.getElementById('notificacao_cobranca_vencida').checked ? 'S' : 'N';
            dados.impressao_variavel_negrito = document.getElementById('impressao_variavel_negrito').checked ? 'S' : 'N';
            dados.impressao_remover_tarja_amarela = document.getElementById('impressao_remover_tarja_amarela').checked ? 'S' : 'N';

            // Adicionar horarios de funcionamento
            dados.horarios_funcionamento = coletarHorariosFuncionamento();

            // Adicionar excecoes
            dados.horarios_excecoes = coletarExcecoes();

            // Adicionar emails de contato (JSON)
            dados.emails = JSON.stringify(emails);

            // Adicionar telefones de contato (JSON)
            dados.telefones = JSON.stringify(telefones);

            // Dados de auditoria
            const auditData = FormAudit.getAuditData(this);
            Object.assign(dados, auditData);

            try {
                let url = '/matrizes-filiais/salvar';
                if (editMode && registroId) {
                    url = '/matrizes-filiais/' + registroId + '/atualizar';
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    salvamentoConcluido = true;
                    // Voltar para lista
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'navigate',
                            page: '/pages/matrizes-filiais'
                        }, '*');
                    }
                } else {
                    showAlert(i18n.saveError.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                showAlert(i18n.serverError);
            } finally {
                if (!salvamentoConcluido) {
                    salvandoFormulario = false;
                    if (btnSalvar) {
                        btnSalvar.disabled = false;
                        btnSalvar.innerHTML = btnSalvarHtmlOriginal;
                    }
                }
            }
        });

        // Busca CEP
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('blur', async function() {
                const cep = this.value.replace(/\D/g, '');
                if (cep.length === 8) {
                    try {
                        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                        const data = await response.json();

                        if (!data.erro) {
                            document.getElementById('rua').value = data.logradouro || '';
                            document.getElementById('bairro').value = data.bairro || '';
                            document.getElementById('cidade').value = data.localidade || '';
                            document.getElementById('estado').value = data.uf || '';
                        }
                    } catch (error) {
                        console.error('Erro ao buscar CEP:', error);
                    }
                }
            });
        }

        // ========== SUPORTE A ?tab= NA URL ==========
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            const tabMap = { 'dados': 'Dados', 'configuracoes': 'Configuracoes', 'nfse': 'Nfse' };
            const tabId = tabMap[tabParam];
            if (tabId) {
                const btnTab = document.querySelector('[data-form-tab-target="#tab' + tabId + '"]');
                if (btnTab) btnTab.click();
            }
        }

        // ========== NFS-e CONFIGURACOES ==========
        const tabNfse = document.getElementById('tabNfse');
        if (tabNfse) {
            let nfseConfigAtual = null;

            const formCert = document.getElementById('formCertificado');
            const formConfig = document.getElementById('formConfiguracoes');
            const inputTipoEmissao = document.getElementById('inputTipoEmissao');

            if (editMode && registroId) {
                document.getElementById('nfseConteudo').classList.remove('hidden');
                nfseCarregarConfiguracoes(registroId);
            } else {
                document.getElementById('nfseAvisoSalvar').classList.remove('hidden');
            }

            // Event listeners NFS-e
            inputTipoEmissao.addEventListener('change', nfseToggleDpsFields);
            document.getElementById('inputRegimeTributario').addEventListener('change', nfseToggleDpsFields);
            document.getElementById('inputPreencherIBSCBS').addEventListener('change', nfseToggleDpsFields);
            formCert.addEventListener('submit', nfseUploadCertificado);
            formConfig.addEventListener('submit', nfseSalvarConfiguracoes);
            document.getElementById('btnTestarConexao').addEventListener('click', nfseTestarConexao);
            document.getElementById('btnRemoverCert').addEventListener('click', nfseRemoverCertificado);
            document.getElementById('inputCertificado').addEventListener('change', function() {
                document.getElementById('nomeArquivoCert').textContent = this.files.length ? this.files[0].name : i18n.noFileSelected;
            });

            if (viewMode) {
                tabNfse.querySelectorAll('input, select, textarea, button[type="submit"]').forEach(el => {
                    el.disabled = true;
                });
                document.getElementById('btnTestarConexao').disabled = true;
                document.getElementById('btnRemoverCert').disabled = true;
            }

            async function nfseCarregarConfiguracoes(filialId) {
                try {
                    const result = await API.get('/api/nfse/configuracoes', { filial: filialId });
                    nfseConfigAtual = result.success ? result.data : null;
                    nfsePreencherFormulario(nfseConfigAtual);
                } catch (e) {
                    console.error('Erro ao carregar configuracoes NFS-e:', e);
                }
            }

            function nfsePreencherFormulario(config) {
                document.getElementById('certFilialId').value = registroId;
                document.getElementById('configFilialId').value = registroId;

                if (!config) {
                    nfseLimparFormulario();
                    return;
                }

                // Certificado
                nfseAtualizarInfoCertificado(config);

                // Campos gerais
                document.getElementById('inputAtivo').checked = config.ativo === 'S';
                document.getElementById('inputAmbiente').value = config.ambiente || '2';
                document.getElementById('inputTipoEmissao').value = ['nacional', 'betha', 'issnet'].includes(config.tipo_emissao) ? config.tipo_emissao : 'nacional';
                document.getElementById('inputSerie').value = config.serie || '';
                document.getElementById('inputNumeroAtual').value = config.numero_atual || 0;
                document.getElementById('inputEmissaoAuto').checked = config.emissao_auto === 'S';
                document.getElementById('inputEnviarEmail').checked = config.enviar_email === 'S';

                // Fiscais
                document.getElementById('inputCodigoMunicipio').value = config.codigo_municipio || '';
                document.getElementById('inputCodigoServico').value = config.codigo_servico || '';
                document.getElementById('inputItemListaServico').value = config.item_lista_servico || '';
                document.getElementById('inputCodigoCnae').value = config.codigo_cnae || '';
                document.getElementById('inputCodigoTributacaoMunicipio').value = config.codigo_tributacao_municipio || '';
                document.getElementById('inputDescricaoServico').value = config.descricao_servico || '';
                document.getElementById('inputRegimeTributario').value = config.regime_tributario || '1';
                document.getElementById('inputRegApuracaoSN').value = config.reg_apuracao_sn || '1';
                document.getElementById('inputTribISSQN').value = config.trib_issqn || '4';
                document.getElementById('inputAliquotaISS').value = config.aliquota_iss || '';
                document.getElementById('inputPreencherIBSCBS').checked = config.preencher_ibscbs === 'S';
                document.getElementById('inputCIndOpIBSCBS').value = config.c_ind_op_ibscbs || '';
                document.getElementById('inputCstIBSCBS').value = config.cst_ibscbs || '';
                document.getElementById('inputCClassTribIBSCBS').value = config.c_class_trib_ibscbs || '';
                document.getElementById('inputExigibilidadeISS').value = config.exigibilidade_iss || '1';
                document.getElementById('inputIncentivoFiscal').checked = config.incentivo_fiscal === 'S';
                document.getElementById('inputEnviarIM').checked = config.enviar_im === 'S';

                nfseToggleDpsFields();
            }

            function nfseLimparFormulario() {
                formConfig.reset();
                document.getElementById('certNaoConfigurado').classList.remove('hidden');
                document.getElementById('certConfigurado').classList.add('hidden');
                nfseToggleDpsFields();
            }

            function nfseAtualizarInfoCertificado(config) {
                const naoConfig = document.getElementById('certNaoConfigurado');
                const configurado = document.getElementById('certConfigurado');

                if (config && config.certificado_arquivo) {
                    naoConfig.classList.add('hidden');
                    configurado.classList.remove('hidden');

                    const validade = config.certificado_validade || '';
                    document.getElementById('certValidadeLabel').textContent =
                        '<?= t('modules.nfse.config.cert_validade') ?>: ' + validade;

                    const dias = config.certificado_dias_expiracao;
                    const status = config.certificado_status || '';
                    const mensagem = config.certificado_status_mensagem || 'Não foi possível validar o certificado. Reenvie o arquivo e a senha.';
                    const diasEl = document.getElementById('certDiasExpirar');
                    if (status === 'vencido') {
                        diasEl.innerHTML = '<span class="text-red-600 font-bold"><?= t('modules.nfse.config.cert_expirado') ?></span>';
                    } else if (status && status !== 'valido') {
                        diasEl.innerHTML = '<span class="text-red-600 font-bold">' + mensagem + '</span>';
                    } else if (dias !== null && dias !== undefined) {
                        diasEl.textContent = dias + ' <?= t('modules.nfse.config.cert_dias_expirar') ?>';
                    } else {
                        diasEl.textContent = mensagem;
                    }
                } else {
                    naoConfig.classList.remove('hidden');
                    configurado.classList.add('hidden');
                }
            }

            function nfseToggleDpsFields() {
                const isNacional = inputTipoEmissao.value === 'nacional';
                const isDps = isNacional || inputTipoEmissao.value === 'betha';
                const isIssnet = inputTipoEmissao.value === 'issnet';
                const isSimples = document.getElementById('inputRegimeTributario').value === '1';
                const preencherIBSCBS = document.getElementById('inputPreencherIBSCBS').checked;
                document.getElementById('fieldNumeroAtual').style.display = 'block';
                document.getElementById('fieldEnviarIM').style.display = (isDps || isIssnet) ? 'block' : 'none';
                document.getElementById('fieldRegApuracaoSN').style.display = isDps && isSimples ? 'block' : 'none';
                document.getElementById('sectionIBSCBS').style.display = isNacional ? 'block' : 'none';
                document.getElementById('fieldPreencherIBSCBS').style.display = isNacional ? 'block' : 'none';
                tabNfse.querySelectorAll('.field-ibscbs-code').forEach(el => {
                    el.style.display = isNacional && preencherIBSCBS ? 'block' : 'none';
                });
                tabNfse.querySelectorAll('.field-issnet').forEach(el => {
                    el.style.display = isIssnet ? 'block' : 'none';
                });
                if (isIssnet) {
                    document.getElementById('inputEnviarIM').checked = true;
                }
            }

            async function nfseUploadCertificado(e) {
                e.preventDefault();
                const formData = new FormData(formCert);

                try {
                    const result = await API.postForm('/nfse/configuracoes/certificado', formData);
                    if (result.success) {
                        window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.cert_uploaded') ?>' }, '*');
                        nfseCarregarConfiguracoes(registroId);
                        formCert.reset();
                        document.getElementById('nomeArquivoCert').textContent = i18n.noFileSelected;
                        document.getElementById('certFilialId').value = registroId;
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: result.message || <?= js_t('modules.nfse.messages.cert_error') ?> }, '*');
                    }
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.cert_error') ?> }, '*');
                }
            }

            async function nfseRemoverCertificado() {
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: '<?= t('modules.nfse.buttons.remove_cert') ?>',
                    message: '<?= t('modules.nfse.messages.cancel_confirm') ?>',
                    confirmText: '<?= t('common.buttons.confirm') ?>'
                }, '*');

                window.addEventListener('message', async function handler(event) {
                    if (event.data && event.data.action === 'genericConfirmed') {
                        window.removeEventListener('message', handler);
                        try {
                            const result = await API.post('/nfse/configuracoes/certificado/remover', {
                                id_matriz_filial: registroId
                            });
                            if (result.success) {
                                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.cert_removed') ?>' }, '*');
                                nfseCarregarConfiguracoes(registroId);
                            }
                        } catch (e) {
                            window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.cert_error') ?> }, '*');
                        }
                    }
                });
            }

            async function nfseSalvarConfiguracoes(e) {
                e.preventDefault();
                const serie = document.getElementById('inputSerie').value.trim();
                if (document.getElementById('inputAtivo').checked && !serie) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.serie_required_active') ?> }, '*');
                    return;
                }
                const codigoMunicipio = document.getElementById('inputCodigoMunicipio').value.replace(/\D/g, '');
                if (document.getElementById('inputAtivo').checked && codigoMunicipio.length !== 7) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.codigo_municipio_required_active') ?> }, '*');
                    return;
                }
                const codigoServico = document.getElementById('inputCodigoServico').value.trim();
                if (document.getElementById('inputAtivo').checked && !codigoServico) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.codigo_servico_required_active') ?> }, '*');
                    return;
                }
                const tipoEmissao = document.getElementById('inputTipoEmissao').value;
                const itemListaServico = document.getElementById('inputItemListaServico').value.trim();
                if (document.getElementById('inputAtivo').checked && tipoEmissao === 'issnet' && !itemListaServico) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.item_lista_servico_required_active') ?> }, '*');
                    return;
                }
                const preencherIBSCBS = document.getElementById('inputPreencherIBSCBS').checked;
                const cIndOpIBSCBS = document.getElementById('inputCIndOpIBSCBS').value.replace(/\D/g, '');
                const cstIBSCBS = document.getElementById('inputCstIBSCBS').value.replace(/\D/g, '');
                const cClassTribIBSCBS = document.getElementById('inputCClassTribIBSCBS').value.replace(/\D/g, '');
                if (document.getElementById('inputAtivo').checked && tipoEmissao === 'nacional' && preencherIBSCBS
                    && (cIndOpIBSCBS.length !== 6 || cstIBSCBS.length !== 3 || cClassTribIBSCBS.length !== 6
                        || !cClassTribIBSCBS.startsWith(cstIBSCBS))) {
                    window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.ibscbs_codes_required') ?> }, '*');
                    return;
                }

                const dados = {
                    id_matriz_filial: registroId,
                    ativo: document.getElementById('inputAtivo').checked ? 'S' : 'N',
                    ambiente: document.getElementById('inputAmbiente').value,
                    tipo_emissao: tipoEmissao,
                    serie: serie,
                    numero_atual: document.getElementById('inputNumeroAtual').value,
                    emissao_auto: document.getElementById('inputEmissaoAuto').checked ? 'S' : 'N',
                    enviar_email: document.getElementById('inputEnviarEmail').checked ? 'S' : 'N',
                    codigo_municipio: codigoMunicipio,
                    codigo_servico: codigoServico,
                    item_lista_servico: itemListaServico,
                    codigo_cnae: document.getElementById('inputCodigoCnae').value,
                    codigo_tributacao_municipio: document.getElementById('inputCodigoTributacaoMunicipio').value,
                    descricao_servico: document.getElementById('inputDescricaoServico').value,
                    regime_tributario: document.getElementById('inputRegimeTributario').value,
                    reg_apuracao_sn: document.getElementById('inputRegApuracaoSN').value,
                    trib_issqn: document.getElementById('inputTribISSQN').value,
                    aliquota_iss: document.getElementById('inputAliquotaISS').value,
                    preencher_ibscbs: preencherIBSCBS ? 'S' : 'N',
                    c_ind_op_ibscbs: cIndOpIBSCBS,
                    cst_ibscbs: cstIBSCBS,
                    c_class_trib_ibscbs: cClassTribIBSCBS,
                    exigibilidade_iss: document.getElementById('inputExigibilidadeISS').value,
                    incentivo_fiscal: document.getElementById('inputIncentivoFiscal').checked ? 'S' : 'N',
                    enviar_im: document.getElementById('inputEnviarIM').checked ? 'S' : 'N',
                };

                try {
                    const result = await API.post('/nfse/configuracoes/salvar', dados);
                    if (result.success) {
                        window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.config_saved') ?>' }, '*');
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: result.message || '<?= t('modules.nfse.messages.config_error') ?>' }, '*');
                    }
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.config_error') ?>' }, '*');
                }
            }

            async function nfseTestarConexao() {
                const btn = document.getElementById('btnTestarConexao');
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.testing;
                btn.disabled = true;

                try {
                    const result = await API.post('/nfse/configuracoes/testar-conexao', {
                        id_matriz_filial: registroId
                    });
                    const msg = result.success
                        ? '<?= t('modules.nfse.messages.connection_ok') ?>'
                        : (result.message || '<?= t('modules.nfse.messages.connection_error') ?>');
                    window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
                } catch (e) {
                    window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.connection_error') ?>' }, '*');
                } finally {
                    btn.innerHTML = textoOriginal;
                    btn.disabled = false;
                }
            }
        }
    })();
</script>
@endsection
