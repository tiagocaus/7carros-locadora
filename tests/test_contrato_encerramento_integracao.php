<?php

$arquivos = [
    'controller' => file_get_contents(__DIR__ . '/../app/Controllers/ContratosController.php'),
    'rotas' => file_get_contents(__DIR__ . '/../app/Routes/web.php'),
    'view' => file_get_contents(__DIR__ . '/../app/Views/pages/contratos/devolver.php'),
    'taxa' => file_get_contents(__DIR__ . '/../app/Models/ContratoTaxaServico.php'),
    'migration' => file_get_contents(__DIR__ . '/../app/Database/migrations/00419_create_contratos_encerramentos.php'),
    'repair_migration' => file_get_contents(__DIR__ . '/../app/Database/migrations/00420_repair_contract_refund_chart_account.php'),
];
$falhas = [];
$assert = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    if (!$condicao) $falhas[] = $mensagem;
};

$assert(str_contains($arquivos['rotas'], "/api/contratos/{id}/devolucao/preview"), 'Rota do preview nao registrada');
$assert(str_contains($arquivos['controller'], 'prepararCalculoDevolucao'), 'Preview e confirmacao nao compartilham o calculo');
$assert(str_contains($arquivos['controller'], 'bloquearContrato($id)'), 'Confirmacao nao bloqueia o contrato para concorrencia');
$assert(str_contains($arquivos['controller'], "'calculo_json' => json_encode"), 'Snapshot auditavel nao e persistido');
$assert(str_contains($arquivos['controller'], "'origem' => 'devolucao'"), 'Taxas da devolucao nao recebem origem propria');
$assert(str_contains($arquivos['taxa'], "'origem' =>"), 'Model de taxas nao persiste a origem');
$assert(str_contains($arquivos['migration'], "'3.4.1.23'"), 'Plano de credito contratual nao e criado');
$assert(str_contains($arquivos['repair_migration'], "HIERARQUIA_GLOBAL = '3.4.1.23'"), 'Migration corretiva nao reserva o plano global');
$assert(str_contains($arquivos['repair_migration'], "HIERARQUIA_REALOCADA = '3.4.1.24'"), 'Migration corretiva nao realoca a colisao');
$assert(str_contains($arquivos['repair_migration'], "(string) \$existente['chave'] === '0'"), 'Migration corretiva nao diferencia plano global de plano tenant');
$assert(str_contains($arquivos['repair_migration'], "->update(['hierarquia' => self::HIERARQUIA_REALOCADA])"), 'Migration corretiva nao preserva o plano tenant');
$assert(str_contains($arquivos['repair_migration'], "'chave' => '0'"), 'Migration corretiva nao cria o plano como global');
$assert(str_contains($arquivos['repair_migration'], '$qb->beginTransaction()'), 'Migration corretiva nao inicia transacao');
$assert(str_contains($arquivos['repair_migration'], '$qb->rollback()'), 'Migration corretiva nao reverte falhas');
$assert(str_contains($arquivos['view'], 'Credito a devolver ao cliente'), 'Tela nao diferencia credito ao cliente');
$assert(str_contains($arquivos['view'], 'Principal ja lancado'), 'Tela nao apresenta a conciliacao financeira');
$assert(str_contains($arquivos['view'], 'Sem adicionais a lancar'), 'Resumo parcial sem cobranca continua indicando conciliacao do contrato');
$assert(str_contains($arquivos['view'], 'conciliacao financeira serao apurados na ultima devolucao'), 'Resumo parcial nao explica que o acerto fica para a ultima devolucao');
$assert(str_contains($arquivos['controller'], "unset(\$calculoPreview['veiculos_historico_calculo'])"), 'Endpoint de preview expoe o historico de veiculos na tela');
$assert(str_contains($arquivos['view'], 'function validarDadosPreview'), 'Tela nao valida os dados antes de solicitar a previa');
$assert(str_contains($arquivos['view'], 'nao pode ser menor que ${Km.format(minimo)} km'), 'Tela nao bloqueia odometro inferior ao minimo');
$assert(str_contains($arquivos['view'], 'function invalidarPreviewEncerramento'), 'Tela nao invalida resumos antigos apos alteracoes');
$assert(str_contains($arquivos['view'], 'if (!previewEncerramento)'), 'Confirmacao nao exige uma previa oficial valida');
$assert(str_contains($arquivos['view'], 'if (!result.success)'), 'Erro de validacao da previa continua sendo ignorado');
$assert(!str_contains($arquivos['view'], 'i18n.summaryContractValues'), 'Resumo financeiro legado ainda pode ser renderizado como fallback');
$assert(str_contains($arquivos['view'], 'id="btnConfirmar" class="btn-green py-2 px-6 rounded-md text-sm font-medium" disabled'), 'Botao de confirmacao nasce habilitado antes da previa');
$assert(!preg_match('/\balert\s*\(/', $arquivos['view']), 'Tela usa alert nativo');

if ($falhas) {
    fwrite(STDERR, implode(PHP_EOL, $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - integracao do preview, snapshot, financeiro e tela de encerramento\n";
