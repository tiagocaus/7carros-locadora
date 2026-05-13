<?php

/**
 * Migration 00261: Criar tabela serpro_configuracoes
 *
 * Armazena configuracoes da integracao de consultas online por tenant.
 * Cada locadora tem seu CNPJ registrado e configuracoes de
 * auto-consulta e auto-eventos.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('serpro_configuracoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE serpro_configuracoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',
                cnpj_empresa VARCHAR(14) NOT NULL COMMENT 'CNPJ da locadora cadastrado na SERPRO',

                auto_consulta_ativo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=desativado, 1=ativado',
                intervalo_dias_consulta INT NOT NULL DEFAULT 7 COMMENT 'Intervalo em dias entre consultas automaticas',
                ultima_consulta_em DATETIME NULL COMMENT 'Data/hora da ultima consulta automatica',

                auto_eventos_ativo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=desativado, 1=ativado',
                webhook_registrado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se webhook ja foi registrado na SERPRO',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE INDEX idx_sc_chave (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Configuracoes da integracao de consultas online por tenant'
        ");
    }

    public function down(): void
    {
        $this->drop('serpro_configuracoes');
    }
};
