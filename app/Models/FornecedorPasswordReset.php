<?php

namespace App\Models;

use App\Helpers\DateHelper;

class FornecedorPasswordReset extends Model
{
    public const TTL_MINUTES = 60;

    public function criar(int $idFornecedor, string $chave, ?string $requestIp = null): string
    {
        $token = bin2hex(random_bytes(32));
        $agora = DateHelper::timestamp();

        $this->qb
            ->table('fornecedor_password_resets')
            ->withChave($chave)
            ->where('id_fornecedor', '=', $idFornecedor)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $this->qb
            ->table('fornecedor_password_resets')
            ->withChave($chave)
            ->insert([
                'id_fornecedor' => $idFornecedor,
                'token_hash' => hash('sha256', $token),
                'expires_at' => DateHelper::formatTimestamp(
                    $agora + (self::TTL_MINUTES * 60),
                    'Y-m-d H:i:s',
                    false
                ),
                'used_at' => null,
                'request_ip' => $requestIp,
            ]);

        return $token;
    }

    public function validar(string $token): ?array
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $row = $this->qb
            ->table('fornecedor_password_resets')
            ->withoutChave()
            ->where('token_hash', '=', hash('sha256', $token))
            ->whereNull('used_at')
            ->first();

        if (!$row || strtotime((string) $row['expires_at']) < DateHelper::timestamp()) {
            return null;
        }

        return $row;
    }

    public function marcarUsado(int $id, string $chave): void
    {
        $this->qb
            ->table('fornecedor_password_resets')
            ->withChave($chave)
            ->where('id', '=', $id)
            ->update(['used_at' => now()]);
    }
}
