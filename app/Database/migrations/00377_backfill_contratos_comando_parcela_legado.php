<?php

use App\Database\Migration;
use App\Models\ComandoParcela;

/**
 * Migration 00377: normaliza contratos.id_comando_parcela a partir do legado.
 *
 * A migration 00244 dependia de formas_pagamento_clone, que pode nao existir.
 * Este backfill usa copias locais das tabelas legadas: contratos_old e formas_old.
 */
return new class extends Migration
{
    private const AUDIT_TABLE = 'contratos_comando_parcela_backfill_audit';

    public function up(): void
    {
        if (
            !$this->tableExists('contratos') ||
            !$this->tableExists('formas_pagamento_comandos') ||
            !$this->columnExists('contratos', 'id_comando_parcela') ||
            !$this->tableExists('contratos_old') ||
            !$this->tableExists('formas_old')
        ) {
            echo "  [SKIP] Tabelas/colunas necessarias ausentes (contratos, contratos_old, formas_old, formas_pagamento_comandos)\n";
            return;
        }

        $this->ensureAuditTable();
        $this->assertOldTablesShape();
        $this->assertNoDuplicateLegacyContracts();

        $commands = $this->loadCommandMap();
        $rows = $this->loadLegacyContractCommands();

        $stats = [
            'analisados' => 0,
            'atualizados' => 0,
            'ja_corretos' => 0,
            'sem_match' => 0,
            'invalidos' => 0,
            'comandos_criados' => 0,
        ];

        foreach ($rows as $row) {
            $stats['analisados']++;

            $normalizado = $this->normalizarParcelas((string) ($row['parcelas'] ?? ''));
            if ($normalizado === null) {
                $stats['invalidos']++;
                continue;
            }

            $idComando = $this->resolveCommandId($commands, (string) $row['chave'], $normalizado, $stats);
            if ($idComando === null) {
                $stats['sem_match']++;
                continue;
            }

            $idAtual = !empty($row['id_comando_parcela']) ? (int) $row['id_comando_parcela'] : null;
            if ($idAtual === $idComando) {
                $stats['ja_corretos']++;
                continue;
            }

            $this->auditChange(
                (int) $row['id'],
                (string) $row['chave'],
                (string) $row['codigo'],
                $idAtual,
                $idComando,
                (string) $row['parcelas'],
                $normalizado
            );

            $this->updateContractCommand((int) $row['id'], $idComando);
            $stats['atualizados']++;
        }

        echo "  Contratos analisados: {$stats['analisados']}\n";
        echo "  Contratos atualizados: {$stats['atualizados']}\n";
        echo "  Ja corretos: {$stats['ja_corretos']}\n";
        echo "  Sem match: {$stats['sem_match']}\n";
        echo "  Comandos invalidos: {$stats['invalidos']}\n";
        echo "  Comandos criados: {$stats['comandos_criados']}\n";
    }

    public function down(): void
    {
        // Backfill conservador: nao revertemos para evitar apagar/corromper
        // escolhas de comando feitas manualmente apos a migration.
    }

    private function ensureAuditTable(): void
    {
        if (!$this->tableExists(self::AUDIT_TABLE)) {
            $this->createAuditTable();
            return;
        }

        $type = $this->getColumnType(self::AUDIT_TABLE, 'parcelas_legado');
        if ($type !== null && !str_contains(strtolower($type), 'text')) {
            $this->modifyColumn(self::AUDIT_TABLE, 'parcelas_legado', 'TEXT', ['null' => true]);
        }
    }

    private function createAuditTable(): void
    {
        $this->create(self::AUDIT_TABLE, function ($table) {
            $table->id();
            $table->integer('id_contrato')->unsigned();
            $table->string('chave', 45);
            $table->string('codigo', 15);
            $table->integer('id_comando_anterior')->unsigned()->nullable();
            $table->integer('id_comando_novo')->unsigned();
            $table->text('parcelas_legado')->nullable();
            $table->string('comando_normalizado', 255);
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

            $table->unique('id_contrato', 'uniq_contrato_comando_backfill_contrato');
            $table->index(['chave', 'codigo'], 'idx_contrato_comando_backfill_chave_codigo');
        });
    }

    private function assertOldTablesShape(): void
    {
        $required = [
            'contratos_old' => ['chave', 'codigo', 'formaPagamento'],
            'formas_old' => ['id', 'parcelas'],
        ];

        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    throw new \RuntimeException("Backfill abortado: coluna {$table}.{$column} ausente.");
                }
            }
        }
    }

    private function assertNoDuplicateLegacyContracts(): void
    {
        $sql = "
            SELECT COUNT(*) FROM (
                SELECT chave, codigo
                FROM contratos_old
                GROUP BY chave, codigo
                HAVING COUNT(*) > 1
            ) duplicados
        ";

        $count = (int) $this->pdo->query($sql)->fetchColumn();
        if ($count > 0) {
            throw new \RuntimeException("Backfill abortado: contratos duplicados por chave+codigo no legado ({$count}).");
        }
    }

    /**
     * @return array<string,int>
     */
    private function loadCommandMap(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, chave, comando FROM formas_pagamento_comandos ORDER BY chave ASC, id ASC')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['chave'] . '|' . $row['comando']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadLegacyContractCommands(): array
    {
        $sql = "
            SELECT
                c.id,
                c.chave,
                c.codigo,
                c.id_comando_parcela,
                lf.parcelas
            FROM contratos c
            INNER JOIN contratos_old lc
                ON lc.chave = c.chave
               AND lc.codigo = c.codigo
            INNER JOIN formas_old lf
                ON lf.id = lc.formaPagamento
            WHERE lf.parcelas IS NOT NULL
              AND TRIM(lf.parcelas) <> ''
        ";

        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,int> $commands
     */
    private function resolveCommandId(array &$commands, string $chave, string $comando, array &$stats): ?int
    {
        $tenantKey = $chave . '|' . $comando;
        $systemKey = '0|' . $comando;

        if (isset($commands[$systemKey])) {
            return $commands[$systemKey];
        }

        if (isset($commands[$tenantKey])) {
            return $commands[$tenantKey];
        }

        $id = $this->createTenantCommand($chave, $comando);
        $commands[$tenantKey] = $id;
        $stats['comandos_criados']++;

        return $id;
    }

    private function createTenantCommand(string $chave, string $comando): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO formas_pagamento_comandos (chave, comando, descricao, status)
            VALUES (?, ?, ?, 'A')
        ");
        $stmt->execute([$chave, $comando, ComandoParcela::inferirLabel($comando)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function auditChange(
        int $idContrato,
        string $chave,
        string $codigo,
        ?int $idAnterior,
        int $idNovo,
        string $parcelasLegado,
        string $comandoNormalizado
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::AUDIT_TABLE . " (
                id_contrato,
                chave,
                codigo,
                id_comando_anterior,
                id_comando_novo,
                parcelas_legado,
                comando_normalizado
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_comando_anterior = VALUES(id_comando_anterior),
                id_comando_novo = VALUES(id_comando_novo),
                parcelas_legado = VALUES(parcelas_legado),
                comando_normalizado = VALUES(comando_normalizado)
        ");
        $stmt->execute([
            $idContrato,
            $chave,
            $codigo,
            $idAnterior,
            $idNovo,
            $parcelasLegado,
            $comandoNormalizado,
        ]);
    }

    private function updateContractCommand(int $idContrato, int $idComando): void
    {
        $stmt = $this->pdo->prepare('UPDATE contratos SET id_comando_parcela = ? WHERE id = ?');
        $stmt->execute([$idComando, $idContrato]);
    }

    /**
     * Mapa de normalizacao equivalente ao da migration 00244.
     */
    private function normalizarParcelas(string $parcelas): ?string
    {
        $parcelas = trim($parcelas);

        $aVista = ['', '0', 'não', 'nao', 'a vista', 'A Vista', 'O', '0 - ', '0 -'];
        if (in_array($parcelas, $aVista, true)) {
            return '0';
        }

        if (preg_match('/^(\d+)\s*dias?$/i', $parcelas, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d+)[xX]$/', $parcelas, $m)) {
            return $m[1];
        }

        if (preg_match('/^0+(\d+)$/', $parcelas, $m)) {
            return $m[1];
        }

        $diasExtenso = [
            'segunda' => 'Seg', 'Segunda' => 'Seg',
            'terca' => 'Ter', 'Terca' => 'Ter', 'terça' => 'Ter', 'Terça' => 'Ter',
            'quarta' => 'Qua', 'Quarta' => 'Qua',
            'quinta' => 'Qui', 'Quinta' => 'Qui',
            'sexta' => 'Sex', 'Sexta' => 'Sex',
            'sabado' => 'Sab', 'Sabado' => 'Sab', 'sábado' => 'Sab', 'Sábado' => 'Sab',
            'domingo' => 'Dom', 'Domingo' => 'Dom',
        ];
        if (isset($diasExtenso[$parcelas])) {
            return $diasExtenso[$parcelas];
        }

        if (preg_match('/^([a-zA-Z]{3})(\/\1)+$/i', $parcelas)) {
            $partes = explode('/', $parcelas);
            $n = count($partes);
            $dia = ucfirst(strtolower($partes[0]));
            return "w{$n}-{$dia}";
        }

        if (preg_match('/^[A-Z]{3}\s+(.+)$/i', $parcelas, $m)) {
            $parcelas = $m[1];
        }

        if (preg_match('/^\d+(\/\d+)+$/', $parcelas)) {
            $partes = array_map(function ($p) {
                return ltrim($p, '0') ?: '0';
            }, explode('/', $parcelas));

            if (count($partes) > 6) {
                $nums = array_map('intval', $partes);
                $intervaloConstante = true;
                for ($i = 1; $i < count($nums); $i++) {
                    if (($nums[$i] - $nums[$i - 1]) !== 7) {
                        $intervaloConstante = false;
                        break;
                    }
                }
                if ($intervaloConstante) {
                    return 'w' . count($nums);
                }
            }

            $resultado = implode('/', $partes);
            return strlen($resultado) <= 255 ? $resultado : null;
        }

        if (preg_match('/^0\/[a-z]/i', $parcelas)) {
            return null;
        }

        if (preg_match('/^\d+(-\d+){2,}$/', $parcelas)) {
            return str_replace('-', '/', $parcelas);
        }

        if (preg_match('/^\d+[\s\/]+\d+([\s\/]+\d+)*$/', $parcelas)) {
            $limpo = preg_replace('/[\s]+/', '/', $parcelas);
            if (preg_match('/^\d+(\/\d+)+$/', $limpo)) {
                $partes = array_map(function ($p) {
                    return ltrim($p, '0') ?: '0';
                }, explode('/', $limpo));

                if (count($partes) > 6) {
                    $nums = array_map('intval', $partes);
                    $intervaloConstante = true;
                    for ($i = 1; $i < count($nums); $i++) {
                        if (($nums[$i] - $nums[$i - 1]) !== 7) {
                            $intervaloConstante = false;
                            break;
                        }
                    }
                    if ($intervaloConstante) {
                        return 'w' . count($nums);
                    }
                }

                $resultado = implode('/', $partes);
                if (strlen($resultado) <= 255) {
                    return $resultado;
                }
            }
        }

        if (strlen($parcelas) > 255) {
            return null;
        }

        return $parcelas;
    }
};
