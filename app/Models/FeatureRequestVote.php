<?php

namespace App\Models;

/**
 * Model FeatureRequestVote
 *
 * Gerencia votos em pedidos de recursos.
 * Cada email pode votar apenas uma vez por pedido.
 */
class FeatureRequestVote extends Model
{
    /**
     * Verifica se um email já votou em um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do votante
     * @return bool True se já votou
     */
    public function jaVotou(int $featureRequestId, string $email): bool
    {
        $voto = $this->qb
            ->table('feature_request_votes')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email_votante', '=', $email)
            ->first();

        return $voto !== null;
    }

    /**
     * Registra um voto
     *
     * @param array $dados Dados do voto (feature_request_id, chave, usuario_id, email_votante)
     * @return int|false ID do voto ou false se já existir
     */
    public function votar(array $dados): int|false
    {
        // Verificar se já votou
        if ($this->jaVotou($dados['feature_request_id'], $dados['email_votante'])) {
            return false;
        }

        // Inserir voto
        $id = $this->qb
            ->table('feature_request_votes')
            ->withoutChave()
            ->insert([
                'feature_request_id' => $dados['feature_request_id'],
                'chave' => $dados['chave'],
                'usuario_id' => $dados['usuario_id'] ?? null,
                'email_votante' => $dados['email_votante'],
            ]);

        // Incrementar contador no pedido
        if ($id) {
            $featureRequest = new FeatureRequest();
            $featureRequest->incrementarVotos($dados['feature_request_id']);
        }

        return $id;
    }

    /**
     * Remove um voto
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do votante
     * @return bool True se removeu, false se não existia
     */
    public function removerVoto(int $featureRequestId, string $email): bool
    {
        $deleted = $this->qb
            ->table('feature_request_votes')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email_votante', '=', $email)
            ->delete();

        // Decrementar contador no pedido
        if ($deleted > 0) {
            $featureRequest = new FeatureRequest();
            $featureRequest->decrementarVotos($featureRequestId);
            return true;
        }

        return false;
    }

    /**
     * Lista votantes de um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @return array Lista de votos
     */
    public function listarPorPedido(int $featureRequestId): array
    {
        return $this->qb
            ->table('feature_request_votes')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Conta votos de um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @return int Total de votos
     */
    public function contarPorPedido(int $featureRequestId): int
    {
        return $this->qb
            ->table('feature_request_votes')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->count();
    }

    /**
     * Lista pedidos votados por um email
     *
     * @param string $email Email do votante
     * @return array Lista de IDs de pedidos votados
     */
    public function listarPedidosVotados(string $email): array
    {
        $votos = $this->qb
            ->table('feature_request_votes')
            ->select(['feature_request_id'])
            ->withoutChave()
            ->where('email_votante', '=', $email)
            ->get();

        return array_column($votos, 'feature_request_id');
    }
}
