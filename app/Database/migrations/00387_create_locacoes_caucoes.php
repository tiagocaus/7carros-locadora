<?php

/**
 * Migration 00387: Criar controle de caucoes de locacoes.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('locacoes_caucoes')) {
            $this->execute("
                CREATE TABLE locacoes_caucoes (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    chave VARCHAR(45) NOT NULL,
                    id_locacao INT UNSIGNED NOT NULL,
                    id_cliente INT UNSIGNED NULL,
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
                    legacy_tipo VARCHAR(100) NULL,
                    observacoes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_locacoes_caucoes_chave_locacao (chave, id_locacao),
                    KEY idx_locacoes_caucoes_status (chave, status),
                    KEY idx_locacoes_caucoes_forma_pagamento (id_forma_pagamento),
                    KEY idx_locacoes_caucoes_fin_entrada (id_financeiro_entrada),
                    KEY idx_locacoes_caucoes_fin_devolucao (id_financeiro_devolucao),
                    CONSTRAINT fk_locacoes_caucoes_locacao
                        FOREIGN KEY (id_locacao) REFERENCES locacoes(id)
                        ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_cliente
                        FOREIGN KEY (id_cliente) REFERENCES clientes(id)
                        ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_conta
                        FOREIGN KEY (id_conta) REFERENCES contas_bancarias(id)
                        ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_cartao
                        FOREIGN KEY (id_cartao) REFERENCES clientes_cartoes(id)
                        ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_forma_pagamento
                        FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento(id)
                        ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_fin_entrada
                        FOREIGN KEY (id_financeiro_entrada) REFERENCES financeiro(id)
                        ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_locacoes_caucoes_fin_devolucao
                        FOREIGN KEY (id_financeiro_devolucao) REFERENCES financeiro(id)
                        ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($this->columnExists('locacoes', 'caucao_valor')) {
            $this->execute("
                INSERT INTO locacoes_caucoes (
                    chave,
                    id_locacao,
                    id_cliente,
                    id_conta,
                    id_cartao,
                    id_forma_pagamento,
                    valor,
                    prazo_devolucao,
                    data_devolucao,
                    lancar_financeiro,
                    status,
                    legacy_tipo,
                    observacoes,
                    created_at
                )
                SELECT
                    l.chave,
                    l.id,
                    NULLIF(l.id_cliente, 0),
                    NULLIF(l.id_conta_caucao, 0),
                    NULLIF(l.id_cartao_caucao, 0),
                    CASE
                        WHEN l.caucao_tipo REGEXP '^[0-9]+$'
                            THEN (
                                SELECT fp_num.id
                                FROM formas_pagamento fp_num
                                WHERE fp_num.chave = l.chave
                                  AND fp_num.id = CAST(l.caucao_tipo AS UNSIGNED)
                                LIMIT 1
                            )
                        WHEN l.caucao_tipo IS NOT NULL AND TRIM(l.caucao_tipo) <> ''
                            THEN (
                                SELECT MIN(fp_txt.id)
                                FROM formas_pagamento fp_txt
                                WHERE fp_txt.chave = l.chave
                                  AND LOWER(TRIM(fp_txt.nome)) = LOWER(TRIM(l.caucao_tipo))
                            )
                        ELSE NULL
                    END,
                    l.caucao_valor,
                    l.caucao_prazo_devolucao,
                    l.caucao_data_devolucao,
                    0,
                    CASE
                        WHEN l.caucao_data_devolucao IS NOT NULL THEN 'devolvida'
                        ELSE 'ativa'
                    END,
                    NULLIF(l.caucao_tipo, ''),
                    NULL,
                    COALESCE(l.created_at, CURRENT_TIMESTAMP)
                FROM locacoes l
                WHERE COALESCE(l.caucao_valor, 0) > 0
                  AND NOT EXISTS (
                      SELECT 1
                      FROM locacoes_caucoes lc
                      WHERE lc.chave = l.chave
                        AND lc.id_locacao = l.id
                  )
            ");
        }
    }

    public function down(): void
    {
        $this->drop('locacoes_caucoes');
    }
};
