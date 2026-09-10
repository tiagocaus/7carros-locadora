<?php
/** Integracao LOCAL com fixtures isoladas. Nao envia mensagens nem chama gateways reais. */
require dirname(__DIR__) . '/vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';
use App\Core\{Database, Auth};
use App\Models\{Model, ExclusaoVinculoFinanceiro};
use App\Classes\QueryBuilder;
use App\Services\ExclusaoVinculoFinanceiroService;

if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Teste exige localhost');
if (session_status() !== PHP_SESSION_ACTIVE) { session_save_path(sys_get_temp_dir()); session_start(); }
// O PHP CLI local pode nao carregar Redis; testar permissoes pelo banco.
(new ReflectionProperty(App\Core\Cache::class, 'enabled'))->setValue(null, false);
$db = Model::sharedMysqli();
$suporte = $db->query("SELECT f.id,f.usuario FROM funcionarios f JOIN funcionarios_roles r ON r.id=f.id_role WHERE f.chave='1111111111111' AND r.name='Suporte 7Carros' LIMIT 1")->fetch_assoc();
if (!$suporte) throw new RuntimeException('Fixture exige suporte do tenant de testes');
$_SESSION = ['authenticated'=>true, 'chave'=>'1111111111111', 'user_id'=>(int)$suporte['id'], 'user_usuario'=>$suporte['usuario'], 'user_name'=>'TESTE EXCLUSAO '.bin2hex(random_bytes(3)), 'filiais_permitidas'=>[]];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$qb = new QueryBuilder($db);
$prefix = $_SESSION['user_name'];
$filial = (int) $qb->table('matrizes_filiais')->value('id');
if (!$filial) throw new RuntimeException('Fixture exige filial local');
$registros = []; $financeiros = []; $promissorias = []; $encerramentos = []; $checklists = [];
class ExclusaoTeste extends ExclusaoVinculoFinanceiroService {
    public bool $falharGateway = false;
    public int $falharLog = 0;
    private int $logs = 0;
    protected function prepararGateways(string $tipo, int $id, array $estado): void {
        if ($this->falharGateway) throw new RuntimeException('Falha simulada de gateway');
    }
    protected function auditar(string $mensagem, array $campos): void {
        if (++$this->logs === $this->falharLog) throw new RuntimeException('Falha simulada de auditoria');
        parent::auditar($mensagem, $campos);
    }
}
function verificar(bool $ok, string $msg): void { if (!$ok) throw new RuntimeException($msg); echo "OK $msg\n"; }
function esperarFalha(callable $fn, int $code, string $msg): void {
    try { $fn(); } catch (Throwable $e) { verificar($e->getCode() === $code, $msg . ' ['.$e->getMessage().']'); return; }
    throw new RuntimeException('Nao bloqueou: '.$msg);
}
$criar = function(string $tipo) use ($qb, &$registros, $filial): int {
    $table = ExclusaoVinculoFinanceiro::tabela($tipo);
    $dados = ['id_matriz_filial_retirada'=>$filial, 'codigo'=>'T'.bin2hex(random_bytes(6)), 'status'=>'F', 'dias'=>1, 'total_fatura'=>0, 'total_pagar'=>0];
    $dados += $tipo === 'contrato' ? ['data_ini'=>'2026-09-01 10:00:00', 'data_fim'=>'2026-09-02 10:00:00', 'contagem'=>'dias'] : ['data_saida'=>'2026-09-01 10:00:00', 'data_prevista'=>'2026-09-02 10:00:00', 'cliente_nome'=>'Teste'];
    $id = $qb->table($table)->insert($dados); $registros[] = [$table,$id]; return $id;
};
$criarFinanceiro = function(string $tipo, ?int $id, string $pago, string $valor, array $extra=[]) use ($qb, &$financeiros, $prefix): int {
    $fid = $qb->table('financeiro')->insert($extra + ['id_'.$tipo=>$id, 'descricao'=>$prefix, 'tipo'=>'R', 'pago'=>$pago, 'data_criada'=>'2026-09-01', 'data_venci'=>'2026-09-02', 'valor_subtotal'=>$valor, 'valor_total'=>$valor]);
    $financeiros[]=$fid; return $fid;
};
try {
    verificar(Auth::can('financeiro.excluir'), 'permissao da fixture');
    foreach (['contrato','locacao'] as $tipo) {
        $id=$criar($tipo);$service=new ExclusaoTeste();
        $vazio=$service->preview($tipo,$id);verificar($vazio['resumo']['quantidade']===0, "$tipo sem financeiro");
        $aberto=$criarFinanceiro($tipo,$id,'N','1000.00');
        $pago=$criarFinanceiro($tipo,$id,'S','200.00');
        $taxa=$criarFinanceiro($tipo,null,'S','5.00',['tipo'=>'D','id_financeiro_taxa_origem'=>$pago]);
        $filho=$criarFinanceiro($tipo,null,'N','50.00',['id_financeiro_origem'=>$aberto]);
        $outro=$criarFinanceiro($tipo,null,'N','999.00');
        $ck=$qb->table('checklist')->insert(['id_'.$tipo=>$id,'tipo'=>'V','status'=>'3']);$checklists[]=$ck;
        $qb->table('financeiro_itens')->insert(['id_financeiro'=>$aberto,'descricao'=>'Item auditavel','valor'=>'1000.00','ordem'=>1]);
        esperarFalha(fn()=>$service->excluir($tipo,$id,$vazio['referencia']),409,"$tipo previa obsoleta");
        $previa=$service->preview($tipo,$id);
        verificar($previa['resumo']['aberto']===105000 && $previa['resumo']['pago']===20500 && $previa['resumo']['total']===125500,"$tipo totais incluem pagos, filhos e taxas");
        $_SESSION['chave']='outro-tenant-teste';
        esperarFalha(fn()=>$service->preview($tipo,$id),404,"$tipo isolamento de tenant");
        $_SESSION['chave']='1111111111111';
        $_SESSION['authenticated']=false;
        esperarFalha(fn()=>$service->preview($tipo,$id),403,"$tipo permissao");
        $_SESSION['authenticated']=true;
        $_SESSION['filiais_permitidas']=[9999999];
        // Filial real da entidade difere da lista permitida.
        esperarFalha(fn()=>$service->preview($tipo,$id),403,"$tipo filial");
        $_SESSION['filiais_permitidas']=[];
        $pid=$qb->table('promissorias')->insert(['id_financeiro'=>$aberto,'pago'=>'N','data_criada'=>'2026-09-01','data_vencimento'=>'2026-09-02','valor_parcela'=>1000]);$promissorias[]=$pid;
        esperarFalha(fn()=>$service->preview($tipo,$id),422,"$tipo promissoria bloqueia");
        $qb->table('promissorias')->where('id','=',$pid)->delete();
        $falha=new ExclusaoTeste();$falha->falharGateway=true;
        esperarFalha(fn()=>$falha->excluir($tipo,$id,$previa['referencia']),0,"$tipo gateway bloqueia");
        $falha=new ExclusaoTeste();$falha->falharLog=5; // quatro lancamentos, depois log do principal
        esperarFalha(fn()=>$falha->excluir($tipo,$id,$previa['referencia']),0,"$tipo auditoria reverte exclusao");
        verificar($qb->table(ExclusaoVinculoFinanceiro::tabela($tipo))->where('id','=',$id)->exists(),"$tipo principal preservado no rollback");
        verificar($qb->table('checklist')->where('id','=',$ck)->exists(),"$tipo checklist preservado no rollback");
        verificar($qb->table('financeiro')->whereIn('id',[$aberto,$pago,$taxa,$filho])->count()===4,"$tipo financeiro preservado no rollback");
        verificar(!$qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->exists(),"$tipo nenhum log parcial");
        $service->excluir($tipo,$id,$previa['referencia']);
        verificar(!$qb->table('financeiro')->whereIn('id',[$aberto,$pago,$taxa,$filho])->exists(),"$tipo todos os lancamentos removidos");
        verificar($qb->table('financeiro')->where('id','=',$outro)->exists(),"$tipo financeiro avulso preservado");
        verificar($qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->count()===5,"$tipo auditoria por lancamento e principal");
        verificar(!$qb->table('checklist')->where('id','=',$ck)->exists(),"$tipo checklist removido no commit");
        verificar(!$qb->table('financeiro_itens')->where('id_financeiro','=',$aberto)->exists(),"$tipo itens removidos");
        esperarFalha(fn()=>$service->excluir($tipo,$id,$previa['referencia']),404,"$tipo repeticao nao duplica logs");
        $qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->delete();
        $id=$criar($tipo);$previa=$service->preview($tipo,$id);$service->excluir($tipo,$id,$previa['referencia']);
        verificar(!$qb->table(ExclusaoVinculoFinanceiro::tabela($tipo))->where('id','=',$id)->exists(),"$tipo exclusao sem financeiro");
        $qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->delete();
        // Caucoes pagas e pendentes devem entrar no mesmo resumo, mesmo por referencia.
        $id=$criar($tipo);
        $fid=$criarFinanceiro($tipo,null,'S','150.00');
        $cliente=(int)$qb->table('clientes')->value('id');
        $qb->table(ExclusaoVinculoFinanceiro::tabela($tipo).'_caucoes')->insert(['id_'.$tipo=>$id,'id_cliente'=>$cliente,'valor'=>150,'id_financeiro_entrada'=>$fid,'status'=>'ativa']);
        $previa=$service->preview($tipo,$id);
        verificar($previa['resumo']['pago']===15000,"$tipo caucao paga incluida");
        $service->excluir($tipo,$id,$previa['referencia']);
        verificar(!$qb->table('financeiro')->where('id','=',$fid)->exists(),"$tipo caucao financeira removida");
        $qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->delete();
    }
    $id=$criar('contrato');$fid=$criarFinanceiro('contrato',$id,'N','80.00');
    $dados=['id_contrato'=>$id,'data_encerramento'=>'2026-09-02 10:00:00','contagem'=>'dias','base_dias'=>1,'ajuste_tipo'=>'R','id_financeiro_ajuste'=>$fid,'calculo_json'=>'{}'];
    foreach(['total_original','total_veiculos','total_seguros','total_taxas_contrato','total_adicionais_devolucao','desconto_original','desconto_aplicado','total_final','principal_lancado','diferenca'] as $campo)$dados[$campo]=0;
    $eid=$qb->table('contratos_encerramentos')->insert($dados);$encerramentos[]=$eid;
    $service=new ExclusaoTeste();$previa=$service->preview('contrato',$id);
    verificar($previa['exige_motivo'],'ajuste exige motivo no modal');
    esperarFalha(fn()=>$service->excluir('contrato',$id,$previa['referencia']),422,'ajuste sem motivo bloqueado');
    $service->excluir('contrato',$id,$previa['referencia'],'Exclusao de contrato de teste');
    $snapshot=$qb->table('contratos_encerramentos')->where('id','=',$eid)->first();
    verificar($snapshot['calculo_json']==='{}' && $snapshot['id_financeiro_ajuste']===null,'snapshot preservado sem referencia invalida');
} finally {
    $_SESSION['chave']='1111111111111';
    foreach($checklists as $id)$qb->table('checklist')->where('id','=',$id)->delete();
    foreach($encerramentos as $id)$qb->table('contratos_encerramentos')->where('id','=',$id)->delete();
    foreach($promissorias as $id)$qb->table('promissorias')->where('id','=',$id)->delete();
    foreach(array_reverse($financeiros) as $id)$qb->table('financeiro')->where('id','=',$id)->delete();
    foreach($registros as [$table,$id])$qb->table($table)->where('id','=',$id)->delete();
    $qb->table('logs')->where('mensagem','LIKE',$prefix.'%')->delete();
}
echo "PASS exclusao financeira de contratos e locacoes\n";
