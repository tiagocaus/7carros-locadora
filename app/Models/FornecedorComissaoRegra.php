<?php

namespace App\Models;

/**
 * Regras de comissao especificas para fornecedores investidores.
 */
class FornecedorComissaoRegra extends Model
{
    private const TIPOS_VALIDOS = [
        'percentual_locadora',
        'fixo_locadora',
        'fixo_locadora_mensal',
        'fixo_investidor_mensal',
    ];

    public function listarPorFornecedor(int $idFornecedor, string $chave): array
    {
        return $this->qb
            ->table('fornecedores_comissoes_regras', 'r')
            ->select([
                'r.*',
                'g.nome AS grupo_nome',
            ])
            ->withChave($chave)
            ->leftJoinRaw('grupos', 'g', 'g.id = r.id_grupo AND g.chave = r.chave')
            ->where('r.id_fornecedor', '=', $idFornecedor)
            ->orderByRaw('CASE WHEN r.id_grupo IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('g.nome', 'ASC')
            ->get();
    }

    public function buscarAplicavel(string $chave, int $idFornecedor, ?int $idGrupo): ?array
    {
        if ($idGrupo) {
            $regraGrupo = $this->qb
                ->table('fornecedores_comissoes_regras')
                ->withChave($chave)
                ->where('id_fornecedor', '=', $idFornecedor)
                ->where('id_grupo', '=', $idGrupo)
                ->where('ativo', '=', 1)
                ->orderByDesc('id')
                ->first();

            if ($regraGrupo) {
                $regraGrupo['origem_regra'] = 'fornecedor_grupo';
                return $regraGrupo;
            }
        }

        $regraPadrao = $this->qb
            ->table('fornecedores_comissoes_regras')
            ->withChave($chave)
            ->where('id_fornecedor', '=', $idFornecedor)
            ->whereNull('id_grupo')
            ->where('ativo', '=', 1)
            ->orderByDesc('id')
            ->first();

        if ($regraPadrao) {
            $regraPadrao['origem_regra'] = 'fornecedor_padrao';
            return $regraPadrao;
        }

        return null;
    }

    public function salvarParaFornecedor(int $idFornecedor, string $chave, array $regras): void
    {
        $this->qb
            ->table('fornecedores_comissoes_regras')
            ->withChave($chave)
            ->where('id_fornecedor', '=', $idFornecedor)
            ->delete();

        foreach ($this->normalizarRegras($regras) as $regra) {
            $this->qb
                ->table('fornecedores_comissoes_regras')
                ->insert([
                    'chave' => $chave,
                    'id_fornecedor' => $idFornecedor,
                    'id_grupo' => $regra['id_grupo'],
                    'comissao_tipo' => $regra['comissao_tipo'],
                    'comissao_valor' => $regra['comissao_valor'],
                    'ativo' => $regra['ativo'],
                    'updated_at' => now(),
                ]);
        }
    }

    private function normalizarRegras(array $regras): array
    {
        $normalizadas = [];
        $vistos = [];

        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $tipo = (string) ($regra['comissao_tipo'] ?? '');
            if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
                continue;
            }

            $idGrupo = isset($regra['id_grupo']) && $regra['id_grupo'] !== ''
                ? (int) $regra['id_grupo']
                : null;
            $chaveRegra = $idGrupo ? 'grupo_' . $idGrupo : 'padrao';

            if (isset($vistos[$chaveRegra])) {
                continue;
            }

            $normalizadas[] = [
                'id_grupo' => $idGrupo,
                'comissao_tipo' => $tipo,
                'comissao_valor' => currency_parse($regra['comissao_valor'] ?? 0),
                'ativo' => isset($regra['ativo']) ? (int) $regra['ativo'] : 1,
            ];
            $vistos[$chaveRegra] = true;
        }

        return $normalizadas;
    }
}
