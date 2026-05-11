<?php

/**
 * Migration 00027: Otimização da tabela logs
 *
 * Otimiza a tabela de logs que possui 6.5 milhões de registros.
 * Dados: 2.9GB + Índices: 1.1GB = 4GB total
 *
 * Ações:
 * - Adiciona índice idx_logs_data para filtro por período
 * - Permite limpeza eficiente de logs antigos
 *
 * NOTA: Considerar implementar:
 * 1. Rotação automática de logs (cron job para remover logs > 90 dias)
 * 2. Particionamento por mês (requer ALTER TABLE mais complexo)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por data (permite cleanup eficiente)
        $this->addIndexIfNotExists('logs', 'data', 'idx_logs_data');

        // Índice composto: filtro por tipo de log + data
        // Útil para queries como "últimos erros" ou "atividades do dia"
        if ($this->columnExists('logs', 'tipo')) {
            $this->addIndexIfNotExists('logs', ['tipo', 'data'], 'idx_logs_tipo_data');
        }
    }

    public function down(): void
    {
        if ($this->columnExists('logs', 'tipo')) {
            $this->dropIndexIfExists('logs', 'idx_logs_tipo_data');
        }
        $this->dropIndexIfExists('logs', 'idx_logs_data');
    }
};
