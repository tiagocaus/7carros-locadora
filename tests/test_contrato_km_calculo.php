<?php
require __DIR__.'/../vendor/autoload.php';
use App\Services\ContratoKmCalculo;
$c=new ContratoKmCalculo(); $checks=0;
function kmCheck(bool $ok,string $msg):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($msg);}
$v=['plano'=>'KMC','data_saida'=>'2026-09-01 00:00:00','odometro_saida'=>50000,'km_franquia'=>3000,'valor_km_excedente'=>0.5];
$leitura=fn($data,$odo,$fronteira=0)=>['id'=>1,'data'=>substr($data,0,10),'data_referencia'=>$data,'odometro'=>$odo,'fronteira_km'=>$fronteira];
foreach([52000=>0,53000=>0,53200=>100,53600=>300]as$odo=>$valor){$r=$c->calcular(['contagem'=>'mes'],$v,[$leitura('2026-09-15 12:00:00',$odo)],[],'2026-09-15 12:00:00');kmCheck($r['total']===$valor*1.0,"Franquia integral $odo");}
$r=$c->calcular(['contagem'=>'mes'],$v,[$leitura('2026-09-15 12:00:00',53600)],[$v['data_saida']=>100],'2026-09-15 12:00:00');kmCheck($r['total']===200.0,'Desconta fatura anterior');
$r=$c->calcular(['contagem'=>'mes'],$v,[$leitura('2026-09-15 12:00:00',53100)],[$v['data_saida']=>100],'2026-09-15 12:00:00');kmCheck($r['total']===0.0,'Correção não gera crédito imediato');
$r=$c->calcular(['contagem'=>'mes'],$v,[$leitura('2026-10-10 12:00:00',54000)],[],'2026-10-10 12:00:00');kmCheck($r['total']===0.0 && count($r['pendencias'])===1,'Sem estimativa na fronteira');
$r=$c->calcular(['contagem'=>'mes'],$v,[$leitura('2026-10-01 00:00:00',53500,1),$leitura('2026-10-10 12:00:00',54000)],[],'2026-10-10 12:00:00');kmCheck($r['total']===250.0 && !$r['pendencias'],'Complementação cobra setembro');
kmCheck($c->fronteira('2026-01-31 10:00:00','mes',2)==='2026-03-31 10:00:00','Âncora mensal');
kmCheck($c->fronteira('2024-02-29 10:00:00','ano',4)==='2028-02-29 10:00:00','Âncora bissexta');
foreach(['dia'=>1,'semana'=>7,'mes'=>30,'ano'=>365]as$contagem=>$base){$vv=$v;$fim=$c->fronteira($v['data_saida'],$contagem,1);$r=$c->calcular(['contagem'=>$contagem],$vv,[$leitura($fim,53500,1)],[],$fim);kmCheck($r['total']===250.0,"Limite $contagem");}
$v['plano']='KL';kmCheck($c->calcular(['contagem'=>'mes'],$v,[],[],'2026-10-01 00:00:00')['total']===0.0,'Km Livre');
$fechamento=['encerramento_final'=>false,'veiculos'=>[['id_contrato_veiculo'=>1]],'diferenca'=>250,'ajuste_valor'=>250,'ajuste_tipo'=>'R'];
$r=$c->conciliarDevolucao($fechamento,[1=>100]);kmCheck($r['ajuste_valor']===150.0 && $r['ajuste_tipo']==='R','Parcial desconta antecipação');
$r=$c->conciliarDevolucao($fechamento,[1=>300]);kmCheck($r['ajuste_valor']===50.0 && $r['ajuste_tipo']==='D','Parcial gera crédito');
$r=$c->conciliarDevolucao($fechamento,[2=>300]);kmCheck($r['ajuste_valor']===250,'Não usa antecipação de outro veículo');
$fechamento['encerramento_final']=true;
$r=$c->conciliarDevolucao($fechamento,[1=>300]);kmCheck($r['ajuste_valor']===250 && $r['diferenca']===250,'Final não desconta principal duas vezes');
echo "OK: $checks cenários de cálculo.\n";
