<?php

declare(strict_types=1);

namespace App\Services\Mcp;

/** Identity is server-resolved; empty branches and unrestricted access are distinct. */
final readonly class Context
{
    public function __construct(
        public string $chave,
        public int $employeeId,
        public string $connectionId,
        public string $clientId,
        public array $scopes,
        public array $permissions,
        public bool $allBranches,
        public array $branchIds,
    ) {
        if (trim($chave) === '' || $chave === '0' || strlen($chave) > 45 || $employeeId < 1
            || $connectionId === '' || $clientId === '') {
            throw new \InvalidArgumentException('Contexto MCP incompleto.');
        }
        foreach ($branchIds as $id) {
            if (!is_int($id) || $id < 1) {
                throw new \InvalidArgumentException('Filial inválida no contexto MCP.');
            }
        }
    }

    public function permits(string $scope): bool
    {
        $permission = Catalog::SCOPES[$scope] ?? null;
        return $permission !== null && in_array($scope, $this->scopes, true)
            && in_array('mcp.conectar', $this->permissions, true)
            && in_array($permission, $this->permissions, true);
    }
}
