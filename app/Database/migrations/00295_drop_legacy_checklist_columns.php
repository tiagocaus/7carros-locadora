<?php

use App\Database\Migration;

/**
 * Migration: Remover colunas legadas do checklist
 *
 * Apos a normalizacao (00291/00292), todos os dados foram migrados para
 * os novos campos unificados. As colunas antigas nao sao mais utilizadas
 * pelo codigo e podem ser removidas.
 *
 * Colunas removidas:
 * - questoes_saida, questoes_chegada (substituidas por 'questoes')
 * - vistoria_saida, vistoria_chegada (substituidas por 'vistoria')
 * - data_saida, data_chegada (substituidas por 'data_checklist')
 * - assinatura, assinatura_chegada (substituidas por 'assinatura_unica')
 * - obs, obs_chegada (substituidas por 'obs_unica')
 * - vistoria_temp (campo temporario nao mais utilizado)
 */
return new class extends Migration
{
    private const COLUMNS_TO_DROP = [
        'questoes_saida',
        'questoes_chegada',
        'vistoria_saida',
        'vistoria_chegada',
        'data_saida',
        'data_chegada',
        'assinatura',
        'assinatura_chegada',
        'obs',
        'obs_chegada',
        'vistoria_temp',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS_TO_DROP as $col) {
            $this->dropColumnIfExists('checklist', $col);
        }
    }

    public function down(): void
    {
        // Recriar colunas legadas (sem dados - irreversivel)
        $this->addColumnIfNotExists('checklist', 'questoes_saida', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'questoes_chegada', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'vistoria_saida', 'LONGTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'vistoria_chegada', 'LONGTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'data_saida', 'DATETIME', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'data_chegada', 'DATETIME', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'assinatura', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'assinatura_chegada', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'obs', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'obs_chegada', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('checklist', 'vistoria_temp', 'LONGTEXT', ['null' => true]);
    }
};
