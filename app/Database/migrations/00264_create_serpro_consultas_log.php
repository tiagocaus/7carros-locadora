<?php

/**
 * Migration 00264: Criar tabela serpro_consultas_log
 *
 * Log tecnico detalhado de cada chamada a API SERPRO eFrotas.
 * Registra request/response para debug e auditoria.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('serpro_consultas_log')) {
            return;
        }

        $this->execute("
            CREATE TABLE serpro_consultas_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',

                tipo_operacao VARCHAR(50) NOT NULL COMMENT 'Ex: consulta_infracoes, consulta_veiculo, indicar_real_infrator',

                placa VARCHAR(10) NULL COMMENT 'Placa do veiculo consultado',
                endpoint VARCHAR(500) NOT NULL COMMENT 'URL completa da chamada',

                request_headers JSON NULL COMMENT 'Headers enviados',
                request_payload JSON NULL COMMENT 'Body enviado (POST/PUT)',
                response_status INT NULL COMMENT 'HTTP status code da resposta',
                response_payload JSON NULL COMMENT 'Body da resposta',

                status ENUM('sucesso', 'erro', 'timeout') NOT NULL DEFAULT 'sucesso',
                erro_mensagem VARCHAR(500) NULL COMMENT 'Mensagem de erro se houver',

                id_serpro_transacao INT UNSIGNED NULL COMMENT 'FK para serpro_transacoes',
                duracao_ms INT NULL COMMENT 'Tempo de resposta em milissegundos',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_scl_chave (chave),
                INDEX idx_scl_placa (placa),
                INDEX idx_scl_tipo (tipo_operacao),
                INDEX idx_scl_created (created_at),
                INDEX idx_scl_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Log de chamadas a API SERPRO eFrotas'
        ");

        $this->addForeignKeyIfNotExists(
            'serpro_consultas_log',
            'id_serpro_transacao',
            'serpro_transacoes',
            'id',
            'SET NULL',
            'CASCADE',
            'fk_scl_transacao'
        );
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('serpro_consultas_log', 'fk_scl_transacao');
        $this->drop('serpro_consultas_log');
    }
};
