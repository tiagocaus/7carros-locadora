<?php

use App\Database\Migration;

/**
 * Migration 00157: Adicionar campos de taxas na tabela formas_pagamento
 *
 * Novos campos:
 * - taxa_fixa: Taxa fixa total, diluída entre as parcelas
 * - taxa_fixa_parcela: Taxa fixa cobrada em cada parcela
 * - taxa_percentual_parcela: Percentual cobrado sobre cada parcela
 * - desconto_antecipacao_dias: Quantidade de dias antes do vencimento para dar desconto
 * - desconto_antecipacao_percentual: Percentual de desconto para pagamento antecipado
 */
return new class extends Migration
{
    public function up(): void
    {
        // Taxa fixa total - será diluída entre as parcelas
        // Ex: R$ 10,00 em 2 parcelas = R$ 5,00 por parcela
        if (!$this->columnExists('formas_pagamento', 'taxa_fixa')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN taxa_fixa DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER juros_por_dia
            ");
        }

        // Taxa fixa por parcela - cobrada em cada parcela
        // Ex: R$ 2,50 em 2 parcelas = R$ 2,50 + R$ 2,50 = R$ 5,00 total
        if (!$this->columnExists('formas_pagamento', 'taxa_fixa_parcela')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN taxa_fixa_parcela DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER taxa_fixa
            ");
        }

        // Taxa percentual por parcela - % cobrado sobre cada parcela
        // Ex: 5% sobre parcela de R$ 100,00 = R$ 5,00 por parcela
        if (!$this->columnExists('formas_pagamento', 'taxa_percentual_parcela')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN taxa_percentual_parcela DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER taxa_fixa_parcela
            ");
        }

        // Dias antes do vencimento para aplicar desconto
        // Ex: 5 = desconto se pagar 5 dias antes do vencimento
        if (!$this->columnExists('formas_pagamento', 'desconto_antecipacao_dias')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN desconto_antecipacao_dias INT NOT NULL DEFAULT 0 AFTER taxa_percentual_parcela
            ");
        }

        // Percentual de desconto para pagamento antecipado
        // Ex: 3.00 = 3% de desconto
        if (!$this->columnExists('formas_pagamento', 'desconto_antecipacao_percentual')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN desconto_antecipacao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER desconto_antecipacao_dias
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('formas_pagamento', 'desconto_antecipacao_percentual')) {
            $this->execute("ALTER TABLE formas_pagamento DROP COLUMN desconto_antecipacao_percentual");
        }

        if ($this->columnExists('formas_pagamento', 'desconto_antecipacao_dias')) {
            $this->execute("ALTER TABLE formas_pagamento DROP COLUMN desconto_antecipacao_dias");
        }

        if ($this->columnExists('formas_pagamento', 'taxa_percentual_parcela')) {
            $this->execute("ALTER TABLE formas_pagamento DROP COLUMN taxa_percentual_parcela");
        }

        if ($this->columnExists('formas_pagamento', 'taxa_fixa_parcela')) {
            $this->execute("ALTER TABLE formas_pagamento DROP COLUMN taxa_fixa_parcela");
        }

        if ($this->columnExists('formas_pagamento', 'taxa_fixa')) {
            $this->execute("ALTER TABLE formas_pagamento DROP COLUMN taxa_fixa");
        }
    }
};
