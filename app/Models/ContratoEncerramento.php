<?php

namespace App\Models;

class ContratoEncerramento extends Model
{
    public function iniciarTransacao(): void
    {
        $this->qb->beginTransaction();
    }

    public function confirmarTransacao(): void
    {
        $this->qb->commit();
    }

    public function reverterTransacao(): void
    {
        $this->qb->rollback();
    }

    public function buscarPorContrato(int $contratoId): ?array
    {
        return $this->qb
            ->table('contratos_encerramentos')
            ->where('id_contrato', '=', $contratoId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Retorna o snapshot normalizado usado por telas e documentos de contratos
     * finalizados. O snapshot e a fonte de verdade do encerramento.
     */
    public function buscarDetalhadoPorContrato(int $contratoId): ?array
    {
        $encerramento = $this->buscarPorContrato($contratoId);
        if (!$encerramento) {
            return null;
        }

        $calculo = json_decode((string) ($encerramento['calculo_json'] ?? ''), true);
        $encerramento['calculo'] = is_array($calculo) ? $calculo : [];

        $idAjuste = (int) ($encerramento['id_financeiro_ajuste'] ?? 0);
        $encerramento['ajuste_financeiro_existe'] = $idAjuste > 0
            ? $this->qb->table('financeiro')->where('id', '=', $idAjuste)->exists()
            : false;

        unset($encerramento['calculo_json']);

        return $encerramento;
    }

    public function buscarPorAjusteFinanceiro(int $idFinanceiro): ?array
    {
        if ($idFinanceiro < 1) {
            return null;
        }

        return $this->qb
            ->table('contratos_encerramentos')
            ->where('id_financeiro_ajuste', '=', $idFinanceiro)
            ->orderByDesc('id')
            ->first();
    }

    public function ehAjusteFinanceiroProtegido(int $contratoId, int $idFinanceiro): bool
    {
        $encerramento = $this->buscarPorAjusteFinanceiro($idFinanceiro);

        return $encerramento !== null
            && (int) ($encerramento['id_contrato'] ?? 0) === $contratoId;
    }

    public function bloquearContrato(int $contratoId): ?array
    {
        return $this->qb
            ->table('contratos')
            ->select(['id', 'status'])
            ->where('id', '=', $contratoId)
            ->lockForUpdate()
            ->first();
    }

    public function criar(array $dados): int
    {
        return $this->qb
            ->table('contratos_encerramentos')
            ->insert($dados);
    }

    /**
     * Principal contratual já lançado. Taxas do meio de pagamento, juros,
     * multa e caução não participam da conciliação do encerramento.
     */
    public function calcularPrincipalLancado(int $contratoId): float
    {
        $resultado = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("COALESCE(SUM(CASE
                WHEN f.tipo = 'R' THEN COALESCE(f.valor_subtotal, 0)
                WHEN f.tipo = 'D' AND pc.hierarquia = '3.4.1.23' THEN -COALESCE(f.valor_subtotal, 0)
                ELSE 0 END), 0) AS total")
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->where('f.id_contrato', '=', $contratoId)
            ->whereNull('f.id_multa')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM contratos_caucoes cc WHERE cc.chave = f.chave AND (cc.id_financeiro_entrada = f.id OR cc.id_financeiro_devolucao = f.id))')
            ->first();

        return round((float) ($resultado['total'] ?? 0), 2);
    }
}
