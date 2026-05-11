<?php

/**
 * Migration 00111: Adicionar Triggers para Sincronizacao de valor_total
 *
 * Cria triggers que mantem financeiro.valor_principal e financeiro.valor_total
 * sincronizados automaticamente quando itens sao inseridos, atualizados ou
 * removidos.
 *
 * Modelo:
 * - valor_principal = SUM(financeiro_itens.valor)  (cache da soma dos itens)
 * - valor_total     = valor_principal + juros + multa - desconto
 *
 * Triggers:
 * - trg_financeiro_itens_after_insert
 * - trg_financeiro_itens_after_update
 * - trg_financeiro_itens_after_delete
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Remover triggers existentes (se houver)
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");

        // Trigger AFTER INSERT
        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_insert
            AFTER INSERT ON financeiro_itens
            FOR EACH ROW
            BEGIN
                UPDATE financeiro
                SET valor_principal = (SELECT COALESCE(SUM(valor), 0)
                                       FROM financeiro_itens
                                       WHERE id_financeiro = NEW.id_financeiro),
                    valor_total = (SELECT COALESCE(SUM(valor), 0)
                                   FROM financeiro_itens
                                   WHERE id_financeiro = NEW.id_financeiro)
                                + COALESCE(juros, 0)
                                + COALESCE(multa, 0)
                                - COALESCE(desconto, 0),
                    updated_at = NOW()
                WHERE id = NEW.id_financeiro;
            END
        ");

        // Trigger AFTER UPDATE
        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_update
            AFTER UPDATE ON financeiro_itens
            FOR EACH ROW
            BEGIN
                -- Atualizar documento antigo se mudou de documento
                IF OLD.id_financeiro != NEW.id_financeiro THEN
                    UPDATE financeiro
                    SET valor_principal = (SELECT COALESCE(SUM(valor), 0)
                                           FROM financeiro_itens
                                           WHERE id_financeiro = OLD.id_financeiro),
                        valor_total = (SELECT COALESCE(SUM(valor), 0)
                                       FROM financeiro_itens
                                       WHERE id_financeiro = OLD.id_financeiro)
                                    + COALESCE(juros, 0)
                                    + COALESCE(multa, 0)
                                    - COALESCE(desconto, 0),
                        updated_at = NOW()
                    WHERE id = OLD.id_financeiro;
                END IF;

                -- Atualizar documento atual
                UPDATE financeiro
                SET valor_principal = (SELECT COALESCE(SUM(valor), 0)
                                       FROM financeiro_itens
                                       WHERE id_financeiro = NEW.id_financeiro),
                    valor_total = (SELECT COALESCE(SUM(valor), 0)
                                   FROM financeiro_itens
                                   WHERE id_financeiro = NEW.id_financeiro)
                                + COALESCE(juros, 0)
                                + COALESCE(multa, 0)
                                - COALESCE(desconto, 0),
                    updated_at = NOW()
                WHERE id = NEW.id_financeiro;
            END
        ");

        // Trigger AFTER DELETE
        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_delete
            AFTER DELETE ON financeiro_itens
            FOR EACH ROW
            BEGIN
                UPDATE financeiro
                SET valor_principal = (SELECT COALESCE(SUM(valor), 0)
                                       FROM financeiro_itens
                                       WHERE id_financeiro = OLD.id_financeiro),
                    valor_total = (SELECT COALESCE(SUM(valor), 0)
                                   FROM financeiro_itens
                                   WHERE id_financeiro = OLD.id_financeiro)
                                + COALESCE(juros, 0)
                                + COALESCE(multa, 0)
                                - COALESCE(desconto, 0),
                    updated_at = NOW()
                WHERE id = OLD.id_financeiro;
            END
        ");

        // Recalcular valor_principal e valor_total para todos os registros
        $this->execute("
            UPDATE financeiro f
            SET f.valor_principal = (SELECT COALESCE(SUM(fi.valor), 0)
                                     FROM financeiro_itens fi
                                     WHERE fi.id_financeiro = f.id),
                f.valor_total = (SELECT COALESCE(SUM(fi.valor), 0)
                                 FROM financeiro_itens fi
                                 WHERE fi.id_financeiro = f.id)
                              + COALESCE(f.juros, 0)
                              + COALESCE(f.multa, 0)
                              - COALESCE(f.desconto, 0)
        ");
    }

    public function down(): void
    {
        // Remover triggers
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");

        // Recalcular valor_total apenas com base no proprio cabecalho (sem itens)
        $this->execute("
            UPDATE financeiro
            SET valor_total = COALESCE(valor_principal, 0)
                + COALESCE(juros, 0)
                + COALESCE(multa, 0)
                - COALESCE(desconto, 0)
        ");
    }
};
