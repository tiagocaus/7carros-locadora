<?php

namespace App\Models;

/**
 * Model SerproTransacao
 *
 * Historico completo de transacoes de saldo SERPRO (recargas e debitos).
 * Armazena valores SERPRO (interno) e valores com markup (visivel ao tenant).
 */
class SerproTransacao extends Model
{
    /**
     * Lista transacoes paginadas do tenant
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $filtroTipo = '',
        ?string $dataInicio = null,
        ?string $dataFim = null
    ): array {
        $query = $this->qb
            ->table('serpro_transacoes', 'st')
            ->select([
                'st.id',
                'st.tipo',
                'st.valor_total',
                'st.saldo_anterior',
                'st.saldo_posterior',
                'st.descricao',
                'st.referencia',
                'st.payment_method',
                'st.status',
                'st.confirmado_em',
                'st.created_at',
            ]);

        if (!empty($filtroTipo)) {
            $query->where('st.tipo', '=', $filtroTipo);
        }

        if (!empty($dataInicio)) {
            $query->whereRaw('DATE(st.created_at) >= ?', [$dataInicio]);
        }

        if (!empty($dataFim)) {
            $query->whereRaw('DATE(st.created_at) <= ?', [$dataFim]);
        }

        return $query
            ->orderByDesc('st.created_at')
            ->orderByDesc('st.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de transacoes com filtros
     */
    public function contar(
        string $filtroTipo = '',
        ?string $dataInicio = null,
        ?string $dataFim = null
    ): int {
        $query = $this->qb
            ->table('serpro_transacoes', 'st');

        if (!empty($filtroTipo)) {
            $query->where('st.tipo', '=', $filtroTipo);
        }

        if (!empty($dataInicio)) {
            $query->whereRaw('DATE(st.created_at) >= ?', [$dataInicio]);
        }

        if (!empty($dataFim)) {
            $query->whereRaw('DATE(st.created_at) <= ?', [$dataFim]);
        }

        return $query->count();
    }

    /**
     * Cria transacao de debito (consulta ou evento)
     */
    public function criarDebito(
        string $tipo,
        float $valorSerpro,
        float $valorMarkup,
        float $valorTotal,
        float $saldoAnterior,
        float $saldoPosterior,
        string $descricao,
        ?string $referencia = null
    ): int {
        return $this->qb
            ->table('serpro_transacoes')
            ->insert([
                'chave' => $_SESSION['chave'],
                'tipo' => $tipo,
                'valor_serpro' => $valorSerpro,
                'valor_markup' => $valorMarkup,
                'valor_total' => $valorTotal,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'descricao' => $descricao,
                'referencia' => $referencia,
                'status' => 'confirmado',
                'confirmado_em' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Cria transacao de recarga (pendente, confirmada por webhook)
     */
    public function criarRecarga(
        string $tipo,
        float $valor,
        string $descricao,
        ?string $externalId = null,
        ?string $paymentMethod = null,
        ?string $paymentUrl = null,
        ?string $pixCode = null,
        ?string $pixQrcode = null
    ): int {
        $saldo = (new SerproSaldo())->getSaldo();

        return $this->qb
            ->table('serpro_transacoes')
            ->insert([
                'chave' => $_SESSION['chave'],
                'tipo' => $tipo,
                'valor_serpro' => 0,
                'valor_markup' => 0,
                'valor_total' => $valor,
                'saldo_anterior' => $saldo,
                'saldo_posterior' => $saldo,
                'descricao' => $descricao,
                'external_id' => $externalId,
                'payment_method' => $paymentMethod,
                'payment_url' => $paymentUrl,
                'pix_code' => $pixCode,
                'pix_qrcode' => $pixQrcode,
                'status' => 'pendente',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Confirma recarga (chamado por webhook)
     */
    public function confirmarRecarga(int $id, float $saldoAnterior, float $saldoPosterior): int
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'status' => 'confirmado',
                'confirmado_em' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Busca transacao por external_id (para idempotencia de webhook)
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->withoutChave()
            ->where('external_id', '=', $externalId)
            ->first();
    }

    /**
     * Busca transacao por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Marca transacao como falha
     */
    public function marcarFalha(int $id): int
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'status' => 'falha',
            ]);
    }

    /**
     * Resumo de gastos do tenant (para dashboard)
     */
    public function resumoGastos(): array
    {
        $resultado = $this->qb
            ->table('serpro_transacoes')
            ->selectRaw("
                COUNT(CASE WHEN tipo = 'consulta' AND status = 'confirmado' THEN 1 END) AS total_consultas,
                COUNT(CASE WHEN tipo = 'evento' AND status = 'confirmado' THEN 1 END) AS total_eventos,
                COALESCE(SUM(CASE WHEN tipo IN ('consulta', 'evento') AND status = 'confirmado' THEN valor_total ELSE 0 END), 0) AS total_gasto,
                COALESCE(SUM(CASE WHEN tipo LIKE 'recarga_%' AND status = 'confirmado' THEN valor_total ELSE 0 END), 0) AS total_recarregado
            ")
            ->first();

        return $resultado ?: [
            'total_consultas' => 0,
            'total_eventos' => 0,
            'total_gasto' => 0,
            'total_recarregado' => 0,
        ];
    }
}
