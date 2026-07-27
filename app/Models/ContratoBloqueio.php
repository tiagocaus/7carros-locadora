<?php

namespace App\Models;

/**
 * Model ContratoBloqueio
 *
 * Gerencia authorization holds (pre-autorizacao) no cartao de credito
 * vinculados a contratos. Um hold reserva um valor no limite do cartao
 * sem efetuar cobranca. Pode ser capturado ou liberado posteriormente.
 */
class ContratoBloqueio extends Model
{
    /**
     * Cria um novo registro de bloqueio
     *
     * @return int ID do bloqueio criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('contratos_bloqueios')
            ->insert([
                'chave' => $dados['chave'],
                'id_contrato' => (int) $dados['id_contrato'],
                'id_cliente' => (int) $dados['id_cliente'],
                'id_cartao' => (int) $dados['id_cartao'],
                'id_gateway' => (int) $dados['id_gateway'],
                'gateway_code' => $dados['gateway_code'],
                'external_id' => $dados['external_id'] ?? null,
                'valor' => (float) $dados['valor'],
                'moeda' => $dados['moeda'] ?? 'BRL',
                'status' => $dados['status'] ?? 'pending',
                'autorizado_em' => $dados['autorizado_em'] ?? null,
                'expira_em' => $dados['expira_em'] ?? null,
                'payload' => isset($dados['payload']) ? json_encode($dados['payload']) : null,
            ]);
    }

    /**
     * Busca um bloqueio por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('contratos_bloqueios')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca o bloqueio ativo (authorized) de um contrato
     */
    public function buscarAtivoPorContrato(int $idContrato): ?array
    {
        return $this->qb
            ->table('contratos_bloqueios')
            ->where('id_contrato', '=', $idContrato)
            ->where('status', '=', 'authorized')
            ->first();
    }

    /**
     * Lista todos os bloqueios de um contrato (historico)
     */
    public function listarPorContrato(int $idContrato): array
    {
        return $this->qb
            ->table('contratos_bloqueios', 'cb')
            ->select([
                'cb.*',
                'cc.bandeira',
                'cc.ultimos_digitos',
            ])
            ->leftJoin('clientes_cartoes', 'cc', 'cb.id_cartao', '=', 'cc.id')
            ->where('cb.id_contrato', '=', $idContrato)
            ->orderBy('cb.id', 'DESC')
            ->get();
    }

    /**
     * Lista bloqueios que ainda podem reter limite no cartao.
     *
     * A chave explicita e usada por fluxos administrativos sem sessao, como
     * o encerramento de tenant pelo WHMCS.
     */
    public function listarLiberaveisPorContrato(int $idContrato, ?string $chave = null): array
    {
        $query = $this->qb
            ->table('contratos_bloqueios')
            ->where('id_contrato', '=', $idContrato)
            ->whereIn('status', ['pending', 'authorized'])
            ->orderBy('id', 'ASC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->get();
    }

    /**
     * Lista bloqueios liberaveis de todos os contratos de um tenant.
     */
    public function listarLiberaveisPorTenant(string $chave): array
    {
        return $this->qb
            ->table('contratos_bloqueios')
            ->withChave($chave)
            ->whereIn('status', ['pending', 'authorized'])
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Busca um bloqueio pelo external_id do gateway
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->qb
            ->table('contratos_bloqueios')
            ->where('external_id', '=', $externalId)
            ->first();
    }

    /**
     * Atualiza o status de um bloqueio
     *
     * @param int $id ID do bloqueio
     * @param string $status Novo status
     * @param array $extras Campos adicionais (capturado_em, liberado_em, valor_capturado, payload)
     */
    public function atualizarStatus(
        int $id,
        string $status,
        array $extras = [],
        ?string $chave = null
    ): int
    {
        $dados = ['status' => $status];

        if (isset($extras['autorizado_em'])) {
            $dados['autorizado_em'] = $extras['autorizado_em'];
        }
        if (isset($extras['capturado_em'])) {
            $dados['capturado_em'] = $extras['capturado_em'];
        }
        if (isset($extras['liberado_em'])) {
            $dados['liberado_em'] = $extras['liberado_em'];
        }
        if (isset($extras['valor_capturado'])) {
            $dados['valor_capturado'] = (float) $extras['valor_capturado'];
        }
        if (isset($extras['payload'])) {
            $dados['payload'] = json_encode($extras['payload']);
        }
        if (isset($extras['external_id'])) {
            $dados['external_id'] = $extras['external_id'];
        }
        if (isset($extras['expira_em'])) {
            $dados['expira_em'] = $extras['expira_em'];
        }

        $query = $this->qb
            ->table('contratos_bloqueios')
            ->where('id', '=', $id);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->update($dados);
    }
}
