<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna id_veiculo em financeiro
 *
 * Permite vincular registros financeiros diretamente ao veiculo ativo
 * no momento da criacao. Essencial para rastreabilidade em substituicoes
 * veiculares: parcelas criadas antes da troca ficam no veiculo antigo,
 * parcelas criadas apos ficam no novo veiculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->columnExists('financeiro', 'id_veiculo')) {
            $this->alter('financeiro', function ($table) {
                $table->addColumn('`id_veiculo` INT UNSIGNED NULL COMMENT "Veiculo ativo no momento da criacao" AFTER `id_locacao`');
            });
        }

        if (!$this->indexExists('financeiro', 'idx_financeiro_veiculo')) {
            $this->alter('financeiro', function ($table) {
                $table->index(['chave', 'id_veiculo'], 'idx_financeiro_veiculo');
            });
        }

        if (!$this->foreignKeyExists('financeiro', 'fk_financeiro_id_veiculo')) {
            $this->alter('financeiro', function ($table) {
                $table->foreign('id_veiculo')
                    ->on('veiculos')
                    ->references('id')
                    ->onDelete('SET NULL')
                    ->onUpdate('CASCADE');
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('financeiro', 'fk_financeiro_id_veiculo')) {
            $this->db()->rawQuery('ALTER TABLE financeiro DROP FOREIGN KEY fk_financeiro_id_veiculo');
        }

        if ($this->indexExists('financeiro', 'idx_financeiro_veiculo')) {
            $this->db()->rawQuery('ALTER TABLE financeiro DROP INDEX idx_financeiro_veiculo');
        }

        if ($this->columnExists('financeiro', 'id_veiculo')) {
            $this->alter('financeiro', function ($table) {
                $table->dropColumn('id_veiculo');
            });
        }
    }
};
