<?php

$arquivos = [
    'controller' => file_get_contents(__DIR__ . '/../app/Controllers/ContratosController.php'),
    'contrato' => file_get_contents(__DIR__ . '/../app/Models/Contrato.php'),
    'taxa' => file_get_contents(__DIR__ . '/../app/Models/ContratoTaxaServico.php'),
    'view' => file_get_contents(__DIR__ . '/../app/Views/pages/contratos/substituir.php'),
    'migration' => file_get_contents(__DIR__ . '/../app/Database/migrations/00437_repair_contratos_ajustes_substituicao.php'),
    'docs' => file_get_contents(__DIR__ . '/../docs/contratos.md'),
];

$falhas = [];
$assert = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    if (!$condicao) $falhas[] = $mensagem;
};

$assert(str_contains($arquivos['controller'], 'FILTER_VALIDATE_INT'), 'Backend nao valida combustivel/carga de chegada');
$assert(str_contains($arquivos['controller'], "'message' => t('modules.contratos.substitution.fuel_arrival_required')"), 'Backend nao retorna erro claro para nivel ausente');
$assert(substr_count($arquivos['controller'], "'origem' => 'devolucao'") >= 4, 'Ajustes da substituicao nao usam origem operacional');
$assert(str_contains($arquivos['controller'], '$totalAjusteImediato'), 'Substituicao nao consolida os adicionais pontuais');
$assert(str_contains($arquivos['controller'], "'Ajustes da substituição - Contrato '"), 'Financeiro separado da substituicao nao e criado');
$assert(str_contains($arquivos['controller'], "'id_financeiro_ajuste' => \$idFinanceiroAjuste"), 'Resposta nao identifica o financeiro criado');
$assert(str_contains($arquivos['controller'], "\$contrato['ajustes_operacionais']"), 'API nao separa ajustes operacionais das taxas editaveis');
$assert(str_contains($arquivos['contrato'], 'listarContratuaisPorContrato($id)'), 'Total recorrente ainda consulta ajustes operacionais');
$assert(str_contains($arquivos['taxa'], 'public function removerContratuais'), 'Sincronizacao nao preserva ajustes operacionais');
$assert(str_contains($arquivos['taxa'], '$this->removerContratuais($contratoId)'), 'CRUD continua removendo todas as taxas');
$assert(str_contains($arquivos['view'], "document.getElementById('tanqueChegada').value === ''"), 'Tela permite substituir sem nivel de chegada');
$assert(str_contains($arquivos['migration'], "update(['origem' => 'devolucao'])"), 'Migration nao reclassifica ajustes antigos');
$assert(str_contains($arquivos['migration'], "\$contrato['status'] ?? '') !== 'A'"), 'Migration pode recalcular contratos finalizados');
$assert(str_contains($arquivos['docs'], 'nao alteram `total_fatura`/`total_pagar` recorrentes'), 'Regra recorrente nao foi documentada');

if ($falhas) {
    fwrite(STDERR, implode(PHP_EOL, $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - ajustes de substituicao separados do valor recorrente\n";
