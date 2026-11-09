<?php
/** Regressão LOCAL com banco isolado por tenant; sem rede nem mensageria. */
define('APP_ROOT', dirname(__DIR__));
$_ENV['APP_ENV'] = 'development';
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Financeiro;
use App\Models\FinanceiroTransacao;
use App\Models\Model;
use App\Models\PagamentoLink;
use App\Services\CoraPaymentReconciliationService;
use App\Services\Gateways\CoraGateway;
use App\Services\PagamentoLinkSyncService;

if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Exige localhost.');
function reconcileCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
class LocalCoraReconciler extends CoraPaymentReconciliationService {
    public array $hooks = [];
    protected function aposPagamento(array $transaction): void {
        $row = (new Financeiro())->buscarPorId((int) $transaction['id_financeiro']);
        reconcileCheck($row['pago'] === 'S', 'Hook executou antes da baixa');
        $this->hooks[] = $transaction['id'];
    }
}
class LocalCoraGateway extends CoraGateway {
    public array $result = [];
    public function __construct() { parent::__construct([]); }
    public function getChargeStatus(string $externalId): array { return $this->result; }
}
if (($argv[1] ?? '') === '--worker') {
    $row = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $_SESSION = ['chave'=>$row['chave'],'user_id'=>0,'user_name'=>'Sistema'];
    $gateway = new LocalCoraGateway();
    $gateway->result = ['success'=>true,'external_id'=>$row['external_id'],'status'=>'paid','paid_at'=>'2026-10-28 12:00:00',
        'raw'=>['total_amount'=>10000,'total_paid'=>10000]];
    (new LocalCoraReconciler())->confirmar($row,$gateway,['event'=>'invoice.cron','resource_id'=>$row['external_id']]);
    echo "OK\n";
    exit;
}
$db = Model::sharedMysqli();
$tenant = 'test_reconcile_' . bin2hex(random_bytes(6));
$_SESSION = ['chave' => $tenant, 'user_id' => 0, 'user_name' => 'Sistema'];
$model = new FinanceiroTransacao();
$service = new LocalCoraReconciler();
$gateway = new LocalCoraGateway();
$fixture = function(float $amount = 100, float $subtotal = 100, float $total = 105, float $juros = 3, float $multa = 2) use ($db, $tenant, $model): array {
    $stmt = $db->prepare("INSERT INTO financeiro (chave,tipo,pago,valor_subtotal,valor_total,juros,multa,valor_taxa,data_venci,data_criada) VALUES (?,'R','N',?,?,?,?,0,'2026-10-28','2026-10-28')");
    $stmt->bind_param('sdddd', $tenant, $subtotal, $total, $juros, $multa); $stmt->execute(); $id = $db->insert_id;
    $external = 'inv_fixture_' . bin2hex(random_bytes(8));
    $tx = $model->criar(['chave'=>$tenant,'gateway'=>'cora','id_financeiro'=>$id,'external_id'=>$external,'type'=>'charge','status'=>'pending','amount'=>$amount]);
    $link = (new PagamentoLink())->criar(['chave'=>$tenant,'id_financeiro'=>$id,'valor'=>$total]);
    return [$model->buscarPorId($tx), $link];
};
$confirmed = static fn(array $row, string $date = '2026-10-28'): array => [
    'success'=>true, 'external_id'=>$row['external_id'], 'status'=>'paid', 'paid_at'=>$date . ' 12:00:00',
    'raw'=>['id'=>$row['external_id'],'total_amount'=>(int) round($row['amount'] * 100),'total_paid'=>(int) round($row['amount'] * 100)],
];
$event = static fn(array $row): array => ['event'=>'invoice.paid','event_id'=>'evt_local','resource_id'=>$row['external_id']];
try {
    // Reproduz o incidente: poll pendente, CRON marca só transação paga, financeiro fica aberto.
    [$row, $linkId] = $fixture();
    $model->aplicarWebhookCora($row, ['status'=>'pending'], ['event'=>'invoice.poll'], static function(){});
    $model->atualizarStatusPorId($row['id'], 'paid', '2026-10-28 12:00:00');
    reconcileCheck(!in_array($row['id'], array_column($model->listarCoraPendentesParaConciliacao(0), 'id')), 'CRON incluiu pagamento antigo');
    $gateway->result = $confirmed($row);
    $service->confirmar($row, $gateway, $event($row));
    $f = (new Financeiro())->buscarPorId($row['id_financeiro']);
    reconcileCheck($f['pago'] === 'S' && $f['data_pago'] === '2026-10-28', 'Não recuperou baixa incompleta');
    reconcileCheck((float)$f['valor_total'] === 100.0 && (float)$f['juros'] === 0.0 && (float)$f['multa'] === 0.0, 'Cobrou encargos após pagamento no vencimento');
    reconcileCheck((new PagamentoLink())->buscarPorId($linkId)['status'] === 'paid', 'Não baixou link');
    $count = $db->query("SELECT COUNT(*) n FROM financeiro_transacoes WHERE id_financeiro=" . (int)$row['id_financeiro'] . " AND type='webhook' AND status='invoice:paid'")->fetch_assoc()['n'];
    $service->confirmar($row, $gateway, $event($row));
    reconcileCheck($count === $db->query("SELECT COUNT(*) n FROM financeiro_transacoes WHERE id_financeiro=" . (int)$row['id_financeiro'] . " AND type='webhook' AND status='invoice:paid'")->fetch_assoc()['n'], 'Duplicou evento pago');

    // A consulta de segurança encontra só pendentes; um tenant em sessão não filtra o bootstrap CRON.
    [$manual, $manualLink] = $fixture(100, 100, 100, 0, 0);
    $_SESSION['chave'] = 'outro_tenant';
    reconcileCheck(in_array($manual['id'], array_column($model->listarCoraPendentesParaConciliacao((int)$manual['id'] - 1), 'id')), 'Bootstrap CRON filtrado por sessão');
    $_SESSION['chave'] = $tenant;
    (new Financeiro())->atualizar($manual['id_financeiro'], ['pago'=>'S','data_pago'=>'2026-10-27']);
    $gateway->result = $confirmed($manual);
    $service->confirmar($manual, $gateway, $event($manual));
    $f = (new Financeiro())->buscarPorId($manual['id_financeiro']);
    reconcileCheck($f['data_pago'] === '2026-10-27' && (float)$f['valor_total'] === 100.0, 'Alterou baixa manual');

    [$invalid] = $fixture(100, 100, 100, 0, 0);
    foreach (['id','amount','total_paid','date','success'] as $failure) {
        $gateway->result = $confirmed($invalid);
        if ($failure === 'id') $gateway->result['external_id'] = 'inv_diferente';
        if ($failure === 'amount') $gateway->result['raw']['total_amount'] = 999999;
        if ($failure === 'total_paid') $gateway->result['raw']['total_paid'] = 999;
        if ($failure === 'date') $gateway->result['paid_at'] = '2026-99-99 00:00:00';
        if ($failure === 'success') $gateway->result = ['success'=>false];
        try { $service->confirmar($invalid, $gateway, $event($invalid)); throw new LogicException('Aceitou confirmação inválida: '.$failure); }
        catch (RuntimeException) {}
        reconcileCheck((new Financeiro())->buscarPorId($invalid['id_financeiro'])['pago'] === 'N', 'Baixou confirmação inválida');
        reconcileCheck($model->buscarPorId($invalid['id'])['status'] === 'pending', 'Não reverteu transação');
    }
    $forged = $invalid; $forged['chave'] = 'outro_tenant';
    $gateway->result = $confirmed($invalid);
    try { $service->confirmar($forged, $gateway, $event($forged)); throw new LogicException('Aceitou outro tenant'); }
    catch (RuntimeException) {}
    reconcileCheck($_SESSION['chave'] === $tenant, 'Não restaurou tenant após falha');

    // Encargos legítimos anteriores ao pagamento não podem ser perdoados.
    $f = ['valor_total'=>1816.08,'valor_subtotal'=>1700,'juros'=>90.58,'multa'=>25.50,'desconto'=>0,
        'data_venci'=>'2026-10-24','cora_multa_percentual'=>1.5,'cora_juros_por_dia'=>0.333];
    $values = $service->valoresParaBaixa($f, 1782.11, '2026-11-03');
    reconcileCheck($values === ['juros'=>56.61,'multa'=>25.5], 'Removeu encargos devidos antes do pagamento');
    try { $service->valoresParaBaixa($f, 1700, '2026-11-03'); throw new LogicException('Aceitou desconto não comprovado'); }
    catch (RuntimeException) {}

    // Mesmo ponto da falha original: sincronização Cora deve concluir a baixa antes de bloquear encargos.
    [$sync] = $fixture(100, 100, 100, 0, 0);
    $gateway->result = $confirmed($sync);
    $method = new ReflectionMethod(PagamentoLinkSyncService::class, 'tratarStatusTerminal');
    try { $method->invoke(new PagamentoLinkSyncService(), $sync, 'paid', '2026-10-28 12:00:00', $gateway); }
    catch (RuntimeException $e) { reconcileCheck(str_contains($e->getMessage(), 'ja consta como paga'), 'Falha inesperada na sincronização'); }
    reconcileCheck((new Financeiro())->buscarPorId($sync['id_financeiro'])['pago'] === 'S', 'Sincronização marcou apenas transação');

    // Duas requisições reais em processos diferentes disputam a mesma fatura.
    [$concurrent] = $fixture(100,100,100,0,0);
    $workers = [];
    for ($i=0; $i<2; $i++) {
        $process = proc_open([PHP_BINARY,__FILE__,'--worker',json_encode($concurrent)], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        reconcileCheck(is_resource($process),'Não iniciou processo concorrente');
        fclose($pipes[0]); $workers[] = [$process,$pipes];
    }
    foreach ($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        reconcileCheck(proc_close($process) === 0 && trim($output) === 'OK','Falhou concorrência: '.$error);
    }
    $n=$db->query("SELECT COUNT(*) n FROM financeiro_transacoes WHERE id_financeiro=".(int)$concurrent['id_financeiro']." AND status='invoice:paid'")->fetch_assoc()['n'];
    reconcileCheck((int)$n === 1,'Concorrência duplicou evento de baixa');

    // O ramo compartilhado dos demais bancos mantém a marcação e o bloqueio anteriores.
    $otherModel = new class extends FinanceiroTransacao {
        public array $calls = [];
        public function __construct() {}
        public function atualizarStatusPorId(int $id,string $status,?string $paidAt=null,?string $refundedAt=null): int {
            $this->calls[] = [$id,$status,$paidAt]; return 1;
        }
    };
    $syncService = new PagamentoLinkSyncService();
    (new ReflectionProperty($syncService,'transacaoModel'))->setValue($syncService,$otherModel);
    try { $method->invoke($syncService,['id'=>77],'paid','2026-10-28 12:00:00',new \App\Services\Gateways\AsaasGateway([])); }
    catch (RuntimeException $e) { reconcileCheck(str_contains($e->getMessage(),'ja consta como paga'),'Mudou bloqueio dos outros bancos'); }
    reconcileCheck($otherModel->calls === [[77,'paid','2026-10-28 12:00:00']], 'Alterou comportamento dos outros bancos');
    echo "[OK] Cora: incidente reproduzido e corrigido, encargos, baixa manual, validações, isolamento e sincronização.\n";
} finally {
    $_SESSION['chave'] = $tenant;
    foreach (['pagamentos_links','financeiro_transacoes','financeiro','logs'] as $table) {
        $stmt=$db->prepare('DELETE FROM ' . $table . ' WHERE chave=?'); $stmt->bind_param('s',$tenant); $stmt->execute();
    }
}
