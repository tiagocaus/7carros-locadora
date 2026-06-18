@extends('layouts.iframe')

@section('title', t('modules.manutencao.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-4">
        <h2 class="title-page" id="pageTitle"><?= t('modules.manutencao.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formManutencao" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Navegacao de Abas -->
        <div class="mb-4 border-b border-slate-300">
            <nav class="flex -mb-px" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabDados" class="form-tab-button active">
                    <i class="fas fa-wrench mr-2"></i><?= t('modules.manutencao.tabs.data') ?>
                </button>
                <button type="button" data-form-tab-target="#tabItens" class="form-tab-button">
                    <i class="fas fa-list mr-2"></i><?= t('modules.manutencao.tabs.items') ?>
                </button>
                <button type="button" data-form-tab-target="#tabFinanceiro" class="form-tab-button" id="tabFinanceiroBtn" style="display: none;">
                    <i class="fas fa-dollar-sign mr-2"></i><?= t('modules.manutencao.tabs.financial') ?>
                </button>
            </nav>
        </div>

        <!-- Aba 1: Dados Principais -->
        <div id="tabDados" class="form-tab-content active">
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-info-circle mr-2"></i><?= t('modules.manutencao.sections.maintenance_data') ?></h3>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- OS -->
                    <div class="md:col-span-2 form-input-group">
                        <label for="os" class="form-label-group"><?= t('modules.manutencao.fields.os') ?></label>
                        <input type="text" id="os" name="os" class="form-input-group-field bg-slate-100" readonly>
                    </div>

                    <!-- Status -->
                    <div class="md:col-span-2 form-input-group">
                        <label for="status" class="form-label-group"><?= t('modules.manutencao.fields.status') ?></label>
                        <select id="status" name="status" class="form-input-group-field">
                            <option value="C"><?= t('modules.manutencao.status_options.created') ?></option>
                            <option value="A"><?= t('modules.manutencao.status_options.open') ?></option>
                            <option value="F"><?= t('modules.manutencao.status_options.closed') ?></option>
                        </select>
                    </div>

                    <!-- Filial -->
                    <div class="md:col-span-4 form-input-group">
                        <label for="id_matriz_filial" class="form-label-group"><?= t('modules.manutencao.fields.branch') ?></label>
                        <select id="id_matriz_filial" name="id_matriz_filial"
                                class="form-input-group-field chosen-select"
                                data-chosen-type="server-side"
                                data-chosen-search-url="/api/matrizes-filiais/buscar"
                                data-chosen-placeholder="<?= t('modules.manutencao.placeholders.select') ?>">
                            <option value=""><?= t('modules.manutencao.placeholders.select') ?></option>
                        </select>
                    </div>

                    <!-- Veiculo -->
                    <div class="md:col-span-4 form-input-group">
                        <label for="id_veiculo" class="form-label-group">
                            <?= t('modules.manutencao.fields.vehicle') ?> <span class="text-red-500">*</span>
                        </label>
                        <select id="id_veiculo" name="id_veiculo"
                                class="form-input-group-field chosen-select"
                                data-chosen-type="server-side"
                                data-chosen-search-url="/api/veiculos/buscar"
                                data-chosen-placeholder="<?= t('modules.manutencao.placeholders.search_type') ?>"
                                required>
                            <option value=""><?= t('modules.manutencao.placeholders.select') ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <!-- Oficina -->
                    <div class="md:col-span-12 form-input-group">
                        <label for="id_oficina" class="form-label-group"><?= t('modules.manutencao.fields.workshop') ?></label>
                        <select id="id_oficina" name="id_oficina"
                                class="form-input-group-field chosen-select"
                                data-chosen-type="server-side"
                                data-chosen-search-url="/api/oficinas/buscar"
                                data-chosen-placeholder="<?= t('modules.manutencao.placeholders.search_type') ?>">
                            <option value=""><?= t('modules.manutencao.placeholders.select') ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Envio e Retorno lado a lado -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Secao Envio -->
                <div class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-arrow-up mr-2"></i><?= t('modules.manutencao.sections.send_to_workshop') ?></h3>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-6 form-input-group">
                            <label for="data_enviado" class="form-label-group"><?= t('modules.manutencao.fields.send_date') ?></label>
                            <input type="datetime-local" id="data_enviado" name="data_enviado" class="form-input-group-field">
                        </div>
                        <div class="col-span-3 form-input-group">
                            <label for="odo_enviado" class="form-label-group"><?= t('modules.manutencao.fields.odometer') ?></label>
                            <input type="text" id="odo_enviado" name="odo_enviado" class="form-input-group-field input-km">
                        </div>
                        <div class="col-span-3 form-input-group">
                            <label for="tanque_enviado" class="form-label-group"><?= t('modules.manutencao.fields.tank') ?></label>
                            <select id="tanque_enviado" name="tanque_enviado" class="form-input-group-field">
                                <option value="">-</option>
                                <option value="8"><?= t('modules.manutencao.tank_levels.full') ?></option>
                                <option value="7">7/8</option>
                                <option value="6">3/4</option>
                                <option value="5">5/8</option>
                                <option value="4">1/2</option>
                                <option value="3">3/8</option>
                                <option value="2">1/4</option>
                                <option value="1">1/8</option>
                                <option value="0"><?= t('modules.manutencao.tank_levels.reserve') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-12 gap-4 mt-4">
                        <div class="col-span-12 form-input-group">
                            <label for="motivo" class="form-label-group"><?= t('modules.manutencao.fields.send_reason') ?></label>
                            <textarea id="motivo" name="motivo" class="form-input-group-field" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Secao Retorno -->
                <div class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-arrow-down mr-2"></i><?= t('modules.manutencao.sections.return_from_workshop') ?></h3>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-6 form-input-group">
                            <label for="data_retorno" class="form-label-group"><?= t('modules.manutencao.fields.return_date') ?></label>
                            <input type="datetime-local" id="data_retorno" name="data_retorno" class="form-input-group-field">
                        </div>
                        <div class="col-span-3 form-input-group">
                            <label for="odo_retorno" class="form-label-group"><?= t('modules.manutencao.fields.odometer') ?></label>
                            <input type="text" id="odo_retorno" name="odo_retorno" class="form-input-group-field input-km">
                        </div>
                        <div class="col-span-3 form-input-group">
                            <label for="tanque_retorno" class="form-label-group"><?= t('modules.manutencao.fields.tank') ?></label>
                            <select id="tanque_retorno" name="tanque_retorno" class="form-input-group-field">
                                <option value="">-</option>
                                <option value="8"><?= t('modules.manutencao.tank_levels.full') ?></option>
                                <option value="7">7/8</option>
                                <option value="6">3/4</option>
                                <option value="5">5/8</option>
                                <option value="4">1/2</option>
                                <option value="3">3/8</option>
                                <option value="2">1/4</option>
                                <option value="1">1/8</option>
                                <option value="0"><?= t('modules.manutencao.tank_levels.reserve') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-12 gap-4 mt-4">
                        <div class="col-span-12 form-input-group">
                            <label for="obs_oficina" class="form-label-group"><?= t('modules.manutencao.fields.workshop_notes') ?></label>
                            <textarea id="obs_oficina" name="obs_oficina" class="form-input-group-field" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Secao Servicos Realizados -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-tools mr-2"></i><?= t('modules.manutencao.sections.services_performed') ?></h3>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.manutencao.sections.services_performed_note') ?></p>
                <div class="flex gap-6">
                    <div class="form-input-group">
                        <div class="flex items-center">
                            <input type="checkbox" id="trocou_oleo" name="trocou_oleo" value="S" class="form-checkbox">
                            <label for="trocou_oleo" class="ml-2 text-sm"><?= t('modules.manutencao.fields.changed_oil') ?></label>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <div class="flex items-center">
                            <input type="checkbox" id="trocou_pneus" name="trocou_pneus" value="S" class="form-checkbox">
                            <label for="trocou_pneus" class="ml-2 text-sm"><?= t('modules.manutencao.fields.changed_tires') ?></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba 2: Itens/Servicos -->
        <div id="tabItens" class="form-tab-content">
            <div class="form-section mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="form-section-title"><i class="fas fa-list mr-2"></i><?= t('modules.manutencao.sections.maintenance_items') ?></h3>
                    <button type="button" id="btnAdicionarItem" class="btn-blue py-1 px-3 text-sm">
                        <i class="fas fa-plus mr-1"></i><?= t('modules.manutencao.actions.add_item') ?>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-slate-200" id="itensTable">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase w-96"><?= t('modules.manutencao.fields.product') ?></th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase w-20"><?= t('modules.manutencao.fields.qty') ?></th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase w-28"><?= t('modules.manutencao.fields.unit_value') ?></th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase w-28"><?= t('modules.manutencao.fields.discount') ?></th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase w-28"><?= t('modules.manutencao.fields.total_value') ?></th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase w-20"><?= t('modules.manutencao.fields.status') ?></th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase w-16"><?= t('modules.manutencao.fields.action') ?></th>
                            </tr>
                        </thead>
                        <tbody id="itensTableBody" class="bg-white divide-y divide-slate-200">
                            <!-- Itens serao inseridos aqui -->
                        </tbody>
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right text-sm font-bold"><?= t('modules.manutencao.table.totals') ?></td>
                                <td class="px-3 py-2 text-right text-sm font-bold" id="totalDescontos"><?= currency_format(0) ?></td>
                                <td class="px-3 py-2 text-right text-sm font-bold" id="totalServicos"><?= currency_format(0) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-4 flex gap-4 text-sm">
                    <div><span class="font-medium"><?= t('modules.manutencao.table.total_paid') ?></span> <span id="totalPago" class="text-green-600"><?= currency_format(0) ?></span></div>
                    <div><span class="font-medium"><?= t('modules.manutencao.table.total_pending') ?></span> <span id="totalPendente" class="text-amber-600"><?= currency_format(0) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Aba 3: Financeiro -->
        <div id="tabFinanceiro" class="form-tab-content">
            <div class="form-section mb-6">
                <h3 class="form-section-title"><i class="fas fa-dollar-sign mr-2"></i><?= t('modules.manutencao.sections.financial_entries') ?></h3>

                <div class="mb-4">
                    <p class="text-sm text-slate-600 mb-4">
                        <?= t('modules.manutencao.messages.financial_desc') ?>
                    </p>

                    <div class="flex gap-2 mb-4">
                        <button type="button" id="btnLancamentoCompleto" class="btn-blue py-2 px-4 text-sm">
                            <i class="fas fa-file-invoice-dollar mr-2"></i><?= t('modules.manutencao.actions.create_full_entry') ?>
                        </button>
                        <button type="button" id="btnLancamentoParcial" class="btn-secondary py-2 px-4 text-sm">
                            <i class="fas fa-file-invoice mr-2"></i><?= t('modules.manutencao.actions.close_selected') ?>
                        </button>
                    </div>
                </div>

                <!-- Grid de itens pendentes -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-slate-200" id="itensPendentesTable">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">
                                    <input type="checkbox" id="checkAllPendentes" class="form-checkbox">
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase"><?= t('modules.manutencao.fields.description') ?></th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase w-28"><?= t('modules.manutencao.fields.qty') ?></th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase w-36"><?= t('modules.manutencao.fields.value') ?></th>
                            </tr>
                        </thead>
                        <tbody id="itensPendentesTableBody" class="bg-white divide-y divide-slate-200">
                            <!-- Itens pendentes -->
                        </tbody>
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right text-sm font-bold"><?= t('modules.manutencao.table.total_selected') ?></td>
                                <td class="px-3 py-2 text-right text-sm font-bold" id="totalSelecionado"><?= currency_format(0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Configuracao do lancamento -->
                <div id="configFinanceiro" class="mt-4 p-4 bg-slate-50 rounded-lg" style="display: none;">
                    <h4 class="font-medium mb-3"><?= t('modules.manutencao.sections.entry_config') ?></h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="form-input-group">
                            <label for="fin_forma_pagamento" class="form-label-group"><?= t('modules.manutencao.fields.payment_method') ?></label>
                            <select id="fin_forma_pagamento" class="form-input-group-field">
                                <option value=""><?= t('modules.manutencao.placeholders.select') ?></option>
                            </select>
                        </div>
                        <div class="form-input-group">
                            <label for="fin_parcelas" class="form-label-group"><?= t('modules.manutencao.fields.installments') ?></label>
                            <input type="number" id="fin_parcelas" class="form-input-group-field" value="1" min="1" max="24">
                        </div>
                        <div class="form-input-group">
                            <label for="fin_data_vencimento" class="form-label-group"><?= t('modules.manutencao.fields.first_due_date') ?></label>
                            <input type="date" id="fin_data_vencimento" class="form-input-group-field">
                        </div>
                        <div class="form-input-group">
                            <label for="fin_intervalo" class="form-label-group"><?= t('modules.manutencao.fields.interval_days') ?></label>
                            <input type="number" id="fin_intervalo" class="form-input-group-field" value="30" min="1">
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" id="btnConfirmarLancamento" class="btn-green py-2 px-4 text-sm">
                            <i class="fas fa-check mr-1"></i><?= t('common.buttons.confirm') ?>
                        </button>
                        <button type="button" id="btnCancelarLancamento" class="btn-secondary py-2 px-4 text-sm">
                            <?= t('common.buttons.cancel') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botoes de acao -->
        <div class="mt-6 flex justify-between">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" class="btn-blue py-2 px-6 rounded-md">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
window.manutencoesAuditI18n = <?= json_encode([
    'tabs' => [
        'data' => t('modules.manutencao.tabs.data'),
        'items' => t('modules.manutencao.tabs.items'),
    ],
    'fields' => [
        'os' => t('modules.manutencao.fields.os'),
        'status' => t('modules.manutencao.fields.status'),
        'branch' => t('modules.manutencao.fields.branch'),
        'vehicle' => t('modules.manutencao.fields.vehicle'),
        'workshop' => t('modules.manutencao.fields.workshop'),
        'send_date' => t('modules.manutencao.fields.send_date'),
        'send_odometer' => t('modules.manutencao.fields.send_odometer'),
        'send_tank' => t('modules.manutencao.fields.send_tank'),
        'send_reason' => t('modules.manutencao.fields.send_reason'),
        'return_date' => t('modules.manutencao.fields.return_date'),
        'return_odometer' => t('modules.manutencao.fields.return_odometer'),
        'return_tank' => t('modules.manutencao.fields.return_tank'),
        'workshop_notes' => t('modules.manutencao.fields.workshop_notes'),
        'changed_oil' => t('modules.manutencao.fields.changed_oil'),
        'changed_tires' => t('modules.manutencao.fields.changed_tires'),
        'description' => t('modules.manutencao.fields.description'),
        'qty' => t('modules.manutencao.fields.qty'),
        'unit_value' => t('modules.manutencao.fields.unit_value'),
        'discount' => t('modules.manutencao.fields.discount'),
        'total_value' => t('modules.manutencao.fields.total_value'),
    ],
    'sections' => [
        'maintenance_items' => t('modules.manutencao.sections.maintenance_items'),
    ],
    'status' => [
        'created' => t('modules.manutencao.status_options.created'),
        'open' => t('modules.manutencao.status_options.open'),
        'closed' => t('modules.manutencao.status_options.closed'),
    ],
    'tank' => [
        'reserve' => t('modules.manutencao.tank_levels.reserve'),
        'full' => t('modules.manutencao.tank_levels.full'),
    ],
    'badges' => [
        'paid' => t('modules.manutencao.badges.paid'),
        'pending' => t('modules.manutencao.badges.pending'),
    ],
    'common' => [
        'yes' => t('common.labels.yes'),
        'no' => t('common.labels.no'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script>
(function() {
    const i18n = <?= json_encode([
        'newTitle' => t('modules.manutencao.new_title'),
        'editTitle' => t('modules.manutencao.edit_title'),
        'loadError' => t('modules.manutencao.messages.load_error'),
        'serverError' => t('modules.manutencao.messages.server_error'),
        'saveError' => t('modules.manutencao.messages.save_error'),
        'saveSuccess' => t('modules.manutencao.messages.save_success'),
        'savedTitle' => t('modules.manutencao.messages.saved_title'),
        'savedGoToList' => t('modules.manutencao.messages.saved_go_to_list'),
        'goToList' => t('modules.manutencao.actions.go_to_list'),
        'statusCreated' => t('modules.manutencao.status_options.created'),
        'statusOpen' => t('modules.manutencao.status_options.open'),
        'statusClosed' => t('modules.manutencao.status_options.closed'),
        'odometerRequired' => t('modules.manutencao.messages.odometer_required'),
        'noItems' => t('modules.manutencao.messages.no_items'),
        'noPendingItems' => t('modules.manutencao.messages.no_pending_items'),
        'badgePaid' => t('modules.manutencao.badges.paid'),
        'badgePending' => t('modules.manutencao.badges.pending'),
        'badgeNew' => t('modules.manutencao.badges.new'),
        'badgeEditing' => t('modules.manutencao.badges.editing'),
        'actionEdit' => t('common.buttons.edit'),
        'actionRemove' => t('common.buttons.delete'),
        'actionConfirm' => t('common.buttons.confirm'),
        'actionCancel' => t('common.buttons.cancel'),
        'actionSave' => t('common.buttons.save'),
        'selectProduct' => t('modules.manutencao.messages.select_product'),
        'cannotRemovePaid' => t('modules.manutencao.messages.cannot_remove_paid'),
        'cannotEditPaid' => t('modules.manutencao.messages.cannot_edit_paid'),
        'provideDescription' => t('modules.manutencao.messages.provide_description'),
        'productOutOfStock' => t('modules.manutencao.messages.product_out_of_stock'),
        'stockInsufficient' => t('modules.manutencao.messages.stock_insufficient'),
        'discountExceedsSubtotal' => t('modules.manutencao.messages.discount_exceeds_subtotal'),
        'selectAtLeastOne' => t('modules.manutencao.messages.select_at_least_one'),
        'entryCreated' => t('modules.manutencao.messages.entry_created'),
        'genericError' => t('modules.manutencao.messages.generic_error'),
        'placeholderSelect' => t('modules.manutencao.placeholders.select'),
        'placeholderSearchProduct' => t('modules.manutencao.placeholders.search_product'),
        'placeholderSearchProductService' => t('modules.manutencao.placeholders.search_product_service'),
        'placeholderItemDescription' => t('modules.manutencao.placeholders.item_description'),
        'placeholderManualDescription' => t('modules.manutencao.placeholders.manual_description'),
        'auditSection' => t('modules.manutencao.audit_financial.section'),
        'auditType' => t('modules.manutencao.audit_financial.type'),
        'auditComplete' => t('modules.manutencao.audit_financial.complete'),
        'auditPartial' => t('modules.manutencao.audit_financial.partial'),
        'auditPaymentMethod' => t('modules.manutencao.audit_financial.payment_method'),
        'auditInstallments' => t('modules.manutencao.audit_financial.installments'),
        'auditFirstDueDate' => t('modules.manutencao.audit_financial.first_due_date'),
        'auditInterval' => t('modules.manutencao.audit_financial.interval'),
        'auditDays' => t('modules.manutencao.audit_financial.days'),
        'auditTotalValue' => t('modules.manutencao.audit_financial.total_value'),
        'auditSelectedItems' => t('modules.manutencao.audit_financial.selected_items'),
        'auditItem' => t('modules.manutencao.audit_financial.item'),
        'auditValue' => t('modules.manutencao.audit_financial.value'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // Estado
    const urlParams = new URLSearchParams(window.location.search);
    const registroId = urlParams.get('id');
    let itensData = [];
    let itensPendentesData = [];
    let tipoLancamento = null; // 'completo' ou 'parcial'
    let statusOriginal = 'C'; // Status original da manutencao
    let veiculoDados = null; // Dados do veiculo selecionado (odometro, tanque)
    let saveResult = null; // Resultado do salvamento para usar apos confirmacao do modal

    // Expor itensData globalmente para o audit handler
    window.itensData = itensData;

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    document.getElementById('btnVoltar')?.addEventListener('click', () => navegarPara('/pages/manutencoes'));
    document.getElementById('btnCancelar')?.addEventListener('click', () => navegarPara('/pages/manutencoes'));

    // ===== ABAS =====

    document.querySelectorAll('[data-form-tab-target]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-form-tab-target');

            // Remover active de todos
            document.querySelectorAll('.form-tab-button').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.form-tab-content').forEach(c => c.classList.remove('active'));

            // Ativar selecionado
            this.classList.add('active');
            document.querySelector(target)?.classList.add('active');
        });
    });

    // ===== CARREGAR DADOS =====

    async function carregarDados(id) {
        try {
            const result = await API.get('/api/manutencoes/' + id);
            if (result.success) {
                preencherFormulario(result.data);
            } else {
                toast.error(result.message || i18n.loadError);
            }
        } catch (error) {
            toast.error(error.message || i18n.serverError);
        }
    }

    function preencherFormulario(m) {
        document.getElementById('registroId').value = m.id;
        document.getElementById('os').value = m.os;
        document.getElementById('status').value = m.status;
        document.getElementById('motivo').value = m.motivo || '';

        // Guardar status original e configurar opcoes do select
        statusOriginal = m.status;
        configurarOpcoesStatus(statusOriginal);
        document.getElementById('obs_oficina').value = m.obs_oficina || '';
        document.getElementById('odo_enviado').value = m.odo_enviado ? Km.format(m.odo_enviado) : '';
        document.getElementById('odo_retorno').value = m.odo_retorno ? Km.format(m.odo_retorno) : '';
        document.getElementById('tanque_enviado').value = m.tanque_enviado || '';
        document.getElementById('tanque_retorno').value = m.tanque_retorno || '';

        if (m.trocou_oleo === 'S') document.getElementById('trocou_oleo').checked = true;
        if (m.trocou_pneus === 'S') document.getElementById('trocou_pneus').checked = true;

        // Datas
        if (m.data_enviado) {
            document.getElementById('data_enviado').value = m.data_enviado.replace(' ', 'T').substring(0, 16);
        }
        if (m.data_retorno) {
            document.getElementById('data_retorno').value = m.data_retorno.replace(' ', 'T').substring(0, 16);
        }

        // Selects com chosen
        if (m.id_matriz_filial) {
            setChosenValue('id_matriz_filial', m.id_matriz_filial, m.filial_nome);
        }
        if (m.id_veiculo) {
            const veiculoText = `${m.veiculo_placa} - ${m.veiculo_marca} ${m.veiculo_modelo}`;
            setChosenValue('id_veiculo', m.id_veiculo, veiculoText);
            // Buscar dados do veiculo para auto-preenchimento
            buscarDadosVeiculo(m.id_veiculo);
        }
        if (m.id_oficina) {
            setChosenValue('id_oficina', m.id_oficina, m.oficina_nome);
        }

        // Itens
        if (m.itens) {
            itensData = m.itens;
            window.itensData = itensData; // Sincronizar com global
            renderItens();
        }

        // Totais
        document.getElementById('totalPago').textContent = Currency.format(m.total_pago || 0, true);
        document.getElementById('totalPendente').textContent = Currency.format(m.total_pendente || 0, true);

        // Titulo
        document.getElementById('pageTitle').textContent = i18n.editTitle + ' - ' + m.os;

        // Mostrar aba financeiro se nao for nova
        document.getElementById('tabFinanceiroBtn').style.display = 'block';

        // Carregar itens pendentes
        carregarItensPendentes(m.id);

        // Re-capturar estado inicial para auditoria apos carregar dados
        // Timeout maior para garantir que todos os dados foram renderizados
        setTimeout(() => {
            // Garantir sincronizacao do itensData com a variavel global
            window.itensData = itensData;

            const form = document.getElementById('formManutencao');
            if (form && window.FormAudit) {
                FormAudit.recapture(form);
            }
        }, 800);
    }

    // ===== HELPERS DE QUANTIDADE =====

    // Formata quantidade para exibicao (formato brasileiro: 1,000)
    function formatarQtd(valor) {
        const num = parseFloat(valor) || 0;
        return num.toFixed(3).replace('.', ',');
    }

    // Converte quantidade do formato brasileiro para numero
    function parseQtd(valor) {
        if (typeof valor === 'number') return valor;
        return parseFloat(String(valor).replace(',', '.')) || 0;
    }

    function setChosenValue(selectId, value, text) {
        const select = document.getElementById(selectId);
        if (!select) return;

        // Adicionar option se nao existir
        let option = select.querySelector(`option[value="${value}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            select.appendChild(option);
        }
        select.value = value;

        // Atualizar chosen customizado
        if (select.chosenSelect) {
            select.chosenSelect.selectedValue = value;
            select.chosenSelect.display.textContent = text;
            if (select.chosenSelect.clearButton) {
                select.chosenSelect.clearButton.style.display = value ? 'block' : 'none';
            }
        }
    }

    // ===== CONTROLE DE STATUS =====

    /**
     * Configura as opcoes do select de status baseado no status atual
     * Transicoes permitidas:
     * - C -> A, F
     * - A -> F
     * - F -> A
     */
    function configurarOpcoesStatus(status) {
        const select = document.getElementById('status');
        if (!select) return;

        const opcoes = {
            'C': { value: 'C', text: i18n.statusCreated },
            'A': { value: 'A', text: i18n.statusOpen },
            'F': { value: 'F', text: i18n.statusClosed }
        };

        const transicoesPermitidas = {
            'C': ['C', 'A', 'F'],
            'A': ['A', 'F'],
            'F': ['F', 'A']
        };

        const permitidas = transicoesPermitidas[status] || ['C', 'A', 'F'];

        // Recriar opcoes
        select.innerHTML = '';
        permitidas.forEach(s => {
            const opt = document.createElement('option');
            opt.value = opcoes[s].value;
            opt.textContent = opcoes[s].text;
            select.appendChild(opt);
        });

        select.value = status;
    }

    /**
     * Busca dados do veiculo para auto-preenchimento
     */
    async function buscarDadosVeiculo(idVeiculo) {
        if (!idVeiculo) {
            veiculoDados = null;
            return;
        }

        try {
            const result = await API.get('/api/veiculos/' + idVeiculo);
            if (result.success && result.data) {
                veiculoDados = {
                    odometro: result.data.odometro || null,
                    tanque: result.data.tanque_fracao || null
                };
            }
        } catch (error) {
            console.error('Erro ao buscar dados do veiculo:', error);
            veiculoDados = null;
        }
    }

    /**
     * Auto-preenche campos ao mudar status para Aberta
     */
    function autoPreencherAoAbrir() {
        // Preencher odometro de envio com dados do veiculo
        const odoEnviado = document.getElementById('odo_enviado');
        if (odoEnviado && !odoEnviado.value && veiculoDados?.odometro) {
            odoEnviado.value = Km.format(veiculoDados.odometro);
        }

        // Preencher tanque de envio com dados do veiculo
        const tanqueEnviado = document.getElementById('tanque_enviado');
        if (tanqueEnviado && !tanqueEnviado.value && veiculoDados?.tanque) {
            tanqueEnviado.value = veiculoDados.tanque;
        }

        // Preencher data de envio com data/hora atual
        const dataEnviado = document.getElementById('data_enviado');
        if (dataEnviado && !dataEnviado.value) {
            const now = new Date();
            dataEnviado.value = now.toISOString().slice(0, 16);
        }
    }

    /**
     * Atualiza campos de retorno como obrigatorios baseado no status
     * Campos obrigatorios quando: status original era A e novo status e F
     */
    function atualizarCamposRetornoObrigatorios() {
        const statusAtual = document.getElementById('status').value;
        const deveSerObrigatorio = statusOriginal === 'A' && statusAtual === 'F';

        // Campos normais (data e tanque) - required funciona normalmente
        ['data_retorno', 'tanque_retorno'].forEach(campo => {
            const element = document.getElementById(campo);
            if (element) {
                if (deveSerObrigatorio) {
                    element.setAttribute('required', '');
                } else {
                    element.removeAttribute('required');
                }
            }
        });

        // Campo odo_retorno - validacao customizada para KM (valor 0 nao e valido)
        const odoRetorno = document.getElementById('odo_retorno');
        if (odoRetorno) {
            if (deveSerObrigatorio) {
                odoRetorno.setAttribute('required', '');

                // Validar se valor > 0 (campo com mascara KM pode ter "0" como vazio)
                const valor = Km.parse(odoRetorno.value) || 0;
                if (valor <= 0) {
                    odoRetorno.setCustomValidity(i18n.odometerRequired);
                } else {
                    odoRetorno.setCustomValidity('');
                }

                // Adicionar listener para revalidar ao digitar
                odoRetorno.removeEventListener('input', validarOdoRetorno);
                odoRetorno.addEventListener('input', validarOdoRetorno);
            } else {
                odoRetorno.removeAttribute('required');
                odoRetorno.setCustomValidity('');
                odoRetorno.removeEventListener('input', validarOdoRetorno);
            }
        }

        // Atualizar asteriscos visuais nos labels
        atualizarAsteriscosRetorno(deveSerObrigatorio);
    }

    /**
     * Valida campo odo_retorno em tempo real
     * Considera valor 0 como nao preenchido (campo com mascara KM)
     */
    function validarOdoRetorno() {
        const odoRetorno = document.getElementById('odo_retorno');
        if (odoRetorno && odoRetorno.hasAttribute('required')) {
            const valor = Km.parse(odoRetorno.value) || 0;
            if (valor <= 0) {
                odoRetorno.setCustomValidity(i18n.odometerRequired);
            } else {
                odoRetorno.setCustomValidity('');
            }
        }
    }

    /**
     * Adiciona/remove asteriscos visuais nos labels dos campos de retorno
     */
    function atualizarAsteriscosRetorno(obrigatorio) {
        const campos = ['data_retorno', 'odo_retorno', 'tanque_retorno'];

        campos.forEach(campo => {
            const inputGroup = document.getElementById(campo)?.closest('.form-input-group');
            const label = inputGroup?.querySelector('.form-label-group');
            if (label) {
                // Remover asterisco existente
                const asterisco = label.querySelector('.text-red-500');
                if (asterisco) asterisco.remove();

                // Adicionar se obrigatorio
                if (obrigatorio) {
                    const span = document.createElement('span');
                    span.className = 'text-red-500';
                    span.textContent = ' *';
                    label.appendChild(span);
                }
            }
        });
    }

    // Listener para mudanca de status
    document.getElementById('status')?.addEventListener('change', function() {
        const novoStatus = this.value;

        // Se mudar para Aberta, auto-preencher campos
        if (novoStatus === 'A' && statusOriginal !== 'A') {
            autoPreencherAoAbrir();
        }

        // Atualizar campos obrigatorios de retorno
        atualizarCamposRetornoObrigatorios();
    });

    // Listener para mudanca de veiculo - buscar dados para auto-preenchimento
    document.getElementById('id_veiculo')?.addEventListener('change', function() {
        buscarDadosVeiculo(this.value);
    });

    // ===== ITENS =====

    function renderItens() {
        const tbody = document.getElementById('itensTableBody');
        if (!tbody) return;

        if (itensData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-slate-500">${i18n.noItems}</td></tr>`;
            document.getElementById('totalDescontos').textContent = Currency.format(0, true);
            document.getElementById('totalServicos').textContent = Currency.format(0, true);
            return;
        }

        let html = '';
        let total = 0;
        let totalDescontos = 0;

        itensData.forEach((item, index) => {
            const valorTotal = parseFloat(item.valor_total) || 0;
            const desconto = parseFloat(item.desconto) || 0;
            total += valorTotal;
            totalDescontos += desconto;

            const statusBadge = item.pago === 'S'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.badgePaid}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${i18n.badgePending}</span>`;

            const canEdit = item.pago !== 'S';
            const canDelete = item.pago !== 'S';

            const unidade = item.estoque_unidade || 'UN';

            html += `
                <tr data-index="${index}">
                    <td class="px-3 py-2">
                        <span class="text-sm">${item.descricao || item.estoque_nome || '-'}</span>
                    </td>
                    <td class="px-3 py-2 text-center text-sm">${formatarQtd(item.quantidade)} <span class="text-slate-400 text-xs">${unidade}</span></td>
                    <td class="px-3 py-2 text-right text-sm">${Currency.format(item.valor_unitario, true)}</td>
                    <td class="px-3 py-2 text-right text-sm">${Currency.format(desconto, true)}</td>
                    <td class="px-3 py-2 text-right text-sm">${Currency.format(item.valor_total, true)}</td>
                    <td class="px-3 py-2 text-center">${statusBadge}</td>
                    <td class="px-3 py-2 text-center">
                        ${canEdit ? `<button type="button" onclick="editarItem(${index})" class="btn-icon text-blue-600 hover:text-blue-800" title="${i18n.actionEdit}"><i class="fas fa-edit"></i></button>` : ''}
                        ${canDelete ? `<button type="button" onclick="removerItem(${index})" class="btn-icon text-red-600 hover:text-red-800" title="${i18n.actionRemove}"><i class="fas fa-trash"></i></button>` : ''}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        document.getElementById('totalDescontos').textContent = Currency.format(totalDescontos, true);
        document.getElementById('totalServicos').textContent = Currency.format(total, true);
    }

    document.getElementById('btnAdicionarItem')?.addEventListener('click', function() {
        adicionarNovoItem();
    });

    function adicionarNovoItem() {
        // Criar linha de edicao
        const tbody = document.getElementById('itensTableBody');

        // Remover mensagem de vazio se existir
        const emptyRow = tbody.querySelector('tr td[colspan]');
        if (emptyRow) emptyRow.parentElement.remove();

        const tr = document.createElement('tr');
        tr.className = 'bg-blue-50';
        tr.dataset.unidade = 'UN'; // Unidade padrao
        tr.innerHTML = `
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm item-descricao mb-1" placeholder="${i18n.placeholderItemDescription}">
                <select class="chosen-select form-input-focus w-full text-sm item-produto"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/estoque/buscar"
                        data-chosen-placeholder="${i18n.placeholderSearchProduct}">
                    <option value="">${i18n.placeholderManualDescription}</option>
                </select>
            </td>
            <td class="px-3 py-2">
                <div class="flex items-center justify-center gap-1">
                    <input type="text" class="form-input-focus w-20 text-sm text-center item-qtd" value="1,000">
                    <span class="text-slate-400 text-xs item-unidade">UN</span>
                </div>
            </td>
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm text-right item-valor input-moeda" value="0,00">
            </td>
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm text-right item-desconto input-moeda" value="0,00">
            </td>
            <td class="px-3 py-2 text-right text-sm item-total">${Currency.format(0, true)}</td>
            <td class="px-3 py-2 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">${i18n.badgeNew}</span></td>
            <td class="px-3 py-2 text-center">
                <button type="button" class="btn-icon text-green-600 hover:text-green-800 btn-confirmar-item" title="${i18n.actionConfirm}"><i class="fas fa-check"></i></button>
                <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-cancelar-item" title="${i18n.actionCancel}"><i class="fas fa-times"></i></button>
            </td>
        `;
        tbody.appendChild(tr);

        // Inicializar chosen-select do sistema
        if (window.initChosenSelects) {
            window.initChosenSelects();
        }

        // Aplicar mascara de moeda
        if (window.Currency && window.Currency.applyMask) {
            Currency.applyMask(tr.querySelector('.item-valor'));
            Currency.applyMask(tr.querySelector('.item-desconto'));
        }

        // Evento de selecao do produto
        const select = tr.querySelector('.item-produto');
        const inputDescricao = tr.querySelector('.item-descricao');
        select.addEventListener('change', async function() {
            if (this.value) {
                // Buscar dados do produto selecionado
                try {
                    const result = await API.get('/api/estoque/' + this.value);
                    if (result.success && result.data) {
                        const estoqueAtual = parseFloat(result.data.produto_estoque_atual) || 0;
                        const permitirNegativo = result.data.permitir_estoque_negativo === 'S';

                        // Validar estoque: se nao permite negativo e estoque <= 0, bloquear
                        if (!permitirNegativo && estoqueAtual <= 0) {
                            toast.error(i18n.productOutOfStock);
                            this.value = '';
                            this.dispatchEvent(new Event('change', { bubbles: true }));
                            if (this.chosenSelect) this.chosenSelect.clear();
                            tr.dataset.estoqueAtual = '';
                            tr.dataset.permitirNegativo = '';
                            return;
                        }

                        // Armazenar dados de estoque na linha
                        tr.dataset.estoqueAtual = estoqueAtual;
                        tr.dataset.permitirNegativo = permitirNegativo ? 'S' : 'N';

                        inputDescricao.value = result.data.produto_nome || inputDescricao.value;
                        const valor = result.data.valor_venda || 0;
                        tr.querySelector('.item-valor').value = Currency.format(valor, false);
                        // Atualizar unidade
                        const unidade = result.data.produto_unidade || result.data.unidade || 'UN';
                        tr.dataset.unidade = unidade;
                        tr.querySelector('.item-unidade').textContent = unidade;
                        calcularTotalLinha(tr);
                    }
                } catch (e) {
                    console.error('Erro ao buscar produto:', e);
                }
            } else {
                // Sem produto: volta para unidade padrao
                tr.dataset.unidade = 'UN';
                tr.querySelector('.item-unidade').textContent = 'UN';
                tr.dataset.estoqueAtual = '';
                tr.dataset.permitirNegativo = '';
            }
        });

        // Eventos
        tr.querySelector('.btn-confirmar-item')?.addEventListener('click', () => confirmarItem(tr));
        tr.querySelector('.btn-cancelar-item')?.addEventListener('click', () => tr.remove());

        tr.querySelector('.item-qtd')?.addEventListener('input', () => calcularTotalLinha(tr));
        tr.querySelector('.item-valor')?.addEventListener('input', () => calcularTotalLinha(tr));
        tr.querySelector('.item-desconto')?.addEventListener('input', () => calcularTotalLinha(tr));
    }

    function calcularTotalLinha(tr) {
        const qtd = parseQtd(tr.querySelector('.item-qtd')?.value || 0);
        const valorStr = tr.querySelector('.item-valor')?.value || '0';
        const valor = Currency.parse(valorStr);
        const descontoStr = tr.querySelector('.item-desconto')?.value || '0';
        const desconto = Currency.parse(descontoStr);
        const total = Math.max(0, (qtd * valor) - desconto);
        tr.querySelector('.item-total').textContent = Currency.format(total, true);
    }

    async function confirmarItem(tr) {
        const select = tr.querySelector('.item-produto');
        const descricao = tr.querySelector('.item-descricao')?.value?.trim() || '';
        let qtd = parseQtd(tr.querySelector('.item-qtd')?.value || 0);
        const valorStr = tr.querySelector('.item-valor')?.value || '0';
        const valor = Currency.parse(valorStr);
        const descontoStr = tr.querySelector('.item-desconto')?.value || '0';
        const desconto = Currency.parse(descontoStr);

        if (!select.value && !descricao) {
            toast.error(i18n.provideDescription);
            return;
        }

        // Validar quantidade vs estoque disponivel
        const estoqueAtual = parseFloat(tr.dataset.estoqueAtual);
        const permitirNegativo = tr.dataset.permitirNegativo === 'S';

        if (select.value && !permitirNegativo && !isNaN(estoqueAtual) && estoqueAtual > 0 && qtd > estoqueAtual) {
            toast.warning(i18n.stockInsufficient.replace(':qty', formatarQtd(estoqueAtual)));
            qtd = estoqueAtual;
            tr.querySelector('.item-qtd').value = formatarQtd(qtd);
            calcularTotalLinha(tr);
        }

        const selectedOption = select.options[select.selectedIndex];
        const unidade = tr.dataset.unidade || 'UN';
        const subtotal = qtd * valor;

        if (desconto > subtotal) {
            toast.error(i18n.discountExceedsSubtotal);
            return;
        }

        const item = {
            id_estoque: select.value || null,
            descricao: descricao || selectedOption?.textContent || '',
            estoque_unidade: unidade,
            quantidade: qtd,
            valor_unitario: valor,
            desconto: desconto,
            valor_total: subtotal - desconto,
            pago: 'N'
        };

        const idManutencao = document.getElementById('registroId').value;
        if (idManutencao) {
            try {
                const result = await API.post('/manutencoes/' + idManutencao + '/itens/salvar', item);
                if (!result.success) {
                    toast.error(result.message || i18n.genericError);
                    return;
                }
                itensData.push(result.data);
                await carregarItensPendentes(idManutencao);
            } catch (error) {
                toast.error(error.message || i18n.genericError);
                return;
            }
        } else {
            itensData.push(item);
        }

        renderItens();
    }

    window.removerItem = async function(index) {
        if (itensData[index].pago === 'S') {
            toast.error(i18n.cannotRemovePaid);
            return;
        }

        const idManutencao = document.getElementById('registroId').value;
        if (idManutencao && itensData[index].id) {
            try {
                const result = await API.post('/manutencoes/' + idManutencao + '/itens/' + itensData[index].id + '/excluir', {});
                if (!result.success) {
                    toast.error(result.message || i18n.genericError);
                    return;
                }
                await carregarItensPendentes(idManutencao);
            } catch (error) {
                toast.error(error.message || i18n.genericError);
                return;
            }
        }

        itensData.splice(index, 1);
        renderItens();
    };

    // ===== EDICAO DE ITENS =====

    window.editarItem = function(index) {
        const item = itensData[index];
        if (item.pago === 'S') {
            toast.error(i18n.cannotEditPaid);
            return;
        }

        const tbody = document.getElementById('itensTableBody');
        const linhaAtual = tbody.querySelector(`tr[data-index="${index}"]`);
        if (!linhaAtual) return;

        // Criar linha de edicao
        const temEstoque = !!item.id_estoque;
        const unidadeAtual = item.estoque_unidade || 'UN';
        const tr = document.createElement('tr');
        tr.className = 'bg-amber-50';
        tr.setAttribute('data-editing-index', index);
        tr.dataset.unidade = unidadeAtual;
        tr.innerHTML = `
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm item-descricao mb-1 ${temEstoque ? 'hidden' : ''}" placeholder="${i18n.placeholderItemDescription}" value="${item.descricao || ''}">
                <select class="chosen-select form-input-focus w-full text-sm item-estoque"
                        data-chosen-type="server-side"
                        data-chosen-search-url="/api/estoque/buscar"
                        data-chosen-placeholder="${i18n.placeholderSearchProductService}">
                    <option value="">${i18n.placeholderManualDescription}</option>
                    ${item.id_estoque ? `<option value="${item.id_estoque}" selected>${item.estoque_nome || item.descricao}</option>` : ''}
                </select>
            </td>
            <td class="px-3 py-2">
                <div class="flex items-center justify-center gap-1">
                    <input type="text" class="form-input-focus w-20 text-sm text-center item-qtd" value="${formatarQtd(item.quantidade)}">
                    <span class="text-slate-400 text-xs item-unidade">${unidadeAtual}</span>
                </div>
            </td>
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm text-right item-valor input-moeda" value="${Currency.format(item.valor_unitario, false)}">
            </td>
            <td class="px-3 py-2">
                <input type="text" class="form-input-focus w-full text-sm text-right item-desconto input-moeda" value="${Currency.format(item.desconto || 0, false)}">
            </td>
            <td class="px-3 py-2 text-right text-sm item-total">${Currency.format(item.valor_total, true)}</td>
            <td class="px-3 py-2 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${i18n.badgeEditing}</span></td>
            <td class="px-3 py-2 text-center">
                <button type="button" class="btn-icon text-green-600 hover:text-green-800 btn-salvar-edicao" title="${i18n.actionSave}"><i class="fas fa-check"></i></button>
                <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-cancelar-edicao" title="${i18n.actionCancel}"><i class="fas fa-times"></i></button>
            </td>
        `;

        // Substituir linha atual pela linha de edicao
        linhaAtual.replaceWith(tr);

        // Inicializar chosen-select do sistema
        if (window.initChosenSelects) {
            window.initChosenSelects();
        }

        // Aplicar mascara de moeda
        if (window.Currency && window.Currency.applyMask) {
            Currency.applyMask(tr.querySelector('.item-valor'));
            Currency.applyMask(tr.querySelector('.item-desconto'));
        }

        // Evento de selecao do estoque
        const selectEstoque = tr.querySelector('.item-estoque');
        const inputDescricao = tr.querySelector('.item-descricao');
        const spanUnidade = tr.querySelector('.item-unidade');
        selectEstoque.addEventListener('change', async function() {
            if (this.value) {
                // Produto selecionado: ocultar input de descricao
                inputDescricao.classList.add('hidden');
                try {
                    const result = await API.get('/api/estoque/' + this.value);
                    if (result.success && result.data) {
                        const estoqueAtual = parseFloat(result.data.produto_estoque_atual) || 0;
                        const permitirNegativo = result.data.permitir_estoque_negativo === 'S';

                        // Validar estoque: se nao permite negativo e estoque <= 0, bloquear
                        if (!permitirNegativo && estoqueAtual <= 0) {
                            toast.error(i18n.productOutOfStock);
                            this.value = '';
                            this.dispatchEvent(new Event('change', { bubbles: true }));
                            if (this.chosenSelect) this.chosenSelect.clear();
                            inputDescricao.classList.remove('hidden');
                            tr.dataset.estoqueAtual = '';
                            tr.dataset.permitirNegativo = '';
                            return;
                        }

                        // Armazenar dados de estoque na linha
                        tr.dataset.estoqueAtual = estoqueAtual;
                        tr.dataset.permitirNegativo = permitirNegativo ? 'S' : 'N';

                        // Preencher descricao com nome do produto
                        inputDescricao.value = result.data.produto_nome || result.data.text;
                        // Preencher valor unitario
                        const valor = result.data.valor_venda || 0;
                        tr.querySelector('.item-valor').value = Currency.format(valor, false);
                        // Atualizar unidade
                        const unidade = result.data.produto_unidade || result.data.unidade || 'UN';
                        tr.dataset.unidade = unidade;
                        spanUnidade.textContent = unidade;
                        calcularTotalLinha(tr);
                    }
                } catch (e) {
                    console.error('Erro ao buscar produto:', e);
                }
            } else {
                // Sem produto: mostrar input de descricao manual
                inputDescricao.classList.remove('hidden');
                inputDescricao.focus();
                // Volta para unidade padrao
                tr.dataset.unidade = 'UN';
                spanUnidade.textContent = 'UN';
            }
        });

        // Eventos de calculo
        tr.querySelector('.item-qtd')?.addEventListener('input', () => calcularTotalLinha(tr));
        tr.querySelector('.item-valor')?.addEventListener('input', () => calcularTotalLinha(tr));
        tr.querySelector('.item-desconto')?.addEventListener('input', () => calcularTotalLinha(tr));

        // Eventos de salvar/cancelar
        tr.querySelector('.btn-salvar-edicao')?.addEventListener('click', () => salvarEdicaoItem(index, tr));
        tr.querySelector('.btn-cancelar-edicao')?.addEventListener('click', () => cancelarEdicaoItem());
    };

    // Salva TODAS as linhas em edicao antes de re-renderizar
    function salvarTodasEdicoesAbertas() {
        const tbody = document.getElementById('itensTableBody');
        const linhasEditando = tbody.querySelectorAll('tr[data-editing-index]');
        let invalido = false;

        linhasEditando.forEach(tr => {
            const idx = parseInt(tr.getAttribute('data-editing-index'));
            const desc = tr.querySelector('.item-descricao')?.value?.trim() || '';
            const selectEst = tr.querySelector('.item-estoque');
            const idEst = selectEst?.value || null;
            const q = parseQtd(tr.querySelector('.item-qtd')?.value || 0);
            const vStr = tr.querySelector('.item-valor')?.value || '0';
            const v = Currency.parse(vStr);
            const dStr = tr.querySelector('.item-desconto')?.value || '0';
            const d = Currency.parse(dStr);
            const un = tr.dataset.unidade || 'UN';
            const subtotal = q * v;

            if (d > subtotal) {
                toast.error(i18n.discountExceedsSubtotal);
                invalido = true;
                return;
            }

            // So atualizar se tiver descricao ou estoque
            if (desc || idEst) {
                itensData[idx] = {
                    ...itensData[idx],
                    id_estoque: idEst,
                    descricao: desc,
                    estoque_unidade: un,
                    quantidade: q,
                    valor_unitario: v,
                    desconto: d,
                    valor_total: subtotal - d
                };
            }
        });

        return !invalido;
    }

    async function salvarEdicaoItem(index, tr) {
        const descricao = tr.querySelector('.item-descricao')?.value?.trim() || '';
        const selectEstoque = tr.querySelector('.item-estoque');
        const idEstoque = selectEstoque?.value || null;

        // Validar: deve ter descricao ou estoque
        if (!descricao && !idEstoque) {
            toast.error(i18n.provideDescription);
            return;
        }

        // Validar quantidade vs estoque disponivel
        if (idEstoque) {
            const estoqueAtual = parseFloat(tr.dataset.estoqueAtual);
            const permitirNegativo = tr.dataset.permitirNegativo === 'S';
            let qtd = parseQtd(tr.querySelector('.item-qtd')?.value || 0);

            if (!permitirNegativo && !isNaN(estoqueAtual) && estoqueAtual > 0 && qtd > estoqueAtual) {
                toast.warning(i18n.stockInsufficient.replace(':qty', formatarQtd(estoqueAtual)));
                tr.querySelector('.item-qtd').value = formatarQtd(estoqueAtual);
                calcularTotalLinha(tr);
            }
        }

        const qtd = parseQtd(tr.querySelector('.item-qtd')?.value || 0);
        const valor = Currency.parse(tr.querySelector('.item-valor')?.value || '0');
        const desconto = Currency.parse(tr.querySelector('.item-desconto')?.value || '0');
        if (desconto > (qtd * valor)) {
            toast.error(i18n.discountExceedsSubtotal);
            return;
        }

        const unidade = tr.dataset.unidade || 'UN';
        const item = {
            ...itensData[index],
            id_estoque: idEstoque,
            descricao: descricao,
            estoque_unidade: unidade,
            quantidade: qtd,
            valor_unitario: valor,
            desconto: desconto,
            valor_total: (qtd * valor) - desconto
        };

        const idManutencao = document.getElementById('registroId').value;
        if (idManutencao && item.id) {
            try {
                const result = await API.post('/manutencoes/' + idManutencao + '/itens/salvar', item);
                if (!result.success) {
                    toast.error(result.message || i18n.genericError);
                    return;
                }
                itensData[index] = result.data;
                await carregarItensPendentes(idManutencao);
            } catch (error) {
                toast.error(error.message || i18n.genericError);
                return;
            }
        } else {
            itensData[index] = item;
        }

        renderItens();
    }

    function cancelarEdicaoItem() {
        renderItens();
    }

    // ===== ITENS PENDENTES (ABA FINANCEIRO) =====

    async function carregarItensPendentes(id) {
        try {
            const result = await API.get('/api/manutencoes/' + id + '/itens/pendentes');
            if (result.success) {
                itensPendentesData = result.data;
                renderItensPendentes();
            }
        } catch (error) {
            console.error('Erro ao carregar itens pendentes:', error);
        }
    }

    function renderItensPendentes() {
        const tbody = document.getElementById('itensPendentesTableBody');
        if (!tbody) return;

        if (itensPendentesData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-slate-500">${i18n.noPendingItems}</td></tr>`;
            return;
        }

        let html = '';
        itensPendentesData.forEach(item => {
            const unidade = item.estoque_unidade || 'UN';
            html += `
                <tr>
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" class="form-checkbox item-check" value="${item.id}" data-valor="${item.valor_total}">
                    </td>
                    <td class="px-3 py-2 text-sm">${item.descricao}</td>
                    <td class="px-3 py-2 text-center text-sm">${formatarQtd(item.quantidade)} <span class="text-slate-400 text-xs">${unidade}</span></td>
                    <td class="px-3 py-2 text-right text-sm">${Currency.format(item.valor_total, true)}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;

        // Evento para checkboxes
        tbody.querySelectorAll('.item-check').forEach(cb => {
            cb.addEventListener('change', atualizarTotalSelecionado);
        });
    }

    document.getElementById('checkAllPendentes')?.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('#itensPendentesTableBody .item-check').forEach(cb => {
            cb.checked = checked;
        });
        atualizarTotalSelecionado();
    });

    function atualizarTotalSelecionado() {
        let total = 0;
        document.querySelectorAll('#itensPendentesTableBody .item-check:checked').forEach(cb => {
            total += parseFloat(cb.dataset.valor) || 0;
        });
        document.getElementById('totalSelecionado').textContent = Currency.format(total, true);
    }

    // ===== LANCAMENTOS FINANCEIROS =====

    async function carregarFormasPagamento() {
        try {
            const result = await API.get('/api/formas-pagamento/select');
            if (result.success && result.data) {
                const select = document.getElementById('fin_forma_pagamento');
                select.innerHTML = `<option value="">${i18n.placeholderSelect}</option>`;

                result.data.forEach(forma => {
                    const option = document.createElement('option');
                    option.value = forma.id;
                    option.textContent = forma.nome;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar formas:', error);
        }
    }

    document.getElementById('btnLancamentoCompleto')?.addEventListener('click', function() {
        tipoLancamento = 'completo';
        // Marcar todos os itens pendentes para ilustrar o que sera incluido
        const checkAll = document.getElementById('checkAllPendentes');
        if (checkAll) {
            checkAll.checked = true;
            checkAll.dispatchEvent(new Event('change'));
        }
        document.getElementById('configFinanceiro').style.display = 'block';
    });

    document.getElementById('btnLancamentoParcial')?.addEventListener('click', function() {
        const selecionados = document.querySelectorAll('#itensPendentesTableBody .item-check:checked');
        if (selecionados.length === 0) {
            toast.error(i18n.selectAtLeastOne);
            return;
        }
        tipoLancamento = 'parcial';
        document.getElementById('configFinanceiro').style.display = 'block';
    });

    document.getElementById('btnCancelarLancamento')?.addEventListener('click', function() {
        document.getElementById('configFinanceiro').style.display = 'none';
        tipoLancamento = null;
    });

    document.getElementById('btnConfirmarLancamento')?.addEventListener('click', async function() {
        const id = document.getElementById('registroId').value;
        if (!id) return;

        // Capturar dados do formulario
        const formaPgtoSelect = document.getElementById('fin_forma_pagamento');
        const formaPgtoTexto = formaPgtoSelect.options[formaPgtoSelect.selectedIndex]?.text || '';
        const parcelas = document.getElementById('fin_parcelas').value;
        const dataVencimento = document.getElementById('fin_data_vencimento').value;
        const intervaloDias = document.getElementById('fin_intervalo').value;

        const dados = {
            id_forma_pagamento: formaPgtoSelect.value,
            parcelas: parcelas,
            data_vencimento: dataVencimento,
            intervalo_dias: intervaloDias
        };

        // Montar dados de auditoria
        const auditChanges = {
            [i18n.auditSection]: []
        };

        // Tipo de lancamento
        auditChanges[i18n.auditSection].push({
            label: i18n.auditType,
            de: null,
            para: tipoLancamento === 'completo' ? i18n.auditComplete : i18n.auditPartial
        });

        // Forma de pagamento
        if (formaPgtoTexto) {
            auditChanges[i18n.auditSection].push({
                label: i18n.auditPaymentMethod,
                de: null,
                para: formaPgtoTexto
            });
        }

        // Parcelas
        auditChanges[i18n.auditSection].push({
            label: i18n.auditInstallments,
            de: null,
            para: parcelas + 'x'
        });

        // Data vencimento
        if (dataVencimento) {
            auditChanges[i18n.auditSection].push({
                label: i18n.auditFirstDueDate,
                de: null,
                para: DateHelper.format(dataVencimento)
            });
        }

        // Intervalo
        auditChanges[i18n.auditSection].push({
            label: i18n.auditInterval,
            de: null,
            para: intervaloDias + ' ' + i18n.auditDays
        });

        // Total do lancamento
        const totalSelecionado = document.getElementById('totalSelecionado').textContent;
        auditChanges[i18n.auditSection].push({
            label: i18n.auditTotalValue,
            de: null,
            para: totalSelecionado
        });

        // Se parcial, listar itens selecionados
        if (tipoLancamento !== 'completo') {
            const itensSelecionados = [];
            document.querySelectorAll('#itensPendentesTableBody .item-check:checked').forEach(cb => {
                const tr = cb.closest('tr');
                if (tr) {
                    const descricao = tr.querySelector('td:nth-child(2)')?.textContent?.trim() || '';
                    const valor = tr.querySelector('td:nth-child(4)')?.textContent?.trim() || '';
                    itensSelecionados.push({ [i18n.auditItem]: descricao, [i18n.auditValue]: valor });
                }
            });
            if (itensSelecionados.length > 0) {
                auditChanges[i18n.auditSection].push({
                    label: i18n.auditSelectedItems,
                    de: null,
                    para: itensSelecionados
                });
            }
        }

        try {
            let url, body;
            if (tipoLancamento === 'completo') {
                url = '/manutencoes/' + id + '/financeiro/criar';
                body = { ...dados, _audit_changes: JSON.stringify(auditChanges) };
            } else {
                url = '/manutencoes/' + id + '/financeiro/parcial';
                const itens = [];
                document.querySelectorAll('#itensPendentesTableBody .item-check:checked').forEach(cb => {
                    itens.push(parseInt(cb.value));
                });
                body = { ...dados, itens, _audit_changes: JSON.stringify(auditChanges) };
            }

            const result = await API.post(url, body);
            if (result.success) {
                toast.success(i18n.entryCreated);
                document.getElementById('configFinanceiro').style.display = 'none';
                carregarDados(id); // Recarregar
            } else {
                toast.error(result.message || i18n.genericError);
            }
        } catch (error) {
            toast.error(error.message || i18n.genericError);
        }
    });

    // ===== SALVAR =====

    document.getElementById('formManutencao')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const id = document.getElementById('registroId').value;
        const form = document.getElementById('formManutencao');

        // Sincronizar itensData global antes de capturar auditoria
        window.itensData = itensData;

        const dados = {
            status: document.getElementById('status').value,
            id_matriz_filial: document.getElementById('id_matriz_filial').value,
            id_veiculo: document.getElementById('id_veiculo').value,
            id_oficina: document.getElementById('id_oficina').value,
            motivo: document.getElementById('motivo').value,
            data_enviado: document.getElementById('data_enviado').value?.replace('T', ' ') || null,
            data_retorno: document.getElementById('data_retorno').value?.replace('T', ' ') || null,
            odo_enviado: Km.parse(document.getElementById('odo_enviado').value),
            odo_retorno: Km.parse(document.getElementById('odo_retorno').value),
            tanque_enviado: document.getElementById('tanque_enviado').value,
            tanque_retorno: document.getElementById('tanque_retorno').value,
            obs_oficina: document.getElementById('obs_oficina').value,
            trocou_oleo: document.getElementById('trocou_oleo').checked ? 'S' : 'N',
            trocou_pneus: document.getElementById('trocou_pneus').checked ? 'S' : 'N',
            itens: itensData,
            // Dados do veiculo para auto-preenchimento no backend (quando mudar para A)
            _veiculo_odometro: veiculoDados?.odometro || null,
            _veiculo_tanque: veiculoDados?.tanque || null
        };

        // Capturar dados de auditoria (manter como string JSON)
        if (window.FormAudit && form) {
            const auditData = FormAudit.getAuditData(form);
            if (auditData._audit_changes) {
                dados._audit_changes = auditData._audit_changes;
            } else if (auditData._audit_data) {
                dados._audit_data = auditData._audit_data;
            }
        }

        try {
            const url = id ? '/manutencoes/' + id + '/atualizar' : '/manutencoes/salvar';
            const result = await API.post(url, dados);

            if (result.success) {
                toast.success(result.message || i18n.saveSuccess);

                // Armazenar resultado para uso apos confirmacao
                saveResult = result;

                // Usar modal generico do sistema
                parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.savedTitle,
                    message: i18n.savedGoToList,
                    confirmText: i18n.goToList
                }, '*');
            } else {
                toast.error(result.message || i18n.saveError);
            }
        } catch (error) {
            toast.error(error.message || i18n.serverError);
        }
    });

    // ===== INICIALIZACAO =====

    if (registroId) {
        document.getElementById('registroId').value = registroId;
        carregarDados(registroId);
    }

    carregarFormasPagamento();

    // Data padrao para vencimento
    document.getElementById('fin_data_vencimento').value = new Date().toISOString().split('T')[0];

    // ===== LISTENER PARA MODAL DE CONFIRMACAO =====

    window.addEventListener('message', function(event) {
        // Usuario confirmou - ir para listagem
        if (event.data && event.data.action === 'genericConfirmed' && saveResult) {
            navegarPara('/pages/manutencoes');
            saveResult = null;
        }
        // Usuario cancelou - continuar editando
        else if (event.data && event.data.action === 'genericModalClosed' && saveResult) {
            const novoId = saveResult.data?.id || document.getElementById('registroId').value;
            if (novoId) {
                document.getElementById('registroId').value = novoId;
                carregarDados(novoId);
            }
            saveResult = null;
        }
    });

})();
</script>
@endsection
