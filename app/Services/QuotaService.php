<?php

namespace App\Services;

use App\Config\Security;
use App\Models\Security\UserQuota;

/**
 * Service para gerenciamento de quotas de usuário
 *
 * Controla a quantidade de registros que um usuário pode acessar
 * e exportar por dia, baseado no plano de assinatura.
 */
class QuotaService
{
    private int $userId;
    private string $chave;
    private string $plano;
    private UserQuota $model;

    public function __construct(int $userId, string $chave, string $plano)
    {
        $this->userId = $userId;
        $this->chave = $chave;
        $this->plano = $plano;
        $this->model = new UserQuota();
    }

    /**
     * Verifica se o usuário pode acessar mais registros
     *
     * @param int $count Quantidade de registros a acessar
     * @return bool True se pode acessar
     */
    public function canAccessRecords(int $count = 1): bool
    {
        if (!Security::QUOTA['enabled']) {
            return true;
        }

        $quota = $this->getTodayQuota();
        $limit = Security::getRecordsQuota($this->plano);

        return ($quota['records_accessed'] + $count) <= $limit;
    }

    /**
     * Verifica se o usuário pode fazer mais exportações
     *
     * @return bool True se pode exportar
     */
    public function canExport(): bool
    {
        if (!Security::QUOTA['enabled']) {
            return true;
        }

        $quota = $this->getTodayQuota();
        $limit = Security::getExportsQuota($this->plano);

        return $quota['exports_count'] < $limit;
    }

    /**
     * Registra acesso a registros
     *
     * @param int $count Quantidade de registros acessados
     * @return bool True se operação bem sucedida
     */
    public function recordAccess(int $count = 1): bool
    {
        if (!Security::QUOTA['enabled']) {
            return true;
        }

        $this->ensureTodayQuotaExists();
        $this->model->incrementarAcessos($this->userId, $count);

        return true;
    }

    /**
     * Registra uma exportação
     *
     * @return bool True se operação bem sucedida
     */
    public function recordExport(): bool
    {
        if (!Security::QUOTA['enabled']) {
            return true;
        }

        $this->ensureTodayQuotaExists();
        $this->model->incrementarExportacoes($this->userId);

        return true;
    }

    /**
     * Obtém quota atual do dia
     *
     * @return array ['records_accessed' => int, 'exports_count' => int]
     */
    public function getTodayQuota(): array
    {
        $quota = $this->model->buscarQuotaHoje($this->userId);

        if (!$quota) {
            return [
                'records_accessed' => 0,
                'exports_count' => 0,
            ];
        }

        return $quota;
    }

    /**
     * Obtém informações detalhadas de quota
     *
     * @return array Informações de quota com limites e uso
     */
    public function getQuotaInfo(): array
    {
        $quota = $this->getTodayQuota();
        $recordsLimit = Security::getRecordsQuota($this->plano);
        $exportsLimit = Security::getExportsQuota($this->plano);

        return [
            'records' => [
                'used' => $quota['records_accessed'],
                'limit' => $recordsLimit,
                'remaining' => max(0, $recordsLimit - $quota['records_accessed']),
                'percentage' => $recordsLimit > 0
                    ? round(($quota['records_accessed'] / $recordsLimit) * 100, 2)
                    : 0,
            ],
            'exports' => [
                'used' => $quota['exports_count'],
                'limit' => $exportsLimit,
                'remaining' => max(0, $exportsLimit - $quota['exports_count']),
                'percentage' => $exportsLimit > 0 && $exportsLimit < PHP_INT_MAX
                    ? round(($quota['exports_count'] / $exportsLimit) * 100, 2)
                    : 0,
            ],
            'plano' => $this->plano,
            'date' => today(),
        ];
    }

    /**
     * Garante que existe um registro de quota para o dia atual
     */
    private function ensureTodayQuotaExists(): void
    {
        $this->model->garantirQuotaHoje($this->userId, $this->chave);
    }

    /**
     * Limpa quotas antigas (mais de 30 dias)
     *
     * @return int Número de registros removidos
     */
    public static function cleanup(): int
    {
        $model = new UserQuota();
        return $model->limparAntigas(30);
    }

    /**
     * Obtém histórico de uso de quota
     *
     * @param int $days Número de dias para retornar
     * @return array Histórico de uso por dia
     */
    public function getHistory(int $days = 7): array
    {
        return $this->model->buscarHistorico($this->userId, $days);
    }

    /**
     * Verifica quota e registra acesso de uma vez
     *
     * @param int $count Quantidade de registros
     * @return bool True se permitido e registrado
     */
    public function checkAndRecordAccess(int $count = 1): bool
    {
        if (!$this->canAccessRecords($count)) {
            return false;
        }

        return $this->recordAccess($count);
    }

    /**
     * Método estático para verificação rápida
     */
    public static function checkUserQuota(int $userId, string $chave, string $plano, int $recordCount = 1): bool
    {
        $service = new self($userId, $chave, $plano);
        return $service->canAccessRecords($recordCount);
    }

    /**
     * Método estático para registrar acesso
     */
    public static function recordUserAccess(int $userId, string $chave, string $plano, int $recordCount = 1): bool
    {
        $service = new self($userId, $chave, $plano);
        return $service->checkAndRecordAccess($recordCount);
    }
}
