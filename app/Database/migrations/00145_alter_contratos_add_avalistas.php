<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas array_avalistas e array_testemunhas em contratos
 *
 * Permite registrar avalistas e testemunhas do contrato em formato JSON.
 * Estrutura: [{"id": 123, "nome": "João", "cc": "123.456.789-00"}]
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar se coluna array_avalistas já existe
        if (!$this->columnExists('contratos', 'array_avalistas')) {
            $this->alter('contratos', function ($table) {
                $table->addColumn('`array_avalistas` MEDIUMTEXT NULL COMMENT "JSON: [{id, nome, cc}]" AFTER `array_fiadores`');
            });
        }

        // Verificar se coluna array_testemunhas já existe
        if (!$this->columnExists('contratos', 'array_testemunhas')) {
            $this->alter('contratos', function ($table) {
                $table->addColumn('`array_testemunhas` MEDIUMTEXT NULL COMMENT "JSON: [{id, nome, cc}]" AFTER `array_avalistas`');
            });
        }
    }

    public function down(): void
    {
        if ($this->columnExists('contratos', 'array_testemunhas')) {
            $this->alter('contratos', function ($table) {
                $table->dropColumn('array_testemunhas');
            });
        }

        if ($this->columnExists('contratos', 'array_avalistas')) {
            $this->alter('contratos', function ($table) {
                $table->dropColumn('array_avalistas');
            });
        }
    }
};
