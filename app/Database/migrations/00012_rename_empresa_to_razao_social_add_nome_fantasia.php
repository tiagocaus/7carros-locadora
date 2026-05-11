<?php

/**
 * Migration: Renomear coluna empresa para razao_social e adicionar nome_fantasia
 *
 * 1. Renomeia a coluna 'empresa' para 'razao_social' preservando tipo e propriedades
 * 2. Adiciona nova coluna 'nome_fantasia'
 * 3. Copia os dados de 'razao_social' (que era 'empresa') para 'nome_fantasia'
 *    para não perder os registros existentes
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        // Verifica se a coluna 'empresa' existe antes de renomear
        if ($this->columnExists('matriz_filial', 'empresa')) {
            // 1. Renomear coluna 'empresa' para 'razao_social' preservando tipo e propriedades
            $this->renameColumnPreservingType('matriz_filial', 'empresa', 'razao_social');
        }

        // 2. Adicionar nova coluna 'nome_fantasia' usando o padrão $this->table()
        $this->table('matriz_filial', function ($table) {
            // Obtém o tipo da coluna razao_social para usar o mesmo tipo
            $razaoSocialType = $this->getColumnType('matriz_filial', 'razao_social');
            
            // Se não encontrou razao_social, tenta empresa (caso a renomeação não tenha funcionado)
            if (!$razaoSocialType) {
                $razaoSocialType = $this->getColumnType('matriz_filial', 'empresa') ?: 'VARCHAR(255)';
            }

            // Determina o tipo base e tamanho
            $typeBase = 'VARCHAR';
            $length = 255;
            
            if (preg_match('/^VARCHAR\((\d+)\)$/i', $razaoSocialType, $matches)) {
                $typeBase = 'VARCHAR';
                $length = (int)$matches[1];
            } elseif (preg_match('/^TEXT$/i', $razaoSocialType)) {
                $typeBase = 'TEXT';
                $length = null;
            } elseif (preg_match('/^CHAR\((\d+)\)$/i', $razaoSocialType, $matches)) {
                $typeBase = 'CHAR';
                $length = (int)$matches[1];
            }

            // Adiciona a coluna nome_fantasia usando fluent API
            if ($length !== null) {
                $table->string('nome_fantasia', $length)->nullable()->after('razao_social');
            } else {
                $table->text('nome_fantasia')->nullable()->after('razao_social');
            }
        });

        // 3. Copiar dados de 'razao_social' para 'nome_fantasia' usando QueryBuilder
        if ($this->columnExists('matriz_filial', 'razao_social') && 
            $this->columnExists('matriz_filial', 'nome_fantasia')) {
            // Usa QueryBuilder ao invés de $this->pdo->exec
            $this->copyColumnData('matriz_filial', 'razao_social', 'nome_fantasia');
        }
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        // Verifica se a coluna 'razao_social' existe antes de renomear de volta
        if ($this->columnExists('matriz_filial', 'razao_social')) {
            // Renomear de volta 'razao_social' para 'empresa'
            $this->renameColumnPreservingType('matriz_filial', 'razao_social', 'empresa');
        }

        // Remove a coluna 'nome_fantasia' se existir
        if ($this->columnExists('matriz_filial', 'nome_fantasia')) {
            $this->dropColumn('matriz_filial', 'nome_fantasia');
        }
    }
};
