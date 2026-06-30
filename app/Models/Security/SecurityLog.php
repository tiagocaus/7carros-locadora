<?php

namespace App\Models\Security;

use App\Models\Model;

/**
 * Model para gerenciamento de Logs de Segurança
 *
 * Tabela: security_logs (sem coluna chave)
 */
class SecurityLog extends Model
{
    /**
     * Registra um evento de segurança
     *
     * @param array $dados Dados do evento
     * @return int ID criado
     */
    public function registrar(array $dados): int
    {
        $dados['created_at'] = $dados['created_at'] ?? now();
        $dados['updated_at'] = $dados['updated_at'] ?? now();

        // Converter details para JSON se for array
        if (isset($dados['details']) && is_array($dados['details'])) {
            $dados['details'] = json_encode($dados['details'], JSON_UNESCAPED_UNICODE);
        }

        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->insert($dados);
    }

    /**
     * Remove logs antigos
     *
     * @param int $dias Dias de retenção
     * @return int Número de registros removidos
     */
    public function limparAntigos(int $dias): int
    {
        $cutoff = \App\Helpers\DateHelper::addDaysForDatabase(-$dias, null, 'Y-m-d H:i:s');

        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Obtém estatísticas por período
     *
     * @param string $dataInicio Data inicial (Y-m-d)
     * @param string $dataFim Data final (Y-m-d)
     * @return array Estatísticas agrupadas por tipo
     */
    public function obterEstatisticas(string $dataInicio, string $dataFim): array
    {
        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->selectRaw('
                event_type,
                COUNT(*) as total,
                COUNT(DISTINCT ip_address) as unique_ips,
                AVG(score) as avg_score
            ')
            ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy('event_type')
            ->orderByRaw('total DESC')
            ->get();
    }

    /**
     * Obtém IPs mais suspeitos
     *
     * @param int $limite Quantidade de IPs
     * @param int $dias Período em dias
     * @return array Lista de IPs
     */
    public function obterIpsSuspeitos(int $limite = 10, int $dias = 7): array
    {
        $cutoff = \App\Helpers\DateHelper::addDaysForDatabase(-$dias, null, 'Y-m-d H:i:s');

        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->selectRaw('
                ip_address,
                COUNT(*) as total_events,
                MAX(score) as max_score,
                GROUP_CONCAT(DISTINCT event_type) as event_types
            ')
            ->where('created_at', '>=', $cutoff)
            ->groupBy('ip_address')
            ->orderByRaw('total_events DESC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca logs por IP
     *
     * @param string $ipAddress IP
     * @param int $limite Limite de registros
     * @return array Logs
     */
    public function buscarPorIp(string $ipAddress, int $limite = 100): array
    {
        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->where('ip_address', '=', $ipAddress)
            ->orderBy('created_at', 'DESC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca logs por usuário
     *
     * @param int $userId ID do usuário
     * @param int $limite Limite de registros
     * @return array Logs
     */
    public function buscarPorUsuario(int $userId, int $limite = 100): array
    {
        return $this->qb
            ->table('security_logs')
            ->withoutChave()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limite)
            ->get();
    }
}
