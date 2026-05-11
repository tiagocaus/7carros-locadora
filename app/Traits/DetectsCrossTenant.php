<?php

namespace App\Traits;

use App\Services\CrossTenantDetectionService;

/**
 * Trait para adicionar detecção de tentativas cross-tenant em Models
 *
 * Requer que a classe que usa este trait tenha:
 * - Método buscarPorId(int $id): ?array
 *
 * Opcionalmente pode sobrescrever:
 * - getTableNameForCrossTenantDetection(): string
 */
trait DetectsCrossTenant
{
    /**
     * Retorna o nome da tabela para detecção cross-tenant
     * Sobrescreva este método na classe se necessário
     *
     * @return string
     */
    protected function getTableNameForCrossTenantDetection(): string
    {
        // Tenta obter da propriedade $table se existir
        if (property_exists($this, 'table')) {
            return $this->table;
        }

        // Fallback: converte nome da classe para snake_case + 's'
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . 's';
    }

    /**
     * Busca por ID com detecção de tentativa cross-tenant
     *
     * Se o registro não for encontrado, verifica se existe em outro tenant
     * e loga a tentativa se positivo.
     *
     * @param int $id ID do registro
     * @return array|null Registro encontrado ou null
     */
    public function buscarPorIdComDeteccao(int $id): ?array
    {
        // Primeiro tenta buscar normalmente (com filtro de chave)
        $result = $this->buscarPorId($id);

        // Se encontrou, retorna normalmente
        if ($result !== null) {
            return $result;
        }

        // Não encontrou - verificar se é tentativa cross-tenant
        CrossTenantDetectionService::check(
            $this->getTableNameForCrossTenantDetection(),
            $id
        );

        return null;
    }

    /**
     * Verifica se um ID específico é uma tentativa cross-tenant
     * Útil para verificações manuais sem buscar o registro
     *
     * @param int $id ID do registro
     * @return bool True se o ID existe em outro tenant
     */
    public function isCrossTenantAttempt(int $id): bool
    {
        $result = CrossTenantDetectionService::check(
            $this->getTableNameForCrossTenantDetection(),
            $id
        );

        return $result->isCrossTenant;
    }

    /**
     * Verifica múltiplos IDs de uma vez para detecção cross-tenant
     *
     * @param array $ids Array de IDs
     * @return array Array com resultado para cada ID
     */
    public function checkCrossTenantBatch(array $ids): array
    {
        $results = [];
        $table = $this->getTableNameForCrossTenantDetection();

        foreach ($ids as $id) {
            $results[$id] = CrossTenantDetectionService::check($table, (int) $id);
        }

        return $results;
    }
}
