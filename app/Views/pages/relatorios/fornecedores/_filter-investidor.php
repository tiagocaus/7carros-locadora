<div class="flex-1 min-w-[220px] max-w-[320px]">
    <label for="filterFornecedor" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.fornecedores.investidor.filter_investor') ?></label>
    <select id="filterFornecedor"
            class="form-input-focus w-full text-sm chosen-select"
            data-chosen-type="server-side"
            data-chosen-search-url="/api/fornecedores/investidores/select"
            data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.fornecedores.investidor.filter_all_investors') ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <option value=""><?= t('modules.relatorios.fornecedores.investidor.filter_all_investors') ?></option>
    </select>
</div>
<div class="flex-1 min-w-[160px] max-w-[220px]">
    <label for="filterModelo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.fornecedores.investidor.filter_model') ?></label>
    <select id="filterModelo" class="form-input-focus w-full text-sm">
        <option value="agrupado"><?= t('modules.relatorios.fornecedores.investidor.model_grouped') ?></option>
        <option value="detalhado" selected><?= t('modules.relatorios.fornecedores.investidor.model_detailed') ?></option>
    </select>
</div>
