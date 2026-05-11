<?php

/**
 * Migration 00277: Adicionar colunas de taxa de cobranca ao financeiro
 *
 * Rastreia taxas de operadoras (cartao, gateway) retidas sobre receitas.
 * - valor_taxa: valor retido pela operadora nesta parcela
 * - valor_liquido: GENERATED (valor_total - valor_taxa) = valor efetivo recebido
 * - snapshots: config da forma de pagamento no momento da criacao (auditoria)
 *
 * Retrocompativel: registros antigos ficam com valor_taxa=0, valor_liquido=valor_total.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // valor_taxa: valor total da taxa retida pela operadora nesta parcela
        $this->addColumnIfNotExists('financeiro', 'valor_taxa', 'DECIMAL(10,2)', [
            'null' => false,
            'default' => 0.00,
            'after' => 'valor_total'
        ]);

        // valor_liquido: GENERATED STORED = valor_total - valor_taxa
        // Sempre consistente, sem risco de dessincronizar
        if (!$this->columnExists('financeiro', 'valor_liquido')) {
            $this->execute(
                "ALTER TABLE financeiro ADD COLUMN valor_liquido DECIMAL(15,2) "
                . "AS (valor_total - valor_taxa) STORED AFTER valor_taxa"
            );
        }

        // Snapshots das taxas da forma de pagamento no momento da criacao (auditoria)
        $this->addColumnIfNotExists('financeiro', 'taxa_percentual_snapshot', 'DECIMAL(5,2)', [
            'null' => true,
            'after' => 'valor_liquido'
        ]);

        $this->addColumnIfNotExists('financeiro', 'taxa_fixa_snapshot', 'DECIMAL(10,2)', [
            'null' => true,
            'after' => 'taxa_percentual_snapshot'
        ]);

        $this->addColumnIfNotExists('financeiro', 'taxa_fixa_parcela_snapshot', 'DECIMAL(10,2)', [
            'null' => true,
            'after' => 'taxa_fixa_snapshot'
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('financeiro', 'taxa_fixa_parcela_snapshot');
        $this->dropColumnIfExists('financeiro', 'taxa_fixa_snapshot');
        $this->dropColumnIfExists('financeiro', 'taxa_percentual_snapshot');
        $this->dropColumnIfExists('financeiro', 'valor_liquido');
        $this->dropColumnIfExists('financeiro', 'valor_taxa');
    }
};
