<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkLocacaoTaxas(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$view = file_get_contents(APP_ROOT . '/app/Views/pages/locacoes/adicionar.php');
$controller = file_get_contents(APP_ROOT . '/app/Controllers/TaxasServicosController.php');

checkLocacaoTaxas($view !== false, 'A view de locacoes deve estar disponivel.');
checkLocacaoTaxas($controller !== false, 'O controller de taxas deve estar disponivel.');

checkLocacaoTaxas(
    !str_contains($view, 'taxasDisponiveis'),
    'A selecao de taxas nao deve depender do preload limitado do Chosen.'
);
checkLocacaoTaxas(
    str_contains($view, 'API.get(`/api/taxas-e-servicos/${taxaId}`')
        && str_contains($view, 'id_filial: filialId'),
    'A taxa selecionada deve ser carregada por ID e filial.'
);
checkLocacaoTaxas(
    str_contains($view, 'buscar?id_filial=${encodeURIComponent(filialId)}'),
    'A busca server-side deve ser restringida pela filial de retirada.'
);
checkLocacaoTaxas(
    str_contains($controller, '$model->resolverValor($taxa, $filialId)')
        && str_contains($controller, 'Taxa/servico nao disponivel para a filial informada'),
    'O endpoint individual deve validar o vinculo e resolver o valor por filial.'
);

echo "OK: taxas da locacao usam busca e valor resolvidos pela filial.\n";
