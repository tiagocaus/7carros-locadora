<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas array_avalistas e array_testemunhas em locacoes
 *
 * Permite registrar avalistas e testemunhas da locacao em formato JSON.
 * Estrutura: [{"id": 123, "nome": "Joao", "cc": "123.456.789-00"}]
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->columnExists('locacoes', 'array_avalistas')) {
            $this->alter('locacoes', function ($table) {
                $table->addColumn('`array_avalistas` MEDIUMTEXT NULL COMMENT "JSON: [{id, nome, cc}]" AFTER `array_fiadores`');
            });
        }

        if (!$this->columnExists('locacoes', 'array_testemunhas')) {
            $this->alter('locacoes', function ($table) {
                $table->addColumn('`array_testemunhas` MEDIUMTEXT NULL COMMENT "JSON: [{id, nome, cc}]" AFTER `array_avalistas`');
            });
        }
    }

    public function down(): void
    {
        if ($this->columnExists('locacoes', 'array_testemunhas')) {
            $this->alter('locacoes', function ($table) {
                $table->dropColumn('array_testemunhas');
            });
        }

        if ($this->columnExists('locacoes', 'array_avalistas')) {
            $this->alter('locacoes', function ($table) {
                $table->dropColumn('array_avalistas');
            });
        }
    }
};
