<?php

/**
 * Migration 00355: Renomear financeiro.valor_principal -> valor_subtotal
 *
 * Alinha o nome da coluna com o vocabulario do mercado (Stripe, QuickBooks,
 * Shopify, SAP usam "subtotal" para "soma das linhas/itens antes de juros,
 * multas e descontos").
 *
 * Como os 3 triggers de financeiro_itens referenciam valor_principal, eles
 * sao removidos e recriados com o novo nome.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover triggers (referenciam valor_principal)
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");

        // 2. Renomear coluna (idempotente)
        if ($this->columnExists('financeiro', 'valor_principal') && !$this->columnExists('financeiro', 'valor_subtotal')) {
            $this->renameColumnPreservingType('financeiro', 'valor_principal', 'valor_subtotal');
        }

        // 3. Recriar triggers usando valor_subtotal
        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_insert
            AFTER INSERT ON financeiro_itens
            FOR EACH ROW
            BEGIN
                UPDATE financeiro
                SET valor_subtotal = (SELECT COALESCE(SUM(valor), 0)
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

        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_update
            AFTER UPDATE ON financeiro_itens
            FOR EACH ROW
            BEGIN
                IF OLD.id_financeiro != NEW.id_financeiro THEN
                    UPDATE financeiro
                    SET valor_subtotal = (SELECT COALESCE(SUM(valor), 0)
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

                UPDATE financeiro
                SET valor_subtotal = (SELECT COALESCE(SUM(valor), 0)
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

        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_delete
            AFTER DELETE ON financeiro_itens
            FOR EACH ROW
            BEGIN
                UPDATE financeiro
                SET valor_subtotal = (SELECT COALESCE(SUM(valor), 0)
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
    }

    public function down(): void
    {
        // 1. Remover triggers (referenciam valor_subtotal)
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");

        // 2. Reverter coluna
        if ($this->columnExists('financeiro', 'valor_subtotal') && !$this->columnExists('financeiro', 'valor_principal')) {
            $this->renameColumnPreservingType('financeiro', 'valor_subtotal', 'valor_principal');
        }

        // 3. Recriar triggers com nome antigo
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

        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_update
            AFTER UPDATE ON financeiro_itens
            FOR EACH ROW
            BEGIN
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
    }
};
