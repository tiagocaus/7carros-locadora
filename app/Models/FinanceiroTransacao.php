<?php

namespace App\Models;

use App\Core\Auth;

/**
 * Model para gerenciamento de transações financeiras com gateways de pagamento
 *
 * Registra todas as interações com gateways: cobranças, reembolsos, webhooks, etc.
 */
class FinanceiroTransacao extends Model
{
    /** Serializa operações Cora usando a conexão Singleton, sem segurar uma transação durante HTTP. */
    public function comBloqueioCora(string $chave, string $operation, callable $callback): mixed
    {
        if ($chave === '') throw new \InvalidArgumentException('Tenant obrigatório.');
        $db = $this->getMysqli();
        $name = 'cora:' . substr(hash('sha256', $chave . ':' . $operation), 0, 59);
        $stmt = $db->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $acquired = (int) ($stmt->get_result()->fetch_assoc()['acquired'] ?? 0);
        $stmt->close();
        if ($acquired !== 1) throw new \RuntimeException('Operação Cora em andamento. Tente novamente.');
        try { return $callback(); }
        finally {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function financeiroDisponivelParaCora(string $chave, int $financeiroId, float $valor): bool
    {
        $row = $this->qb->table('financeiro')->withChave($chave)
            ->where('id', '=', $financeiroId)->first();
        return $row && $row['pago'] === 'N' && abs((float) $row['valor_total'] - $valor) < 0.009;
    }

    /** Inclui pagas para impedir reemissão antes da conciliação financeira. */
    public function listarTentativasParaTrocaCora(string $chave, int $financeiroId): array
    {
        return $this->qb->table('financeiro_transacoes')->withChave($chave)
            ->where('id_financeiro', '=', $financeiroId)->where('type', '=', 'charge')
            ->whereRaw('(status IS NULL OR status NOT IN (?, ?, ?))', ['cancelled', 'failed', 'refunded'])
            ->orderBy('id', 'DESC')->get();
    }

    public function buscarTentativaCora(string $chave, int $gatewayId, int $financeiroId, string $fingerprint): ?array
    {
        $rows = $this->qb->table('financeiro_transacoes')->withChave($chave)
            ->where('gateway', '=', 'cora')->where('id_gateway', '=', $gatewayId)
            ->where('id_financeiro', '=', $financeiroId)->where('type', '=', 'charge')
            ->whereRaw('(status IS NULL OR status NOT IN (?, ?, ?))', ['cancelled', 'failed', 'refunded'])
            ->orderBy('id', 'DESC')->get();
        foreach ($rows as $row) {
            $meta = json_decode($row['payload'] ?? '{}', true);
            if (($meta['_cora_fingerprint'] ?? '') === $fingerprint) return $row;
        }
        return null;
    }

    public function temTentativaCoraAberta(string $chave, int $gatewayId, int $financeiroId): bool
    {
        return $this->qb->table('financeiro_transacoes')->withChave($chave)
            ->where('gateway', '=', 'cora')->where('id_gateway', '=', $gatewayId)
            ->where('id_financeiro', '=', $financeiroId)->where('type', '=', 'charge')
            ->whereRaw('(status IS NULL OR status NOT IN (?, ?, ?))', ['cancelled', 'failed', 'refunded'])->exists();
    }

    public function salvarRetornoCora(string $chave, int $id, array $result, array $payload): void
    {
        $this->qb->table('financeiro_transacoes')->withChave($chave)->where('id', '=', $id)
            ->where('gateway', '=', 'cora')->where('type', '=', 'charge')->update([
                'external_id' => $result['external_id'], 'status' => $result['status'],
                'payment_url' => $result['payment_url'], 'pix_code' => $result['pix_code'],
                'barcode' => $result['barcode'], 'expires_at' => $result['expires_at'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
    }

    /** Bootstrap público: só cobranças Cora; nenhum tenant é aceito do remetente. */
    public function buscarCobrancaCoraPorExternalId(string $externalId): ?array
    {
        $rows = $this->qb->table('financeiro_transacoes')->withoutChave()
            ->where('gateway', '=', 'cora')->where('type', '=', 'charge')
            ->where('external_id', '=', $externalId)->limit(2)->get();
        if (count($rows) > 1) throw new \RuntimeException('Identificador Cora ambíguo.');
        return $rows[0] ?? null;
    }

    /** Atualiza cobrança e financeiro atomicamente; callback usa os Models existentes. */
    public function aplicarWebhookCora(array $transaction, array $confirmed, array $event, callable $onPaid): bool
    {
        $db = $this->getMysqli();
        $chave = (string) $transaction['chave'];
        $db->begin_transaction();
        try {
            $row = $this->qb->table('financeiro_transacoes')->withChave($chave)
                ->where('id', '=', $transaction['id'])->where('gateway', '=', 'cora')
                ->where('type', '=', 'charge')->lockForUpdate()->first();
            if (!$row) throw new \RuntimeException('Cobrança Cora não encontrada.');
            $status = $confirmed['status'];
            if (!empty($row['webhook_received_at']) && ($row['status'] === 'paid' || $row['status'] === $status)) {
                $db->commit();
                return false;
            }
            // Polling/webhook atrasado não reabre uma cobrança já cancelada na troca.
            if ($row['status'] === 'cancelled' && $status !== 'paid') {
                $db->commit();
                return false;
            }
            if ($row['status'] === 'paid' && $status !== 'paid') {
                $db->commit();
                return false;
            }
            $this->qb->table('financeiro_transacoes')->withChave($chave)->where('id', '=', $row['id'])
                ->update(['status' => $status, 'paid_at' => $status === 'paid' ? ($confirmed['paid_at'] ?? now()) : null,
                    'webhook_received_at' => now(), 'updated_at' => now()]);
            if ($status === 'paid') $onPaid($row);
            $this->qb->table('financeiro_transacoes')->withChave($chave)->insert([
                'chave' => $chave, 'id_gateway' => $row['id_gateway'], 'id_financeiro' => $row['id_financeiro'],
                'gateway' => 'cora', 'external_id' => $row['external_id'], 'type' => 'webhook',
                'status' => 'invoice:' . $status, 'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            ]);
            $db->commit();
            return true;
        } catch (\Throwable $e) { $db->rollback(); throw $e; }
    }

    /**
     * Cria nova transação
     *
     * @param array<string, mixed> $dados
     * @return int ID da transação criada
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->insert([
                'chave' => $dados['chave'] ?? Auth::chave(),
                'id_financeiro' => $dados['id_financeiro'] ?? null,
                'id_gateway' => $dados['id_gateway'] ?? null,
                'gateway' => $dados['gateway'],
                'external_id' => $dados['external_id'] ?? null,
                'type' => $dados['type'], // charge, refund, webhook, callback
                'payment_method' => $dados['payment_method'] ?? null,
                'status' => $dados['status'] ?? null,
                'amount' => $dados['amount'] ?? null,
                'fee' => $dados['fee'] ?? null,
                'net_amount' => $dados['net_amount'] ?? null,
                'payment_url' => $dados['payment_url'] ?? null,
                'pix_code' => $dados['pix_code'] ?? null,
                'barcode' => $dados['barcode'] ?? null,
                'expires_at' => $dados['expires_at'] ?? null,
                'payload' => $dados['payload'] ?? null,
            ]);
    }

    /**
     * Busca transação por ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca transação por external_id
     *
     * @param string $externalId
     * @return array<string, mixed>|null
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->withoutChave()
            ->where('external_id', '=', $externalId)
            ->first();
    }

    /**
     * Busca transações por ID do financeiro
     *
     * @param int $idFinanceiro
     * @return array<int, array<string, mixed>>
     */
    public function buscarPorFinanceiro(int $idFinanceiro): array
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Lista cobrancas abertas vinculadas ao lancamento.
     *
     * @param int $idFinanceiro
     * @return array<int, array<string, mixed>>
     */
    public function listarCobrancasAbertasPorFinanceiro(int $idFinanceiro): array
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->where('type', '=', 'charge')
            ->whereRaw('(status IS NULL OR status NOT IN (?, ?, ?, ?, ?))', ['paid', 'cancelled', 'refunded', 'failed', 'expired'])
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Atualiza status de uma transacao pelo ID interno.
     *
     * @param int $id
     * @param string $status
     * @param string|null $paidAt
     * @param string|null $refundedAt
     * @return int
     */
    public function atualizarStatusPorId(
        int $id,
        string $status,
        ?string $paidAt = null,
        ?string $refundedAt = null
    ): int {
        $dados = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($paidAt !== null) {
            $dados['paid_at'] = $paidAt;
        }
        if ($refundedAt !== null) {
            $dados['refunded_at'] = $refundedAt;
        }

        return $this->qb
            ->table('financeiro_transacoes')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Atualiza status por external_id
     *
     * @param string $externalId
     * @param string $status
     * @param string|null $paidAt
     * @param string|null $refundedAt
     * @return int
     */
    public function atualizarPorExternalId(
        string $externalId,
        string $status,
        ?string $paidAt = null,
        ?string $refundedAt = null
    ): int {
        $dados = ['status' => $status];

        if ($paidAt !== null) {
            $dados['paid_at'] = $paidAt;
        }
        if ($refundedAt !== null) {
            $dados['refunded_at'] = $refundedAt;
        }

        return $this->qb
            ->table('financeiro_transacoes')
            ->withoutChave()
            ->where('external_id', '=', $externalId)
            ->where('type', '!=', 'webhook')
            ->update($dados);
    }

    /**
     * Registra recebimento de webhook
     *
     * @param string $chave
     * @param string $gateway
     * @param string $externalId
     * @param string $event
     * @param string $status
     * @param array<string, mixed> $payload
     * @return int
     */
    public function registrarWebhook(
        string $chave,
        string $gateway,
        string $externalId,
        string $event,
        string $status,
        array $payload
    ): int {
        // Atualizar webhook_received_at na transação original
        $this->qb
            ->table('financeiro_transacoes')
            ->withoutChave()
            ->where('external_id', '=', $externalId)
            ->where('type', '!=', 'webhook')
            ->update(['webhook_received_at' => now()]);

        // Criar registro do webhook
        return $this->qb
            ->table('financeiro_transacoes')
            ->withoutChave()
            ->insert([
                'chave' => $chave,
                'gateway' => $gateway,
                'external_id' => $externalId,
                'type' => 'webhook',
                'status' => "{$event}:{$status}",
                'payload' => json_encode($payload),
            ]);
    }

    /**
     * Verifica se webhook já foi processado (idempotência)
     *
     * @param string $externalId
     * @param string $event
     * @return bool
     */
    public function webhookJaProcessado(string $externalId, string $event): bool
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->withoutChave()
            ->where('external_id', '=', $externalId)
            ->where('type', '=', 'webhook')
            ->where('status', 'LIKE', "{$event}:%")
            ->exists();
    }

    /**
     * Lista transações com paginação
     *
     * @param int $page
     * @param int $perPage
     * @param string $search
     * @param string $gateway
     * @param string $type
     * @return array<int, array<string, mixed>>
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        string $gateway = '',
        string $type = ''
    ): array {
        $offset = ($page - 1) * $perPage;

        $query = $this->qb
            ->table('financeiro_transacoes', 'ft')
            ->select([
                'ft.*',
                'gp.nome AS gateway_nome',
                'f.descricao AS financeiro_descricao',
            ])
            ->leftJoin('gateways_pagamento', 'gp', 'ft.id_gateway', '=', 'gp.id')
            ->leftJoin('financeiro', 'f', 'ft.id_financeiro', '=', 'f.id')
            ->orderBy('ft.created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ft.external_id', 'LIKE', "%{$search}%")
                  ->orWhere('f.descricao', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($gateway)) {
            $query->where('ft.gateway', '=', $gateway);
        }

        if (!empty($type)) {
            $query->where('ft.type', '=', $type);
        }

        return $query->get();
    }

    /**
     * Conta transações
     *
     * @param string $search
     * @param string $gateway
     * @param string $type
     * @return int
     */
    public function contar(string $search = '', string $gateway = '', string $type = ''): int
    {
        $query = $this->qb
            ->table('financeiro_transacoes', 'ft')
            ->selectRaw('COUNT(*) as total')
            ->leftJoin('financeiro', 'f', 'ft.id_financeiro', '=', 'f.id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ft.external_id', 'LIKE', "%{$search}%")
                  ->orWhere('f.descricao', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($gateway)) {
            $query->where('ft.gateway', '=', $gateway);
        }

        if (!empty($type)) {
            $query->where('ft.type', '=', $type);
        }

        $result = $query->first();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Busca última transação de cobrança para um financeiro
     *
     * @param int $idFinanceiro
     * @return array<string, mixed>|null
     */
    public function buscarUltimaCobranca(int $idFinanceiro): ?array
    {
        return $this->qb
            ->table('financeiro_transacoes')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->where('type', '=', 'charge')
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    /**
     * Estatísticas de transações por período
     *
     * @param string $dataInicio
     * @param string $dataFim
     * @return array<string, mixed>
     */
    public function estatisticas(string $dataInicio, string $dataFim): array
    {
        $result = $this->qb
            ->table('financeiro_transacoes')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN type = 'charge' THEN 1 ELSE 0 END) as total_charges,
                SUM(CASE WHEN type = 'refund' THEN 1 ELSE 0 END) as total_refunds,
                SUM(CASE WHEN status IN ('paid', 'RECEIVED', 'CONFIRMED') THEN amount ELSE 0 END) as valor_pago,
                SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as valor_reembolsado
            ")
            ->whereRaw("DATE(created_at) >= ?", [$dataInicio])
            ->whereRaw("DATE(created_at) <= ?", [$dataFim])
            ->first();

        return $result ?: [
            'total' => 0,
            'total_charges' => 0,
            'total_refunds' => 0,
            'valor_pago' => 0,
            'valor_reembolsado' => 0,
        ];
    }
}
