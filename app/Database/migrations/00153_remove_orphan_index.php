<?php

use App\Database\Migration;

/**
 * Migration: Remover índice órfão da tabela contratos
 *
 * O índice `idx_contratos_chave_veiculo` ficou órfão após a remoção
 * da coluna `id_veiculo` na migração 00152. Agora ele aponta apenas
 * para `chave`, duplicando o índice `idx_contratos_chave`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('contratos', 'idx_contratos_chave_veiculo')) {
            $this->execute('ALTER TABLE `contratos` DROP INDEX `idx_contratos_chave_veiculo`');
            echo "Índice órfão removido: idx_contratos_chave_veiculo\n";
        } else {
            echo "Índice idx_contratos_chave_veiculo já não existe\n";
        }
    }

    public function down(): void
    {
        // Não é necessário recriar - o índice era órfão
    }
};
