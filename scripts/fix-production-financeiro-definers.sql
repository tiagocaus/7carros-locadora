/*
 * Corrige DEFINER dos triggers de financeiro_itens e manutencoes_itens em
 * producao.
 *
 * Execute conectado ao MySQL como:
 *   7carros_locador@localhost
 *
 * Nao adicione CREATE DEFINER=... neste arquivo. O MySQL deve gravar o
 * DEFINER automaticamente a partir do usuario conectado.
 */

SELECT DATABASE() AS db, USER() AS user_client, CURRENT_USER() AS user_privileges;

SELECT 'ANTES' AS fase, 'TRIGGER' AS tipo, TRIGGER_NAME AS objeto, DEFINER
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME IN (
      'trg_financeiro_itens_after_insert',
      'trg_financeiro_itens_after_update',
      'trg_financeiro_itens_after_delete',
      'trg_manutencoes_itens_after_insert',
      'trg_manutencoes_itens_after_update',
      'trg_manutencoes_itens_after_delete'
  );

DROP TRIGGER IF EXISTS trg_financeiro_itens_after_insert;
DROP TRIGGER IF EXISTS trg_financeiro_itens_after_update;
DROP TRIGGER IF EXISTS trg_financeiro_itens_after_delete;
DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_insert;
DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_update;
DROP TRIGGER IF EXISTS trg_manutencoes_itens_after_delete;

DELIMITER $$

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
END$$

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
END$$

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
END$$

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
END$$

CREATE TRIGGER trg_manutencoes_itens_after_update
AFTER UPDATE ON manutencoes_itens
FOR EACH ROW
BEGIN
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
END$$

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
END$$

DELIMITER ;

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
   OR ABS(COALESCE(f.valor_total, 0) - (fi.soma_itens + COALESCE(f.juros, 0) + COALESCE(f.multa, 0) - COALESCE(f.desconto, 0))) > 0.01;

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
    );

SELECT 'DEPOIS' AS fase, 'TRIGGER' AS tipo, TRIGGER_NAME AS objeto, DEFINER
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME IN (
      'trg_financeiro_itens_after_insert',
      'trg_financeiro_itens_after_update',
      'trg_financeiro_itens_after_delete',
      'trg_manutencoes_itens_after_insert',
      'trg_manutencoes_itens_after_update',
      'trg_manutencoes_itens_after_delete'
  );
