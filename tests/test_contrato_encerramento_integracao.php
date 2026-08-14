<?php

$arquivos = [
    'controller' => file_get_contents(__DIR__ . '/../app/Controllers/ContratosController.php'),
    'rotas' => file_get_contents(__DIR__ . '/../app/Routes/web.php'),
    'view' => file_get_contents(__DIR__ . '/../app/Views/pages/contratos/devolver.php'),
    'offcanvas_valores' => file_get_contents(__DIR__ . '/../app/Views/pages/contratos/offcanvas-valores-devolucao.php'),
    'model_veiculo' => file_get_contents(__DIR__ . '/../app/Models/ContratoVeiculo.php'),
    'service' => file_get_contents(__DIR__ . '/../app/Services/ContratoEncerramentoService.php'),
    'docs' => file_get_contents(__DIR__ . '/../docs/contratos.md'),
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
$assert(str_contains($arquivos['rotas'], '/pages/contratos/offcanvas-valores-devolucao'), 'Rota do offcanvas de valores nao registrada');
$assert(str_contains($arquivos['controller'], "Auth::can('contratos.editar_valores')"), 'Backend nao exige permissao para ajustar valores');
$assert(str_contains($arquivos['controller'], 'normalizarAjustesValoresDevolucao'), 'Backend nao normaliza os ajustes por plano');
$assert(str_contains($arquivos['controller'], 'atualizarValoresDevolucao($idCv, $ajustesValores)'), 'Confirmacao nao persiste os valores ajustados');
$assert(str_contains($arquivos['view'], 'payload.valores_ajustados'), 'Tela nao envia ajustes no payload da previa e confirmacao');
$assert(str_contains($arquivos['view'], 'if (!state.selecionado && !singleMode) return;'), 'Payload nao restringe ajustes aos veiculos selecionados');
$assert(str_contains($arquivos['view'], "action: 'openOffcanvasIframe'"), 'Tela nao abre o ajuste no offcanvas global');
$assert(str_contains($arquivos['offcanvas_valores'], "action: 'valoresDevolucaoAplicados'"), 'Offcanvas nao devolve os valores para o estado da tela');
$assert(!str_contains($arquivos['offcanvas_valores'], 'API.post('), 'Offcanvas persiste valores antes da confirmacao');
$assert(!preg_match('/\balert\s*\(/', $arquivos['offcanvas_valores']), 'Offcanvas usa alert nativo');
$assert(str_contains($arquivos['model_veiculo'], 'public function atualizarValoresDevolucao'), 'Model nao possui atualizacao tenant-scoped dos valores');
$assert(!str_contains($arquivos['model_veiculo'], 'withoutChave()'), 'Model de veiculo ignora o isolamento de tenant');
$assert(str_contains($arquivos['service'], "\$devolucoesPorId[\$id]['valores_ajustados']"), 'Preview nao projeta os valores ajustados sem persistir');
$assert(str_contains($arquivos['docs'], 'Ao clicar em **Aplicar valores**, nenhuma gravacao e feita no banco'), 'Fluxo temporario dos ajustes nao foi documentado');

if ($falhas) {
    fwrite(STDERR, implode(PHP_EOL, $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - integracao do preview, snapshot, financeiro e tela de encerramento\n";
