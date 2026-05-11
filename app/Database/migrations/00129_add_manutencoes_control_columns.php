<?php

/**
 * Migration 00129: Adicionar colunas de controle financeiro em manutencoes
 *
 * Adiciona:
 * - id_financeiro_principal: FK para lancamento financeiro principal
 * - total_pago: soma dos itens ja pagos
 * - total_pendente: soma dos itens pendentes de pagamento
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar coluna id_financeiro_principal
        if (!$this->columnExists('manutencoes', 'id_financeiro_principal')) {
            $this->execute("
                ALTER TABLE manutencoes
                ADD COLUMN id_financeiro_principal INT UNSIGNED NULL AFTER total_servicos
            ");
        }

        // Adicionar coluna total_pago
        if (!$this->columnExists('manutencoes', 'total_pago')) {
            $this->execute("
                ALTER TABLE manutencoes
                ADD COLUMN total_pago DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER id_financeiro_principal
            ");
        }

        // Adicionar coluna total_pendente
        if (!$this->columnExists('manutencoes', 'total_pendente')) {
            $this->execute("
                ALTER TABLE manutencoes
                ADD COLUMN total_pendente DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_pago
            ");
        }

        // Adicionar FK para financeiro
        $this->execute("
            ALTER TABLE manutencoes
            ADD CONSTRAINT fk_manutencoes_financeiro_principal
            FOREIGN KEY (id_financeiro_principal)
            REFERENCES financeiro(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE
        ");

        // Adicionar indice para FK
        $this->execute("
            CREATE INDEX idx_manutencoes_id_financeiro ON manutencoes(id_financeiro_principal)
        ");
    }

    public function down(): void
    {
        // Remover FK
        $this->execute("
            ALTER TABLE manutencoes
            DROP FOREIGN KEY fk_manutencoes_financeiro_principal
        ");

        // Remover indice
        $this->execute("
            DROP INDEX idx_manutencoes_id_financeiro ON manutencoes
        ");

        // Remover colunas
        if ($this->columnExists('manutencoes', 'total_pendente')) {
            $this->execute("ALTER TABLE manutencoes DROP COLUMN total_pendente");
        }

        if ($this->columnExists('manutencoes', 'total_pago')) {
            $this->execute("ALTER TABLE manutencoes DROP COLUMN total_pago");
        }

        if ($this->columnExists('manutencoes', 'id_financeiro_principal')) {
            $this->execute("ALTER TABLE manutencoes DROP COLUMN id_financeiro_principal");
        }
    }
};
