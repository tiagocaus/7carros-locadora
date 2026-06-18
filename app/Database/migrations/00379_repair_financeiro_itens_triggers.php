<?php

/**
 * Migration 00379: Reparar triggers de financeiro_itens
 *
 * Garante que os caches do cabecalho financeiro sejam sincronizados com os
 * itens usando o schema atual: financeiro.valor_subtotal + juros + multa -
 * desconto = financeiro.valor_total.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");

        $this->execute("
            CREATE TRIGGER trg_financeiro_itens_after_insert
            AFTER INSERT ON financeiro_itens
            FOR EACH ROW
            BEGIN
                UPDATE financeiro
                SET valor_subtotal = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = NEW.id_financeiro
                    ),
                    valor_total = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = NEW.id_financeiro
                    ) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0),
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
                    SET valor_subtotal = (
                            SELECT COALESCE(SUM(valor), 0)
                            FROM financeiro_itens
                            WHERE id_financeiro = OLD.id_financeiro
                        ),
                        valor_total = (
                            SELECT COALESCE(SUM(valor), 0)
                            FROM financeiro_itens
                            WHERE id_financeiro = OLD.id_financeiro
                        ) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0),
                        updated_at = NOW()
                    WHERE id = OLD.id_financeiro;
                END IF;

                UPDATE financeiro
                SET valor_subtotal = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = NEW.id_financeiro
                    ),
                    valor_total = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = NEW.id_financeiro
                    ) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0),
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
                SET valor_subtotal = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = OLD.id_financeiro
                    ),
                    valor_total = (
                        SELECT COALESCE(SUM(valor), 0)
                        FROM financeiro_itens
                        WHERE id_financeiro = OLD.id_financeiro
                    ) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0),
                    updated_at = NOW()
                WHERE id = OLD.id_financeiro;
            END
        ");

        $this->execute("
            UPDATE financeiro f
            INNER JOIN (
                SELECT id_financeiro, chave, COALESCE(SUM(valor), 0) AS soma_itens
                FROM financeiro_itens
                GROUP BY id_financeiro, chave
            ) fi ON fi.id_financeiro = f.id AND fi.chave = f.chave
            SET f.valor_subtotal = fi.soma_itens,
                f.valor_total = fi.soma_itens + COALESCE(f.juros, 0) + COALESCE(f.multa, 0) - COALESCE(f.desconto, 0),
                f.updated_at = NOW()
            WHERE ABS(COALESCE(f.valor_subtotal, 0) - fi.soma_itens) > 0.01
               OR ABS(COALESCE(f.valor_total, 0) - (fi.soma_itens + COALESCE(f.juros, 0) + COALESCE(f.multa, 0) - COALESCE(f.desconto, 0))) > 0.01
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update");
        $this->execute("DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete");
    }
};
