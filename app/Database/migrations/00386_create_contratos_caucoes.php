<?php

/**
 * Migration 00386: Criar controle de caucoes de contratos.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('contratos_caucoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE contratos_caucoes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                chave VARCHAR(45) NOT NULL,
                id_contrato INT UNSIGNED NOT NULL,
                id_cliente INT UNSIGNED NOT NULL,
                id_conta INT UNSIGNED NULL,
                id_cartao INT UNSIGNED NULL,
                id_forma_pagamento INT UNSIGNED NULL,
                id_financeiro_entrada INT UNSIGNED NULL,
                id_financeiro_devolucao INT UNSIGNED NULL,
                valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                prazo_devolucao INT NULL,
                data_devolucao DATE NULL,
                lancar_financeiro TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM('ativa', 'devolvida', 'cancelada') NOT NULL DEFAULT 'ativa',
                observacoes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_contratos_caucoes_chave_contrato (chave, id_contrato),
                KEY idx_contratos_caucoes_status (chave, status),
                KEY idx_contratos_caucoes_forma_pagamento (id_forma_pagamento),
                KEY idx_contratos_caucoes_fin_entrada (id_financeiro_entrada),
                KEY idx_contratos_caucoes_fin_devolucao (id_financeiro_devolucao),
                CONSTRAINT fk_contratos_caucoes_contrato
                    FOREIGN KEY (id_contrato) REFERENCES contratos(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_cliente
                    FOREIGN KEY (id_cliente) REFERENCES clientes(id)
                    ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_conta
                    FOREIGN KEY (id_conta) REFERENCES contas_bancarias(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_cartao
                    FOREIGN KEY (id_cartao) REFERENCES clientes_cartoes(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_forma_pagamento
                    FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_fin_entrada
                    FOREIGN KEY (id_financeiro_entrada) REFERENCES financeiro(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_contratos_caucoes_fin_devolucao
                    FOREIGN KEY (id_financeiro_devolucao) REFERENCES financeiro(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->drop('contratos_caucoes');
    }
};
