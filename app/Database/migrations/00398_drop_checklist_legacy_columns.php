<?php

use App\Database\Migration;

/**
 * Migration 00398: Remover colunas legadas da tabela checklist.
 *
 * A migration 00397 consolida os dados antigos em campos de saida/entrada e
 * preserva uma copia completa em checklist_clone. Esta migration remove apenas
 * as colunas que nao fazem mais parte do schema operacional.
 */
return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'tanque',
        'odometro',
        'momento',
        'questoes',
        'vistoria',
        'assinatura_unica',
        'obs_unica',
        'data_checklist',
    ];

    public function up(): void
    {
        if (!$this->tableExists('checklist_clone')) {
            throw new \RuntimeException('A tabela checklist_clone deve existir antes de remover colunas legadas de checklist.');
        }

        foreach ([
            'questoes_saida',
            'vistoria_saida',
            'data_saida',
            'questoes_entrada',
            'vistoria_entrada',
            'data_entrada',
        ] as $column) {
            if (!$this->columnExists('checklist', $column)) {
                throw new \RuntimeException("A coluna checklist.{$column} deve existir antes de remover colunas legadas.");
            }
        }

        foreach (self::LEGACY_COLUMNS as $column) {
            $this->dropColumnIfExists('checklist', $column);
        }
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('checklist', 'momento', 'VARCHAR(1)', [
            'null' => true,
            'after' => 'tipo',
        ]);
        $this->addColumnIfNotExists('checklist', 'tanque', 'VARCHAR(10)', [
            'null' => true,
            'after' => 'id_modelo',
        ]);
        $this->addColumnIfNotExists('checklist', 'odometro', 'INT UNSIGNED', [
            'null' => true,
            'after' => 'tanque',
        ]);
        $this->addColumnIfNotExists('checklist', 'questoes', 'MEDIUMTEXT', [
            'null' => true,
            'after' => 'odometro',
        ]);
        $this->addColumnIfNotExists('checklist', 'vistoria', 'LONGTEXT', [
            'null' => true,
            'after' => 'questoes',
        ]);
        $this->addColumnIfNotExists('checklist', 'assinatura_unica', 'MEDIUMTEXT', [
            'null' => true,
            'after' => 'vistoria',
        ]);
        $this->addColumnIfNotExists('checklist', 'obs_unica', 'MEDIUMTEXT', [
            'null' => true,
            'after' => 'assinatura_unica',
        ]);
        $this->addColumnIfNotExists('checklist', 'data_checklist', 'DATETIME', [
            'null' => true,
            'after' => 'obs_unica',
        ]);
    }
};
