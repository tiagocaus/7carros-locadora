<?php

namespace App\Models;

/**
 * Model FeatureRequestFollower
 *
 * Gerencia seguidores de pedidos de recursos.
 * Cada email pode seguir apenas uma vez por pedido.
 * Seguidores são notificados quando o status muda.
 */
class FeatureRequestFollower extends Model
{
    /**
     * Verifica se um email já segue um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do seguidor
     * @return bool True se já segue
     */
    public function jaSegue(int $featureRequestId, string $email): bool
    {
        $seguidor = $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email', '=', $email)
            ->first();

        return $seguidor !== null;
    }

    /**
     * Registra um seguidor
     *
     * @param array $dados Dados do seguidor
     * @return int|false ID do registro ou false se já existir
     */
    public function seguir(array $dados): int|false
    {
        // Verificar se já segue
        if ($this->jaSegue($dados['feature_request_id'], $dados['email'])) {
            return false;
        }

        // Inserir seguidor
        $id = $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->insert([
                'feature_request_id' => $dados['feature_request_id'],
                'chave' => $dados['chave'],
                'usuario_id' => $dados['usuario_id'] ?? null,
                'email' => $dados['email'],
                'telefone' => $dados['telefone'] ?? null,
                'notificar_email' => $dados['notificar_email'] ?? 1,
                'notificar_whatsapp' => $dados['notificar_whatsapp'] ?? 1,
            ]);

        // Incrementar contador no pedido
        if ($id) {
            $featureRequest = new FeatureRequest();
            $featureRequest->incrementarSeguidores($dados['feature_request_id']);
        }

        return $id;
    }

    /**
     * Remove um seguidor
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do seguidor
     * @return bool True se removeu, false se não existia
     */
    public function deixarDeSeguir(int $featureRequestId, string $email): bool
    {
        $deleted = $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email', '=', $email)
            ->delete();

        // Decrementar contador no pedido
        if ($deleted > 0) {
            $featureRequest = new FeatureRequest();
            $featureRequest->decrementarSeguidores($featureRequestId);
            return true;
        }

        return false;
    }

    /**
     * Atualiza preferências de notificação
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do seguidor
     * @param array $preferencias Array com notificar_email e/ou notificar_whatsapp
     * @return int Linhas afetadas
     */
    public function atualizarPreferencias(int $featureRequestId, string $email, array $preferencias): int
    {
        $dadosUpdate = [];

        if (isset($preferencias['notificar_email'])) {
            $dadosUpdate['notificar_email'] = (int) $preferencias['notificar_email'];
        }
        if (isset($preferencias['notificar_whatsapp'])) {
            $dadosUpdate['notificar_whatsapp'] = (int) $preferencias['notificar_whatsapp'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email', '=', $email)
            ->update($dadosUpdate);
    }

    /**
     * Lista seguidores de um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @return array Lista de seguidores
     */
    public function listarPorPedido(int $featureRequestId): array
    {
        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Lista seguidores de um pedido que desejam notificação por email
     *
     * @param int $featureRequestId ID do pedido
     * @return array Lista de seguidores
     */
    public function listarParaNotificacaoEmail(int $featureRequestId): array
    {
        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('notificar_email', '=', 1)
            ->get();
    }

    /**
     * Lista seguidores de um pedido que desejam notificação por WhatsApp
     *
     * @param int $featureRequestId ID do pedido
     * @return array Lista de seguidores com telefone
     */
    public function listarParaNotificacaoWhatsApp(int $featureRequestId): array
    {
        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('notificar_whatsapp', '=', 1)
            ->whereNotNull('telefone')
            ->get();
    }

    /**
     * Conta seguidores de um pedido
     *
     * @param int $featureRequestId ID do pedido
     * @return int Total de seguidores
     */
    public function contarPorPedido(int $featureRequestId): int
    {
        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->count();
    }

    /**
     * Lista pedidos seguidos por um email
     *
     * @param string $email Email do seguidor
     * @return array Lista de IDs de pedidos seguidos
     */
    public function listarPedidosSeguidos(string $email): array
    {
        $seguidores = $this->qb
            ->table('feature_request_followers')
            ->select(['feature_request_id'])
            ->withoutChave()
            ->where('email', '=', $email)
            ->get();

        return array_column($seguidores, 'feature_request_id');
    }

    /**
     * Busca dados de seguidor por pedido e email
     *
     * @param int $featureRequestId ID do pedido
     * @param string $email Email do seguidor
     * @return array|null Dados ou null
     */
    public function buscar(int $featureRequestId, string $email): ?array
    {
        return $this->qb
            ->table('feature_request_followers')
            ->withoutChave()
            ->where('feature_request_id', '=', $featureRequestId)
            ->where('email', '=', $email)
            ->first();
    }
}
