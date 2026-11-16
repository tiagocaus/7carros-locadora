<?php

declare(strict_types=1);
namespace App\Services\Mcp;

final class SecurityException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $retryAfter)
    {
        parent::__construct($reason === 'mcp_suspended'
            ? 'Acesso MCP temporariamente suspenso por tentativas repetidas de operações não permitidas.'
            : 'Cota de consultas MCP atingida. Aguarde a renovação do limite.', 403);
    }

    public function payload(): array
    {
        return ['codigo' => $this->reason, 'mensagem' => $this->getMessage(), 'retry_after_seconds' => max(1, $this->retryAfter)];
    }
}
