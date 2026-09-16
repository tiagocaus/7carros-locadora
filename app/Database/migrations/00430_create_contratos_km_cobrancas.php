<?php
use App\Database\Migration;

return new class extends Migration {
    public function up(): void
    {
        foreach (['data_referencia' => 'DATETIME', 'operacao_km' => 'VARCHAR(64)', 'resultado_km' => 'LONGTEXT'] as $coluna => $tipo) {
            $this->addColumnIfNotExists('contratos_odometros', $coluna, $tipo, ['null' => true]);
        }
        $this->addColumnIfNotExists('contratos_odometros', 'fronteira_km', 'TINYINT(1)', ['null' => false, 'default' => 0]);
        if (!$this->indexExists('contratos_odometros', 'uniq_odometro_operacao_km')) {
            $this->table('contratos_odometros', fn($t) => $t->unique(['chave', 'operacao_km'], 'uniq_odometro_operacao_km'));
        }
        if (!$this->tableExists('contratos_km_ciclos')) {
            $this->create('contratos_km_ciclos', function ($t) {
                $t->id(); $t->string('chave',45); $t->integer('id_contrato')->unsigned();
                $t->integer('id_contrato_veiculo')->unsigned();
                $t->datetime('inicio'); $t->datetime('fim'); $t->string('contagem',7);
                $t->integer('franquia')->unsigned(); $t->decimal('valor_km',10,2);
                $t->longText('calculo_json'); $t->timestamps();
                $t->unique(['chave','id_contrato_veiculo','inicio'], 'uniq_km_ciclo');
                $t->index(['chave','id_contrato'], 'idx_km_ciclo_contrato');
                $t->foreign('id_contrato')->references('id')->on('contratos')->onDelete('CASCADE');
                $t->foreign('id_contrato_veiculo')->references('id')->on('contratos_veiculos')->onDelete('CASCADE');
            });
        }
        $this->addColumnIfNotExists('contratos_km_ciclos','id_financeiro_acerto','INT UNSIGNED',['null'=>true]);
        $this->addColumnIfNotExists('contratos_km_ciclos','acerto_json','LONGTEXT',['null'=>true]);
        if (!$this->tableExists('contratos_km_apuracoes')) {
            $this->create('contratos_km_apuracoes', function ($t) {
                $t->id(); $t->string('chave',45); $t->integer('id_ciclo')->unsigned();
                $t->integer('id_leitura')->unsigned(); $t->integer('id_financeiro')->unsigned()->nullable();
                $t->integer('id_financeiro_item')->unsigned()->nullable();
                $t->decimal('valor',15,2); $t->longText('calculo_json'); $t->timestamps();
                $t->unique(['chave','id_ciclo','id_leitura'], 'uniq_km_apuracao');
                $t->index(['chave','id_financeiro'], 'idx_km_apuracao_financeiro');
                $t->foreign('id_ciclo')->references('id')->on('contratos_km_ciclos')->onDelete('CASCADE');
                $t->foreign('id_leitura')->references('id')->on('contratos_odometros')->onDelete('RESTRICT');
                $t->foreign('id_financeiro')->references('id')->on('financeiro')->onDelete('SET NULL');
                $t->foreign('id_financeiro_item')->references('id')->on('financeiro_itens')->onDelete('SET NULL');
            });
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Migration aditiva: desative a emissão sem apagar o histórico financeiro.');
    }
};
