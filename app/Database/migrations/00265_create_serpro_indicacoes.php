<?php

/**
 * Migration 00265: Criar tabela serpro_indicacoes
 *
 * Registra indicacoes de real infrator e principal condutor
 * enviadas a SERPRO eFrotas. Vincula com multas, veiculos,
 * clientes e contratos/locacoes do sistema.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('serpro_indicacoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE serpro_indicacoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',

                tipo ENUM('real_infrator', 'principal_condutor') NOT NULL COMMENT 'Tipo da indicacao',

                id_multa INT UNSIGNED NULL COMMENT 'FK multas (para real infrator)',
                id_veiculo INT UNSIGNED NULL COMMENT 'FK veiculos',
                id_cliente INT UNSIGNED NULL COMMENT 'FK clientes (locatario indicado)',
                id_contrato INT UNSIGNED NULL COMMENT 'FK contratos',
                id_locacao INT UNSIGNED NULL COMMENT 'FK locacoes',

                placa VARCHAR(10) NOT NULL COMMENT 'Placa do veiculo',
                codigo_orgao VARCHAR(20) NULL COMMENT 'Codigo do orgao autuador (SERPRO)',
                numero_ait VARCHAR(30) NULL COMMENT 'Numero do auto de infracao (SERPRO)',
                codigo_infracao VARCHAR(20) NULL COMMENT 'Codigo da infracao (SERPRO)',

                cpf_indicado VARCHAR(14) NOT NULL COMMENT 'CPF do indicado',
                nome_indicado VARCHAR(150) NULL COMMENT 'Nome do indicado',
                cnh_indicado VARCHAR(20) NULL COMMENT 'Numero da CNH do indicado',

                chave_indicacao VARCHAR(50) NULL COMMENT 'Chave da indicacao retornada pela SERPRO',
                status_serpro VARCHAR(50) NOT NULL DEFAULT 'enviado' COMMENT 'Status: enviado, processando, aceito, rejeitado, cancelado, expirado',
                motivo_rejeicao VARCHAR(500) NULL COMMENT 'Motivo da rejeicao se houver',
                documento_assinado TEXT NULL COMMENT 'PDF base64 do documento assinado',

                data_indicacao DATETIME NULL COMMENT 'Data da indicacao na SERPRO',
                data_resposta DATETIME NULL COMMENT 'Data da resposta da SERPRO',
                data_expiracao DATETIME NULL COMMENT 'Data de expiracao da indicacao',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_si_chave (chave),
                INDEX idx_si_placa (placa),
                INDEX idx_si_tipo (tipo),
                INDEX idx_si_multa (id_multa),
                INDEX idx_si_status (status_serpro),
                INDEX idx_si_chave_ind (chave_indicacao),
                INDEX idx_si_veiculo (id_veiculo),
                INDEX idx_si_cliente (id_cliente)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Indicacoes de real infrator e principal condutor via SERPRO'
        ");
    }

    public function down(): void
    {
        $this->drop('serpro_indicacoes');
    }
};
