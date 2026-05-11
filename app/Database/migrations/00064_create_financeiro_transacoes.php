<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Cria tabela financeiro_transacoes
        $this->create('financeiro_transacoes', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('id_financeiro')->unsigned()->nullable();
            $table->string('gateway', 50);
            $table->string('external_id', 100)->nullable();
            $table->enum('type', ['charge', 'refund', 'webhook', 'callback']);
            $table->string('status', 50)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->json('payload')->nullable();
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_ft_chave');
            $table->index(['chave', 'id_financeiro'], 'idx_ft_chave_financeiro');
            $table->index(['chave', 'gateway'], 'idx_ft_chave_gateway');
            $table->index('external_id', 'idx_ft_external_id');

            $table->foreign('id_financeiro')
                ->references('id')
                ->on('financeiro')
                ->cascadeOnDelete()
                ->name('fk_ft_financeiro');
        });

        // Remove colunas obsoletas do financeiro
        $this->dropColumnIfExists('financeiro', 'e_lancamento');
        $this->dropColumnIfExists('financeiro', 'token');
        $this->dropColumnIfExists('financeiro', 'id_transacao');
        $this->dropColumnIfExists('financeiro', 'array');
    }

    public function down(): void
    {
        // Recria colunas removidas do financeiro
        $this->addColumnIfNotExists('financeiro', 'array', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('financeiro', 'id_transacao', 'VARCHAR(100)', ['null' => true]);
        $this->addColumnIfNotExists('financeiro', 'token', 'VARCHAR(100)', ['null' => true]);
        $this->addColumnIfNotExists('financeiro', 'e_lancamento', 'VARCHAR(1)', ['null' => true]);

        // Remove tabela financeiro_transacoes
        $this->drop('financeiro_transacoes');
    }
};
