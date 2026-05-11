<?php

use App\Database\Migration;

/**
 * Migration: Renomear tabela matriz_filial para matrizes_filiais
 * e coluna cnpj para cpf_cnpj
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomear coluna cnpj → cpf_cnpj (preservando tipo)
        if ($this->columnExists('matriz_filial', 'cnpj')) {
            $this->renameColumnPreservingType('matriz_filial', 'cnpj', 'cpf_cnpj');
        }

        // 2. Renomear tabela matriz_filial → matrizes_filiais
        if ($this->tableExists('matriz_filial')) {
            $this->renameTable('matriz_filial', 'matrizes_filiais');
        }
    }

    public function down(): void
    {
        // Reverter: renomear tabela de volta
        if ($this->tableExists('matrizes_filiais')) {
            $this->renameTable('matrizes_filiais', 'matriz_filial');
        }

        // Reverter: renomear coluna de volta
        if ($this->columnExists('matriz_filial', 'cpf_cnpj')) {
            $this->renameColumnPreservingType('matriz_filial', 'cpf_cnpj', 'cnpj');
        }
    }
};
