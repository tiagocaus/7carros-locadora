<?php

/**
 * Migration 00131: Adicionar Triggers para Sincronizacao de totais em manutencoes
 *
 * Cria triggers que mantem manutencoes.total_servicos, total_pago e total_pendente
 * sincronizados automaticamente quando itens sao inseridos, atualizados ou removidos.
 *
 * Formulas:
 * - total_servicos = SUM(itens.valor_total)
 * - total_pago = SUM(itens.valor_total) WHERE pago = 'S'
 * - total_pendente = SUM(itens.valor_total) WHERE pago = 'N'
 *
 * Triggers:
 * - trg_manutencoes_itens_after_insert
 * - trg_manutencoes_itens_after_update
 * - trg_manutencoes_itens_after_delete
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Remover triggers existentes (se houver)
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_delete");

        // Trigger AFTER INSERT
        $this->execute("
            CREATE TRIGGER trg_manutencoes_itens_after_insert
            AFTER INSERT ON manutencoes_itens
            FOR EACH ROW
            BEGIN
                UPDATE manutencoes
                SET total_servicos = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao
                    ),
                    total_pago = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao AND pago = 'S'
                    ),
                    total_pendente = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao AND pago = 'N'
                    ),
                    updated_at = NOW()
                WHERE id = NEW.id_manutencao;
            END
        ");

        // Trigger AFTER UPDATE
        $this->execute("
            CREATE TRIGGER trg_manutencoes_itens_after_update
            AFTER UPDATE ON manutencoes_itens
            FOR EACH ROW
            BEGIN
                -- Se mudou de manutencao, atualizar a antiga
                IF OLD.id_manutencao != NEW.id_manutencao THEN
                    UPDATE manutencoes
                    SET total_servicos = (
                            SELECT COALESCE(SUM(valor_total), 0)
                            FROM manutencoes_itens
                            WHERE id_manutencao = OLD.id_manutencao
                        ),
                        total_pago = (
                            SELECT COALESCE(SUM(valor_total), 0)
                            FROM manutencoes_itens
                            WHERE id_manutencao = OLD.id_manutencao AND pago = 'S'
                        ),
                        total_pendente = (
                            SELECT COALESCE(SUM(valor_total), 0)
                            FROM manutencoes_itens
                            WHERE id_manutencao = OLD.id_manutencao AND pago = 'N'
                        ),
                        updated_at = NOW()
                    WHERE id = OLD.id_manutencao;
                END IF;

                -- Atualizar a manutencao atual
                UPDATE manutencoes
                SET total_servicos = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao
                    ),
                    total_pago = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao AND pago = 'S'
                    ),
                    total_pendente = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = NEW.id_manutencao AND pago = 'N'
                    ),
                    updated_at = NOW()
                WHERE id = NEW.id_manutencao;
            END
        ");

        // Trigger AFTER DELETE
        $this->execute("
            CREATE TRIGGER trg_manutencoes_itens_after_delete
            AFTER DELETE ON manutencoes_itens
            FOR EACH ROW
            BEGIN
                UPDATE manutencoes
                SET total_servicos = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = OLD.id_manutencao
                    ),
                    total_pago = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = OLD.id_manutencao AND pago = 'S'
                    ),
                    total_pendente = (
                        SELECT COALESCE(SUM(valor_total), 0)
                        FROM manutencoes_itens
                        WHERE id_manutencao = OLD.id_manutencao AND pago = 'N'
                    ),
                    updated_at = NOW()
                WHERE id = OLD.id_manutencao;
            END
        ");

        // Recalcular totais para todos os registros existentes
        $this->execute("
            UPDATE manutencoes m
            SET m.total_servicos = (
                    SELECT COALESCE(SUM(valor_total), 0)
                    FROM manutencoes_itens
                    WHERE id_manutencao = m.id
                ),
                m.total_pago = (
                    SELECT COALESCE(SUM(valor_total), 0)
                    FROM manutencoes_itens
                    WHERE id_manutencao = m.id AND pago = 'S'
                ),
                m.total_pendente = (
                    SELECT COALESCE(SUM(valor_total), 0)
                    FROM manutencoes_itens
                    WHERE id_manutencao = m.id AND pago = 'N'
                )
        ");
    }

    public function down(): void
    {
        // Remover triggers
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_delete");

        // Zerar totais calculados (manter total_servicos original)
        $this->execute("
            UPDATE manutencoes
            SET total_pago = 0,
                total_pendente = 0
        ");
    }
};
