<?php

/**
 * Migration 00113: Refatorar Promissórias para Parcelas
 *
 * Esta migration ajusta a tabela promissorias para suportar o novo
 * sistema de parcelas sem integração com a tabela financeiro.
 *
 * Mudanças:
 * 1. Renomear data_venci → data_vencimento
 * 2. Renomear valor → valor_parcela
 * 3. Adicionar numero_parcela INT (extraído de "2 de 4")
 * 4. Adicionar total_parcelas INT (extraído de "2 de 4")
 * 5. Adicionar data_pagamento DATE
 * 6. Adicionar codigo_base (coluna gerada)
 * 7. Remover coluna parcela antiga (VARCHAR "2 de 4")
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar novas colunas
        if (!$this->columnExists('promissorias', 'numero_parcela')) {
            $this->addColumn('promissorias', 'numero_parcela', 'INT UNSIGNED DEFAULT 1 AFTER parcela');
        }

        if (!$this->columnExists('promissorias', 'total_parcelas')) {
            $this->addColumn('promissorias', 'total_parcelas', 'INT UNSIGNED DEFAULT 1 AFTER numero_parcela');
        }

        if (!$this->columnExists('promissorias', 'data_pagamento')) {
            $this->addColumn('promissorias', 'data_pagamento', 'DATE NULL AFTER pago');
        }

        // 2. Migrar dados de "X de Y" para numero_parcela e total_parcelas
        $this->execute("
            UPDATE promissorias
            SET numero_parcela = CAST(SUBSTRING_INDEX(parcela, ' de ', 1) AS UNSIGNED),
                total_parcelas = CAST(SUBSTRING_INDEX(parcela, ' de ', -1) AS UNSIGNED)
            WHERE parcela IS NOT NULL
            AND parcela LIKE '% de %'
            AND numero_parcela IS NULL OR numero_parcela = 1
        ");

        // Para registros sem formato "X de Y", definir como parcela 1 de 1
        $this->execute("
            UPDATE promissorias
            SET numero_parcela = 1,
                total_parcelas = 1
            WHERE parcela IS NULL
            OR parcela NOT LIKE '% de %'
        ");

        // 3. Renomear colunas (se existirem com nomes antigos)
        if ($this->columnExists('promissorias', 'data_venci') && !$this->columnExists('promissorias', 'data_vencimento')) {
            $this->renameColumnPreservingType('promissorias', 'data_venci', 'data_vencimento');
        }

        if ($this->columnExists('promissorias', 'valor') && !$this->columnExists('promissorias', 'valor_parcela')) {
            $this->renameColumnPreservingType('promissorias', 'valor', 'valor_parcela');
        }

        // 4. Adicionar coluna gerada para codigo_base
        if (!$this->columnExists('promissorias', 'codigo_base')) {
            try {
                $this->execute("
                    ALTER TABLE promissorias
                    ADD COLUMN codigo_base VARCHAR(20)
                    GENERATED ALWAYS AS (SUBSTRING_INDEX(codigo, '-', 1)) STORED
                    AFTER codigo
                ");
            } catch (\Exception $e) {
                // Se falhar (versão do MySQL não suporta), criar coluna normal
                $this->addColumn('promissorias', 'codigo_base', 'VARCHAR(20) NULL AFTER codigo');
                $this->execute("
                    UPDATE promissorias
                    SET codigo_base = SUBSTRING_INDEX(codigo, '-', 1)
                    WHERE codigo IS NOT NULL
                ");
            }
        }

        // 5. Criar índice para codigo_base
        $this->addIndexIfNotExists('promissorias', ['chave', 'codigo_base'], 'idx_promissorias_chave_codigo_base');

        // 6. Remover coluna parcela antiga (VARCHAR "2 de 4") se existir
        // Comentado para manter compatibilidade - remover manualmente após verificar migração
        // if ($this->columnExists('promissorias', 'parcela')) {
        //     $this->dropColumn('promissorias', 'parcela');
        // }

        // 7. Setar id_financeiro como NULL (não mais usado)
        if ($this->columnExists('promissorias', 'id_financeiro')) {
            $this->execute("UPDATE promissorias SET id_financeiro = NULL WHERE id_financeiro IS NOT NULL");
        }
    }

    public function down(): void
    {
        // Reverter coluna codigo_base
        if ($this->columnExists('promissorias', 'codigo_base')) {
            $this->dropIndexIfExists('promissorias', 'idx_promissorias_chave_codigo_base');
            $this->dropColumn('promissorias', 'codigo_base');
        }

        // Reverter renomeações
        if ($this->columnExists('promissorias', 'data_vencimento') && !$this->columnExists('promissorias', 'data_venci')) {
            $this->renameColumnPreservingType('promissorias', 'data_vencimento', 'data_venci');
        }

        if ($this->columnExists('promissorias', 'valor_parcela') && !$this->columnExists('promissorias', 'valor')) {
            $this->renameColumnPreservingType('promissorias', 'valor_parcela', 'valor');
        }

        // Remover colunas novas
        if ($this->columnExists('promissorias', 'data_pagamento')) {
            $this->dropColumn('promissorias', 'data_pagamento');
        }

        if ($this->columnExists('promissorias', 'total_parcelas')) {
            $this->dropColumn('promissorias', 'total_parcelas');
        }

        if ($this->columnExists('promissorias', 'numero_parcela')) {
            $this->dropColumn('promissorias', 'numero_parcela');
        }
    }
};
