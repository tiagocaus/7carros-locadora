<?php
namespace App\Services;

use App\Helpers\DateHelper;

/** Calculador puro: nunca atribui km a um ciclo sem uma fronteira real. */
final class ContratoKmCalculo
{
    /** O encerramento final já deduz antecipações no principal; parcial precisa deduzi-las aqui. */
    public function conciliarDevolucao(array $calculo, array $antecipados): array
    {
        $calculo['km_antecipado']=0.0;
        foreach ($calculo['veiculos'] as &$veiculo) {
            $valor=round((float)($antecipados[$veiculo['id_contrato_veiculo']]??0),2);
            $veiculo['km_antecipado']=$valor;
            $calculo['km_antecipado'] += $valor;
        }
        unset($veiculo);
        if (!$calculo['encerramento_final'] && $calculo['km_antecipado']>0) {
            $calculo['diferenca']=round($calculo['diferenca']-$calculo['km_antecipado'],2);
            $calculo['ajuste_tipo']=$calculo['diferenca']>0.009?'R':($calculo['diferenca'] < -0.009?'D':'N');
            $calculo['ajuste_valor']=abs($calculo['diferenca']);
        }
        return $calculo;
    }

    public function fronteira(string $saida, string $contagem, int $numero): string
    {
        $data = DateHelper::parseOperationalDateTime($saida);
        if (!in_array($contagem, ['dia','semana','mes','ano'], true)) {
            throw new \InvalidArgumentException('Contagem inválida.');
        }
        if (in_array($contagem, ['mes','ano'], true)) {
            return DateHelper::addOperationalCalendarPeriods($data, $contagem, $numero)->format('Y-m-d H:i:s');
        }
        return $data->modify('+' . ($numero * ($contagem === 'semana' ? 7 : 1)) . ' days')->format('Y-m-d H:i:s');
    }

    public function calcular(array $contrato, array $veiculo, array $leituras, array $faturados, string $referencia): array
    {
        $resultado = ['ciclos'=>[], 'pendencias'=>[], 'total'=>0.0];
        if (($veiculo['plano'] ?? '') !== 'KMC') return $resultado;
        $saida = $veiculo['data_saida'];
        $contagem = $contrato['contagem'];
        $fronteiras = [$saida => (int)$veiculo['odometro_saida']];
        foreach ($leituras as $leitura) {
            if (!empty($leitura['data_referencia'])) {
                $fronteiras[$leitura['data_referencia']] = (int)$leitura['odometro'];
            }
        }
        for ($n=0; $n<10000; $n++) {
            $inicio=$this->fronteira($saida,$contagem,$n);
            if ($inicio >= $referencia) break;
            $fim=$this->fronteira($saida,$contagem,$n+1);
            $ate=min($fim,$referencia);
            $fechado=$fim <= $referencia;
            $odoFim=$fechado ? ($fronteiras[$fim]??null) : null;
            if (!$fechado) {
                foreach ($leituras as $l) {
                    // Leituras antigas sem horário não são fronteiras; servem apenas dentro do ciclo.
                    $data=$l['data_referencia'] ?? ($l['data'].' 00:00:00');
                    if ($data >= $inicio && $data <= $referencia) $odoFim=(int)$l['odometro'];
                }
            }
            foreach ([$inicio => $fronteiras[$inicio]??null, $fim => $fechado ? $odoFim : 0] as $data=>$odo) {
                if ($odo===null) $resultado['pendencias'][$data]=$data;
            }
            $odoInicio=$fronteiras[$inicio]??null;
            $pendente=$odoInicio===null || $odoFim===null;
            $rodados=$pendente ? null : max(0,$odoFim-$odoInicio);
            $franquia=(int)($veiculo['km_franquia']??0);
            $preco=(float)($veiculo['valor_km_excedente']??0);
            $excedente=$pendente ? 0 : max(0,$rodados-$franquia);
            $valor=round($excedente*$preco,2);
            $faturado=round((float)($faturados[$inicio]??0),2);
            $saldo=$pendente ? 0 : max(0,round($valor-$faturado,2));
            $resultado['ciclos'][]=['inicio'=>$inicio,'fim'=>$fim,'ate'=>$ate,'pendente'=>$pendente,
                'odometro_inicio'=>$odoInicio,'odometro_fim'=>$odoFim,'rodados'=>$rodados,
                'franquia'=>$franquia,'valor_km'=>$preco,'excedente'=>$excedente,
                'valor'=>$valor,'faturado'=>$faturado,'saldo'=>$saldo];
            $resultado['total']=round($resultado['total']+$saldo,2);
        }
        if ($n===10000) throw new \InvalidArgumentException('Período excede o limite de apuração.');
        $resultado['pendencias']=array_values($resultado['pendencias']);
        return $resultado;
    }
}
