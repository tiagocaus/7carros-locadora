<?php

namespace App\Models;

class PortalAuditLog extends Model
{
    public function registrar(
        string $chave,
        string $perfil,
        int $entidadeId,
        string $acao,
        array $campos,
        ?string $ip,
        ?string $userAgent
    ): int {
        return $this->qb
            ->table('portal_audit_logs')
            ->withChave($chave)
            ->insert([
                'perfil' => $perfil,
                'entidade_id' => $entidadeId,
                'acao' => $acao,
                'campos_alterados' => $campos === []
                    ? null
                    : json_encode($campos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $ip,
                'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            ]);
    }
}
