<?php

namespace App\Models;

use App\Helpers\DateHelper;

/**
 * Acesso tenant-scoped aos lancamentos de taxas de meios de pagamento.
 */
class FinanceiroTaxa extends Model
{
    private static ?bool $schemaDisponivel = null;

    public static function schemaDisponivel(): bool
    {
        if (self::$schemaDisponivel !== null) {
            return self::$schemaDisponivel;
        }

        $mysqli = self::sharedMysqli();
        $financeiro = $mysqli->query("SHOW COLUMNS FROM financeiro LIKE 'id_financeiro_taxa_origem'");
        $formas = $mysqli->query("SHOW COLUMNS FROM formas_pagamento LIKE 'id_plano_de_conta_taxa'");

        return self::$schemaDisponivel = $financeiro->num_rows > 0 && $formas->num_rows > 0;
    }

    public function buscarReceita(int $id): ?array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.*',
                'fp.nome AS forma_pagamento_nome',
                'fp.id_plano_de_conta_taxa',
            ])
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->where('f.id', '=', $id)
            ->first();
    }

    public function buscarDespesaVinculada(int $idReceita): ?array
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_financeiro_taxa_origem', '=', $idReceita)
            ->first();
    }

    public function atualizarDespesa(int $id, array $dados): int
    {
        $dados['updated_at'] = DateHelper::systemNow();

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $id)
            ->where('id_financeiro_taxa_origem', 'IS NOT', null)
            ->update($dados);
    }

    public function excluirDespesaPorReceita(int $idReceita): int
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_financeiro_taxa_origem', '=', $idReceita)
            ->delete();
    }

    public function atualizarOrigem(int $id, array $dados): int
    {
        $dados['updated_at'] = DateHelper::systemNow();

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $id)
            ->where('tipo', '=', 'R')
            ->update($dados);
    }

    /** @return array<int, array<string, mixed>> */
    public function listarReceitasParaRetroativo(): array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select(['f.id'])
            ->leftJoinRaw(
                'financeiro',
                'ft',
                'ft.id_financeiro_taxa_origem = f.id AND ft.chave = f.chave'
            )
            ->where('f.tipo', '=', 'R')
            ->where('f.pago', '=', 'S')
            ->where('f.valor_taxa', '>', 0)
            ->whereNull('ft.id')
            ->orderBy('f.id', 'ASC')
            ->get();
    }
}
