<?php

/**
 * Migration 00275: Corrigir tipo do campo bandeira em clientes_cartoes
 *
 * O campo bandeira era INT(2) mas o código armazena strings (VISA, MASTERCARD, etc).
 * MySQL truncava silenciosamente para 0. Esta migration corrige o tipo para VARCHAR(20).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Alterar tipo da coluna de INT para VARCHAR
        $this->modifyColumn('clientes_cartoes', 'bandeira', 'VARCHAR(20)', [
            'null' => false,
            'default' => 'OUTROS'
        ]);

        // Corrigir registros existentes com valor truncado (0)
        $this->execute(
            "UPDATE clientes_cartoes SET bandeira = 'OUTROS' WHERE bandeira = '0' OR bandeira = '' OR bandeira IS NULL"
        );
    }

    public function down(): void
    {
        $this->modifyColumn('clientes_cartoes', 'bandeira', 'INT(2)', [
            'null' => false
        ]);
    }
};
