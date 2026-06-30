<?php

namespace App\Models;

/**
 * Model ClientePasswordReset
 *
 * Gerencia tokens de reset de senha de clientes do site publico.
 * Token em claro nunca eh armazenado — apenas seu hash sha256.
 * Token eh valido por TTL_MINUTES, uso unico.
 */
class ClientePasswordReset extends Model
{
    public const TTL_MINUTES = 60;

    /**
     * Cria um novo token de reset e retorna o token em claro (para enviar por email).
     * O hash fica no BD. Invalida tokens anteriores pendentes do mesmo cliente.
     *
     * @return string Token em claro (64 hex chars)
     */
    public function criar(int $idCliente, string $chave, ?string $requestIp = null): string
    {
        $tokenPlano = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenPlano);
        $expiresAt = \App\Helpers\DateHelper::formatTimestamp(
            \App\Helpers\DateHelper::timestamp() + (self::TTL_MINUTES * 60),
            'Y-m-d H:i:s',
            false
        );

        // Invalida tokens pendentes anteriores deste cliente
        $this->qb
            ->withoutChave()
            ->table('cliente_password_resets')
            ->where('chave', '=', $chave)
            ->where('id_cliente', '=', $idCliente)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $this->qb
            ->withoutChave()
            ->table('cliente_password_resets')
            ->insert([
                'chave' => $chave,
                'id_cliente' => $idCliente,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'used_at' => null,
                'request_ip' => $requestIp,
            ]);

        return $tokenPlano;
    }

    /**
     * Valida um token em claro. Retorna o registro se valido, null caso contrario.
     * "Valido" = nao usado, nao expirado.
     *
     * Usa withoutChave porque o token nao tem contexto de sessao ainda
     * (cliente esta na tela publica de redefinicao). A chave/id_cliente sao
     * devolvidos pela propria linha.
     */
    public function validar(string $tokenPlano): ?array
    {
        if (strlen($tokenPlano) !== 64 || !ctype_xdigit($tokenPlano)) {
            return null;
        }

        $tokenHash = hash('sha256', $tokenPlano);

        $row = $this->qb
            ->withoutChave()
            ->table('cliente_password_resets')
            ->where('token_hash', '=', $tokenHash)
            ->whereNull('used_at')
            ->first();

        if (!$row) return null;

        if (strtotime($row['expires_at']) < \App\Helpers\DateHelper::timestamp()) {
            return null;
        }

        return $row;
    }

    public function marcarUsado(int $id): void
    {
        $this->qb
            ->withoutChave()
            ->table('cliente_password_resets')
            ->where('id', '=', $id)
            ->update(['used_at' => now()]);
    }
}
