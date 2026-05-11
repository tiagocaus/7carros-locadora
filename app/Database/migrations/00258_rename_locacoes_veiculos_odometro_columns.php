<?php

/**
 * Migration 00258: Renomear colunas de odometro/combustivel em locacoes_veiculos
 *
 * Alinha nomenclatura com a perspectiva da empresa:
 * - "saida" = veiculo sai da empresa (inicio da locacao)
 * - "entrada" = veiculo volta a empresa (devolucao)
 *
 * Antes (perspectiva do registro):
 *   odometro_entrada, combustivel_entrada, data_entrada = inicio
 *   odometro_saida, combustivel_saida, data_saida = fim
 *
 * Depois (perspectiva da empresa):
 *   odometro_saida, combustivel_saida, data_saida = inicio (veiculo sai)
 *   odometro_entrada, combustivel_entrada, data_entrada = fim (veiculo volta)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Renomear usando coluna temporaria para evitar conflito de nomes
        // Passo 1: Renomear campos "entrada" para temporarios
        if ($this->columnExists('locacoes_veiculos', 'odometro_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_entrada', 'odometro_entrada_tmp');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_entrada', 'combustivel_entrada_tmp');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_entrada', 'data_entrada_tmp');
        }

        // Passo 2: Renomear campos "saida" para "entrada" (veiculo volta = entrada)
        if ($this->columnExists('locacoes_veiculos', 'odometro_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_saida', 'odometro_entrada');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_saida', 'combustivel_entrada');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_saida', 'data_entrada');
        }

        // Passo 3: Renomear temporarios para "saida" (veiculo sai = saida)
        if ($this->columnExists('locacoes_veiculos', 'odometro_entrada_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_entrada_tmp', 'odometro_saida');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_entrada_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_entrada_tmp', 'combustivel_saida');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_entrada_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_entrada_tmp', 'data_saida');
        }
    }

    public function down(): void
    {
        // Reverter: desfazer a troca
        if ($this->columnExists('locacoes_veiculos', 'odometro_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_saida', 'odometro_saida_tmp');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_saida', 'combustivel_saida_tmp');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_saida')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_saida', 'data_saida_tmp');
        }

        if ($this->columnExists('locacoes_veiculos', 'odometro_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_entrada', 'odometro_saida');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_entrada', 'combustivel_saida');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_entrada')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_entrada', 'data_saida');
        }

        if ($this->columnExists('locacoes_veiculos', 'odometro_saida_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'odometro_saida_tmp', 'odometro_entrada');
        }
        if ($this->columnExists('locacoes_veiculos', 'combustivel_saida_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'combustivel_saida_tmp', 'combustivel_entrada');
        }
        if ($this->columnExists('locacoes_veiculos', 'data_saida_tmp')) {
            $this->renameColumnPreservingType('locacoes_veiculos', 'data_saida_tmp', 'data_entrada');
        }
    }
};
