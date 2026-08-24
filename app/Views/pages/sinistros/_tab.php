<div class="form-section mb-4" id="sinistrosApp">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h3 class="form-section-title mb-0 pb-0 border-b-0">
            <i class="fas fa-car-crash mr-2"></i><?= t('modules.sinistros.title') ?>
        </h3>
        <button type="button" id="btnNovoSinistro" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
            <i class="fas fa-plus mr-1"></i><?= t('modules.sinistros.register') ?>
        </button>
    </div>

    <div id="sinistrosEstadoNovo" class="hidden rounded-md border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">
        <i class="fas fa-info-circle mr-2 text-slate-400"></i><?= t('modules.sinistros.save_first') ?>
    </div>

    <div id="sinistroEditor" class="hidden rounded-md border border-orange-200 bg-orange-50 p-4 mb-4">
        <h4 id="sinistroEditorTitulo" class="text-sm font-semibold text-orange-700 mb-3"><?= t('modules.sinistros.register') ?></h4>
        <input type="hidden" id="sinistro_id">

        <div id="sinistroCamposCadastro" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group" for="sinistro_data_ocorrencia"><?= t('modules.sinistros.fields.date') ?> <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="sinistro_data_ocorrencia" class="form-input-group-field">
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group" for="sinistro_id_veiculo"><?= t('modules.sinistros.fields.vehicle') ?> <span class="text-red-500">*</span></label>
                <select id="sinistro_id_veiculo" class="form-input-group-field">
                    <option value=""><?= t('common.labels.select') ?></option>
                </select>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group" for="sinistro_tipo"><?= t('modules.sinistros.fields.type') ?> <span class="text-red-500">*</span></label>
                <select id="sinistro_tipo" class="form-input-group-field">
                    <option value=""><?= t('common.labels.select') ?></option>
                    <option value="colisao"><?= t('modules.sinistros.types.collision') ?></option>
                    <option value="furto_roubo"><?= t('modules.sinistros.types.theft') ?></option>
                    <option value="incendio"><?= t('modules.sinistros.types.fire') ?></option>
                    <option value="alagamento"><?= t('modules.sinistros.types.flood') ?></option>
                    <option value="danos_terceiros"><?= t('modules.sinistros.types.third_party') ?></option>
                    <option value="perda_total"><?= t('modules.sinistros.types.total_loss') ?></option>
                    <option value="outros"><?= t('modules.sinistros.types.other') ?></option>
                </select>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group" for="sinistro_valor_estimado"><?= t('modules.sinistros.fields.estimated_value') ?></label>
                <div class="relative">
                    <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                    <input type="text" id="sinistro_valor_estimado" class="form-input-group-field pl-10 input-moeda">
                </div>
            </div>
            <div class="md:col-span-8 form-input-group">
                <label class="form-label-group" for="sinistro_descricao"><?= t('modules.sinistros.fields.description') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="sinistro_descricao" class="form-input-group-field" maxlength="1000">
            </div>
            <div class="md:col-span-4 form-input-group">
                <label class="form-label-group" for="sinistro_status"><?= t('modules.sinistros.fields.status') ?></label>
                <select id="sinistro_status" class="form-input-group-field">
                    <option value="A"><?= t('modules.sinistros.status.open') ?></option>
                    <option value="C"><?= t('modules.sinistros.status.completed') ?></option>
                </select>
            </div>
            <div class="md:col-span-12 form-input-group">
                <label class="form-label-group" for="sinistro_observacoes"><?= t('modules.sinistros.fields.notes') ?></label>
                <textarea id="sinistro_observacoes" class="form-input-group-field" rows="3"></textarea>
            </div>
        </div>

        <label id="sinistroGerarCobrancaLabel" class="mt-4 inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
            <input type="checkbox" id="sinistro_gerar_cobranca" class="rounded border-slate-300">
            <span><?= t('modules.sinistros.charge.generate') ?></span>
        </label>

        <div id="sinistroCobrancaCampos" class="hidden mt-3 rounded-md border border-blue-200 bg-blue-50 p-3">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-2 form-input-group">
                    <label class="form-label-group" for="sinistro_cobranca_valor"><?= t('modules.sinistros.charge.value') ?> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="sinistro_cobranca_valor" class="form-input-group-field pl-10 input-moeda">
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group" for="sinistro_cobranca_vencimento"><?= t('modules.sinistros.charge.due_date') ?> <span class="text-red-500">*</span></label>
                    <input type="date" id="sinistro_cobranca_vencimento" class="form-input-group-field">
                </div>
                <div class="md:col-span-4 form-input-group">
                    <label class="form-label-group" for="sinistro_cobranca_conta"><?= t('modules.sinistros.charge.account') ?> <span class="text-red-500">*</span></label>
                    <select id="sinistro_cobranca_conta" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placement="bottom" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group" for="sinistro_cobranca_forma"><?= t('modules.sinistros.charge.payment_method') ?> <span class="text-red-500">*</span></label>
                    <select id="sinistro_cobranca_forma" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select" data-chosen-placement="bottom" data-chosen-placeholder="<?= t('common.labels.select') ?>">
                        <option value=""><?= t('common.labels.select') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" id="btnCancelarSinistro" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium"><?= t('common.buttons.cancel') ?></button>
            <button type="button" id="btnSalvarSinistro" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                <i class="fas fa-save mr-1"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </div>

    <div id="sinistrosCarregando" class="hidden text-center py-8 text-slate-500">
        <i class="fas fa-spinner fa-spin mr-2"></i><?= t('modules.sinistros.loading') ?>
    </div>
    <div id="sinistrosLista" class="space-y-3"></div>
</div>
