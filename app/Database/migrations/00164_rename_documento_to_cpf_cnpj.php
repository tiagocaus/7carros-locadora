<?php

/**
 * Migration: Reverter coluna documento para cpf_cnpj na tabela clientes
 *
 * Esta migration reverte o nome da coluna 'documento' para 'cpf_cnpj',
 * restaurando consistência com outros modelos (Contrato, Financeiro)
 * que já referenciam cpf_cnpj.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('clientes', 'documento') && !$this->columnExists('clientes', 'cpf_cnpj')) {
            $this->renameColumnPreservingType('clientes', 'documento', 'cpf_cnpj');
        }
    }

    public function down(): void
    {
        if ($this->columnExists('clientes', 'cpf_cnpj') && !$this->columnExists('clientes', 'documento')) {
            $this->renameColumnPreservingType('clientes', 'cpf_cnpj', 'documento');
        }
    }
};
