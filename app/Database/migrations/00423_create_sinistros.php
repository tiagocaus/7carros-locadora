<?php

use App\Database\Migration;

/**
 * Cadastro simples de sinistros vinculados a contratos ou locacoes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('sinistros')) {
            $this->create('sinistros', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_contrato')->unsigned()->nullable();
                $table->integer('id_locacao')->unsigned()->nullable();
                $table->integer('id_veiculo')->unsigned();
                $table->integer('id_financeiro')->unsigned()->nullable();
                $table->integer('id_funcionario')->unsigned()->nullable();
                $table->datetime('data_ocorrencia');
                $table->string('tipo', 30);
                $table->addColumn('`descricao` MEDIUMTEXT NOT NULL');
                $table->decimal('valor_estimado', 15, 2)->nullable();
                $table->addColumn('`observacoes` MEDIUMTEXT NULL');
                $table->addColumn("`status` CHAR(1) NOT NULL DEFAULT 'A'");
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->addColumn('`updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

                $table->unique('id_financeiro', 'uniq_sinistros_financeiro');
                $table->index(['chave', 'id_contrato'], 'idx_sinistros_chave_contrato');
                $table->index(['chave', 'id_locacao'], 'idx_sinistros_chave_locacao');
                $table->index(['chave', 'id_veiculo'], 'idx_sinistros_chave_veiculo');
                $table->index(['chave', 'status'], 'idx_sinistros_chave_status');
                $table->index(['chave', 'data_ocorrencia'], 'idx_sinistros_chave_data');

                $table->foreign('id_contrato')->references('id')->on('contratos')->cascadeOnDelete();
                $table->foreign('id_locacao')->references('id')->on('locacoes')->cascadeOnDelete();
                $table->foreign('id_veiculo')->references('id')->on('veiculos')->restrictOnDelete();
                $table->foreign('id_financeiro')->references('id')->on('financeiro')->nullOnDelete();
                $table->foreign('id_funcionario')->references('id')->on('funcionarios')->nullOnDelete();
            });
        }

        $this->execute(<<<'SQL'
UPDATE planos_de_contas
SET descricao_i18n = JSON_SET(
    COALESCE(descricao_i18n, JSON_OBJECT()),
    '$.pt_BR', 'Avarias e Sinistros',
    '$.pt_PT', 'Avarias e Sinistros',
    '$.en_US', 'Damages and Claims',
    '$.es_ES', 'Averías y Siniestros',
    '$.it_IT', 'Danni e Sinistri'
)
WHERE hierarquia = '4.2.2.01'
SQL);
    }

    public function down(): void
    {
        $this->drop('sinistros');

        $this->execute(<<<'SQL'
UPDATE planos_de_contas
SET descricao_i18n = JSON_SET(
    COALESCE(descricao_i18n, JSON_OBJECT()),
    '$.pt_BR', 'Avarias',
    '$.pt_PT', 'Avarias',
    '$.en_US', 'Damages',
    '$.es_ES', 'Averías',
    '$.it_IT', 'Danni'
)
WHERE hierarquia = '4.2.2.01'
SQL);
    }
};
