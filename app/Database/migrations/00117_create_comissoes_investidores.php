<?php

/**
 * Migration 00117: Criar tabela comissoes_investidores
 *
 * Armazena o historico de comissoes calculadas para investidores.
 * Quando uma comissao e marcada como paga, um lancamento e criado
 * na tabela financeiro (abordagem hibrida).
 *
 * Origens de comissao:
 * - locacao: Calculada quando fatura de locacao e paga
 * - contrato: Calculada quando fatura de contrato e paga
 * - mensal: Gerada via cron no 1o dia do mes
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('comissoes_investidores')) {
            $this->create('comissoes_investidores', function ($table) {
                // Chave primaria
                $table->id();

                // Tenant
                $table->string('chave', 45);

                // Relacionamentos principais
                $table->integer('id_fornecedor')->unsigned();
                $table->integer('id_veiculo')->unsigned();
                $table->integer('id_grupo')->unsigned();

                // Origem da comissao
                $table->enum('tipo_origem', ['locacao', 'contrato', 'mensal']);
                $table->integer('id_locacao')->unsigned()->nullable();
                $table->integer('id_contrato')->unsigned()->nullable();
                $table->integer('id_financeiro_origem')->unsigned()->nullable();

                // Valores de calculo
                $table->decimal('valor_base', 12, 2);
                $table->enum('comissao_tipo', [
                    'percentual_locadora',
                    'fixo_locadora',
                    'fixo_locadora_mensal',
                    'fixo_investidor_mensal'
                ]);
                $table->decimal('comissao_percentual', 5, 2)->nullable();
                $table->decimal('comissao_valor_fixo', 10, 2)->nullable();
                $table->decimal('valor_comissao_locadora', 12, 2);
                $table->decimal('valor_repasse_investidor', 12, 2);

                // Status e datas
                $table->enum('status', ['pendente', 'pago', 'cancelado'])->default('pendente');
                $table->date('data_referencia');
                $table->date('data_pagamento')->nullable();

                // Split automatico
                $table->integer('split_aplicado')->unsigned()->default(0);
                $table->string('split_transaction_id', 255)->nullable();

                // Vinculo com financeiro (abordagem hibrida)
                $table->integer('id_financeiro')->unsigned()->nullable();

                // Timestamps
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->datetime('updated_at')->nullable();

                // Indices
                $table->index('chave', 'idx_ci_chave');
                $table->index('id_fornecedor', 'idx_ci_fornecedor');
                $table->index(['chave', 'status'], 'idx_ci_status');
                $table->index(['chave', 'data_referencia'], 'idx_ci_data_ref');
                $table->index('id_financeiro', 'idx_ci_financeiro');
                $table->index('id_financeiro_origem', 'idx_ci_financeiro_origem');
            });

            // Adicionar Foreign Keys separadamente para evitar problemas de ordem
            $this->execute("
                ALTER TABLE comissoes_investidores
                ADD CONSTRAINT fk_ci_fornecedor
                FOREIGN KEY (id_fornecedor) REFERENCES fornecedores(id) ON DELETE CASCADE
            ");

            $this->execute("
                ALTER TABLE comissoes_investidores
                ADD CONSTRAINT fk_ci_veiculo
                FOREIGN KEY (id_veiculo) REFERENCES veiculos(id) ON DELETE CASCADE
            ");

            $this->execute("
                ALTER TABLE comissoes_investidores
                ADD CONSTRAINT fk_ci_grupo
                FOREIGN KEY (id_grupo) REFERENCES grupos(id) ON DELETE CASCADE
            ");

            $this->execute("
                ALTER TABLE comissoes_investidores
                ADD CONSTRAINT fk_ci_financeiro
                FOREIGN KEY (id_financeiro) REFERENCES financeiro(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(): void
    {
        // Apenas dropar a tabela (foreign keys sao removidas automaticamente)
        $this->drop('comissoes_investidores');
    }
};
