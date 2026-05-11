<?php

/**
 * Migration 00333: Define default 'Sedan.jpg' em veiculos.diagrama.
 *
 * A coluna era NOT NULL sem default, e o codigo atual nao preenche o campo
 * (feature de diagrama foi removida do app, mas a coluna ficou no schema).
 * Veiculos novos falhavam ao salvar com "Field 'diagrama' doesn't have a default value".
 *
 * Mantem NOT NULL e adiciona default 'Sedan.jpg' (filename mais comum nos
 * 9374 registros existentes). Os dados antigos nao sao tocados.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyColumn('veiculos', 'diagrama', 'VARCHAR(25)', [
            'null' => false,
            'default' => 'Sedan.jpg',
        ]);
    }

    public function down(): void
    {
        $this->modifyColumn('veiculos', 'diagrama', 'VARCHAR(25)', [
            'null' => false,
        ]);
    }
};
