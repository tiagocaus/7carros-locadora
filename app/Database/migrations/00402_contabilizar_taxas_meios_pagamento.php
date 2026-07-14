<?php

use App\Database\Migration;

/**
 * Contabiliza taxas retidas por meios de pagamento como despesas vinculadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->adicionarColunaIdempotente('formas_pagamento', 'id_plano_de_conta_taxa', 'INT', [
            'unsigned' => true,
            'null' => true,
            'after' => 'taxa_percentual_parcela',
        ]);
        $this->addIndexIfNotExists('formas_pagamento', ['chave', 'id_plano_de_conta_taxa'], 'idx_fp_chave_plano_taxa');
        $this->addForeignKeyIfNotExists(
            'formas_pagamento',
            'id_plano_de_conta_taxa',
            'planos_de_contas',
            'id',
            'SET NULL',
            'CASCADE',
            'fk_fp_plano_taxa'
        );

        $this->adicionarColunaIdempotente('financeiro', 'id_financeiro_taxa_origem', 'INT', [
            'unsigned' => true,
            'null' => true,
            'after' => 'id_financeiro_origem',
        ]);
        $this->adicionarColunaIdempotente('financeiro', 'id_gateway', 'INT', [
            'unsigned' => true,
            'null' => true,
            'after' => 'id_forma_pagamento',
        ]);
        $this->addIndexIfNotExists('financeiro', ['chave', 'id_gateway'], 'idx_fin_chave_gateway');

        if (!$this->indexExists('financeiro', 'uniq_fin_taxa_origem')) {
            $this->pdo->exec(
                'ALTER TABLE financeiro ADD UNIQUE INDEX uniq_fin_taxa_origem (chave, id_financeiro_taxa_origem)'
            );
        }

        $this->addForeignKeyIfNotExists(
            'financeiro',
            'id_financeiro_taxa_origem',
            'financeiro',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_fin_taxa_origem'
        );
        $this->addForeignKeyIfNotExists(
            'financeiro',
            'id_gateway',
            'gateways_pagamento',
            'id',
            'SET NULL',
            'CASCADE',
            'fk_fin_gateway'
        );

        $descricao = json_encode([
            'pt_BR' => 'Taxas de meios de pagamento',
            'en_US' => 'Payment processing fees',
            'es_ES' => 'Tasas de medios de pago',
            'it_IT' => 'Commissioni sui mezzi di pagamento',
            'pt_PT' => 'Taxas de meios de pagamento',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            "UPDATE planos_de_contas SET descricao_i18n = ? WHERE chave = '0' AND hierarquia = '3.4.1.21' AND tipo = 'D'"
        );
        $stmt->execute([$descricao]);
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('financeiro', 'fk_fin_gateway');
        $this->dropForeignKeyIfExists('financeiro', 'fk_fin_taxa_origem');
        $this->dropIndexIfExists('financeiro', 'idx_fin_chave_gateway');
        $this->dropIndexIfExists('financeiro', 'uniq_fin_taxa_origem');
        $this->dropColumnIfExists('financeiro', 'id_gateway');
        $this->dropColumnIfExists('financeiro', 'id_financeiro_taxa_origem');

        $this->dropForeignKeyIfExists('formas_pagamento', 'fk_fp_plano_taxa');
        $this->dropIndexIfExists('formas_pagamento', 'idx_fp_chave_plano_taxa');
        $this->dropColumnIfExists('formas_pagamento', 'id_plano_de_conta_taxa');
    }

    private function adicionarColunaIdempotente(string $tabela, string $coluna, string $tipo, array $opcoes): void
    {
        try {
            $this->addColumnIfNotExists($tabela, $coluna, $tipo, $opcoes);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1060) {
                throw $e;
            }
        }
    }
};
