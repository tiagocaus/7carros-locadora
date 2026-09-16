<?php
/** Integracao local: filas e gateways simulados; nenhum envio real. */
require dirname(__DIR__) . '/vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';
use App\Core\Database;
use App\Models\Model;
use App\Classes\QueryBuilder;
use App\Services\{ExclusaoVinculoFinanceiroService, ReservaNaoConfirmadaNotificationService, MessageTemplateService};
use App\Exceptions\NotificationChannelUnavailableException;

if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Exige localhost');
session_save_path(sys_get_temp_dir()); session_start();
(new ReflectionProperty(App\Core\Cache::class, 'enabled'))->setValue(null, false);
$db = Model::sharedMysqli();
$suporte = $db->query("SELECT f.id,f.usuario FROM funcionarios f JOIN funcionarios_roles r ON r.id=f.id_role WHERE f.chave='1111111111111' AND r.name='Suporte 7Carros' LIMIT 1")->fetch_assoc();
if (!$suporte) throw new RuntimeException('Fixture de suporte ausente');
$_SESSION = ['authenticated'=>true,'chave'=>'1111111111111','user_id'=>(int)$suporte['id'],'user_usuario'=>$suporte['usuario'],'user_name'=>'TESTE RESERVA '.bin2hex(random_bytes(4)),'filiais_permitidas'=>[]];
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$qb = new QueryBuilder($db);
$filial=(int)$qb->table('matrizes_filiais')->value('id');
$cliente=(int)$qb->table('clientes')->value('id');
if (!$filial || !$cliente) throw new RuntimeException('Fixture exige cliente e filial');
function checkReserva(bool $ok, string $msg): void { if (!$ok) throw new RuntimeException($msg); echo "OK $msg\n"; }
class NotificacaoReservaTeste extends ReservaNaoConfirmadaNotificationService {
    public array $canais = [];
    public array $resultados = ['email'=>1,'whatsapp'=>2,'sms'=>3];
    public int $idLocacao = 0;
    protected function enfileirar(string $canal, array $contexto, string $chave): int {
        $stmt=Database::getConnection()->prepare('SELECT COUNT(*) FROM locacoes WHERE id=? AND chave=?');
        $stmt->execute([$this->idLocacao,$chave]);
        checkReserva((int)$stmt->fetchColumn()===0, "$canal somente apos commit (outra conexao)");
        checkReserva(!empty($contexto['locacao']['numero']) && !empty($contexto['cliente']['preferred_locale']), 'snapshot preservado');
        $this->canais[]=$canal;
        $r=$this->resultados[$canal];
        if ($r instanceof Throwable) throw $r;
        return $r;
    }
}
class ExclusaoReservaTeste extends ExclusaoVinculoFinanceiroService {
    public NotificacaoReservaTeste $notificacao;
    public bool $falhar = false;
    public int $gateways = 0;
    public bool $mudarStatus = false;
    public function __construct() { parent::__construct(); $this->notificacao=new NotificacaoReservaTeste(); }
    protected function notificacaoReserva(): ReservaNaoConfirmadaNotificationService { return $this->notificacao; }
    protected function prepararGateways(string $tipo,int $id,array $estado): void {
        $this->gateways++;
        if ($this->mudarStatus) (new QueryBuilder(Model::sharedMysqli()))->table('locacoes')->where('id','=',$id)->update(['status'=>'R']);
    }
    protected function auditar(string $mensagem,array $campos): void {
        if ($this->falhar) throw new RuntimeException('Rollback simulado');
        parent::auditar($mensagem,$campos);
    }
}
$ids=[];
$criar=function(string $status='P') use($qb,$filial,$cliente,&$ids): int {
    $id=$qb->table('locacoes')->insert(['codigo'=>'T'.bin2hex(random_bytes(6)),'status'=>$status,'id_cliente'=>$cliente,'cliente_nome'=>'Teste','id_matriz_filial_retirada'=>$filial,'data_saida'=>'2026-09-20 10:00:00','data_prevista'=>'2026-09-21 10:00:00','dias'=>1,'total_fatura'=>0,'total_pagar'=>0]);
    $ids[]=$id;return $id;
};
try {
    foreach ([true,false] as $notificar) {
        $id=$criar();$s=new ExclusaoReservaTeste();$s->notificacao->idLocacao=$id;
        $p=$s->preview('locacao',$id);checkReserva($p['pode_notificar_cliente'],'pendente elegivel');
        $r=$s->excluir('locacao',$id,$p['referencia'],'',$notificar);
        checkReserva($r['status']===($notificar?'queued':'not_requested'),'resultado da escolha');
        checkReserva(count($s->notificacao->canais)===($notificar?3:0),'canais conforme escolha');
        try {$s->excluir('locacao',$id,$p['referencia'],'',$notificar);throw new RuntimeException('Repetiu exclusao');}catch(DomainException $e){checkReserva($e->getCode()===404,'repeticao bloqueada');}
    }
    foreach (['R','A','F'] as $status) {
        $id=$criar($status);$s=new ExclusaoReservaTeste();$p=$s->preview('locacao',$id);
        checkReserva(!$p['pode_notificar_cliente'],"$status nao elegivel");
        try {$s->excluir('locacao',$id,$p['referencia'],'',true);throw new RuntimeException('Aceitou status errado');}catch(DomainException $e){checkReserva($e->getCode()===422 && $s->gateways===0,'rejeita antes de efeitos externos');}
    }
    $id=$criar();$s=new ExclusaoReservaTeste();$p=$s->preview('locacao',$id);
    $qb->table('locacoes')->where('id','=',$id)->update(['status'=>'R']);
    try {$s->excluir('locacao',$id,$p['referencia'],'',true);throw new RuntimeException('Aceitou previa antiga');}catch(DomainException $e){checkReserva($e->getCode()===409 && $s->gateways===0,'status concorrente invalida previa');}
    $id=$criar();$s=new ExclusaoReservaTeste();$s->mudarStatus=true;$p=$s->preview('locacao',$id);
    try {$s->excluir('locacao',$id,$p['referencia'],'',true);throw new RuntimeException('Aceitou status novo sob lock');}catch(DomainException $e){checkReserva($e->getCode()===409 && !$s->notificacao->canais,'revalidacao sob lock nao notifica');}
    $id=$criar();$s=new ExclusaoReservaTeste();$s->falhar=true;$p=$s->preview('locacao',$id);
    try {$s->excluir('locacao',$id,$p['referencia'],'',true);throw new LogicException('Nao reverteu');}catch(RuntimeException $e){checkReserva($e->getMessage()==='Rollback simulado','falha simulada');}
    checkReserva($qb->table('locacoes')->where('id','=',$id)->exists() && !$s->notificacao->canais,'rollback preserva reserva e nao notifica');
    foreach (['partial','unavailable'] as $status) {
        $id=$criar();$s=new ExclusaoReservaTeste();$s->notificacao->idLocacao=$id;
        $s->notificacao->resultados=['email'=>$status==='partial'?1:0,'whatsapp'=>new NotificationChannelUnavailableException('Desabilitado'),'sms'=>new RuntimeException('Falha simulada de fila')];
        $p=$s->preview('locacao',$id);$r=$s->excluir('locacao',$id,$p['referencia'],'',true);
        checkReserva($r['status']===$status && !$qb->table('locacoes')->where('id','=',$id)->exists(),"$status nao desfaz exclusao");
    }
    $migration=require APP_ROOT.'/app/Database/migrations/00431_add_reserva_nao_confirmada_templates.php';
    $migration->up(); // idempotencia mesmo com sessao tenant ativa
    $stmt=Database::getConnection()->query("SELECT COUNT(*) FROM message_templates m JOIN message_template_types t ON t.id=m.template_type_id WHERE t.slug='reserva_nao_confirmada' AND m.chave='0'");
    checkReserva((int)$stmt->fetchColumn()===15,'15 templates sem duplicacao');
    $typeId=(int)Database::getConnection()->query("SELECT id FROM message_template_types WHERE slug='reserva_nao_confirmada'")->fetchColumn();
    $db->begin_transaction();
    try {
        $qb->table('message_templates')->where('template_type_id','=',$typeId)->where('locale','=','en_US')->where('channel','=','sms')->delete();
        $qb->table('message_templates')->insert(['template_type_id'=>$typeId,'locale'=>'en_US','channel'=>'sms','content'=>'CUSTOM {{locacao.numero}}','is_active'=>1]);
        $custom=new MessageTemplateService(null,'1111111111111');
        checkReserva($custom->getTemplate('reserva_nao_confirmada','sms','en_US')['is_custom'],'customizacao tem prioridade');
        $outro=new MessageTemplateService(null,'tenant-inexistente-teste');
        checkReserva(!$outro->getTemplate('reserva_nao_confirmada','sms','en_US')['is_custom'],'customizacao isolada por tenant');
        $custom->restoreDefault('reserva_nao_confirmada','sms','en_US');
        checkReserva(!$custom->getTemplate('reserva_nao_confirmada','sms','en_US')['is_custom'],'restauracao padrao');
    } finally { $db->rollback(); }
    $templates=new MessageTemplateService(null,'1111111111111');
    foreach(['pt_BR','pt_PT','en_US','es_ES','it_IT'] as $locale) {
        foreach(['email','whatsapp','sms'] as $canal) {
            $r=$templates->render('reserva_nao_confirmada',$canal,['cliente'=>['nome'=>'Maria Teste','primeiro_nome'=>'Maria','preferred_locale'=>$locale],'empresa'=>['nome_fantasia'=>'Locadora Teste'],'locacao'=>['numero'=>'R12345678']]);
            checkReserva($r && $r['locale']===$locale && !str_contains($r['content'],'{{') && str_contains($r['content'],'R12345678'),"render $locale/$canal");
            if($canal==='sms')checkReserva(mb_strlen($r['content_plain'])<=160,'SMS exemplo ate 160 caracteres');
        }
        $lang=require APP_ROOT."/app/Lang/$locale/templates.php";
        checkReserva(isset($lang['types']['reserva_nao_confirmada']),"rotulo $locale");
    }
} finally {
    foreach($ids as $id)$qb->table('locacoes')->where('id','=',$id)->delete();
    $qb->table('logs')->where('mensagem','LIKE',$_SESSION['user_name'].'%')->delete();
}
echo "PASS reserva nao confirmada; nenhum envio real\n";
