<?php

/**
 * Migration 00259: Renomear colunas de odometro/combustivel em contratos_veiculos
 *
 * Alinha nomenclatura com a perspectiva da empresa (consistente com locacoes_veiculos):
 * - "saida" = veiculo sai da empresa (inicio do contrato)
 * - "entrada" = veiculo volta a empresa (devolucao/substituicao)
 *
 * Antes (perspectiva do contrato):
 *   odometro_entrada, combustivel_entrada, data_entrada = inicio (veiculo entra no contrato)
 *   odometro_saida, combustivel_saida, data_saida = fim (veiculo sai do contrato)
 *
 * Depois (perspectiva da empresa):
 *   odometro_saida, combustivel_saida, data_saida = inicio (veiculo sai da empresa)
 *   odometro_entrada, combustivel_entrada, data_entrada = fim (veiculo volta)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Renomear usando coluna temporaria para evitar conflito de nomes
        // Passo 1: Renomear campos "entrada" para temporarios
        if ($this->columnExists('contratos_veiculos', 'odometro_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_entrada', 'odometro_entrada_tmp');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_entrada', 'combustivel_entrada_tmp');
        }
        if ($this->columnExists('contratos_veiculos', 'data_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_entrada', 'data_entrada_tmp');
        }

        // Passo 2: Renomear campos "saida" para "entrada" (veiculo volta = entrada)
        if ($this->columnExists('contratos_veiculos', 'odometro_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_saida', 'odometro_entrada');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_saida', 'combustivel_entrada');
        }
        if ($this->columnExists('contratos_veiculos', 'data_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_saida', 'data_entrada');
        }

        // Passo 3: Renomear temporarios para "saida" (veiculo sai = saida)
        if ($this->columnExists('contratos_veiculos', 'odometro_entrada_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_entrada_tmp', 'odometro_saida');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_entrada_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_entrada_tmp', 'combustivel_saida');
        }
        if ($this->columnExists('contratos_veiculos', 'data_entrada_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_entrada_tmp', 'data_saida');
        }
    }

    public function down(): void
    {
        // Reverter: desfazer a troca
        if ($this->columnExists('contratos_veiculos', 'odometro_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_saida', 'odometro_saida_tmp');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_saida', 'combustivel_saida_tmp');
        }
        if ($this->columnExists('contratos_veiculos', 'data_saida')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_saida', 'data_saida_tmp');
        }

        if ($this->columnExists('contratos_veiculos', 'odometro_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_entrada', 'odometro_saida');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_entrada', 'combustivel_saida');
        }
        if ($this->columnExists('contratos_veiculos', 'data_entrada')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_entrada', 'data_saida');
        }

        if ($this->columnExists('contratos_veiculos', 'odometro_saida_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'odometro_saida_tmp', 'odometro_entrada');
        }
        if ($this->columnExists('contratos_veiculos', 'combustivel_saida_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'combustivel_saida_tmp', 'combustivel_entrada');
        }
        if ($this->columnExists('contratos_veiculos', 'data_saida_tmp')) {
            $this->renameColumnPreservingType('contratos_veiculos', 'data_saida_tmp', 'data_entrada');
        }
    }
};
