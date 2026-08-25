<?php

$arquivos = [
    'controller' => file_get_contents(__DIR__ . '/../app/Controllers/ContratosController.php'),
    'financeiro_controller' => file_get_contents(__DIR__ . '/../app/Controllers/FinanceiroController.php'),
    'contrato' => file_get_contents(__DIR__ . '/../app/Models/Contrato.php'),
    'encerramento' => file_get_contents(__DIR__ . '/../app/Models/ContratoEncerramento.php'),
    'js' => file_get_contents(__DIR__ . '/../public/assets/js/contratos.js'),
    'fatura' => file_get_contents(__DIR__ . '/../app/Views/pages/contratos/imprimir/_partials/_fatura_content.php'),
    'docs_contratos' => file_get_contents(__DIR__ . '/../docs/contratos.md'),
    'docs_financeiro' => file_get_contents(__DIR__ . '/../docs/financeiro.md'),
];

$falhas = [];
$assert = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    if (!$condicao) {
        $falhas[] = $mensagem;
    }
};

$assert(str_contains($arquivos['encerramento'], 'buscarDetalhadoPorContrato'), 'Model nao normaliza o snapshot do encerramento.');
$assert(str_contains($arquivos['contrato'], "['encerramento']"), 'Contrato completo nao entrega o encerramento.');
$assert(str_contains($arquivos['controller'], '$contratoFinalizado'), 'Update nao distingue contrato finalizado.');
$assert(str_contains($arquivos['controller'], 'if (!$contratoFinalizado)'), 'Update ainda recalcula totais finalizados.');
$assert(str_contains($arquivos['controller'], "'obs', 'condutor_adicional'"), 'Campos editaveis do contrato finalizado nao foram restringidos.');
$assert(str_contains($arquivos['js'], 'atualizarResumoEncerramento'), 'Resumo nao renderiza o snapshot final.');
$assert(str_contains($arquivos['js'], 'ajuste_financeiro_existe'), 'Resumo nao avisa quando o ajuste foi removido.');
$assert(str_contains($arquivos['fatura'], 'veiculos_historico_calculo'), 'PDF nao usa os veiculos do snapshot.');
$assert(str_contains($arquivos['controller'], 'confirmar_ajuste_encerramento'), 'Exclusao do ajuste nao exige confirmacao especifica.');
$assert(str_contains($arquivos['controller'], "Auth::can('financeiro.excluir')"), 'Exclusao de parcela nao exige financeiro.excluir.');
$assert(str_contains($arquivos['financeiro_controller'], 'Ajuste de encerramento protegido'), 'Exclusao em lote pode contornar a protecao.');
$assert(str_contains($arquivos['docs_contratos'], 'snapshot de'), 'Regra do snapshot nao foi documentada em contratos.');
$assert(str_contains($arquivos['docs_financeiro'], 'id_financeiro_ajuste'), 'Protecao financeira nao foi documentada.');
$assert(!preg_match('/\balert\s*\(/', $arquivos['js']), 'JavaScript usa alert nativo.');

if ($falhas) {
    fwrite(STDERR, implode(PHP_EOL, $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - snapshot final, resumo e ajuste financeiro protegidos\n";
