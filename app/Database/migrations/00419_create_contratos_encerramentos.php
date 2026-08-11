<?php

use App\Database\Migration;

/**
 * Registra snapshots auditaveis do encerramento proporcional de contratos.
 */
return new class extends Migration
{
    private const PLANO_DEVOLUCAO_CONTRATO = '3.4.1.23';

    public function up(): void
    {
        if (!$this->columnExists('contratos_taxaseservicos', 'origem')) {
            $this->addColumnIfNotExists('contratos_taxaseservicos', 'origem', 'VARCHAR(12)', [
                'null' => false,
                'default' => 'contrato',
                'after' => 'valor_total',
            ]);
            $this->addIndexIfNotExists(
                'contratos_taxaseservicos',
                ['chave', 'id_contrato', 'origem'],
                'idx_cts_contrato_origem'
            );

            $this->db()->table('contratos_taxaseservicos')
                ->whereRaw("nome LIKE '%Devolucao%' OR nome LIKE '%Devolução%'")
                ->update(['origem' => 'devolucao']);
        }

        if (!$this->tableExists('contratos_encerramentos')) {
            $this->create('contratos_encerramentos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_contrato')->unsigned();
                $table->integer('id_funcionario')->unsigned()->nullable();
                $table->datetime('data_encerramento');
                $table->string('contagem', 7);
                $table->integer('base_dias')->unsigned();
                $table->decimal('total_original', 15, 2);
                $table->decimal('total_veiculos', 15, 2);
                $table->decimal('total_seguros', 15, 2);
                $table->decimal('total_taxas_contrato', 15, 2);
                $table->decimal('total_adicionais_devolucao', 15, 2);
                $table->decimal('desconto_original', 15, 2);
                $table->decimal('desconto_aplicado', 15, 2);
                $table->decimal('total_final', 15, 2);
                $table->decimal('principal_lancado', 15, 2);
                $table->decimal('diferenca', 15, 2);
                $table->enum('ajuste_tipo', ['R', 'D', 'N'])->default('N');
                $table->integer('id_financeiro_ajuste')->unsigned()->nullable();
                $table->longText('calculo_json');
                $table->timestamps();

                $table->unique(['chave', 'id_contrato'], 'uniq_contrato_encerramento');
                $table->index(['chave', 'data_encerramento'], 'idx_contrato_encerramento_data');
            });
        }

        if ($this->tableExists('planos_de_contas')) {
            $plano = $this->db()
                ->table('planos_de_contas')
                ->select(['id'])
                ->whereRaw('hierarquia = ?', [self::PLANO_DEVOLUCAO_CONTRATO])
                ->first();

            if (!$plano) {
                $this->db()->table('planos_de_contas')->insert([
                    'chave' => '0',
                    'hierarquia' => self::PLANO_DEVOLUCAO_CONTRATO,
                    'descricao_i18n' => json_encode([
                        'pt_BR' => 'Devolução/Reembolso de contrato',
                        'pt_PT' => 'Devolução/Reembolso de contrato',
                        'en_US' => 'Contract refund',
                        'es_ES' => 'Reembolso de contrato',
                        'it_IT' => 'Rimborso contratto',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'tipo' => 'D',
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->tableExists('planos_de_contas')) {
            $this->db()
                ->table('planos_de_contas')
                ->whereRaw('hierarquia = ?', [self::PLANO_DEVOLUCAO_CONTRATO])
                ->delete();
        }

        if ($this->tableExists('contratos_encerramentos')) {
            $this->drop('contratos_encerramentos');
        }

        if ($this->columnExists('contratos_taxaseservicos', 'origem')) {
            $this->dropIndexIfExists('contratos_taxaseservicos', 'idx_cts_contrato_origem');
            $this->dropColumnIfExists('contratos_taxaseservicos', 'origem');
        }
    }
};
