<?php

namespace App\Models\Security;

use App\Models\Model;

/**
 * Model para gerenciamento de Quotas de Usuário
 *
 * Tabela: security_user_quotas (sem coluna chave)
 */
class UserQuota extends Model
{
    /**
     * Busca quota do dia atual para um usuário
     *
     * @param int $userId ID do usuário
     * @return array|null Dados da quota ou null
     */
    public function buscarQuotaHoje(int $userId): ?array
    {
        return $this->qb
            ->table('security_user_quotas')
            ->withoutChave()
            ->select(['records_accessed', 'exports_count'])
            ->where('user_id', '=', $userId)
            ->whereRaw('quota_date = CURDATE()')
            ->first();
    }

    /**
     * Cria registro de quota para o dia atual (se não existir)
     *
     * @param int $userId ID do usuário
     * @param string $chave Chave do tenant
     * @return void
     */
    public function garantirQuotaHoje(int $userId, string $chave): void
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // INSERT IGNORE - não faz nada se já existir
        $sql = "INSERT IGNORE INTO security_user_quotas
                (user_id, chave, records_accessed, exports_count, quota_date, created_at, updated_at)
                VALUES (?, ?, 0, 0, ?, ?, ?)";

        $stmt = $this->getMysqli()->prepare($sql);
        $stmt->bind_param('issss', $userId, $chave, $today, $now, $now);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Incrementa contador de registros acessados
     *
     * @param int $userId ID do usuário
     * @param int $count Quantidade a incrementar
     * @return int Linhas afetadas
     */
    public function incrementarAcessos(int $userId, int $count = 1): int
    {
        $sql = "UPDATE security_user_quotas
                SET records_accessed = records_accessed + ?, updated_at = NOW()
                WHERE user_id = ? AND quota_date = CURDATE()";

        $stmt = $this->getMysqli()->prepare($sql);
        $stmt->bind_param('ii', $count, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Incrementa contador de exportações
     *
     * @param int $userId ID do usuário
     * @return int Linhas afetadas
     */
    public function incrementarExportacoes(int $userId): int
    {
        $sql = "UPDATE security_user_quotas
                SET exports_count = exports_count + 1, updated_at = NOW()
                WHERE user_id = ? AND quota_date = CURDATE()";

        $stmt = $this->getMysqli()->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Busca histórico de uso de quota
     *
     * @param int $userId ID do usuário
     * @param int $dias Número de dias
     * @return array Histórico
     */
    public function buscarHistorico(int $userId, int $dias = 7): array
    {
        return $this->qb
            ->table('security_user_quotas')
            ->withoutChave()
            ->select(['quota_date', 'records_accessed', 'exports_count'])
            ->where('user_id', '=', $userId)
            ->whereRaw('quota_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', [$dias])
            ->orderBy('quota_date', 'DESC')
            ->get();
    }

    /**
     * Remove quotas antigas
     *
     * @param int $dias Dias para manter (padrão: 30)
     * @return int Número de registros removidos
     */
    public function limparAntigas(int $dias = 30): int
    {
        $cutoff = date('Y-m-d', strtotime("-{$dias} days"));

        return $this->qb
            ->table('security_user_quotas')
            ->withoutChave()
            ->where('quota_date', '<', $cutoff)
            ->delete();
    }
}
