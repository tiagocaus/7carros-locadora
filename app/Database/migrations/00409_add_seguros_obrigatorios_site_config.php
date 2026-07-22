<?php

use App\Database\Migration;

/**
 * Permite definir, de forma independente, os seguros obrigatorios no site.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('site_config', function ($table) {
            $table->boolean('seguro_carro_obrigatorio')
                ->default(0)
                ->after('pagamento_antecipado');
            $table->boolean('seguro_terceiros_obrigatorio')
                ->default(0)
                ->after('seguro_carro_obrigatorio');
        });
    }

    public function down(): void
    {
        $this->table('site_config', function ($table) {
            $table->dropColumn('seguro_terceiros_obrigatorio');
            $table->dropColumn('seguro_carro_obrigatorio');
        });
    }
};
