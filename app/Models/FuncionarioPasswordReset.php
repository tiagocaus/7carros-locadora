<?php

namespace App\Models;

/**
 * Gerencia tokens de reset de senha de funcionarios do painel.
 *
 * O token em claro nunca eh armazenado, apenas seu hash SHA-256. O token e de
 * uso unico e expira automaticamente.
 */
class FuncionarioPasswordReset extends Model
{
    public const TTL_MINUTES = 60;

    public function criar(int $idFuncionario, string $chave, ?string $requestIp = null): string
    {
        $tokenPlano = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenPlano);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TTL_MINUTES * 60));

        $this->qb
            ->withoutChave()
            ->table('funcionario_password_resets')
            ->where('chave', '=', $chave)
            ->where('id_funcionario', '=', $idFuncionario)
            ->whereNull('used_at')
            ->update(['used_at' => date('Y-m-d H:i:s')]);

        $this->qb
            ->withoutChave()
            ->table('funcionario_password_resets')
            ->insert([
                'chave' => $chave,
                'id_funcionario' => $idFuncionario,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'used_at' => null,
                'request_ip' => $requestIp,
            ]);

        return $tokenPlano;
    }

    public function validar(string $tokenPlano): ?array
    {
        if (strlen($tokenPlano) !== 64 || !ctype_xdigit($tokenPlano)) {
            return null;
        }

        $tokenHash = hash('sha256', $tokenPlano);

        $row = $this->qb
            ->withoutChave()
            ->table('funcionario_password_resets')
            ->where('token_hash', '=', $tokenHash)
            ->whereNull('used_at')
            ->first();

        if (!$row) {
            return null;
        }

        if (strtotime($row['expires_at']) < time()) {
            return null;
        }

        return $row;
    }

    public function marcarUsado(int $id): void
    {
        $this->qb
            ->withoutChave()
            ->table('funcionario_password_resets')
            ->where('id', '=', $id)
            ->update(['used_at' => date('Y-m-d H:i:s')]);
    }
}
