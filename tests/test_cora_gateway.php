<?php
/** Testes de contrato Cora com transporte simulado: sem rede, mensagens ou banco. */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\FinanceiroTransacao;
use App\Services\Gateways\CoraGateway;

function coraCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
class CoraMemoryTransactions extends FinanceiroTransacao {
    public array $rows = [];
    public bool $financeiroDisponivel = true;
    public bool $locked = false;
    public function financeiroDisponivelParaCora(string $chave, int $financeiroId, float $valor): bool { return $this->financeiroDisponivel; }
    public function __construct() {}
    public function comBloqueioCora(string $chave, string $operation, callable $callback): mixed { $this->locked=true; try { return $callback(); } finally { $this->locked=false; } }
    public function listarTentativasParaTrocaCora(string $chave, int $financeiroId): array {
        return array_values(array_filter($this->rows, fn($r) => $r['chave'] === $chave && $r['id_financeiro'] === $financeiroId
            && !in_array($r['status'], ['cancelled','failed','refunded'])));
    }
    public function criar(array $data): int { $id = count($this->rows)+1; $this->rows[$id] = $data + ['id'=>$id]; return $id; }
    public function buscarTentativaCora(string $chave, int $gatewayId, int $financeiroId, string $fingerprint): ?array {
        foreach ($this->rows as $row) {
            if ($row['chave'] === $chave && $row['id_gateway'] === $gatewayId && $row['id_financeiro'] === $financeiroId
                && !in_array($row['status'], ['cancelled','failed','refunded'])
                && (json_decode($row['payload'], true)['_cora_fingerprint'] ?? '') === $fingerprint) return $row;
        }
        return null;
    }
    public function temTentativaCoraAberta(string $chave, int $gatewayId, int $financeiroId): bool {
        foreach ($this->rows as $row) if ($row['chave'] === $chave && $row['id_gateway'] === $gatewayId && $row['id_financeiro'] === $financeiroId && !in_array($row['status'], ['cancelled','failed','refunded'])) return true;
        return false;
    }
    public function salvarRetornoCora(string $chave, int $id, array $result, array $payload): void {
        coraCheck($this->rows[$id]['chave'] === $chave, 'Tenant incorreto');
        $this->rows[$id] = array_merge($this->rows[$id], $result, ['payload'=>json_encode($payload)]);
    }
}
class CoraFakeGateway extends CoraGateway {
    public array $audits = [];
    protected function auditCancellation(string $externalId, array $diagnostic): void { $this->audits[] = $diagnostic; }
    public array $responses = [];
    public array $requests = [];
    public CoraMemoryTransactions $memory;
    public function __construct(bool $sandbox = true) {
        parent::__construct(['client_id'=>'fixture', 'client_secret'=>'obsolete-secret', 'certificado_arquivo'=>'fixture.pem'], $sandbox, 7);
        $this->memory = new CoraMemoryTransactions();
    }
    public function base(): string { return $this->getBaseUrl(); }
    public function normalize(array $invoice): array { return $this->normalizeInvoice($invoice); }
    protected function getTransacaoModel(): FinanceiroTransacao { return $this->memory; }
    protected function request(string $method, string $endpoint, array $data = [], ?string $token = null, bool $isAuthRequest = false, ?string $idempotencyKey = null): array {
        $this->requests[] = compact('method','endpoint','data','token','isAuthRequest','idempotencyKey') + ['locked'=>$this->memory->locked];
        $r = array_shift($this->responses);
        if ($r instanceof Throwable) throw $r;
        if (!is_array($r)) throw new RuntimeException('Resposta de teste ausente');
        return $r;
    }
}
$token = ['_http_code'=>200,'access_token'=>'test-token','expires_in'=>86400];
$invoice = ['_http_code'=>200,'id'=>'inv_fixture','status'=>'OPEN','total_amount'=>1000,
    'pix'=>['emv'=>'pix-fixture'], 'payment_terms'=>['due_date'=>'2099-01-01'],
    'payment_options'=>['bank_slip'=>['url'=>'https://example.test/boleto.pdf','digitable'=>'12345','barcode'=>'67890']]];
$data = ['chave'=>'1111111111111','id_financeiro'=>9,'value'=>10,'billing_type'=>'pix',
    'customer_name'=>'Teste','customer_document'=>'52998224725','due_date'=>'2099-01-01',
    'description'=>'Locação teste','external_reference'=>'link_99'];
$g = new CoraFakeGateway();
coraCheck(!isset($g->getConfigSchema()['client_secret']), 'Secret ainda no schema');
coraCheck($g->base() === 'https://matls-clients.api.stage.cora.com.br', 'Host stage');
coraCheck((new CoraFakeGateway(false))->base() === 'https://matls-clients.api.cora.com.br', 'Host produção');
$g->responses = [$token,$invoice];
$r=$g->createCharge($data);
coraCheck($r['success'] && $r['pix_code'] === 'pix-fixture', 'Emissão Pix');
$expectedSvg = (string) (new \SimpleSoftwareIO\QrCode\Generator())
    ->format('svg')->size(260)->margin(1)->generate($invoice['pix']['emv']);
coraCheck($r['pix_qrcode'] === 'data:image/svg+xml;base64,' . base64_encode($expectedSvg), 'QR Code deve representar o EMV em Data URI');
$pixOnly = $invoice;
$pixOnly['payment_options']['bank_slip'] = ['url'=>'https://example.test/pix.png'];
$normalizedPix = $g->normalize($pixOnly);
coraCheck($normalizedPix['pix_qrcode'] === $r['pix_qrcode'], 'URL PNG Cora não deve ser tratada como Base64');
$withoutPix = $g->normalize(array_replace($invoice, ['pix'=>null]));
coraCheck($withoutPix['pix_qrcode'] === null && $withoutPix['pix_code'] === null, 'Sem EMV não deve gerar imagem');
$oversizedCode = str_repeat('X', 10000);
$failedQr = $g->normalize(array_replace($invoice, ['pix'=>['emv'=>$oversizedCode]]));
coraCheck($failedQr['pix_qrcode'] === null && $failedQr['pix_code'] === $oversizedCode && $failedQr['success'], 'Falha na imagem deve preservar Copia e Cola');
coraCheck($g->requests[0]['endpoint']==='/token' && $g->requests[0]['data']===['grant_type'=>'client_credentials','client_id'=>'fixture'], 'Autenticação incorreta');
coraCheck($g->requests[1]['endpoint']==='/v2/invoices' && $g->requests[1]['data']['payment_forms']===['PIX'], 'Payload Pix v2');
coraCheck($g->requests[1]['data']['services'][0]['amount']===1000, 'Centavos');
coraCheck(!isset($g->requests[1]['data']['customer']['address']), 'Endereço incompleto');
coraCheck((bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-5[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/',$g->requests[1]['idempotencyKey']), 'UUID');
$g->responses=[$invoice];
$r2=$g->createCharge($data);
coraCheck($r2['transaction_id']===$r['transaction_id'] && count($g->memory->rows)===1, 'Repetição duplicou registro');
coraCheck($r2['pix_qrcode'] === $r['pix_qrcode'], 'Consulta de cobrança existente deve regenerar a imagem');
coraCheck(end($g->requests)['method']==='GET', 'Repetição deveria consultar');
$changed=$data; $changed['customer_name']='Alterado';
coraCheck(!$g->createCharge($changed)['success'], 'Mudança não deve duplicar cobrança aberta');

$g=new CoraFakeGateway(); $g->responses=[$token,new RuntimeException('Timeout'),$invoice];
coraCheck(!$g->createCharge($data)['success'], 'Timeout deveria falhar');
coraCheck($g->createCharge($data)['success'], 'Retry deveria recuperar');
coraCheck($g->requests[1]['idempotencyKey']===$g->requests[2]['idempotencyKey'] && count($g->memory->rows)===1, 'Retry perdeu idempotência');

$g=new CoraFakeGateway(); $g->responses=[$token,array_replace($invoice,['status'=>'DRAFT','pix'=>null,'payment_options'=>[]]),$invoice];
coraCheck(!$g->createCharge($data)['success'], 'DRAFT sem artefato não está pronto');
coraCheck($g->createCharge($data)['success'] && end($g->requests)['method']==='GET', 'DRAFT não foi consultado');

$g=new CoraFakeGateway(); $g->responses=[$token,$invoice]; $boleto=$data; $boleto['billing_type']='boleto';
$boletoResult = $g->createCharge($boleto);
coraCheck($boletoResult['barcode']==='12345' && $boletoResult['boleto_url']===$invoice['payment_options']['bank_slip']['url'], 'Dados do boleto preservados');
coraCheck($boletoResult['pix_qrcode'] === $r['pix_qrcode'], 'Boleto com Pix deve gerar QR Code do EMV');
coraCheck($g->requests[1]['data']['payment_forms']===['BANK_SLIP','PIX'], 'Boleto com Pix');

$g=new CoraFakeGateway(); $g->responses=[$token,['_http_code'=>401],$token,$invoice];
coraCheck($g->getChargeStatus('inv_fixture')['success'], 'Renovação em 401');
$g->responses=[['_http_code'=>404]];
coraCheck(!$g->getChargeStatus('inv_fixture')['success'], '404 não pode virar pendente');
$g->responses=[array_replace($invoice,['status'=>'UNKNOWN'])];
coraCheck(!$g->getChargeStatus('inv_fixture')['success'], 'Estado desconhecido');
$g->responses=[array_replace($invoice,['status'=>'LATE'])];
coraCheck($g->getChargeStatus('inv_fixture')['status']==='pending', 'Atraso não cancela');
$g->responses=[array_replace($invoice,['status'=>'PAID','payments'=>[['status'=>'SUCCESS','finalized_at'=>'2026-09-01T10:30:00Z']]])];
coraCheck($g->getChargeStatus('inv_fixture')['paid_at']==='2026-09-01 10:30:00', 'Data confirmada');
$g->responses=[['_http_code'=>204]];
coraCheck($g->cancel('inv_fixture')['success'], 'Cancelamento 204');
$g->responses=[['_http_code'=>422,'code'=>'REC-0002','message'=>'Cancelamento recusado'], $invoice];
coraCheck(!$g->cancel('inv_fixture')['success'], 'Cancelamento recusado');
$before=count($g->requests); coraCheck(!$g->refund('inv_fixture')['success'] && $before===count($g->requests), 'Estorno não deve chamar endpoint fictício');

$url='https://example.test/webhook/cora';
$registered=['id'=>'end_fixture','url'=>$url,'resource'=>'invoice','trigger'=>'*','active'=>true];
$g=new CoraFakeGateway(); $g->responses=[$token,['_http_code'=>200],$registered+['_http_code'=>201]];
coraCheck($g->activateWebhook($url)['success'], 'Ativação webhook');
coraCheck($g->requests[2]['data']===['url'=>$url,'resource'=>'invoice','trigger'=>'*'], 'Cadastro webhook');
$g->responses=[[$registered,'_http_code'=>200]]; $before=count($g->requests);
coraCheck($g->activateWebhook($url)['message']==='Webhook já está ativo' && count($g->requests)===$before+1, 'Ativação repetida');
$g=new CoraFakeGateway(); $g->responses=[$token,['_http_code'=>200],new RuntimeException('Timeout'),[$registered,'_http_code'=>200]];
coraCheck($g->activateWebhook($url)['success'], 'Recuperar ativação incerta');
$payload=CoraGateway::webhookPayload(['WEBHOOK-EVENT-TYPE'=>'invoice.paid','Webhook-Resource-Id'=>'inv_fixture','webhook-event-id'=>'evt_fixture']);
coraCheck($g->parseWebhookPayload($payload)['external_id']==='inv_fixture', 'Cabeçalhos case-insensitive');
coraCheck(!$g->validateWebhookSignature($payload,[]), 'Aviso não é confirmação');
try { $g->activateWebhook('http://localhost/webhook/cora'); throw new RuntimeException('Aceitou HTTP'); } catch (InvalidArgumentException) {}

echo "[OK] Cora: autenticação mTLS, v2, idempotência, estados, cancelamento e cadastro de webhook.\n";

// Trocas devem cancelar antes de emitir, nos dois sentidos (inclusive boleto com Pix).
foreach (['pix'=>'boleto', 'boleto'=>'pix'] as $from=>$to) {
    $g=new CoraFakeGateway(); $first=$data; $first['billing_type']=$from;
    $g->responses=[$token,$invoice]; coraCheck($g->createCharge($first)['success'], 'Primeira emissão');
    $next=$data; $next['billing_type']=$to;
    $newInvoice=array_replace($invoice,['id'=>'inv_replacement']);
    $g->responses=[$invoice,['_http_code'=>204],$newInvoice];
    $start=count($g->requests); $result=$g->createCharge($next);
    coraCheck($result['success'] && $result['external_id']==='inv_replacement', 'Troca '.$from.' para '.$to);
    coraCheck(array_column(array_slice($g->requests,$start),'method')===['GET','DELETE','POST'], 'Ordem de troca');
    coraCheck(array_column(array_slice($g->requests,$start),'locked')===[true,true,true], 'Todo o ciclo deve manter o bloqueio');
    coraCheck($g->memory->rows[1]['status']==='cancelled' && $g->memory->rows[2]['status']==='pending', 'Uma cobrança válida');
    $g->responses=[$newInvoice]; $same=$g->createCharge($next);
    coraCheck($same['success'] && count($g->memory->rows)===2, 'Repetição reutiliza cobrança');
}

$setupSwitch=static function() use($data,$token,$invoice): CoraFakeGateway {
    $g=new CoraFakeGateway(); $g->responses=[$token,$invoice]; $g->createCharge($data); return $g;
};
$next=$data; $next['billing_type']='boleto';
$cancelledInvoice=array_replace($invoice,['status'=>'CANCELLED']);
$newInvoice=array_replace($invoice,['id'=>'inv_new']);
$paidInvoice=array_replace($invoice,['status'=>'PAID','total_paid'=>1000]);

$g=$setupSwitch();
$g->responses=[$invoice,['_http_code'=>422,'code'=>'REC-0002','message'=>'Erro para 52998224725 email teste@example.test'], $invoice];
$r=$g->createCharge($next);
coraCheck(!$r['success'] && str_contains($r['message'],'REC-0002') && count($g->memory->rows)===1 && $g->memory->rows[1]['status']==='pending', '422 deve preservar cobrança');
coraCheck(!str_contains(json_encode($g->audits),'52998224725') && !str_contains(json_encode($g->audits),'teste@example.test'), 'Auditoria não deve expor dados pessoais');

foreach ([['_http_code'=>422,'code'=>'REC-0002'],new RuntimeException('Timeout')] as $failure) {
    $g=$setupSwitch(); $g->responses=[$invoice,$failure,$cancelledInvoice,$newInvoice];
    coraCheck($g->createCharge($next)['success'] && $g->memory->rows[1]['status']==='cancelled', 'Confirmar cancelamento por consulta');
}
$g=$setupSwitch(); $g->responses=[$cancelledInvoice,$newInvoice];
coraCheck($g->createCharge($next)['success'] && $g->requests[3]['method']==='POST', 'Já cancelada dispensa DELETE');

foreach ([$paidInvoice, array_replace($invoice,['status'=>'IN_PAYMENT'])] as $state) {
    $g=$setupSwitch(); $g->responses=[$state]; $start=count($g->requests); $r=$g->createCharge($next);
    coraCheck(!$r['success'] && count($g->requests)===$start+1 && count($g->memory->rows)===1, 'Pagamento bloqueia troca');
    if($state['status']==='PAID') coraCheck(isset($r['reconcile_transaction']) && $g->memory->rows[1]['status']==='pending', 'Conciliação antes de marcar paga');
}
$g=$setupSwitch(); $g->responses=[$invoice,['_http_code'=>422],$paidInvoice];
$r=$g->createCharge($next); coraCheck(isset($r['reconcile_transaction']) && count($g->memory->rows)===1, 'Pagamento durante cancelamento');

$g=$setupSwitch(); $g->responses=[$invoice,new RuntimeException('Timeout'),new RuntimeException('Timeout')];
coraCheck(!$g->createCharge($next)['success'] && $g->memory->rows[1]['status']==='pending', 'Incerteza não cancela localmente');
$g=$setupSwitch(); $g->responses=[new RuntimeException('Timeout')];
$start=count($g->requests); coraCheck(!$g->createCharge($next)['success'] && count($g->requests)===$start+1, 'Consulta falhou: não cancelar');

$g=$setupSwitch(); $g->responses=[$invoice,['_http_code'=>204],new RuntimeException('Timeout')];
coraCheck(!$g->createCharge($next)['success'] && $g->memory->rows[1]['status']==='cancelled', 'Falha após cancelamento');
$key=end($g->requests)['idempotencyKey']; $g->responses=[$newInvoice];
coraCheck($g->createCharge($next)['success'] && end($g->requests)['idempotencyKey']===$key && count($g->memory->rows)===2, 'Retomar emissão com mesma chave');

$g=new CoraFakeGateway(); $g->responses=[$token,new RuntimeException('Timeout')]; $g->createCharge($data);
$start=count($g->requests); coraCheck(!$g->createCharge($next)['success'] && count($g->requests)===$start, 'Tentativa sem ID remoto não pode ser abandonada');

$g=$setupSwitch(); $g->memory->rows[1]['chave']='outro-tenant'; $g->responses=[$newInvoice];
coraCheck($g->createCharge($next)['success'] && $g->memory->rows[1]['status']==='pending', 'Não cancelar cobrança de outro tenant');
$g=$setupSwitch(); $g->memory->rows[1]['id_gateway']=99; $start=count($g->requests);
coraCheck(!$g->createCharge($next)['success'] && count($g->requests)===$start, 'Não cancelar cobrança de outro gateway');

echo "[OK] Troca Cora: cancelamento confirmado, duas direções, retry, conciliação, auditoria e isolamento.\n";

$g=$setupSwitch(); $g->memory->financeiroDisponivel=false; $start=count($g->requests);
coraCheck(!$g->createCharge($next)['success'] && count($g->requests)===$start, 'Financeiro alterado/pago deve bloquear emissão');
// Mesma opção no dia seguinte mantém a cobrança e seu vencimento original.
$dated=$data; $dated['source_due_date']='2026-09-19';
$g=new CoraFakeGateway(); $g->responses=[$token,$invoice]; $g->createCharge($dated);
$dated['due_date']='2099-01-02'; $g->responses=[$invoice];
coraCheck($g->createCharge($dated)['success'] && count($g->memory->rows)===1 && end($g->requests)['method']==='GET', 'Data de acesso não deve substituir cobrança');
echo "[OK] Cora: validação do financeiro sob bloqueio e preservação de vencimento.\n";
