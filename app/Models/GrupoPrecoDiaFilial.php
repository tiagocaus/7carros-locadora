<?php

namespace App\Models;

/**
 * Model GrupoPrecoDiaFilial
 *
 * Escala progressiva de precos por faixa de dias, agora vinculada a filial.
 * Cada filial tem sua propria tabela de descontos progressivos por grupo,
 * permitindo precos diferentes entre filiais/moedas.
 *
 * Estrutura identica ao GrupoPrecoDia + id_matriz_filial.
 */
class GrupoPrecoDiaFilial extends Model
{
    /**
     * Tipos de plano — preserva compatibilidade com schema legado
     * (enum 'diaria','km_controlado','km_livre').
     */
    public const TIPOS_PLANO = ['diaria', 'km_controlado', 'km_livre'];

    /**
     * Lista todas as faixas de um grupo em uma filial agrupadas por tipo_plano
     */
    public function listarPorGrupoFilial(int $grupoId, int $filialId): array
    {
        $faixas = $this->qb
            ->table('grupos_precos_dias_filiais')
            ->withoutChave()
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->orderBy('tipo_plano', 'ASC')
            ->orderBy('dia_inicio', 'ASC')
            ->get();

        $agrupado = [];
        foreach (self::TIPOS_PLANO as $t) {
            $agrupado[$t] = [];
        }

        foreach ($faixas as $f) {
            $tipo = $f['tipo_plano'];
            if (!isset($agrupado[$tipo])) {
                $agrupado[$tipo] = [];
            }
            $agrupado[$tipo][] = [
                'id' => (int) $f['id'],
                'dia_inicio' => (int) $f['dia_inicio'],
                'dia_fim' => $f['dia_fim'] !== null ? (int) $f['dia_fim'] : null,
                'valor' => (float) $f['valor'],
            ];
        }

        return $agrupado;
    }

    /**
     * Lista todas as faixas progressivas de uma filial, agrupadas por grupo e tipo.
     *
     * @return array<int, array<string, array<int, array<string, int|float|null>>>>
     */
    public function listarPorFilialAgrupado(int $filialId): array
    {
        $faixas = $this->qb
            ->table('grupos_precos_dias_filiais')
            ->select(['id_grupo', 'tipo_plano', 'dia_inicio', 'dia_fim', 'valor'])
            ->where('id_matriz_filial', '=', $filialId)
            ->orderBy('id_grupo', 'ASC')
            ->orderBy('tipo_plano', 'ASC')
            ->orderBy('dia_inicio', 'ASC')
            ->get();

        $agrupado = [];
        foreach ($faixas as $f) {
            $idGrupo = (int) $f['id_grupo'];
            $tipo = (string) $f['tipo_plano'];
            if (!isset($agrupado[$idGrupo])) {
                $agrupado[$idGrupo] = [];
                foreach (self::TIPOS_PLANO as $t) {
                    $agrupado[$idGrupo][$t] = [];
                }
            }
            if (!isset($agrupado[$idGrupo][$tipo])) {
                $agrupado[$idGrupo][$tipo] = [];
            }
            $agrupado[$idGrupo][$tipo][] = [
                'dia_inicio' => (int) $f['dia_inicio'],
                'dia_fim' => $f['dia_fim'] !== null ? (int) $f['dia_fim'] : null,
                'valor' => (float) $f['valor'],
            ];
        }

        return $agrupado;
    }

    /**
     * Lista faixas de um grupo+filial+tipo especifico
     */
    public function listarPorGrupoFilialTipo(int $grupoId, int $filialId, string $tipoPlano): array
    {
        if (!in_array($tipoPlano, self::TIPOS_PLANO, true)) {
            throw new \InvalidArgumentException('Tipo de plano invalido');
        }

        return $this->qb
            ->table('grupos_precos_dias_filiais')
            ->withoutChave()
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->where('tipo_plano', '=', $tipoPlano)
            ->orderBy('dia_inicio', 'ASC')
            ->get();
    }

    /**
     * Substitui as faixas de um grupo+filial+tipo (delete+insert atomico)
     *
     * @param array $faixas [['dia_inicio'=>X,'dia_fim'=>Y,'valor'=>Z], ...]
     */
    public function salvarFaixas(int $grupoId, int $filialId, string $chave, string $tipoPlano, array $faixas): int
    {
        if (!in_array($tipoPlano, self::TIPOS_PLANO, true)) {
            throw new \InvalidArgumentException('Tipo de plano invalido');
        }

        $faixasValidas = [];
        foreach ($faixas as $faixa) {
            $diaInicio = isset($faixa['dia_inicio']) && is_numeric($faixa['dia_inicio']) ? (int) $faixa['dia_inicio'] : null;
            $valor = isset($faixa['valor']) ? currency_parse($faixa['valor']) : null;

            if ($diaInicio === null || $diaInicio < 1 || $valor === null || $valor <= 0) {
                continue;
            }

            $diaFim = isset($faixa['dia_fim']) && is_numeric($faixa['dia_fim']) && $faixa['dia_fim'] !== ''
                ? (int) $faixa['dia_fim']
                : null;

            if ($diaFim !== null && $diaFim < $diaInicio) {
                throw new \InvalidArgumentException("Dia fim ({$diaFim}) deve ser >= dia inicio ({$diaInicio})");
            }

            $faixasValidas[] = compact('diaInicio', 'diaFim', 'valor');
        }

        $this->validarSobreposicao($faixasValidas);

        // Remove as existentes desse grupo+filial+tipo
        $this->qb
            ->table('grupos_precos_dias_filiais')
            ->withoutChave()
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->where('tipo_plano', '=', $tipoPlano)
            ->delete();

        $count = 0;
        foreach ($faixasValidas as $f) {
            $this->qb
                ->table('grupos_precos_dias_filiais')
                ->insert([
                    'chave' => $chave,
                    'id_grupo' => $grupoId,
                    'id_matriz_filial' => $filialId,
                    'tipo_plano' => $tipoPlano,
                    'dia_inicio' => $f['diaInicio'],
                    'dia_fim' => $f['diaFim'],
                    'valor' => $f['valor'],
                ]);
            $count++;
        }

        return $count;
    }

    /**
     * Salva todas as faixas de um grupo+filial (todos os 3 tipos de plano)
     *
     * @param array $dados ['diaria'=>[...], 'km_controlado'=>[...], 'km_livre'=>[...]]
     */
    public function salvarTodos(int $grupoId, int $filialId, string $chave, array $dados): array
    {
        $resultado = [];
        foreach (self::TIPOS_PLANO as $tipo) {
            $resultado[$tipo] = $this->salvarFaixas($grupoId, $filialId, $chave, $tipo, $dados[$tipo] ?? []);
        }
        return $resultado;
    }

    /**
     * Calcula o valor aplicado para um grupo+filial+tipo baseado na quantidade de dias.
     * Retorna null se nao houver faixa correspondente.
     */
    public function calcularValor(int $grupoId, int $filialId, string $tipoPlano, int $quantidadeDias): ?float
    {
        if (!in_array($tipoPlano, self::TIPOS_PLANO, true)) {
            return null;
        }

        $result = $this->qb
            ->table('grupos_precos_dias_filiais')
            ->select(['valor'])
            ->withoutChave()
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->where('tipo_plano', '=', $tipoPlano)
            ->where('dia_inicio', '<=', $quantidadeDias)
            ->whereNested(function ($q) use ($quantidadeDias) {
                $q->whereNull('dia_fim')
                  ->orWhere('dia_fim', '>=', $quantidadeDias);
            })
            ->orderByDesc('dia_inicio')
            ->first();

        return $result ? (float) $result['valor'] : null;
    }

    /**
     * Remove todas as faixas de um grupo em uma filial
     */
    public function excluirPorGrupoFilial(int $grupoId, int $filialId): int
    {
        return $this->qb
            ->table('grupos_precos_dias_filiais')
            ->withoutChave()
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->delete();
    }

    private function validarSobreposicao(array $faixas): void
    {
        if (count($faixas) < 2) {
            return;
        }
        usort($faixas, fn($a, $b) => $a['diaInicio'] <=> $b['diaInicio']);
        for ($i = 0; $i < count($faixas) - 1; $i++) {
            $fimAtual = $faixas[$i]['diaFim'] ?? PHP_INT_MAX;
            if ($fimAtual >= $faixas[$i + 1]['diaInicio']) {
                throw new \InvalidArgumentException(
                    "Faixas sobrepostas: {$faixas[$i]['diaInicio']}-" . ($faixas[$i]['diaFim'] ?? 'inf') .
                    " vs {$faixas[$i + 1]['diaInicio']}-" . ($faixas[$i + 1]['diaFim'] ?? 'inf')
                );
            }
        }
    }

}
