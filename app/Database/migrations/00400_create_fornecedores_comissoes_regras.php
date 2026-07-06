<?php

use App\Database\Migration;

/**
 * Cria regras de comissao especificas por fornecedor investidor.
 *
 * A regra do fornecedor pode ser padrao (id_grupo null) ou uma excecao por
 * grupo. O grupo continua sendo fallback quando nao houver regra especifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('fornecedores_comissoes_regras')) {
            $this->create('fornecedores_comissoes_regras', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_fornecedor')->unsigned();
                $table->integer('id_grupo')->unsigned()->nullable();
                $table->enum('comissao_tipo', [
                    'percentual_locadora',
                    'fixo_locadora',
                    'fixo_locadora_mensal',
                    'fixo_investidor_mensal'
                ]);
                $table->decimal('comissao_valor', 10, 2)->default(0);
                $table->boolean('ativo')->default(true);
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->datetime('updated_at')->nullable();

                $table->index('chave', 'idx_fcr_chave');
                $table->index(['chave', 'id_fornecedor'], 'idx_fcr_fornecedor');
                $table->index(['chave', 'id_fornecedor', 'id_grupo'], 'idx_fcr_fornecedor_grupo');
                $table->index(['chave', 'ativo'], 'idx_fcr_ativo');
            });
        }

        $this->addForeignKeyIfNotExists(
            'fornecedores_comissoes_regras',
            'id_fornecedor',
            'fornecedores',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_fcr_fornecedor'
        );

        $this->addForeignKeyIfNotExists(
            'fornecedores_comissoes_regras',
            'id_grupo',
            'grupos',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_fcr_grupo'
        );
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('fornecedores_comissoes_regras', 'fk_fcr_grupo');
        $this->dropForeignKeyIfExists('fornecedores_comissoes_regras', 'fk_fcr_fornecedor');
        $this->drop('fornecedores_comissoes_regras');
    }
};
