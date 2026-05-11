<?php

use App\Database\Migration;

/**
 * Migration: Tornar colunas legadas do checklist nullable
 *
 * Com a normalizacao (00291), novos checklists usam os campos unificados
 * (questoes, vistoria, data_checklist). As colunas antigas (questoes_saida,
 * vistoria_saida, data_saida) precisam aceitar NULL para que novos registros
 * nao exijam valores nesses campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('checklist', 'questoes_saida')) {
            $this->modifyColumn('checklist', 'questoes_saida', 'MEDIUMTEXT', ['null' => true]);
        }

        if ($this->columnExists('checklist', 'vistoria_saida')) {
            $this->modifyColumn('checklist', 'vistoria_saida', 'LONGTEXT', ['null' => true]);
        }

        if ($this->columnExists('checklist', 'data_saida')) {
            $this->modifyColumn('checklist', 'data_saida', 'DATETIME', ['null' => true]);
        }
    }

    public function down(): void
    {
        // Nao reverter - manter nullable para compatibilidade
    }
};
