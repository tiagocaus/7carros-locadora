<?php

use App\Database\Migration;

/**
 * Normaliza o cadastro de promocoes para uso multi-tenant e multi-canal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->execute("UPDATE promocoes SET codigo = UPPER(TRIM(codigo))");

        $this->execute("
            UPDATE promocoes
            SET onde_exibir = TRIM(BOTH ',' FROM CONCAT_WS(',',
                IF(FIND_IN_SET('SIS', UPPER(onde_exibir)), 'SIS', NULL),
                IF(FIND_IN_SET('SITE', UPPER(onde_exibir)) OR FIND_IN_SET('SIT', UPPER(onde_exibir)), 'SITE', NULL),
                IF(FIND_IN_SET('APP', UPPER(onde_exibir)), 'APP', NULL)
            ))
        ");

        $this->execute("UPDATE promocoes SET validade = NULL WHERE validade = '0000-00-00'");
        $this->modifyColumn('promocoes', 'validade', 'DATE', [
            'null' => true,
            'comment' => 'Data limite inclusiva para aplicar a promocao; NULL sem prazo.',
        ]);

        $this->dropIndexIfExists('promocoes', 'codigo');
        $this->table('promocoes', function ($table) {
            $table->unique(['chave', 'codigo'], 'uniq_promocoes_chave_codigo');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('promocoes', 'uniq_promocoes_chave_codigo');
        $this->table('promocoes', function ($table) {
            $table->unique('codigo', 'codigo');
        });

        $this->execute("UPDATE promocoes SET validade = '9999-12-31' WHERE validade IS NULL");
        $this->modifyColumn('promocoes', 'validade', 'DATE', [
            'null' => false,
            'comment' => 'Determina ate quando a promocao esta valida.',
        ]);
    }
};
