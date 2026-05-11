<?php

use App\Database\Migration;

/**
 * Migration: Drop colunas antigas de valores em `grupos`
 *
 * Fase 4 do refactor multi-moeda. Essas colunas foram substituidas por
 * `grupos_precos_filiais` (1 linha por filial, com valores na moeda dela).
 *
 * O backfill em 00308 ja copiou os valores pra tabela nova.
 * Os fallbacks em LocacaoVeiculo/ContratoVeiculo foram removidos.
 */
return new class extends Migration
{
    private array $colunas = [
        'valor_plano_km_pago',
        'valor_plano_km_controlado',
        'valor_plano_km_livre',
        'valor_km_excedente',
        'km_franquia',
        'valor_seguro_carro',
        'valor_seguro_terceiros',
        'cobertura_carro',
        'cobertura_terceiros',
        'minutos_tolerancia',
        'valor_tolerancia',
        'valor_km_retorno',
        'valor_condutor_adicional',
    ];

    public function up(): void
    {
        foreach ($this->colunas as $col) {
            $this->dropColumnIfExists('grupos', $col);
        }
    }

    public function down(): void
    {
        // Rollback dificil — os dados vivem em grupos_precos_filiais.
        // Recriamos apenas o schema (vazio) pra permitir rollback de schema.
        foreach ($this->colunas as $col) {
            if ($this->columnExists('grupos', $col)) {
                continue;
            }
            if (in_array($col, ['km_franquia', 'minutos_tolerancia'], true)) {
                $this->execute("ALTER TABLE grupos ADD COLUMN `{$col}` INT(5) NOT NULL DEFAULT 0");
            } elseif (in_array($col, ['valor_seguro_carro', 'valor_seguro_terceiros', 'cobertura_carro', 'cobertura_terceiros'], true)) {
                $this->execute("ALTER TABLE grupos ADD COLUMN `{$col}` DECIMAL(12,2) NULL DEFAULT 0.00");
            } else {
                $this->execute("ALTER TABLE grupos ADD COLUMN `{$col}` DECIMAL(10,2) NULL DEFAULT 0.00");
            }
        }
    }
};
