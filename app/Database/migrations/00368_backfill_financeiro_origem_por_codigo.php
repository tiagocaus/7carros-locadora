<?php

/**
 * Migration 00368: Backfill da origem do financeiro por codigo legado.
 *
 * O legado vinculava financeiro a locacoes/contratos via financeiro.codigo.
 * O schema atual possui FKs explicitas em financeiro.id_locacao e
 * financeiro.id_contrato. Esta migration preenche essas FKs usando
 * chave + codigo, preservando cliente, valores, status, descricao e itens.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const AUDIT_TABLE = 'financeiro_origem_backfill_audit';

    public function up(): void
    {
        if (
            !$this->tableExists('financeiro') ||
            !$this->tableExists('locacoes') ||
            !$this->tableExists('contratos')
        ) {
            return;
        }

        if (
            !$this->columnExists('financeiro', 'id_locacao') ||
            !$this->columnExists('financeiro', 'id_contrato')
        ) {
            return;
        }

        $this->createAuditTable();
        $this->assertSafeToBackfill();

        $this->snapshotLocacoes();
        $this->backfillLocacoes();

        $this->snapshotContratos();
        $this->backfillContratos();

        try {
            Cache::flush();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // Backfill conservador: nao revertemos para evitar apagar vinculos legitimos
        // criados manualmente apos a migration.
    }

    private function createAuditTable(): void
    {
        if ($this->tableExists(self::AUDIT_TABLE)) {
            return;
        }

        $this->create(self::AUDIT_TABLE, function ($table) {
            $table->id();
            $table->integer('id_financeiro')->unsigned();
            $table->string('chave', 45);
            $table->string('codigo', 15)->nullable();
            $table->integer('id_locacao_anterior')->unsigned()->nullable();
            $table->integer('id_contrato_anterior')->unsigned()->nullable();
            $table->integer('id_locacao_novo')->unsigned()->nullable();
            $table->integer('id_contrato_novo')->unsigned()->nullable();
            $table->string('tipo_origem', 20);
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

            $table->unique('id_financeiro', 'uniq_fin_origem_audit_financeiro');
            $table->index(['chave', 'codigo'], 'idx_fin_origem_audit_chave_codigo');
            $table->index('tipo_origem', 'idx_fin_origem_audit_tipo');
        });
    }

    private function assertSafeToBackfill(): void
    {
        $checks = [
            'locacoes com codigo duplicado por tenant' => "
                SELECT COUNT(*) FROM (
                    SELECT chave, codigo
                    FROM locacoes
                    GROUP BY chave, codigo
                    HAVING COUNT(*) > 1
                ) x
            ",
            'contratos com codigo duplicado por tenant' => "
                SELECT COUNT(*) FROM (
                    SELECT chave, codigo
                    FROM contratos
                    GROUP BY chave, codigo
                    HAVING COUNT(*) > 1
                ) x
            ",
            'codigo que bate em locacao e contrato no mesmo tenant' => "
                SELECT COUNT(*)
                FROM locacoes l
                INNER JOIN contratos c ON c.chave = l.chave AND c.codigo = l.codigo
            ",
        ];

        foreach ($checks as $label => $sql) {
            $count = (int) $this->pdo->query($sql)->fetchColumn();
            if ($count > 0) {
                throw new \RuntimeException("Backfill financeiro/origem abortado: {$label} ({$count}).");
            }
        }
    }

    private function snapshotLocacoes(): void
    {
        $this->execute("
            INSERT INTO " . self::AUDIT_TABLE . " (
                id_financeiro,
                chave,
                codigo,
                id_locacao_anterior,
                id_contrato_anterior,
                id_locacao_novo,
                id_contrato_novo,
                tipo_origem
            )
            SELECT
                f.id,
                f.chave,
                f.codigo,
                f.id_locacao,
                f.id_contrato,
                l.id,
                NULL,
                'locacao'
            FROM financeiro f
            INNER JOIN locacoes l ON l.chave = f.chave AND l.codigo = f.codigo
            WHERE f.id_locacao IS NULL
              AND f.id_contrato IS NULL
              AND f.codigo IS NOT NULL
              AND f.codigo <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM " . self::AUDIT_TABLE . " a
                  WHERE a.id_financeiro = f.id
              )
        ");
    }

    private function backfillLocacoes(): void
    {
        $this->execute("
            UPDATE financeiro f
            INNER JOIN locacoes l ON l.chave = f.chave AND l.codigo = f.codigo
            SET f.id_locacao = l.id
            WHERE f.id_locacao IS NULL
              AND f.id_contrato IS NULL
              AND f.codigo IS NOT NULL
              AND f.codigo <> ''
        ");
    }

    private function snapshotContratos(): void
    {
        $this->execute("
            INSERT INTO " . self::AUDIT_TABLE . " (
                id_financeiro,
                chave,
                codigo,
                id_locacao_anterior,
                id_contrato_anterior,
                id_locacao_novo,
                id_contrato_novo,
                tipo_origem
            )
            SELECT
                f.id,
                f.chave,
                f.codigo,
                f.id_locacao,
                f.id_contrato,
                NULL,
                c.id,
                'contrato'
            FROM financeiro f
            INNER JOIN contratos c ON c.chave = f.chave AND c.codigo = f.codigo
            WHERE f.id_locacao IS NULL
              AND f.id_contrato IS NULL
              AND f.codigo IS NOT NULL
              AND f.codigo <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM " . self::AUDIT_TABLE . " a
                  WHERE a.id_financeiro = f.id
              )
        ");
    }

    private function backfillContratos(): void
    {
        $this->execute("
            UPDATE financeiro f
            INNER JOIN contratos c ON c.chave = f.chave AND c.codigo = f.codigo
            SET f.id_contrato = c.id
            WHERE f.id_contrato IS NULL
              AND f.id_locacao IS NULL
              AND f.codigo IS NOT NULL
              AND f.codigo <> ''
        ");
    }
};
