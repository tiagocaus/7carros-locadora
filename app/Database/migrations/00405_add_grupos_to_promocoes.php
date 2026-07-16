<?php

use App\Database\Migration;

/**
 * Permite restringir uma promocao a varios grupos do mesmo tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('promocoes', 'todos_grupos', 'TINYINT(1)', [
            'null' => false,
            'default' => 1,
            'after' => 'status',
            'comment' => '1: promocao valida para todos os grupos; 0: usa promocoes_grupos.',
        ]);

        if (!$this->tableExists('promocoes_grupos')) {
            $this->create('promocoes_grupos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_promocao')->unsigned();
                $table->integer('id_grupo')->unsigned();
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

                $table->unique(['id_promocao', 'id_grupo'], 'uk_promocao_grupo');
                $table->index('chave', 'idx_promocoes_grupos_chave');
                $table->index('id_promocao', 'idx_promocoes_grupos_promocao');
                $table->index('id_grupo', 'idx_promocoes_grupos_grupo');
            });
        }

        $this->addForeignKeyIfNotExists(
            'promocoes_grupos',
            'id_promocao',
            'promocoes',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_promocoes_grupos_promocao'
        );
        $this->addForeignKeyIfNotExists(
            'promocoes_grupos',
            'id_grupo',
            'grupos',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_promocoes_grupos_grupo'
        );
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('promocoes_grupos', 'fk_promocoes_grupos_grupo');
        $this->dropForeignKeyIfExists('promocoes_grupos', 'fk_promocoes_grupos_promocao');
        $this->drop('promocoes_grupos');
        $this->dropColumnIfExists('promocoes', 'todos_grupos');
    }
};
