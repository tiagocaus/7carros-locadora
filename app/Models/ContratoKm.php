<?php
namespace App\Models;

use App\Helpers\DateHelper;
use App\Services\ContratoKmCalculo;

/** Persistência e transação do registro + cobrança. Sempre usa o Singleton dos Models. */
class ContratoKm extends Model
{
    private static ?bool $schema = null;

    public static function disponivel(): bool
    {
        if (self::$schema === null) {
            self::$schema = self::sharedMysqli()->query("SHOW TABLES LIKE 'contratos_km_apuracoes'")->num_rows > 0;
        }
        return self::$schema;
    }

    public static function habilitado(): bool
    {
        return self::disponivel();
    }

    public function leituras(int $vinculo): array
    {
        return $this->qb->table('contratos_odometros')->where('id_contrato_veiculo','=',$vinculo)
            ->orderByRaw("COALESCE(data_referencia, CONCAT(data, ' 00:00:00')) ASC, id ASC")->get();
    }

    public function faturados(int $vinculo): array
    {
        if (!self::disponivel()) return [];
        $rows=$this->qb->table('contratos_km_apuracoes','a')
            ->selectRaw('c.inicio, SUM(a.valor) AS valor')
            ->join('contratos_km_ciclos','c','a.id_ciclo','=','c.id')
            ->join('financeiro','f','a.id_financeiro','=','f.id')
            ->where('c.id_contrato_veiculo','=',$vinculo)->groupBy('c.inicio')->get();
        return array_column($rows,'valor','inicio');
    }

    public function totalFaturado(int $vinculo): float
    {
        return round(array_sum($this->faturados($vinculo)),2);
    }

    public function temCobranca(int $contrato, ?int $vinculo=null): bool
    {
        if (!self::disponivel()) return false;
        $q=$this->qb->table('contratos_km_ciclos','c')->join('contratos_km_apuracoes','a','c.id','=','a.id_ciclo')
            ->where('c.id_contrato','=',$contrato);
        if ($vinculo!==null) $q->where('c.id_contrato_veiculo','=',$vinculo);
        return $q->exists();
    }

    public function financeiroProtegido(int $id): bool
    {
        if (!self::disponivel()) return false;
        return $this->qb->table('contratos_km_apuracoes')->where('id_financeiro','=',$id)->exists()
            || $this->qb->table('contratos_km_ciclos')->where('id_financeiro_acerto','=',$id)->exists();
    }

    public function faturasLeitura(int $id): array
    {
        if (!self::disponivel()) return [];
        return $this->qb->table('contratos_km_apuracoes','a')->select(['f.id','f.sequencia','f.pago','f.valor_total'])
            ->join('financeiro','f','a.id_financeiro','=','f.id')->where('a.id_leitura','=',$id)->distinct()->get();
    }

    public static function preservarNoReparcelamento(\App\Classes\QueryBuilder $query): void
    {
        if (!self::disponivel()) return;
        $query->whereRaw('NOT EXISTS (SELECT 1 FROM contratos_km_apuracoes ka WHERE ka.chave = financeiro.chave AND ka.id_financeiro = financeiro.id)');
        $query->whereRaw('NOT EXISTS (SELECT 1 FROM contratos_km_ciclos kc WHERE kc.chave = financeiro.chave AND kc.id_financeiro_acerto = financeiro.id)');
    }

    public function bloquearVinculoAtivo(int $id): ?array
    {
        $v=$this->qb->table('contratos_veiculos')->where('id','=',$id)->lockForUpdate()->first();
        return $v && empty($v['data_entrada']) ? (new ContratoVeiculo())->buscarPorId($id) : null;
    }

    public function registrarAcerto(int $vinculo, ?int $financeiro, array $calculo): void
    {
        if (!self::disponivel()) return;
        $this->qb->table('contratos_km_ciclos')->where('id_contrato_veiculo','=',$vinculo)->update([
            'id_financeiro_acerto'=>$financeiro,'acerto_json'=>json_encode($calculo,JSON_THROW_ON_ERROR),
        ]);
    }

    public function previa(array $contrato, array $veiculo, array $entrada): array
    {
        $agora=DateHelper::todayForDatabase('Y-m-d H:i:s');
        $apuracaoEm=(string)($entrada['apuracao_em']??$agora);
        if ($apuracaoEm>$agora || substr($apuracaoEm,0,10)!==DateHelper::todayForDatabase()) throw new \InvalidArgumentException('Atualize a prévia.');
        $referencia=trim((string)($entrada['data_referencia']??$agora));
        if (DateHelper::parseOperationalDateTime($referencia)->format('Y-m-d H:i:s') !== $referencia || $referencia>$agora || $referencia<$veiculo['data_saida']) {
            throw new \InvalidArgumentException('Data de referência inválida.');
        }
        $fronteira=!empty($entrada['fronteira_km']);
        $leituras=$this->leituras((int)$veiculo['id']);
        $odometro=filter_var($entrada['odometro']??null,FILTER_VALIDATE_INT);
        if ($odometro===false || $odometro<=0 || $odometro>4294967295) throw new \InvalidArgumentException('Odômetro inválido.');
        if (!$fronteira) {
            if (substr($referencia,0,10)!==DateHelper::todayForDatabase()) throw new \InvalidArgumentException('Atualize a prévia para a data atual.');
            $minimo=max((int)$veiculo['odometro_saida'], (int)($veiculo['veiculo_odometro']??0));
            foreach($leituras as $l) $minimo=max($minimo,(int)$l['odometro']);
            if ($odometro<$minimo) throw new \InvalidArgumentException('Odômetro inferior à última leitura.');
        } else {
            $calc=new ContratoKmCalculo(); $valida=false;
            for($n=1;$n<10000;$n++) {
                $data=$calc->fronteira($veiculo['data_saida'],$contrato['contagem'],$n);
                if($data===$referencia){$valida=true;break;} if($data>$referencia)break;
            }
            if(!$valida) throw new \InvalidArgumentException('A data não é uma virada de ciclo deste veículo.');
            $minimo=(int)$veiculo['odometro_saida']; $maximo=PHP_INT_MAX;
            foreach($leituras as $l) {
                if(!empty($l['fronteira_km']) && $l['data_referencia']===$referencia) throw new \InvalidArgumentException('Esta fronteira já está registrada. Corrija a leitura existente.');
                $data=$l['data_referencia']??($l['data'].' 00:00:00');
                if($data<$referencia) $minimo=max($minimo,(int)$l['odometro']);
                else $maximo=min($maximo,(int)$l['odometro']);
            }
            if($odometro<$minimo || $odometro>$maximo) throw new \InvalidArgumentException('Leitura da virada fora do intervalo das leituras anterior e posterior.');
        }
        $nova=['data'=>substr($referencia,0,10),'data_referencia'=>$referencia,'odometro'=>$odometro,'fronteira_km'=>$fronteira?1:0,'id'=>PHP_INT_MAX];
        $leituras[]=$nova;
        usort($leituras,static fn($a,$b)=>strcmp($a['data_referencia']??($a['data'].' 00:00:00'),$b['data_referencia']??($b['data'].' 00:00:00')) ?: ((int)$a['id']<=>(int)$b['id']));
        $res=(new ContratoKmCalculo())->calcular($contrato,$veiculo,$leituras,$this->faturados((int)$veiculo['id']),$fronteira?$apuracaoEm:$referencia);
        $res['data_referencia']=$referencia;
        $res['apuracao_em']=$apuracaoEm;
        // O token cobre também leituras/configuração; o cliente nunca decide quanto faturar.
        $res['versao']=hash('sha256',json_encode([$contrato['contagem'],$veiculo,$leituras,$res],JSON_PRESERVE_ZERO_FRACTION));
        return $res;
    }

    public function registrar(array $contrato, array $veiculo, array $entrada): array
    {
        $op=(string)($entrada['operacao_km']??'');
        if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$op)) throw new \InvalidArgumentException('Identificador de operação inválido.');
        $this->qb->beginTransaction();
        try {
            $c=$this->qb->table('contratos')->where('id','=',(int)$contrato['id'])->lockForUpdate()->first();
            if(!$c || $c['status']!=='A') throw new \InvalidArgumentException('Contrato não está ativo.');
            $v=$this->qb->table('contratos_veiculos')->where('id','=',(int)$veiculo['id'])->lockForUpdate()->first();
            if(!$v || $v['data_entrada']!==null || (int)$v['id_contrato']!==(int)$c['id']) throw new \InvalidArgumentException('Veículo não está ativo neste contrato.');
            $repetida=$this->qb->table('contratos_odometros')->where('operacao_km','=',$op)->first();
            if($repetida) {
                if((int)$repetida['id_contrato_veiculo']!==(int)$v['id'] || (int)$repetida['odometro']!==(int)$entrada['odometro']) throw new \InvalidArgumentException('Operação já utilizada.');
                $this->qb->commit(); $res=json_decode($repetida['resultado_km'],true,512,JSON_THROW_ON_ERROR); $res['reenvio']=true; return $res;
            }
            $veiculo=(new ContratoVeiculo())->buscarPorId((int)$v['id']);
            $previa=$this->previa($c,$veiculo,$entrada);
            if(!hash_equals($previa['versao'],(string)($entrada['versao']??''))) throw new \DomainException('A prévia mudou. Revise os valores antes de confirmar.');
            $fin=$entrada['financeiro']??[];
            if($previa['total']>0) $this->validarFinanceiro($fin);
            $ref=$previa['data_referencia']; $odo=(int)$entrada['odometro'];
            $id=$this->qb->table('contratos_odometros')->insert([
                'chave'=>$c['chave'],'id_contrato'=>$c['id'],'id_contrato_veiculo'=>$v['id'],
                'data'=>substr($ref,0,10),'data_referencia'=>$ref,'fronteira_km'=>empty($entrada['fronteira_km'])?0:1,
                'odometro'=>$odo,'diferenca'=>0,'obs'=>mb_substr(trim((string)($entrada['obs']??'')),0,255),
                'id_funcionario'=>$entrada['id_funcionario']??null,'created_at'=>DateHelper::nowForDatabase(),'operacao_km'=>$op,
            ]);
            $this->recalcularDiferencas((int)$v['id'],(int)$v['odometro_saida']);
            if(empty($entrada['fronteira_km'])) $this->qb->table('veiculos')->where('id','=',(int)$v['id_veiculo'])->update(['odometro'=>$odo]);
            $this->salvarCiclos($c,$v,$previa);
            $fatura=$previa['total']>0 ? $this->faturar($c,$v,$id,$previa,$fin) : null;
            $registro=$this->qb->table('contratos_odometros')->where('id','=',$id)->first();
            $res=['registro'=>$registro,'odometro'=>$odo,'data'=>substr($ref,0,10),'created_at'=>$registro['created_at'],
                'fatura'=>$fatura,'apuracao'=>$previa];
            $this->qb->table('contratos_odometros')->where('id','=',$id)->update(['resultado_km'=>json_encode($res,JSON_THROW_ON_ERROR)]);
            $this->qb->commit(); return $res;
        } catch(\Throwable $e) { $this->qb->rollback(); throw $e; }
    }

    public function recalcularDiferencas(int $vinculo, int $saida): void
    {
        $leituras=$this->leituras($vinculo);
        usort($leituras,static fn($a,$b)=>strcmp($a['data_referencia']??($a['data'].' 00:00:00'),$b['data_referencia']??($b['data'].' 00:00:00')) ?: ((int)$a['id']<=>(int)$b['id']));
        foreach($leituras as $l) {
            if((int)$l['odometro']<$saida) throw new \InvalidArgumentException('A leitura quebra a sequência cronológica do odômetro.');
            $this->qb->table('contratos_odometros')->where('id','=',(int)$l['id'])->update(['diferenca'=>(int)$l['odometro']-$saida]);
            $saida=(int)$l['odometro'];
        }
    }

    private function salvarCiclos(array $contrato,array $veiculo,array $previa): void
    {
        foreach ($previa['ciclos'] as $ciclo) {
            $atual=$this->qb->table('contratos_km_ciclos')->where('id_contrato_veiculo','=',(int)$veiculo['id'])->where('inicio','=',$ciclo['inicio'])->first();
            $dados=['fim'=>$ciclo['fim'],'contagem'=>$contrato['contagem'],'franquia'=>$ciclo['franquia'],
                'valor_km'=>$ciclo['valor_km'],'calculo_json'=>json_encode($ciclo,JSON_THROW_ON_ERROR)];
            if ($atual) $this->qb->table('contratos_km_ciclos')->where('id','=',(int)$atual['id'])->update($dados);
            else $this->qb->table('contratos_km_ciclos')->insert($dados+[
                'chave'=>$contrato['chave'],'id_contrato'=>$contrato['id'],'id_contrato_veiculo'=>$veiculo['id'],'inicio'=>$ciclo['inicio'],
            ]);
        }
    }

    private function validarFinanceiro(array $fin): void
    {
        $conta=(new ContaBancaria())->buscarPorId((int)($fin['id_conta']??0));
        $forma=(new FormaPagamento())->buscarPorId((int)($fin['id_forma_pagamento']??0));
        if(!$conta || $conta['status']!=='A' || !$forma || $forma['status']!=='A') throw new \InvalidArgumentException('Selecione conta e forma de pagamento ativas.');
        $data=(string)($fin['data_venci']??'');
        if(DateHelper::parse($data)!==$data || $data==='') throw new \InvalidArgumentException('Vencimento inválido.');
    }

    protected function faturar(array $c,array $v,int $leitura,array $previa,array $fin): array
    {
        $plano=(new PlanoDeContas())->buscarPorHierarquia('4.1.1.08');
        if(!$plano) throw new \RuntimeException('Plano de contas de quilometragem excedida não encontrado.');
        $filial=$this->qb->table('matrizes_filiais')->where('id','=',(int)$c['id_matriz_filial_retirada'])->lockForUpdate()->first();
        if(!$filial) throw new \InvalidArgumentException('Filial do contrato não encontrada.');
        $sequencia=(int)$filial['sequencia_financeiro']+1;
        $this->qb->table('matrizes_filiais')->where('id','=',(int)$filial['id'])->update(['sequencia_financeiro'=>$sequencia]);
        $id=(new Financeiro())->criar([
            'chave'=>$c['chave'],'sequencia'=>$sequencia,'codigo'=>$c['codigo'],'id_contrato'=>$c['id'],
            'id_veiculo'=>$v['id_veiculo'],'id_cliente'=>$c['id_cliente'],'id_matriz_filial'=>$filial['id'],
            'id_conta'=>$fin['id_conta'],'id_forma_pagamento'=>$fin['id_forma_pagamento'],
            'id_plano_de_conta'=>$plano['id'],'tipo'=>'R','pago'=>'N','parcela'=>1,'total_parcelas'=>1,
            'descricao'=>'Km excedente - Contrato '.$c['codigo'],'data_venci'=>$fin['data_venci'],
            'valor_subtotal'=>$previa['total'],'valor_total'=>$previa['total'],
        ]);
        foreach($previa['ciclos'] as $ciclo) {
            if($ciclo['saldo']<=0) continue;
            $existente=$this->qb->table('contratos_km_ciclos')->where('id_contrato_veiculo','=',(int)$v['id'])->where('inicio','=',$ciclo['inicio'])->first();
            $cicloId=$existente['id']??$this->qb->table('contratos_km_ciclos')->insert([
                'chave'=>$c['chave'],'id_contrato'=>$c['id'],'id_contrato_veiculo'=>$v['id'],
                'inicio'=>$ciclo['inicio'],'fim'=>$ciclo['fim'],'contagem'=>$c['contagem'],
                'franquia'=>$ciclo['franquia'],'valor_km'=>$ciclo['valor_km'],'calculo_json'=>json_encode($ciclo,JSON_THROW_ON_ERROR),
            ]);
            $item=(new FinanceiroItem())->criar(['chave'=>$c['chave'],'id_financeiro'=>$id,'id_veiculo'=>$v['id_veiculo'],
                'id_plano_de_conta'=>$plano['id'],'descricao'=>'Km excedente: '.$ciclo['inicio'].' a '.$ciclo['fim'].'; excedente '.$ciclo['excedente'].' km; já faturado '.number_format($ciclo['faturado'],2,'.',''),
                'valor'=>$ciclo['saldo']]);
            $this->qb->table('contratos_km_apuracoes')->insert(['chave'=>$c['chave'],'id_ciclo'=>$cicloId,
                'id_leitura'=>$leitura,'id_financeiro'=>$id,'id_financeiro_item'=>$item,
                'valor'=>$ciclo['saldo'],'calculo_json'=>json_encode($ciclo,JSON_THROW_ON_ERROR)]);
        }
        return ['id'=>$id,'sequencia'=>$sequencia,'valor_total'=>$previa['total'],'pago'=>'N'];
    }
}
