<?php

/**
 * Migration 00110: Migrar Dados para financeiro_itens
 *
 * Migra os dados existentes da tabela financeiro para financeiro_itens.
 * Cada registro de financeiro gera 1 item correspondente.
 *
 * Mapeamento:
 * - financeiro.chave -> financeiro_itens.chave
 * - financeiro.id -> financeiro_itens.id_financeiro
 * - financeiro_backup_refactor.id_veiculo -> financeiro_itens.id_veiculo
 * - financeiro.id_plano_de_conta -> financeiro_itens.id_plano_de_conta
 * - financeiro.descricao -> financeiro_itens.descricao
 * - financeiro.valor_principal -> financeiro_itens.valor
 * - 1 -> financeiro_itens.ordem
 *
 * Performance: Query unica com INSERT SELECT (mais eficiente que lotes com NOT IN)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a tabela financeiro_itens existe
        if (!$this->tableExists('financeiro_itens')) {
            throw new \RuntimeException('Tabela financeiro_itens nao existe. Execute a migration 00109 primeiro.');
        }

        // Verificar se ja existem itens (evita duplicacao em re-execucao)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM financeiro_itens");
        if ((int) $stmt->fetchColumn() > 0) {
            return; // Ja migrado
        }

        // Verificar se o backup existe para recuperar id_veiculo
        $temBackup = $this->tableExists('financeiro_backup_refactor');

        // Inserir itens sem JOIN (mais rapido para ~420k registros)
        // id_veiculo sera NULL - pode ser atualizado depois se necessario
        $sql = "
            INSERT INTO financeiro_itens (chave, id_financeiro, id_plano_de_conta, descricao, valor, ordem)
            SELECT
                chave,
                id,
                id_plano_de_conta,
                LEFT(descricao, 500),
                COALESCE(valor_principal, 0),
                1
            FROM financeiro
        ";

        $this->pdo->exec($sql);

        // Se backup existe, atualizar id_veiculo em lotes
        if ($temBackup) {
            $this->pdo->exec("
                UPDATE financeiro_itens fi
                JOIN financeiro_backup_refactor b ON b.id = fi.id_financeiro
                SET fi.id_veiculo = b.id_veiculo
                WHERE b.id_veiculo IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        // Limpar todos os itens migrados automaticamente
        // Itens criados manualmente apos a migration serao perdidos
        $this->execute("TRUNCATE TABLE financeiro_itens");
    }
};
