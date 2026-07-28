<?php

namespace App\Models;

use App\Helpers\DateHelper;

/**
 * Sessao opaca usada entre o website publicado e a API do portal.
 */
class PortalSession extends Model
{
    public const IDLE_SECONDS = 1800;
    public const ABSOLUTE_SECONDS = 43200;

    public function criar(
        string $chave,
        string $perfil,
        int $entidadeId,
        ?string $ip,
        ?string $userAgent
    ): string {
        $token = bin2hex(random_bytes(32));
        $agora = DateHelper::timestamp();

        $this->qb
            ->table('portal_sessions')
            ->withChave($chave)
            ->insert([
                'perfil' => $perfil,
                'entidade_id' => $entidadeId,
                'token_hash' => hash('sha256', $token),
                'last_activity_at' => DateHelper::formatTimestamp($agora, 'Y-m-d H:i:s', false),
                'expires_at' => DateHelper::formatTimestamp(
                    $agora + self::ABSOLUTE_SECONDS,
                    'Y-m-d H:i:s',
                    false
                ),
                'revoked_at' => null,
                'ip' => $ip,
                'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            ]);

        return $token;
    }

    public function validar(
        string $chave,
        string $token,
        ?string $userAgent = null,
        bool $tocar = true
    ): ?array {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $session = $this->qb
            ->table('portal_sessions')
            ->withChave($chave)
            ->where('token_hash', '=', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();

        if (!$session) {
            return null;
        }

        $agora = DateHelper::timestamp();
        $ultimaAtividade = strtotime((string) $session['last_activity_at']) ?: 0;
        $expiraEm = strtotime((string) $session['expires_at']) ?: 0;
        $agentHash = $userAgent ? hash('sha256', $userAgent) : null;

        if (
            $expiraEm < $agora
            || ($ultimaAtividade + self::IDLE_SECONDS) < $agora
            || (!empty($session['user_agent_hash']) && !hash_equals($session['user_agent_hash'], (string) $agentHash))
        ) {
            $this->revogar($chave, $token);
            return null;
        }

        if ($tocar) {
            $this->qb
                ->table('portal_sessions')
                ->withChave($chave)
                ->where('id', '=', (int) $session['id'])
                ->update([
                    'last_activity_at' => DateHelper::formatTimestamp($agora, 'Y-m-d H:i:s', false),
                ]);
        }

        return $session;
    }

    public function revogar(string $chave, string $token): void
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return;
        }

        $this->qb
            ->table('portal_sessions')
            ->withChave($chave)
            ->where('token_hash', '=', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revogarEntidade(string $chave, string $perfil, int $entidadeId): void
    {
        $this->qb
            ->table('portal_sessions')
            ->withChave($chave)
            ->where('perfil', '=', $perfil)
            ->where('entidade_id', '=', $entidadeId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
