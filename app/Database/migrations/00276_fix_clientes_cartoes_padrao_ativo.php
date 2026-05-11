<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Converter padrao de CHAR(1) 'S'/'N' para TINYINT(1) 1/0
        if ($this->columnExists('clientes_cartoes', 'padrao')) {
            $this->db()
                ->table('clientes_cartoes')
                ->whereRaw("padrao = 'S'")
                ->update(['padrao' => '1']);

            $this->db()
                ->table('clientes_cartoes')
                ->whereRaw("padrao != '1'")
                ->update(['padrao' => '0']);

            $this->modifyColumn('clientes_cartoes', 'padrao', 'TINYINT(1)', [
                'null' => false,
                'default' => 0,
            ]);
        }

        // Remover registros inativos e dropar coluna ativo
        if ($this->columnExists('clientes_cartoes', 'ativo')) {
            $this->db()
                ->table('clientes_cartoes')
                ->whereRaw("ativo = 'N'")
                ->delete();

            $this->table('clientes_cartoes', function ($table) {
                $table->dropColumn('ativo');
            });
        }
    }

    public function down(): void
    {
        // Restaurar coluna ativo
        $this->addColumnIfNotExists('clientes_cartoes', 'ativo', 'CHAR(1)', [
            'null' => false,
            'default' => 'S',
        ]);

        // Reverter padrao para CHAR(1)
        if ($this->columnExists('clientes_cartoes', 'padrao')) {
            $this->db()
                ->table('clientes_cartoes')
                ->whereRaw("padrao = 1")
                ->update(['padrao' => 'S']);

            $this->db()
                ->table('clientes_cartoes')
                ->whereRaw("padrao != 'S'")
                ->update(['padrao' => 'N']);

            $this->modifyColumn('clientes_cartoes', 'padrao', 'CHAR(1)', [
                'null' => false,
                'default' => 'N',
            ]);
        }
    }
};
