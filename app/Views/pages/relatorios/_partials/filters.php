<?php
/**
 * Partial: Barra de filtros padrão para relatórios
 *
 * Variáveis esperadas (definidas na view pai):
 * - $showGrupoFilter (bool, opcional) - Mostrar filtro de grupo de veículos
 * - $filialChosenServerSide (bool, opcional) - Usar chosen-select server-side no filtro de filial
 */
$showGrupoFilter = $showGrupoFilter ?? false;
$filialChosenServerSide = $filialChosenServerSide ?? true;
?>
<div class="flex flex-wrap gap-3 mb-4 p-3 bg-slate-50 rounded-lg items-end">
    <div class="flex-1 min-w-[150px] max-w-[200px]">
        <label for="filterDataInicio" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_start') ?></label>
        <input type="date" id="filterDataInicio" class="form-input-focus w-full text-sm">
    </div>
    <div class="flex-1 min-w-[150px] max-w-[200px]">
        <label for="filterDataFim" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.date_end') ?></label>
        <input type="date" id="filterDataFim" class="form-input-focus w-full text-sm">
    </div>
    <div class="flex-1 min-w-[180px] max-w-[250px]">
        <label for="filterFilial" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.branch') ?></label>
        <select id="filterFilial"
                class="form-input-focus w-full text-sm<?= $filialChosenServerSide ? ' chosen-select' : '' ?>"
                <?= $filialChosenServerSide ? 'data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="' . htmlspecialchars(t('modules.relatorios.common.all_branches') ?? '', ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <option value=""><?= t('modules.relatorios.common.all_branches') ?></option>
        </select>
    </div>
    <?php if ($showGrupoFilter): ?>
    <div class="flex-1 min-w-[180px] max-w-[250px]">
        <label for="filterGrupo" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.vehicle_group') ?></label>
        <select id="filterGrupo"
                class="form-input-focus w-full text-sm chosen-select"
                data-chosen-type="server-side"
                data-chosen-search-url="/api/grupos"
                data-chosen-placeholder="<?= htmlspecialchars(t('modules.relatorios.common.all_groups') ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <option value=""><?= t('modules.relatorios.common.all_groups') ?></option>
        </select>
    </div>
    <?php endif; ?>
    <div class="flex items-end gap-2">
        <button id="btnAplicar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
            <i class="fas fa-search mr-2"></i><?= t('modules.relatorios.common.apply') ?>
        </button>
        <button id="btnLimpar" class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2" title="<?= t('modules.relatorios.common.clear') ?>">
            <i class="fas fa-times mr-1"></i><?= t('modules.relatorios.common.clear') ?>
        </button>
    </div>
</div>
