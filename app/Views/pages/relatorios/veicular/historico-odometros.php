@extends('layouts.iframe')
@section('title', t('modules.relatorios.veicular.historico_odometros.title'))
@section('content')
<?php
$prefix = 'modules.relatorios.veicular.historico_odometros.';
$columns = ['data', 'veiculo', 'contrato', 'cliente', 'filial', 'grupo', 'odometro', 'obs', 'created_at'];
ob_start();
foreach (['Veiculo' => ['/api/veiculos/buscar', 'vehicle', 'all_vehicles'], 'Cliente' => ['/api/clientes/buscar', 'client', 'all_clients']] as $id => $config): ?>
<div class="flex-1 min-w-[180px] max-w-[250px]">
    <label for="filter<?= $id ?>" class="block text-xs text-slate-500 mb-1"><?= t('modules.relatorios.common.' . $config[1]) ?></label>
    <select id="filter<?= $id ?>" class="form-input-focus w-full text-sm chosen-select" data-chosen-type="server-side" data-chosen-search-url="<?= $config[0] ?>" data-chosen-placeholder="<?= e(t('modules.relatorios.common.' . $config[2])) ?>">
        <option value=""><?= t('modules.relatorios.common.' . $config[2]) ?></option>
    </select>
</div>
<?php endforeach; ?>
<div class="flex-1 min-w-[150px] max-w-[200px]">
    <label for="filterContrato" class="block text-xs text-slate-500 mb-1"><?= t($prefix . 'contrato') ?></label>
    <input id="filterContrato" type="number" min="1" step="1" class="form-input-focus w-full text-sm">
</div>
<?php $extraFilters = ob_get_clean(); ?>
<div class="pl-1 pr-2 py-0">
    <h2 class="title-section mb-0"><?= t($prefix . 'title') ?></h2>
    <p class="text-sm text-slate-500 mb-3"><?= t($prefix . 'description') ?></p>
    @include('pages.relatorios._partials.filters', ['showGrupoFilter' => true, 'extraFiltersAfterFilial' => $extraFilters])
    @include('pages.relatorios._partials.export-buttons')
    @include('pages.relatorios._partials.totalizadores')
    @include('pages.relatorios._partials.empty-state')
    <div id="reportTableContainer" class="bg-white shadow-md rounded-lg overflow-x-auto" style="display:none">
        <table class="w-full divide-y divide-slate-200">
            <thead class="table-header-custom"><tr>
                <?php foreach ($columns as $column): ?><th class="table-header"><?= t($prefix . $column) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody id="reportTableBody" class="bg-white divide-y divide-slate-200"></tbody>
        </table>
    </div>
    @include('pages.relatorios._partials.pagination')
</div>
@endsection
@section('scripts')
<script src="/assets/js/report-utils.min.js"></script>
<script>
window.odometrosReportI18n = <?= json_encode([
    'title' => t($prefix . 'title'), 'empty' => t($prefix . 'empty'),
    'error' => t('modules.relatorios.messages.load_error'),
    'registros' => t($prefix . 'registros'), 'veiculos' => t($prefix . 'veiculos'), 'contratos' => t($prefix . 'contratos'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/assets/js/relatorio-historico-odometros.min.js"></script>
@endsection
