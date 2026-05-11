<?php

use App\Database\Migration;

/**
 * Migration: Normalizar tabela checklist
 *
 * Adiciona colunas para o novo formato unificado de checklists:
 * - FK direta para locacoes/contratos (substituindo match por codigo)
 * - Campo momento (saida/chegada/nao definido)
 * - Campos unificados (questoes, vistoria, assinatura, obs) sem sufixo _saida/_chegada
 * - Tanque, odometro e funcionario responsavel
 */
return new class extends Migration
{
    public function up(): void
    {
        // FK para locacoes
        if (!$this->columnExists('checklist', 'id_locacao')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`id_locacao` INT UNSIGNED NULL COMMENT "FK locacao vinculada" AFTER `codigo`');
            });
        }

        // FK para contratos
        if (!$this->columnExists('checklist', 'id_contrato')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`id_contrato` INT UNSIGNED NULL COMMENT "FK contrato vinculado" AFTER `id_locacao`');
            });
        }

        // Momento: S=saida, C=chegada, N=nao definido
        if (!$this->columnExists('checklist', 'momento')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`momento` VARCHAR(1) NULL COMMENT "S=saida, C=chegada, N=nao definido" AFTER `tipo`');
            });
        }

        // Tanque de combustivel
        if (!$this->columnExists('checklist', 'tanque')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`tanque` VARCHAR(10) NULL COMMENT "Nivel: vazio, 1/4, 1/2, 3/4, cheio" AFTER `momento`');
            });
        }

        // Odometro
        if (!$this->columnExists('checklist', 'odometro')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`odometro` INT UNSIGNED NULL COMMENT "Leitura odometro no momento" AFTER `tanque`');
            });
        }

        // Questoes unificadas (sem sufixo _saida/_chegada)
        if (!$this->columnExists('checklist', 'questoes')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`questoes` MEDIUMTEXT NULL COMMENT "JSON questoes formato novo" AFTER `odometro`');
            });
        }

        // Vistoria unificada
        if (!$this->columnExists('checklist', 'vistoria')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`vistoria` LONGTEXT NULL COMMENT "JSON vistoria formato novo" AFTER `questoes`');
            });
        }

        // Assinatura unica por registro
        if (!$this->columnExists('checklist', 'assinatura_unica')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`assinatura_unica` MEDIUMTEXT NULL COMMENT "Assinatura do checklist" AFTER `vistoria`');
            });
        }

        // Observacoes unicas
        if (!$this->columnExists('checklist', 'obs_unica')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`obs_unica` MEDIUMTEXT NULL COMMENT "Observacoes do checklist" AFTER `assinatura_unica`');
            });
        }

        // Data/hora do checklist
        if (!$this->columnExists('checklist', 'data_checklist')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`data_checklist` DATETIME NULL COMMENT "Data/hora do checklist" AFTER `obs_unica`');
            });
        }

        // Funcionario responsavel
        if (!$this->columnExists('checklist', 'id_funcionario')) {
            $this->alter('checklist', function ($table) {
                $table->addColumn('`id_funcionario` INT UNSIGNED NULL COMMENT "Funcionario que realizou" AFTER `data_checklist`');
            });
        }

        // Indices
        if (!$this->indexExists('checklist', 'idx_checklist_id_locacao')) {
            $this->alter('checklist', function ($table) {
                $table->index(['id_locacao'], 'idx_checklist_id_locacao');
            });
        }

        if (!$this->indexExists('checklist', 'idx_checklist_id_contrato')) {
            $this->alter('checklist', function ($table) {
                $table->index(['id_contrato'], 'idx_checklist_id_contrato');
            });
        }

        if (!$this->indexExists('checklist', 'idx_checklist_momento')) {
            $this->alter('checklist', function ($table) {
                $table->index(['momento'], 'idx_checklist_momento');
            });
        }

        if (!$this->indexExists('checklist', 'idx_checklist_locacao_momento')) {
            $this->alter('checklist', function ($table) {
                $table->index(['id_locacao', 'momento'], 'idx_checklist_locacao_momento');
            });
        }

        if (!$this->indexExists('checklist', 'idx_checklist_contrato_momento')) {
            $this->alter('checklist', function ($table) {
                $table->index(['id_contrato', 'momento'], 'idx_checklist_contrato_momento');
            });
        }

        // Foreign keys
        if (!$this->foreignKeyExists('checklist', 'fk_checklist_id_locacao')) {
            $this->alter('checklist', function ($table) {
                $table->foreign('id_locacao')
                    ->on('locacoes')
                    ->references('id')
                    ->onDelete('SET NULL')
                    ->onUpdate('CASCADE');
            });
        }

        if (!$this->foreignKeyExists('checklist', 'fk_checklist_id_contrato')) {
            $this->alter('checklist', function ($table) {
                $table->foreign('id_contrato')
                    ->on('contratos')
                    ->references('id')
                    ->onDelete('SET NULL')
                    ->onUpdate('CASCADE');
            });
        }
    }

    public function down(): void
    {
        // Foreign keys
        if ($this->foreignKeyExists('checklist', 'fk_checklist_id_contrato')) {
            $this->execute('ALTER TABLE checklist DROP FOREIGN KEY fk_checklist_id_contrato');
        }

        if ($this->foreignKeyExists('checklist', 'fk_checklist_id_locacao')) {
            $this->execute('ALTER TABLE checklist DROP FOREIGN KEY fk_checklist_id_locacao');
        }

        // Indices compostos
        $indexes = [
            'idx_checklist_contrato_momento',
            'idx_checklist_locacao_momento',
            'idx_checklist_momento',
            'idx_checklist_id_contrato',
            'idx_checklist_id_locacao',
        ];

        foreach ($indexes as $idx) {
            if ($this->indexExists('checklist', $idx)) {
                $this->execute("ALTER TABLE checklist DROP INDEX {$idx}");
            }
        }

        // Colunas
        $columns = [
            'id_funcionario', 'data_checklist', 'obs_unica', 'assinatura_unica',
            'vistoria', 'questoes', 'odometro', 'tanque', 'momento',
            'id_contrato', 'id_locacao',
        ];

        foreach ($columns as $col) {
            if ($this->columnExists('checklist', $col)) {
                $this->alter('checklist', function ($table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
