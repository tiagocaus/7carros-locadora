<?php

$root = dirname(__DIR__);

function assertContractVehicleChosen(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHA: {$message}\n");
        exit(1);
    }
}

$view = file_get_contents($root . '/app/Views/pages/contratos/offcanvas-veiculo.php');
$controller = file_get_contents($root . '/app/Controllers/VeiculosController.php');
$model = file_get_contents($root . '/app/Models/Veiculo.php');

assertContractVehicleChosen($view !== false, 'View do select de veiculos deve existir.');
assertContractVehicleChosen($controller !== false, 'Controller de veiculos deve existir.');
assertContractVehicleChosen($model !== false, 'Model de veiculos deve existir.');

assertContractVehicleChosen(
    preg_match('/<select id="veiculo"[\s\S]*?data-chosen-type="server-side"/', $view) === 1,
    'Select de veiculos do contrato deve usar Chosen server-side.'
);
assertContractVehicleChosen(
    str_contains($view, '/api/veiculos/buscar?')
        && str_contains($view, "selectVeiculo.chosenSelect.options.searchUrl = searchUrl"),
    'Busca server-side deve usar a API de busca de veiculos com URL dinamica.'
);
assertContractVehicleChosen(
    str_contains($view, "somente_disponiveis: '1'")
        && str_contains($view, "limit: '100'"),
    'Busca do contrato deve preservar disponibilidade e preload de 100 registros.'
);
assertContractVehicleChosen(
    str_contains($view, 'API.get(`/api/veiculos/${veiculoId}`)'),
    'Selecao server-side deve carregar os dados completos pela API da entidade.'
);
assertContractVehicleChosen(
    str_contains($controller, "\$search = trim((string) \$request->query('q', ''))")
        && str_contains($controller, "\$model->listarParaSelect("),
    'Controller deve delegar a busca recebida em q ao Model.'
);
assertContractVehicleChosen(
    str_contains($model, "UPPER(REPLACE(REPLACE(v.placa, '-', ''), ' ', '')) LIKE ?")
        && strpos($model, '->whereRaw(') < strpos($model, '->limit(max(1, min($limit, 100)))'),
    'Model deve normalizar a placa e filtrar antes do limite.'
);

echo "OK: select de veiculos do contrato usa busca server-side com filtros de grupo e filial.\n";
