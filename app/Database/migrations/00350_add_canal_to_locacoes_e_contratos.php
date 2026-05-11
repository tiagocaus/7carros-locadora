<?php

/**
 * Migration: Adiciona campo `canal` em locacoes e contratos.
 *
 * Justificativa: spec 8.2 (Origem das Locacoes) exige classificar por canal:
 * balcao, telefone, website, whatsapp, app, ota, parceiro, indicacao,
 * redes_sociais, google_ads, outros.
 *
 * Sem ENUM no BD para flexibilidade de novos canais sem migration adicional.
 * Validacao dos valores eh feita em codigo (ex.: nos formularios).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('locacoes', 'canal', 'VARCHAR(20)', [
            'null' => true,
            'default' => null,
            'after' => 'promocao_codigo',
        ]);

        $this->addColumnIfNotExists('contratos', 'canal', 'VARCHAR(20)', [
            'null' => true,
            'default' => null,
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('locacoes', 'canal');
        $this->dropColumnIfExists('contratos', 'canal');
    }
};
