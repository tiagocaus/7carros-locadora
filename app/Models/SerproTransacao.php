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
     * Cria transacao de debito (consulta, evento ou indicacao)
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
                'confirmado_em' => now(),
                'created_at' => now(),
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
        ?string $pixQrcode = null,
        ?string $referencia = null
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
                'referencia' => $referencia,
                'external_id' => $externalId,
                'payment_method' => $paymentMethod,
                'payment_url' => $paymentUrl,
                'pix_code' => $pixCode,
                'pix_qrcode' => $pixQrcode,
                'status' => 'pendente',
                'created_at' => now(),
            ]);
    }

    /**
     * Confirma recarga (chamado por webhook)
     */
    public function confirmarRecarga(int $id, float $saldoAnterior, float $saldoPosterior, ?string $chave = null): int
    {
        if ($chave !== null) {
            $mysqli = $this->getMysqli();
            $stmt = $mysqli->prepare(
                "UPDATE serpro_transacoes
                 SET saldo_anterior = ?, saldo_posterior = ?, status = 'confirmado', confirmado_em = NOW()
                 WHERE id = ? AND chave = ?"
            );
            $stmt->bind_param('ddis', $saldoAnterior, $saldoPosterior, $id, $chave);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            return $affected;
        }

        $query = $this->qb
            ->table('serpro_transacoes')
            ->where('id', '=', $id);

        return $query->update([
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'status' => 'confirmado',
            'confirmado_em' => now(),
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
     * Busca recarga PIX por codigoSolicitacao ou txid do Banco Inter.
     */
    public function buscarRecargaPixPorIdentificador(string $identificador): ?array
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->withoutChave()
            ->where('tipo', '=', 'recarga_pix')
            ->whereNested(function ($q) use ($identificador) {
                $q->where('external_id', '=', $identificador)
                  ->orWhere('referencia', '=', $identificador);
            })
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
     * Bloqueia uma recarga para confirmacao idempotente dentro de transacao.
     */
    public function bloquearRecargaParaConfirmacao(int $id, ?string $chave = null): ?array
    {
        $mysqli = $this->getMysqli();
        $chaveBusca = $chave ?? ($_SESSION['chave'] ?? '');

        if ($chaveBusca === '') {
            throw new \RuntimeException('Chave do tenant obrigatoria para confirmar recarga');
        }

        $stmt = $mysqli->prepare(
            "SELECT * FROM serpro_transacoes WHERE id = ? AND chave = ? LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('is', $id, $chaveBusca);
        $stmt->execute();
        $result = $stmt->get_result();
        $registro = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $registro;
    }

    /**
     * Busca dados de PIX de uma recarga do tenant atual
     */
    public function buscarPixRecargaPorId(int $id): ?array
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->select([
                'id',
                'tipo',
                'valor_total',
                'external_id',
                'payment_method',
                'pix_code',
                'pix_qrcode',
                'status',
                'created_at',
            ])
            ->where('id', '=', $id)
            ->where('tipo', '=', 'recarga_pix')
            ->first();
    }

    /**
     * Lista recargas PIX pendentes para reconciliacao via CRON.
     *
     * Contexto cross-tenant documentado: o cron precisa localizar cobrancas de
     * todos os tenants para recuperar pagamentos que nao chegaram por webhook.
     */
    public function listarRecargasPixPendentesParaReconciliacao(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $mysqli = $this->getMysqli();

        $stmt = $mysqli->prepare(
            "SELECT id, chave, valor_total, external_id, referencia, status, created_at
             FROM serpro_transacoes
             WHERE tipo = 'recarga_pix'
               AND status = 'pendente'
               AND external_id IS NOT NULL
               AND external_id <> ''
             ORDER BY created_at ASC, id ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Atualiza os dados de PIX de uma recarga do tenant atual
     */
    public function atualizarDadosPix(int $id, string $pixCode, ?string $pixQrcode = null): int
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->where('id', '=', $id)
            ->where('tipo', '=', 'recarga_pix')
            ->update([
                'pix_code' => $pixCode,
                'pix_qrcode' => $pixQrcode,
            ]);
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
     * Marca debito do tenant atual como estornado.
     */
    public function marcarEstornado(int $id): int
    {
        return $this->qb
            ->table('serpro_transacoes')
            ->where('id', '=', $id)
            ->where('status', '=', 'confirmado')
            ->update([
                'status' => 'estornado',
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
                COUNT(CASE WHEN tipo = 'indicacao' AND status = 'confirmado' THEN 1 END) AS total_indicacoes,
                COALESCE(SUM(CASE WHEN tipo IN ('consulta', 'evento', 'indicacao') AND status = 'confirmado' THEN valor_total ELSE 0 END), 0) AS total_gasto,
                COALESCE(SUM(CASE WHEN tipo LIKE 'recarga_%' AND status = 'confirmado' THEN valor_total ELSE 0 END), 0) AS total_recarregado
            ")
            ->first();

        return $resultado ?: [
            'total_consultas' => 0,
            'total_eventos' => 0,
            'total_indicacoes' => 0,
            'total_gasto' => 0,
            'total_recarregado' => 0,
        ];
    }
}
