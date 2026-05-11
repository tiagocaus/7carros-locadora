@extends('layouts.iframe')

@section('title', '<?= t("modules.grupos.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.grupos.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formGrupo" method="POST">
        @csrf
        <input type="hidden" id="grupoId" name="id">

        <!-- Abas Principais -->
        <div class="mb-4 border-b border-slate-300">
            <nav class="flex -mb-px" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabDados" class="form-tab-button active">
                    <i class="fas fa-info-circle mr-2"></i><?= t('modules.grupos.tabs.group_data') ?>
                </button>
                <button type="button" data-form-tab-target="#tabValoresFilial" class="form-tab-button" id="btnTabValores" disabled>
                    <i class="fas fa-coins mr-2"></i><?= t('modules.grupos.tabs.values_by_branch', [], 'Valores por filial') ?>
                </button>
                <button type="button" data-form-tab-target="#tabPrecos" class="form-tab-button" id="btnTabPrecos" disabled>
                    <i class="fas fa-chart-line mr-2"></i><?= t('modules.grupos.tabs.prices_by_days') ?>
                </button>
            </nav>
        </div>

        <!-- ========== ABA 1: DADOS DO GRUPO ========== -->
        <div id="tabDados" class="form-tab-content active">

            <!-- Secao 1: Dados Basicos -->
            <div class="form-section mb-6 relative">
                <h3 class="form-section-title"><i class="fas fa-info-circle mr-2"></i><?= t('modules.grupos.sections.basic_data') ?></h3>

                <!-- Imagem no canto superior direito -->
                <div class="absolute top-3 right-3 w-40 h-30 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="imagemContainer">
                    <img id="imagemImg"
                        src="<?= image('assets/img/grupo_padrao.png') ?>"
                        alt="<?= t('modules.grupos.image.alt') ?>"
                        class="w-full h-full object-cover">
                    <input type="file" id="imagemInput" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                    <input type="hidden" id="imagemBase64" name="imagem_base64">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                        <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <?= t('modules.grupos.image.change') ?>
                        </div>
                    </div>
                </div>

                <!-- Nome, Descricao e Visivel -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-4 form-input-group">
                        <label for="grupoNome" class="form-label-group">
                            <?= t('modules.grupos.fields.name') ?> <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="grupoNome" name="nome" class="form-input-group-field" required maxlength="45">
                    </div>

                    <div class="md:col-span-5 form-input-group">
                        <label for="grupoDescricao" class="form-label-group"><?= t('modules.grupos.fields.description') ?></label>
                        <input type="text" id="grupoDescricao" name="descricao" class="form-input-group-field" maxlength="50">
                    </div>

                    <div class="md:col-span-3 pb-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="grupoVisivelSite" name="visivel_no_site" value="1" checked class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-slate-700"><?= t('modules.grupos.fields.visible_on_site') ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Secao 2: Comissao Investidor -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-hand-holding-usd mr-2"></i><?= t('modules.grupos.sections.investor_commission') ?></h3>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.grupos.descriptions.investor_commission') ?></p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-input-group">
                        <label for="comissaoInvestidorTipo" class="form-label-group"><?= t('modules.grupos.fields.commission_type') ?></label>
                        <select id="comissaoInvestidorTipo" name="comissao_investidor_tipo" class="form-input-group-field">
                            <option value=""><?= t('modules.grupos.commission_options.none') ?></option>
                            <option value="percentual_locadora"><?= t('modules.grupos.commission_options.percentage_rental') ?></option>
                            <option value="fixo_locadora"><?= t('modules.grupos.commission_options.fixed_rental_invoice') ?></option>
                            <option value="fixo_locadora_mensal"><?= t('modules.grupos.commission_options.fixed_rental_monthly') ?></option>
                            <option value="fixo_investidor_mensal"><?= t('modules.grupos.commission_options.fixed_investor_monthly') ?></option>
                        </select>
                    </div>

                    <div class="form-input-group" id="comissaoValorContainer">
                        <label for="comissaoInvestidorValor" class="form-label-group">
                            <span id="comissaoValorLabel"><?= t('modules.grupos.fields.commission_value') ?></span>
                        </label>
                        <div class="relative">
                            <span id="comissaoValorPrefixo" class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">%</span>
                            <input type="text" id="comissaoInvestidorValor" name="comissao_investidor_valor" class="form-input-group-field pl-10 input-moeda" value="0,00" disabled>
                        </div>
                    </div>
                </div>

                <div id="comissaoHint" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700 hidden">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span id="comissaoHintText"></span>
                </div>
            </div>

            <!-- Botoes Aba 1 -->
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                </button>
            </div>
        </div><!-- Fim tabDados -->


        <!-- ========== ABA 2: VALORES POR FILIAL ========== -->
        <div id="tabValoresFilial" class="form-tab-content">

            <!-- Sub-tabs de filiais -->
            <div class="border-b border-slate-200 mb-4">
                <nav id="filiaisNavValores" class="flex space-x-2 flex-wrap" aria-label="Filiais">
                    <!-- Preenchido por JS -->
                </nav>
            </div>

            <p id="filiaisLoadingValores" class="text-sm text-slate-500 italic"><?= t('common.loading', [], 'Carregando...') ?></p>

            <div id="valoresFilialContent" class="hidden">

                <!-- Secao: Planos de Locacao -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title"><i class="fas fa-calendar-alt mr-2"></i><?= t('modules.grupos.sections.rental_plans') ?></h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="form-input-group">
                            <label for="f_valorPlanoKmPago" class="form-label-group"><?= t('modules.grupos.fields.km_paid_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorPlanoKmPago" data-campo="valor_plano_km_pago" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorPlanoKmControlado" class="form-label-group"><?= t('modules.grupos.fields.km_controlled_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorPlanoKmControlado" data-campo="valor_plano_km_controlado" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorPlanoKmLivre" class="form-label-group"><?= t('modules.grupos.fields.km_free_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorPlanoKmLivre" data-campo="valor_plano_km_livre" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="form-input-group">
                            <label for="f_valorKmExcedente" class="form-label-group"><?= t('modules.grupos.fields.km_excess_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorKmExcedente" data-campo="valor_km_excedente" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_kmFranquia" class="form-label-group"><?= t('modules.grupos.fields.km_franchise') ?></label>
                            <div class="relative">
                                <input type="number" id="f_kmFranquia" data-campo="km_franquia" class="form-input-group-field pr-10" min="0" value="0">
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500">km</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secao: Seguros -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title"><i class="fas fa-shield-alt mr-2"></i><?= t('modules.grupos.sections.insurance') ?></h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-input-group">
                            <label for="f_valorSeguroCarro" class="form-label-group"><?= t('modules.grupos.fields.car_insurance_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorSeguroCarro" data-campo="valor_seguro_carro" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorSeguroTerceiros" class="form-label-group"><?= t('modules.grupos.fields.third_party_insurance_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorSeguroTerceiros" data-campo="valor_seguro_terceiros" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_coberturaCarro" class="form-label-group"><?= t('modules.grupos.fields.car_coverage') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_coberturaCarro" data-campo="cobertura_carro" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_coberturaTerceiros" class="form-label-group"><?= t('modules.grupos.fields.third_party_coverage') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_coberturaTerceiros" data-campo="cobertura_terceiros" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secao: Tolerancia e Extras -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title"><i class="fas fa-clock mr-2"></i><?= t('modules.grupos.sections.tolerance_extras') ?></h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="form-input-group">
                            <label for="f_minutosTolerancia" class="form-label-group"><?= t('modules.grupos.fields.tolerance_minutes') ?></label>
                            <div class="relative">
                                <input type="number" id="f_minutosTolerancia" data-campo="minutos_tolerancia" class="form-input-group-field pr-12" min="0" value="0">
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500">min</span>
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorTolerancia" class="form-label-group"><?= t('modules.grupos.fields.tolerance_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorTolerancia" data-campo="valor_tolerancia" class="form-input-group-field pl-10 pr-20 input-moeda-filial" value="0,00">
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-xs">/min</span>
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorKmRetorno" class="form-label-group"><?= t('modules.grupos.fields.return_km_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorKmRetorno" data-campo="valor_km_retorno" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>

                        <div class="form-input-group">
                            <label for="f_valorCondutorAdicional" class="form-label-group"><?= t('modules.grupos.fields.additional_driver_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol filial-currency absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="f_valorCondutorAdicional" data-campo="valor_condutor_adicional" class="form-input-group-field pl-10 input-moeda-filial" value="0,00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botoes Aba 2 -->
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" id="btnSalvarValoresFilial" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-save mr-2"></i><?= t('modules.grupos.buttons.save_branch_values', [], 'Salvar valores desta filial') ?>
                    </button>
                </div>
            </div><!-- Fim valoresFilialContent -->
        </div><!-- Fim tabValoresFilial -->


        <!-- ========== ABA 3: PRECOS POR DIAS ========== -->
        <div id="tabPrecos" class="form-tab-content">

            <!-- Sub-tabs de filiais -->
            <div class="border-b border-slate-200 mb-4">
                <nav id="filiaisNavPrecos" class="flex space-x-2 flex-wrap" aria-label="Filiais">
                    <!-- Preenchido por JS -->
                </nav>
            </div>

            <p id="filiaisLoadingPrecos" class="text-sm text-slate-500 italic"><?= t('common.loading', [], 'Carregando...') ?></p>

            <div id="precosFilialContent" class="hidden">
                <div class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-chart-line mr-2"></i><?= t('modules.grupos.sections.progressive_prices') ?></h3>
                    <p class="text-sm text-slate-500 mb-4"><?= t('modules.grupos.descriptions.progressive_prices') ?></p>

                    <!-- Sub-Tabs (Diaria, Km Controlado, Km Livre) -->
                    <div class="border-b border-slate-200 mb-4">
                        <nav class="flex space-x-4" aria-label="Tabs">
                            <button type="button" class="tab-btn active" data-tab="diaria">
                                <?= t('modules.grupos.price_tabs.km_paid', [], 'Diária') ?>
                            </button>
                            <button type="button" class="tab-btn" data-tab="km_controlado">
                                <?= t('modules.grupos.price_tabs.km_controlled') ?>
                            </button>
                            <button type="button" class="tab-btn" data-tab="km_livre">
                                <?= t('modules.grupos.price_tabs.km_free') ?>
                            </button>
                        </nav>
                    </div>

                    <div id="tab-diaria" class="tab-content active-content">
                        <div id="faixas-diaria" class="space-y-2"></div>
                        <button type="button" class="btn-add-faixa mt-3 text-blue-600 hover:text-blue-800 text-sm font-medium" data-tipo="diaria">
                            <i class="fas fa-plus mr-1"></i><?= t('modules.grupos.ranges.add_range') ?>
                        </button>
                    </div>

                    <div id="tab-km_controlado" class="tab-content">
                        <div id="faixas-km_controlado" class="space-y-2"></div>
                        <button type="button" class="btn-add-faixa mt-3 text-blue-600 hover:text-blue-800 text-sm font-medium" data-tipo="km_controlado">
                            <i class="fas fa-plus mr-1"></i><?= t('modules.grupos.ranges.add_range') ?>
                        </button>
                    </div>

                    <div id="tab-km_livre" class="tab-content">
                        <div id="faixas-km_livre" class="space-y-2"></div>
                        <button type="button" class="btn-add-faixa mt-3 text-blue-600 hover:text-blue-800 text-sm font-medium" data-tipo="km_livre">
                            <i class="fas fa-plus mr-1"></i><?= t('modules.grupos.ranges.add_range') ?>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" id="btnSalvarPrecosFilial" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-save mr-2"></i><?= t('modules.grupos.buttons.save_branch_prices', [], 'Salvar preços desta filial') ?>
                    </button>
                </div>
            </div><!-- Fim precosFilialContent -->
        </div><!-- Fim tabPrecos -->

    </form>
</div>

<style>
/* Sub-abas (Km Pago, Km Controlado, Km Livre) */
.tab-btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover { color: #3b82f6; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }

/* Sub-tabs de filiais */
.filial-tab {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    background: transparent;
}
.filial-tab:hover { color: #3b82f6; }
.filial-tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.filial-tab .filial-currency { font-size: 0.75rem; color: #94a3b8; }

/* Aba principal desabilitada */
.form-tab-button:disabled { opacity: 0.45; cursor: not-allowed; }
.form-tab-button:disabled:hover { color: inherit; }

/* Faixas de preco */
.faixa-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
}
.faixa-item input {
    width: 80px;
    padding: 0.5rem;
    font-size: 0.875rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
}
.faixa-item input.input-valor { width: 100px; }
.faixa-item .btn-remove { color: #ef4444; padding: 0.25rem; }
.faixa-item .btn-remove:hover { color: #dc2626; }
</style>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        editTitle: '<?= addslashes(t('modules.grupos.edit_title')) ?>',
        loadError: '<?= addslashes(t('modules.grupos.messages.load_group_error')) ?>',
        invalidImageFormat: '<?= addslashes(t('modules.grupos.messages.invalid_image_format')) ?>',
        imageTooLarge: '<?= addslashes(t('modules.grupos.messages.image_too_large')) ?>',
        nameRequired: '<?= addslashes(t('modules.grupos.messages.name_required')) ?>',
        saveError: '<?= addslashes(t('modules.grupos.messages.save_error')) ?>',
        saveServerError: '<?= addslashes(t('modules.grupos.messages.save_server_error')) ?>',
        created: '<?= addslashes(t('modules.grupos.messages.created')) ?>',
        updated: '<?= addslashes(t('modules.grupos.messages.updated')) ?>',
        commissionValue: '<?= addslashes(t('modules.grupos.fields.commission_value')) ?>',
        commissionLabelPercentage: '<?= addslashes(t('modules.grupos.commission_labels.rental_percentage')) ?>',
        commissionLabelFixedInvoice: '<?= addslashes(t('modules.grupos.commission_labels.fixed_per_invoice')) ?>',
        commissionLabelMonthlyRental: '<?= addslashes(t('modules.grupos.commission_labels.monthly_rental')) ?>',
        commissionLabelMonthlyInvestor: '<?= addslashes(t('modules.grupos.commission_labels.monthly_investor')) ?>',
        hintPercentageRental: '<?= addslashes(t('modules.grupos.commission_hints.percentage_rental')) ?>',
        hintFixedRentalInvoice: '<?= addslashes(t('modules.grupos.commission_hints.fixed_rental_invoice')) ?>',
        hintFixedRentalMonthly: '<?= addslashes(t('modules.grupos.commission_hints.fixed_rental_monthly')) ?>',
        hintFixedInvestorMonthly: '<?= addslashes(t('modules.grupos.commission_hints.fixed_investor_monthly')) ?>',
        rangeFrom: '<?= addslashes(t('modules.grupos.ranges.from')) ?>',
        rangeTo: '<?= addslashes(t('modules.grupos.ranges.to')) ?>',
        rangeDaysEquals: '<?= addslashes(t('modules.grupos.ranges.days_equals')) ?>',
        noRanges: '<?= addslashes(t('modules.grupos.ranges.no_ranges')) ?>',
        rangeInfinity: '<?= addslashes(t('modules.grupos.ranges.infinity')) ?>',
        branchSavedFirst: 'Salve os dados básicos primeiro para editar valores por filial',
        branchValuesSaved: 'Valores salvos',
        branchPricesSaved: 'Preços salvos',
    };

    let currentId = null;
    let isEditMode = false;
    let imagemBase64 = null;
    let removerImagem = false;
    let filiais = [];
    let filialAtivaValores = null;
    let filialAtivaPrecos = null;

    // Faixas de preco (diaria / km_controlado / km_livre) da filial ativa da Aba 3
    let faixasPreco = { diaria: [], km_controlado: [], km_livre: [] };

    const form = document.getElementById('formGrupo');
    const pageTitle = document.getElementById('pageTitle');

    // ===== NAVEGACAO =====
    function navegarParaLista() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: '/pages/grupos' }, '*');
        } else {
            window.location.href = '/pages/grupos';
        }
    }
    document.getElementById('btnVoltar')?.addEventListener('click', navegarParaLista);
    document.getElementById('btnCancelar')?.addEventListener('click', navegarParaLista);

    // ===== UTILS DE MOEDA =====
    function formatarMoedaInput(valor) {
        if (!valor && valor !== 0) return '0,00';
        const num = parseFloat(valor);
        return num.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    }
    function parseMoeda(valor) {
        if (!valor) return 0;
        return parseFloat(String(valor).replace(/\./g, '').replace(',', '.')) || 0;
    }
    function aplicarMascaraMoeda(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (parseInt(value || '0') / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            e.target.value = value;
        });
    }
    document.querySelectorAll('.input-moeda, .input-moeda-filial').forEach(aplicarMascaraMoeda);

    // ===== COMISSAO INVESTIDOR =====
    const comissaoHints = {
        'percentual_locadora': i18n.hintPercentageRental,
        'fixo_locadora': i18n.hintFixedRentalInvoice,
        'fixo_locadora_mensal': i18n.hintFixedRentalMonthly,
        'fixo_investidor_mensal': i18n.hintFixedInvestorMonthly
    };
    function atualizarCamposComissao() {
        const comissaoTipoSelect = document.getElementById('comissaoInvestidorTipo');
        const comissaoValorInput = document.getElementById('comissaoInvestidorValor');
        const comissaoValorLabel = document.getElementById('comissaoValorLabel');
        const comissaoValorPrefixo = document.getElementById('comissaoValorPrefixo');
        const comissaoHint = document.getElementById('comissaoHint');
        const comissaoHintText = document.getElementById('comissaoHintText');
        if (!comissaoTipoSelect) return;
        const tipo = comissaoTipoSelect.value;
        const symbol = (typeof Currency !== 'undefined' && Currency.config) ? Currency.config.symbol : 'R$';
        if (!tipo) {
            comissaoValorInput.disabled = true;
            comissaoValorInput.value = '0,00';
            comissaoHint.classList.add('hidden');
            comissaoValorPrefixo.textContent = '%';
            comissaoValorLabel.textContent = i18n.commissionValue;
            return;
        }
        comissaoValorInput.disabled = false;
        if (tipo === 'percentual_locadora') {
            comissaoValorPrefixo.textContent = '%';
            comissaoValorLabel.textContent = i18n.commissionLabelPercentage;
        } else {
            comissaoValorPrefixo.textContent = symbol;
            if (tipo === 'fixo_locadora') comissaoValorLabel.textContent = i18n.commissionLabelFixedInvoice;
            else if (tipo === 'fixo_locadora_mensal') comissaoValorLabel.textContent = i18n.commissionLabelMonthlyRental;
            else if (tipo === 'fixo_investidor_mensal') comissaoValorLabel.textContent = i18n.commissionLabelMonthlyInvestor;
        }
        if (comissaoHints[tipo]) {
            comissaoHintText.textContent = comissaoHints[tipo];
            comissaoHint.classList.remove('hidden');
        } else {
            comissaoHint.classList.add('hidden');
        }
    }
    document.getElementById('comissaoInvestidorTipo')?.addEventListener('change', atualizarCamposComissao);

    // ===== ABAS PRINCIPAIS =====
    const formTabButtons = document.querySelectorAll('#formTabsNav .form-tab-button');
    const formTabContents = document.querySelectorAll('.form-tab-content');
    formTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            formTabButtons.forEach(btn => btn.classList.remove('active'));
            formTabContents.forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            const targetId = button.dataset.formTabTarget;
            document.querySelector(targetId).classList.add('active');
        });
    });
    function habilitarAbasPorFilial(habilitar) {
        document.getElementById('btnTabValores').disabled = !habilitar;
        document.getElementById('btnTabPrecos').disabled = !habilitar;
    }

    // ===== UPLOAD IMAGEM =====
    const imagemContainer = document.getElementById('imagemContainer');
    const imagemInput = document.getElementById('imagemInput');
    const imagemImg = document.getElementById('imagemImg');
    const imagemBase64Input = document.getElementById('imagemBase64');
    const imagemPadrao = imagemImg?.src || '';
    imagemContainer?.addEventListener('click', () => imagemInput.click());
    imagemInput?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const tiposPermitidos = ['image/jpeg','image/jpg','image/png','image/webp'];
        if (!tiposPermitidos.includes(file.type)) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.invalidImageFormat }, '*');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.imageTooLarge }, '*');
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            imagemBase64 = e.target.result;
            removerImagem = false;
            imagemImg.src = e.target.result;
            if (imagemBase64Input) imagemBase64Input.value = e.target.result;
        };
        reader.readAsDataURL(file);
    });
    function mostrarPreviewImagem(src) { if (imagemImg) imagemImg.src = src; }

    // ===== MODO EDICAO =====
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('id');
    if (editId) {
        isEditMode = true;
        currentId = editId;
        document.getElementById('grupoId').value = editId;
        carregarGrupo(editId);
    }

    async function carregarGrupo(id) {
        try {
            const result = await API.get(`/api/grupos/${id}`);
            if (!result.success) throw new Error('load fail');
            const g = result.data;
            document.getElementById('grupoNome').value = g.nome || '';
            document.getElementById('grupoDescricao').value = g.descricao || '';
            document.getElementById('grupoVisivelSite').checked = g.visivel_no_site == 1;
            if (g.imagem_url) mostrarPreviewImagem(g.imagem_url);
            if (g.comissao_investidor_tipo) {
                document.getElementById('comissaoInvestidorTipo').value = g.comissao_investidor_tipo;
                atualizarCamposComissao();
                document.getElementById('comissaoInvestidorValor').value = formatarMoedaInput(g.comissao_investidor_valor);
            }
            pageTitle.textContent = i18n.editTitle;
            habilitarAbasPorFilial(true);
            await carregarFiliais();
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadError }, '*');
            navegarParaLista();
        }
    }

    // ===== FILIAIS =====
    async function carregarFiliais() {
        try {
            const result = await API.get('/api/matrizes-filiais');
            const lista = Array.isArray(result) ? result : (result.data || []);
            filiais = lista.filter(f => f && f.id).map(f => ({
                id: parseInt(f.id),
                nome: f.nome_fantasia || f.razao_social || ('#'+f.id),
                currency_code: f.currency_code || 'BRL',
                locale: f.locale || 'pt_BR',
            }));
            renderizarNavFiliais();
            if (filiais.length > 0) {
                filialAtivaValores = filiais[0].id;
                filialAtivaPrecos = filiais[0].id;
                await selecionarFilialValores(filialAtivaValores);
                await selecionarFilialPrecos(filialAtivaPrecos);
            }
        } catch (e) {
            console.error('Erro ao carregar filiais:', e);
        }
    }
    function renderizarNavFiliais() {
        const navValores = document.getElementById('filiaisNavValores');
        const navPrecos = document.getElementById('filiaisNavPrecos');
        if (!navValores || !navPrecos) return;
        const makeTabs = (mount, onclick) => {
            mount.innerHTML = '';
            filiais.forEach(f => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'filial-tab';
                btn.dataset.id = f.id;
                btn.innerHTML = `${f.nome} <span class="filial-currency">(${f.currency_code})</span>`;
                btn.addEventListener('click', () => onclick(f.id));
                mount.appendChild(btn);
            });
        };
        makeTabs(navValores, id => selecionarFilialValores(id));
        makeTabs(navPrecos, id => selecionarFilialPrecos(id));
    }
    function marcarFilialAtiva(nav, id) {
        document.querySelectorAll(`#${nav.id} .filial-tab`).forEach(b =>
            b.classList.toggle('active', parseInt(b.dataset.id) === parseInt(id))
        );
    }
    function simboloFilial(id) {
        const f = filiais.find(x => x.id === parseInt(id));
        const code = f?.currency_code || 'BRL';
        const map = { BRL: 'R$', EUR: '€', USD: '$', GBP: '£' };
        return map[code] || code;
    }

    // --- ABA 2: valores por filial ---
    async function selecionarFilialValores(id) {
        filialAtivaValores = parseInt(id);
        marcarFilialAtiva(document.getElementById('filiaisNavValores'), id);
        document.getElementById('filiaisLoadingValores').classList.remove('hidden');
        document.getElementById('valoresFilialContent').classList.add('hidden');
        try {
            const res = await API.get(`/api/grupos/${currentId}/precos-filial/${id}`);
            const d = res.data?.valores || {};
            document.querySelectorAll('#valoresFilialContent .filial-currency').forEach(el => el.textContent = simboloFilial(id));
            const bind = (elId, campo, inteiro = false) => {
                const el = document.getElementById(elId);
                const raw = d[campo];
                el.value = inteiro ? (parseInt(raw) || 0) : formatarMoedaInput(raw);
            };
            bind('f_valorPlanoKmPago', 'valor_plano_km_pago');
            bind('f_valorPlanoKmControlado', 'valor_plano_km_controlado');
            bind('f_valorPlanoKmLivre', 'valor_plano_km_livre');
            bind('f_valorKmExcedente', 'valor_km_excedente');
            bind('f_kmFranquia', 'km_franquia', true);
            bind('f_valorSeguroCarro', 'valor_seguro_carro');
            bind('f_valorSeguroTerceiros', 'valor_seguro_terceiros');
            bind('f_coberturaCarro', 'cobertura_carro');
            bind('f_coberturaTerceiros', 'cobertura_terceiros');
            bind('f_minutosTolerancia', 'minutos_tolerancia', true);
            bind('f_valorTolerancia', 'valor_tolerancia');
            bind('f_valorKmRetorno', 'valor_km_retorno');
            bind('f_valorCondutorAdicional', 'valor_condutor_adicional');

            // Carrega faixas (pra aba 3) aproveitando a mesma resposta
            const faixas = res.data?.precos_dias || {};
            faixasPreco.diaria = faixas.diaria || [];
            faixasPreco.km_controlado = faixas.km_controlado || [];
            faixasPreco.km_livre = faixas.km_livre || [];
        } catch (e) {
            console.error('Erro ao carregar valores da filial:', e);
        } finally {
            document.getElementById('filiaisLoadingValores').classList.add('hidden');
            document.getElementById('valoresFilialContent').classList.remove('hidden');
        }
    }

    document.getElementById('btnSalvarValoresFilial')?.addEventListener('click', async () => {
        if (!currentId || !filialAtivaValores) return;
        const payload = { valores: {
            valor_plano_km_pago:    parseMoeda(document.getElementById('f_valorPlanoKmPago').value),
            valor_plano_km_controlado: parseMoeda(document.getElementById('f_valorPlanoKmControlado').value),
            valor_plano_km_livre:   parseMoeda(document.getElementById('f_valorPlanoKmLivre').value),
            valor_km_excedente:     parseMoeda(document.getElementById('f_valorKmExcedente').value),
            km_franquia:            parseInt(document.getElementById('f_kmFranquia').value) || 0,
            valor_seguro_carro:     parseMoeda(document.getElementById('f_valorSeguroCarro').value),
            valor_seguro_terceiros: parseMoeda(document.getElementById('f_valorSeguroTerceiros').value),
            cobertura_carro:        parseMoeda(document.getElementById('f_coberturaCarro').value),
            cobertura_terceiros:    parseMoeda(document.getElementById('f_coberturaTerceiros').value),
            minutos_tolerancia:     parseInt(document.getElementById('f_minutosTolerancia').value) || 0,
            valor_tolerancia:       parseMoeda(document.getElementById('f_valorTolerancia').value),
            valor_km_retorno:       parseMoeda(document.getElementById('f_valorKmRetorno').value),
            valor_condutor_adicional: parseMoeda(document.getElementById('f_valorCondutorAdicional').value),
        } };
        try {
            const r = await API.post(`/grupos/${currentId}/precos-filial/${filialAtivaValores}`, payload);
            if (r.success) {
                window.parent.postMessage({ action:'showToast', type:'success', message: i18n.branchValuesSaved }, '*');
            } else {
                window.parent.postMessage({ action:'openAlert', message: r.message || i18n.saveError }, '*');
            }
        } catch (e) {
            console.error(e);
            window.parent.postMessage({ action:'openAlert', message: i18n.saveServerError }, '*');
        }
    });

    // --- ABA 3: precos por dias por filial ---
    async function selecionarFilialPrecos(id) {
        filialAtivaPrecos = parseInt(id);
        marcarFilialAtiva(document.getElementById('filiaisNavPrecos'), id);
        document.getElementById('filiaisLoadingPrecos').classList.remove('hidden');
        document.getElementById('precosFilialContent').classList.add('hidden');
        try {
            const res = await API.get(`/api/grupos/${currentId}/precos-filial/${id}`);
            const faixas = res.data?.precos_dias || {};
            faixasPreco.diaria = faixas.diaria || [];
            faixasPreco.km_controlado = faixas.km_controlado || [];
            faixasPreco.km_livre = faixas.km_livre || [];
            renderizarFaixas('diaria');
            renderizarFaixas('km_controlado');
            renderizarFaixas('km_livre');
        } catch (e) {
            console.error('Erro ao carregar preços por dias:', e);
        } finally {
            document.getElementById('filiaisLoadingPrecos').classList.add('hidden');
            document.getElementById('precosFilialContent').classList.remove('hidden');
        }
    }

    document.getElementById('btnSalvarPrecosFilial')?.addEventListener('click', async () => {
        if (!currentId || !filialAtivaPrecos) return;
        const payload = { precos_dias: {
            diaria:         faixasFiltradas('diaria'),
            km_controlado:  faixasFiltradas('km_controlado'),
            km_livre:       faixasFiltradas('km_livre'),
        } };
        try {
            const r = await API.post(`/grupos/${currentId}/precos-filial/${filialAtivaPrecos}`, payload);
            if (r.success) {
                window.parent.postMessage({ action:'showToast', type:'success', message: i18n.branchPricesSaved }, '*');
            } else {
                window.parent.postMessage({ action:'openAlert', message: r.message || i18n.saveError }, '*');
            }
        } catch (e) {
            console.error(e);
            window.parent.postMessage({ action:'openAlert', message: i18n.saveServerError }, '*');
        }
    });

    function faixasFiltradas(tipo) {
        return (faixasPreco[tipo] || [])
            .filter(f => f.dia_inicio && f.valor)
            .map(f => ({
                dia_inicio: parseInt(f.dia_inicio),
                dia_fim:    f.dia_fim ? parseInt(f.dia_fim) : null,
                valor:      parseMoeda(String(f.valor)),
            }));
    }

    // Sub-tabs km_*
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('#tabPrecos .tab-content').forEach(c => c.classList.remove('active-content'));
            document.getElementById(`tab-${tab}`).classList.add('active-content');
        });
    });

    // Faixas de preco — add/remove/render
    document.querySelectorAll('.btn-add-faixa').forEach(btn => {
        btn.addEventListener('click', function() { adicionarFaixa(this.dataset.tipo); });
    });
    function adicionarFaixa(tipo, dados = null) {
        faixasPreco[tipo].push(dados || { dia_inicio: '', dia_fim: '', valor: '' });
        renderizarFaixas(tipo);
    }
    function removerFaixa(tipo, index) {
        faixasPreco[tipo].splice(index, 1);
        renderizarFaixas(tipo);
    }
    window.removerFaixaGlobal = (tipo, index) => removerFaixa(tipo, index);

    function renderizarFaixas(tipo) {
        const container = document.getElementById(`faixas-${tipo}`);
        if (!container) return;
        if (!faixasPreco[tipo].length) {
            container.innerHTML = '<p class="text-sm text-slate-500 italic">' + i18n.noRanges + '</p>';
            return;
        }
        const symbol = simboloFilial(filialAtivaPrecos);
        let html = '';
        faixasPreco[tipo].forEach((faixa, index) => {
            html += `
                <div class="faixa-item">
                    <span class="text-sm text-slate-600">${i18n.rangeFrom}</span>
                    <input type="number" class="faixa-dia-inicio" data-tipo="${tipo}" data-index="${index}"
                           value="${faixa.dia_inicio}" min="1" placeholder="1">
                    <span class="text-sm text-slate-600">${i18n.rangeTo}</span>
                    <input type="number" class="faixa-dia-fim" data-tipo="${tipo}" data-index="${index}"
                           value="${faixa.dia_fim || ''}" min="1" placeholder="${i18n.rangeInfinity}">
                    <span class="text-sm text-slate-600">${i18n.rangeDaysEquals}</span>
                    <span class="currency-symbol text-slate-500">${symbol}</span>
                    <input type="text" class="faixa-valor input-valor input-moeda" data-tipo="${tipo}" data-index="${index}"
                           value="${formatarMoedaInput(faixa.valor)}" placeholder="0,00">
                    <button type="button" class="btn-remove" onclick="removerFaixaGlobal('${tipo}', ${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
        });
        container.innerHTML = html;
        container.querySelectorAll('.input-moeda').forEach(aplicarMascaraMoeda);
        container.querySelectorAll('.faixa-dia-inicio, .faixa-dia-fim, .faixa-valor').forEach(input => {
            input.addEventListener('change', function() {
                const t = this.dataset.tipo;
                const i = parseInt(this.dataset.index);
                const campo = this.classList.contains('faixa-dia-inicio') ? 'dia_inicio' :
                              this.classList.contains('faixa-dia-fim') ? 'dia_fim' : 'valor';
                if (campo === 'valor') {
                    faixasPreco[t][i][campo] = this.value;
                } else {
                    faixasPreco[t][i][campo] = this.value ? parseInt(this.value) : null;
                }
            });
        });
    }

    // ===== SUBMISSAO FORM (ABA 1) =====
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        // Só a Aba 1 submete via submit nativo — demais tem botão próprio.
        // Se a Aba ativa não for tabDados, não faz nada (button "type=submit" só existe lá).
        const nome = document.getElementById('grupoNome').value.trim();
        if (!nome) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.nameRequired }, '*');
            return;
        }
        const dados = {
            nome,
            descricao: document.getElementById('grupoDescricao').value.trim() || null,
            visivel_no_site: document.getElementById('grupoVisivelSite').checked ? 1 : 0,
            comissao_investidor_tipo: document.getElementById('comissaoInvestidorTipo').value || null,
            comissao_investidor_valor: document.getElementById('comissaoInvestidorTipo').value
                ? parseMoeda(document.getElementById('comissaoInvestidorValor').value) : null,
        };
        if (imagemBase64) dados.imagem_base64 = imagemBase64;
        if (removerImagem) dados.remover_imagem = true;

        try {
            let result;
            if (isEditMode && currentId) {
                result = await API.post(`/grupos/${currentId}/atualizar`, dados);
            } else {
                result = await API.post('/grupos/salvar', dados);
            }
            if (result.success) {
                window.parent.postMessage({
                    action:'showToast', type:'success',
                    message: isEditMode ? i18n.updated : i18n.created
                }, '*');
                if (!isEditMode && result.data?.id) {
                    // Modo novo → vira edição, habilita abas 2/3
                    isEditMode = true;
                    currentId = result.data.id;
                    document.getElementById('grupoId').value = currentId;
                    pageTitle.textContent = i18n.editTitle;
                    habilitarAbasPorFilial(true);
                    await carregarFiliais();
                }
            } else {
                window.parent.postMessage({ action:'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error(error);
            window.parent.postMessage({ action:'openAlert', message: i18n.saveServerError }, '*');
        }
    });

    // ===== INICIALIZACAO =====
    atualizarCamposComissao();
})();
</script>
@endsection
