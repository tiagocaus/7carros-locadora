<?php

/**
 * Migration 00330: Alinha colunas e indices em paises e assinaturas com schema canonico
 *
 * paises:
 *   - codigo: idx_codigo (regular) -> uk_codigo (UNIQUE) — codigo ISO de 2 letras precisa ser unico
 *   - updated_at: adicionar DEFAULT CURRENT_TIMESTAMP
 *
 * assinaturas:
 *   - arquivo: longtext -> varchar(255) (coluna guarda apenas o filename, max ~36 chars em prod)
 *
 * Idempotente: cada operacao verifica o estado antes de executar.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. paises.codigo: trocar indice regular por UNIQUE
        $this->dropIndexIfExists('paises', 'idx_codigo');
        if (!$this->indexExists('paises', 'uk_codigo')) {
            $this->execute("ALTER TABLE `paises` ADD UNIQUE KEY `uk_codigo` (`codigo`)");
        }

        // 2. paises.updated_at: adicionar default CURRENT_TIMESTAMP
        $this->modifyColumn('paises', 'updated_at', 'TIMESTAMP', [
            'null' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ]);

        // 3. assinaturas.arquivo: longtext -> varchar(255)
        $this->modifyColumn('assinaturas', 'arquivo', 'VARCHAR(255)', [
            'null' => false,
        ]);
    }

    public function down(): void
    {
        // 1. Reverter indice
        $this->dropIndexIfExists('paises', 'uk_codigo');
        $this->addIndexIfNotExists('paises', 'codigo', 'idx_codigo');

        // 2. Remover default de updated_at
        $this->modifyColumn('paises', 'updated_at', 'TIMESTAMP', [
            'null' => true,
        ]);

        // 3. Reverter arquivo para longtext
        $this->modifyColumn('assinaturas', 'arquivo', 'LONGTEXT', [
            'null' => false,
        ]);
    }
};
