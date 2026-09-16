<?php
/** Integração local isolada. Nunca chama gateways ou mensageria. */
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Helpers/helpers.php';
use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\ContratoKm;
use App\Models\Contrato;
use App\Models\ContratoVeiculo;
putenv('APP_ENV=development');
if(Database::env('DB_HOST')!=='localhost') throw new RuntimeException('Somente localhost.');
$_SESSION=['chave'=>'1111111111111'];
$d=Database::getConnection(); $chave=$_SESSION['chave']; $ids=[]; $checks=0;
if (in_array('--km-worker',$argv,true)) {
    $entrada=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $contrato=(new Contrato())->buscarPorId($entrada['id_contrato']);
    $veiculo=(new ContratoVeiculo())->buscarPorId($entrada['id_contrato_veiculo']);
    $res=(new ContratoKm())->registrar($contrato,$veiculo,$entrada);
    echo json_encode(['id'=>$res['registro']['id']]); exit;
}

function checkKmDb($ok,$msg){global $checks;$checks++;if(!$ok)throw new RuntimeException($msg);}
function insertKmFixture($table,$values){global $d; $cols=array_keys($values);$q=$d->prepare('INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');$q->execute(array_values($values));return (int)$d->lastInsertId();}
function firstKmFixture($table){global $d,$chave;$q=$d->prepare("SELECT * FROM $table WHERE chave=? LIMIT 1");$q->execute([$chave]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException("Fixture base ausente: $table");unset($r['id']);return $r;}
try {
    $c=firstKmFixture('contratos'); $v=firstKmFixture('veiculos'); $cv=firstKmFixture('contratos_veiculos');
    $v['placa']='T'.substr(bin2hex(random_bytes(4)),0,6);$v['odometro']='50000';$ids['veiculo']=insertKmFixture('veiculos',$v);
    $agora=DateHelper::todayForDatabase('Y-m-d H:i:s');$saida=DateHelper::parseOperationalDateTime($agora)->modify('-15 days')->format('Y-m-d H:i:s');
    $c['codigo']='KM'.substr(bin2hex(random_bytes(6)),0,12);$c['contagem']='mes';$c['status']='A';$c['data_ini']=$saida;$c['auto_renovacao']=null;
    $ids['contrato']=insertKmFixture('contratos',$c);
    $cv['id_contrato']=$ids['contrato'];$cv['id_veiculo']=$ids['veiculo'];$cv['data_saida']=$saida;$cv['data_entrada']=null;$cv['odometro_saida']=50000;$cv['odometro_entrada']=null;$cv['km_franquia']=3000;$cv['valor_km_excedente']=0.5;$cv['plano']='KMC';
    $ids['vinculo']=insertKmFixture('contratos_veiculos',$cv);
    $model=new ContratoKm();$contrato=(new Contrato())->buscarPorId($ids['contrato']);
    $conta=$d->query("SELECT id FROM contas_bancarias WHERE chave='1111111111111' AND status='A' LIMIT 1")->fetchColumn();
    $forma=$d->query("SELECT id FROM formas_pagamento WHERE chave='1111111111111' AND status='A' LIMIT 1")->fetchColumn();
    insertKmFixture('contratos_odometros',['chave'=>$chave,'id_contrato'=>$ids['contrato'],'id_contrato_veiculo'=>$ids['vinculo'],'data'=>DateHelper::todayForDatabase(),'odometro'=>51000,'diferenca'=>1000]);
    $entrada=['odometro'=>53200,'operacao_km'=>bin2hex(random_bytes(16)),'financeiro'=>['id_conta'=>$conta,'id_forma_pagamento'=>$forma,'data_venci'=>DateHelper::todayForDatabase()]];
    $vv=(new ContratoVeiculo())->buscarPorId($ids['vinculo']);$p=$model->previa($contrato,$vv,$entrada);
    checkKmDb($p['total']===100.0,'Prévia de R$100');
    $entrada += ['data_referencia'=>$p['data_referencia'],'apuracao_em'=>$p['apuracao_em'],'versao'=>$p['versao']];
    $r=$model->registrar($contrato,$vv,$entrada);checkKmDb($r['fatura']['valor_total']===100.0,'Fatura criada');
    $rr=$model->registrar($contrato,$vv,$entrada);checkKmDb($rr['registro']['id']===$r['registro']['id'],'Reenvio idempotente');
    checkKmDb($model->totalFaturado($ids['vinculo'])===100.0,'Principal rastreável');
    $entrada['odometro']=53600;$entrada['operacao_km']=bin2hex(random_bytes(16));unset($entrada['data_referencia'],$entrada['apuracao_em'],$entrada['versao']);
    $vv=(new ContratoVeiculo())->buscarPorId($ids['vinculo']);$p=$model->previa($contrato,$vv,$entrada);
    checkKmDb($p['total']===200.0,'Cobra somente os 400 km adicionais');
    $entrada += ['data_referencia'=>$p['data_referencia'],'apuracao_em'=>$p['apuracao_em'],'versao'=>$p['versao']];
    $entrada['financeiro']['id_conta']=0;
    try{$model->registrar($contrato,$vv,$entrada);throw new RuntimeException('Conta inválida aceita');}catch(InvalidArgumentException $e){}
    checkKmDb(count($model->leituras($ids['vinculo']))===2,'Falha financeira não grava leitura');
    $entrada['financeiro']['id_conta']=$conta;
    $falha=new class extends ContratoKm {
        protected function faturar(array $c,array $v,int $leitura,array $previa,array $fin): array {
            parent::faturar($c,$v,$leitura,$previa,$fin);
            throw new RuntimeException('Falha simulada após criação da fatura e itens');
        }
    };
    try{$falha->registrar($contrato,$vv,$entrada);throw new LogicException('Falha não ocorreu');}catch(RuntimeException $e){}
    checkKmDb(count($model->leituras($ids['vinculo']))===2 && $model->totalFaturado($ids['vinculo'])===100.0,'Rollback inclui leitura, fatura, itens e apuração');
    $r2=$model->registrar($contrato,$vv,$entrada);
    checkKmDb($r2['fatura']['valor_total']===200.0,'Segunda fatura');
    $financeiro=new App\Models\Financeiro();
    try{$financeiro->atualizar($r2['fatura']['id'],['valor_subtotal'=>1]);throw new RuntimeException('Principal editável');}catch(InvalidArgumentException $e){}
    checkKmDb($model->financeiroProtegido($r2['fatura']['id']),'Protege fatura');
    $principal=(new App\Models\ContratoEncerramento())->calcularPrincipalLancado($ids['contrato']);checkKmDb($principal===300.0,'Encerramento inclui antecipações uma vez');
    $corrigido=(new App\Models\ContratoOdometro())->editarLeitura((int)$r2['registro']['id'],[
        'id_contrato'=>$ids['contrato'],'id_contrato_veiculo'=>$ids['vinculo'],'id_veiculo'=>$ids['veiculo'],
        'odometro_saida'=>50000,'odometro'=>53400,'data'=>$r2['data'],'obs'=>'Correção teste',
    ]);
    checkKmDb($corrigido['success'] && $model->totalFaturado($ids['vinculo'])===300.0,'Correção preserva faturas emitidas');
    $vv=(new ContratoVeiculo())->buscarPorId($ids['vinculo']);
    $p=$model->previa($contrato,$vv,['odometro'=>53400]);
    checkKmDb($p['total']===0.0,'Redução deixa crédito para o encerramento');
    try{$financeiro->deletar($r2['fatura']['id']);throw new LogicException('Exclusão comum aceita');}catch(DomainException $e){}
    checkKmDb($financeiro->buscarPorId($r2['fatura']['id'])!==null,'Exclusão comum protegida');
    try{(new Contrato())->atualizar($ids['contrato'],['contagem'=>'dia']);throw new LogicException('Contagem foi alterada');}catch(DomainException $e){}
    checkKmDb((new Contrato())->buscarPorId($ids['contrato'])['contagem']==='mes','Protege contagem após faturar');
    (new Contrato())->limparParcelasPendentes($ids['contrato']);
    checkKmDb($model->totalFaturado($ids['vinculo'])===300.0,'Regeneração preserva faturas de km');
    $paralelo=['id_contrato'=>$ids['contrato'],'id_contrato_veiculo'=>$ids['vinculo'],'odometro'=>53800,
        'operacao_km'=>bin2hex(random_bytes(16)),'financeiro'=>$entrada['financeiro']];
    $p=$model->previa($contrato,$vv,$paralelo);
    $paralelo += ['data_referencia'=>$p['data_referencia'],'apuracao_em'=>$p['apuracao_em'],'versao'=>$p['versao']];
    $processos=[];
    for($i=0;$i<2;$i++) {
        $proc=proc_open([PHP_BINARY,__FILE__,'--km-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fwrite($pipes[0],json_encode($paralelo));fclose($pipes[0]);$processos[]=[$proc,$pipes];
    }
    $resultados=[];
    foreach($processos as [$proc,$pipes]) {
        $saida=stream_get_contents($pipes[1]);$erro=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        checkKmDb(proc_close($proc)===0,'Worker concorrente: '.$erro);$resultados[]=json_decode($saida,true);
    }
    checkKmDb($resultados[0]['id']===$resultados[1]['id'] && $model->totalFaturado($ids['vinculo'])===400.0,'Duas conexões não duplicam leitura nem fatura');
    $_SESSION['chave']='2222222222222';checkKmDb($model->totalFaturado($ids['vinculo'])===0.0,'Isolamento tenant');$_SESSION['chave']=$chave;
    echo "OK: $checks verificações com MySQL local.\n";
} finally {
    $_SESSION['chave']=$chave;
    if(isset($ids['contrato'])){
        $d->prepare('DELETE FROM financeiro WHERE chave=? AND id_contrato=?')->execute([$chave,$ids['contrato']]);
        $d->prepare('DELETE FROM contratos_km_ciclos WHERE chave=? AND id_contrato=?')->execute([$chave,$ids['contrato']]);
        $d->prepare('DELETE FROM contratos WHERE chave=? AND id=?')->execute([$chave,$ids['contrato']]);
    }
    if(isset($ids['veiculo']))$d->prepare('DELETE FROM veiculos WHERE chave=? AND id=?')->execute([$chave,$ids['veiculo']]);
}
