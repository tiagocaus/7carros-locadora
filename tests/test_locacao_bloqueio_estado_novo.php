<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkLocacaoBloqueioEstadoNovo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$view = file_get_contents(APP_ROOT . '/app/Views/pages/locacoes/adicionar.php');
checkLocacaoBloqueioEstadoNovo($view !== false, 'View de locacoes deve estar disponivel.');

checkLocacaoBloqueioEstadoNovo(
    str_contains($view, 'id="bloqueioEstadoNovo"')
        && str_contains($view, "t('modules.locacoes.form.save_before_hold')"),
    'Nova reserva/locacao deve orientar o usuario a salvar antes de criar o bloqueio.'
);

checkLocacaoBloqueioEstadoNovo(
    str_contains($view, 'id="bloqueioFormFields" class="<?= isset($locacao) ? \'\' : \'hidden\' ?>"'),
    'Campos do bloqueio devem permanecer ocultos enquanto o registro ainda nao foi salvo.'
);

checkLocacaoBloqueioEstadoNovo(
    str_contains($view, "const registroSalvo = Boolean(document.getElementById('registroId')?.value);")
        && str_contains($view, "classList.toggle('hidden', !registroSalvo || !temHold);"),
    'Verificacao de gateway nao deve reexibir os campos antes de existir registroId.'
);

checkLocacaoBloqueioEstadoNovo(
    str_contains($view, "classList.toggle('hidden', registroSalvo);"),
    'Orientacao deve ser ocultada quando a reserva/locacao ja estiver salva.'
);

$formatacaoManual = <<<'JS'
data.valor ? `R$ ${parseFloat(data.valor).toFixed(2).replace('.', ',')}` : '';
JS;

checkLocacaoBloqueioEstadoNovo(
    str_contains($view, "data.valor ? fmtCurrency(parseFloat(data.valor)) : '';")
        && !str_contains($view, $formatacaoManual),
    'Valor do bloqueio deve usar o helper de moeda multi-tenant.'
);

echo "OK: bloqueio de nova reserva/locacao exige salvar antes de exibir os campos.\n";
