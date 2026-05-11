<?php

/**
 * Migration 00018: Converter MyISAM para InnoDB
 *
 * Converte as 4 tabelas que usam engine MyISAM para InnoDB.
 * Benefícios do InnoDB:
 * - Suporte a transações (ACID)
 * - Row-level locking (melhor concorrência)
 * - Crash recovery automático
 * - Foreign keys
 *
 * Tabelas afetadas:
 * - financeiro (419k registros) - CRÍTICA, pode demorar 5-15 min
 * - atualizacoes (393 registros)
 * - feriados (3 registros)
 * - multas (6.2k registros)
 *
 * IMPORTANTE: Durante a conversão, as tabelas ficam bloqueadas para escrita.
 * Executar em horário de baixo uso.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Tabelas a converter (ordem: menores primeiro para testes rápidos)
     */
    private array $myisamTables = [
        'feriados',      // 3 registros - rápido
        'atualizacoes',  // 393 registros - rápido
        'multas',        // 6.2k registros - ~1 min
        'financeiro',    // 419k registros - 5-15 min (deixar por último)
    ];

    public function up(): void
    {
        foreach ($this->myisamTables as $table) {
            if ($this->tableExists($table) && $this->isMyISAM($table)) {
                $this->execute("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        // Reverte para MyISAM (na ordem inversa)
        $tables = array_reverse($this->myisamTables);

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute("ALTER TABLE `{$table}` ENGINE=MyISAM");
            }
        }
    }

    /**
     * Verifica se a tabela usa engine MyISAM
     */
    private function isMyISAM(string $table): bool
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'"
        );

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && strtoupper($result['ENGINE']) === 'MYISAM';
    }
};
