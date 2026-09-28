<?php
/** Integração exclusivamente LOCAL. Fixtures removidas ao final; sem gateway real/mensageria. */
define('APP_ROOT', dirname(__DIR__));
$_ENV['APP_ENV'] = 'development';
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Helpers/helpers.php';
use App\Core\Database;
use App\Models\Model;
use App\Models\FinanceiroTransacao;

if (!is_file(APP_ROOT.'/.env.development') || Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Exige banco localhost development');
$db=Model::sharedMysqli();
$tenant='test_cora_'.bin2hex(random_bytes(6));
$_SESSION['chave']=$tenant;
$gatewayId=null; $financeiroId=null;
function checkCoraDb(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    $stmt=$db->prepare("INSERT INTO gateways_pagamento (chave,gateway_code,nome,currencies,ambiente) VALUES (?, 'cora', 'Teste Cora isolado', '[\"BRL\"]', 'sandbox')");
    $stmt->bind_param('s',$tenant); $stmt->execute(); $gatewayId=$db->insert_id;
    $stmt=$db->prepare("INSERT INTO financeiro (chave,tipo,pago,valor_total,valor_taxa,data_venci,data_criada) VALUES (?, 'R', 'N', 10, 1, '2099-01-01', '2026-09-01')");
    $stmt->bind_param('s',$tenant); $stmt->execute(); $financeiroId=$db->insert_id;
    $model=new FinanceiroTransacao();
    $external='inv_test_'.bin2hex(random_bytes(8));
    $id=$model->criar(['chave'=>$tenant,'gateway'=>'cora','id_gateway'=>$gatewayId,'id_financeiro'=>$financeiroId,
        'type'=>'charge','status'=>'pending','external_id'=>$external,'amount'=>10,'payload'=>json_encode(['_cora_fingerprint'=>'test'])]);
    $row=$model->buscarCobrancaCoraPorExternalId($external);
    checkCoraDb((int)$row['id']===$id,'Bootstrap cobrança');
    checkCoraDb($model->buscarTentativaCora($tenant,$gatewayId,$financeiroId,'test')!==null,'Reuso');
    checkCoraDb($model->buscarTentativaCora('outro_tenant',$gatewayId,$financeiroId,'test')===null,'Isolamento de tenant');
    checkCoraDb(!$model->temTentativaCoraAberta('outro_tenant',$gatewayId,$financeiroId),'Isolamento tentativa');
    checkCoraDb($model->financeiroDisponivelParaCora($tenant,$financeiroId,10), 'Financeiro pendente disponível');
    checkCoraDb(!$model->financeiroDisponivelParaCora('outro_tenant',$financeiroId,10), 'Financeiro de outro tenant indisponível');
    checkCoraDb(!$model->financeiroDisponivelParaCora($tenant,$financeiroId,11), 'Valor alterado bloqueia troca');
    checkCoraDb(count($model->listarTentativasParaTrocaCora($tenant,$financeiroId))===1, 'Consulta de troca');
    checkCoraDb($model->listarTentativasParaTrocaCora('outro_tenant',$financeiroId)===[], 'Consulta de troca isolada');
    $model->atualizarStatusPorId($id,'cancelled');
    checkCoraDb(!$model->aplicarWebhookCora($row,['status'=>'pending'],['event'=>'invoice.poll'],function(){throw new RuntimeException('Não deveria baixar');}), 'Evento atrasado não reabre cancelada');
    checkCoraDb($model->listarTentativasParaTrocaCora($tenant,$financeiroId)===[], 'Cancelada não bloqueia emissão');
    $model->atualizarStatusPorId($id,'pending');
    $confirmed=['status'=>'paid','paid_at'=>'2026-09-01 10:00:00'];
    $event=['event'=>'invoice.paid','event_id'=>'evt_test','resource_id'=>$external];
    // O controller recusa aviso forjado e consulta sem confirmação, sem tocar na baixa.
    $controller = new \App\Controllers\PagamentoPublicoController();
    $confirm = new ReflectionMethod($controller, 'confirmarPagamentoCora');
    $fakeGateway = new class extends \App\Services\Gateways\CoraGateway {
        public array $result = [];
        public function __construct() { parent::__construct([]); }
        public function getChargeStatus(string $externalId): array { return $this->result; }
    };
    foreach ([['success'=>false], ['success'=>true,'status'=>'paid','raw'=>['total_amount'=>999999]]] as $bad) {
        $fakeGateway->result = $bad;
        try { $confirm->invoke($controller,$row,$fakeGateway,$event); throw new LogicException('Confirmação inválida aceita'); }
        catch (RuntimeException) {}
        checkCoraDb($model->buscarCobrancaCoraPorExternalId($external)['status']==='pending','Aviso alterou status');
    }
    $count=0;
    $paid=function(array $r) use(&$count,$db,$financeiroId,$tenant) {
        $count++;
        $stmt=$db->prepare("UPDATE financeiro SET pago='S' WHERE id=? AND chave=?");
        $stmt->bind_param('is',$financeiroId,$tenant);$stmt->execute();
    };
    try {
        $model->aplicarWebhookCora($row,$confirmed,$event,function() use($financeiroId) {
            (new \App\Models\Financeiro())->atualizar($financeiroId, ['pago'=>'S', 'data_pago'=>'2026-09-01'], true);
            throw new RuntimeException('Falha simulada');
        });
        throw new RuntimeException('Deveria propagar falha');
    } catch (RuntimeException $e) { checkCoraDb($e->getMessage()==='Falha simulada','Exceção inesperada'); }
    checkCoraDb($model->buscarCobrancaCoraPorExternalId($external)['status']==='pending','Rollback status');
    $stmt=$db->prepare('SELECT pago FROM financeiro WHERE id=?');$stmt->bind_param('i',$financeiroId);$stmt->execute();
    checkCoraDb($stmt->get_result()->fetch_assoc()['pago']==='N','Rollback financeiro');
    checkCoraDb((new \App\Models\FinanceiroTaxa())->buscarDespesaVinculada($financeiroId)===null,'Rollback da taxa');
    $model->comBloqueioCora($tenant,'charge:'.$financeiroId, function() use($model,$row,$confirmed,$event,$paid){
        checkCoraDb($model->aplicarWebhookCora($row,$confirmed,$event,$paid),'Primeiro evento');
        checkCoraDb(!$model->aplicarWebhookCora($row,$confirmed,$event,$paid),'Evento duplicado');
        checkCoraDb(!$model->aplicarWebhookCora($row,['status'=>'pending'],$event,$paid),'Evento atrasado');
    });
    checkCoraDb($count===1,'Baixa duplicada');
    $stmt=$db->prepare("SELECT COUNT(*) n FROM financeiro_transacoes WHERE chave=? AND type='webhook'");
    $stmt->bind_param('s',$tenant);$stmt->execute();checkCoraDb((int)$stmt->get_result()->fetch_assoc()['n']===1,'Log duplicado/rollback incompleto');
    $stmt=$db->prepare('SELECT pago FROM financeiro WHERE id=?');$stmt->bind_param('i',$financeiroId);$stmt->execute();
    checkCoraDb($stmt->get_result()->fetch_assoc()['pago']==='S','Financeiro não baixado');
    // Outra conexão não deve conseguir o mesmo lock enquanto estiver em uso.
    $model->comBloqueioCora($tenant,'concurrency',function() use($tenant) {
        $other=new mysqli('localhost',Database::env('DB_USERNAME'),Database::env('DB_PASSWORD'),Database::env('DB_DATABASE'),(int)Database::env('DB_PORT',3306));
        $name='cora:'.substr(hash('sha256',$tenant.':concurrency'),0,59);
        $stmt=$other->prepare('SELECT GET_LOCK(?,0) acquired');$stmt->bind_param('s',$name);$stmt->execute();
        checkCoraDb((int)$stmt->get_result()->fetch_assoc()['acquired']===0,'Lock concorrente não protege');
        $other->close();
    });
    echo "[OK] Cora local: isolamento, rollback, baixa única e bloqueio concorrente.\n";
} finally {
    $stmt=$db->prepare('DELETE FROM financeiro_transacoes WHERE chave=?');$stmt->bind_param('s',$tenant);$stmt->execute();
    $stmt=$db->prepare('DELETE FROM financeiro WHERE chave=?');$stmt->bind_param('s',$tenant);$stmt->execute();
    $stmt=$db->prepare('DELETE FROM gateways_pagamento WHERE chave=?');$stmt->bind_param('s',$tenant);$stmt->execute();
}
