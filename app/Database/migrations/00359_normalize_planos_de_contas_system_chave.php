<?php

/**
 * Migration 00359: Normalizar chave dos planos de contas do sistema
 *
 * Planos globais do sistema devem usar chave = '0'. Registros historicos com
 * chave NULL ficam invisiveis para consultas com withGlobals().
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('planos_de_contas') || !$this->columnExists('planos_de_contas', 'chave')) {
            return;
        }

        $this->db()
            ->table('planos_de_contas')
            ->whereRaw('chave IS NULL')
            ->update(['chave' => '0']);

        $this->modifyColumn('planos_de_contas', 'chave', 'VARCHAR(45)', [
            'null' => false,
            'default' => '0',
        ]);
    }

    public function down(): void
    {
        if (!$this->tableExists('planos_de_contas') || !$this->columnExists('planos_de_contas', 'chave')) {
            return;
        }

        $this->modifyColumn('planos_de_contas', 'chave', 'VARCHAR(45)', [
            'null' => true,
            'default' => null,
        ]);
    }
};
