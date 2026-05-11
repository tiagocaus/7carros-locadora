<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de atualizacoes para feature_requests
 *
 * - Migra os 393 registros existentes
 * - Mapeia campo 'pagina' para modulo_id
 * - Converte situacao S/N para status ENUM
 * - Renomeia tabela antiga para backup (atualizacoes_bkp)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a tabela de origem existe
        if (!$this->tableExists('atualizacoes')) {
            echo "Tabela 'atualizacoes' não existe, pulando migração de dados.\n";
            return;
        }

        // Verificar se a tabela de destino existe
        if (!$this->tableExists('feature_requests')) {
            throw new \RuntimeException("Tabela 'feature_requests' não existe. Execute a migration 00132 primeiro.");
        }

        // 1. Migrar dados de atualizacoes para feature_requests
        $this->execute("
            INSERT INTO feature_requests (
                chave, titulo, descricao, modulo_id,
                email_solicitante, telefone_solicitante,
                status, created_at, updated_at
            )
            SELECT
                a.chave,
                LEFT(COALESCE(a.pedido, 'Sem título'), 255) as titulo,
                COALESCE(a.pedido, 'Sem descrição') as descricao,
                COALESCE(
                    (SELECT m.id FROM feature_request_modules m
                     WHERE LOWER(m.nome) LIKE CONCAT('%', LOWER(LEFT(COALESCE(a.pagina, ''), 10)), '%')
                     AND m.nome != 'Outro'
                     LIMIT 1),
                    (SELECT m.id FROM feature_request_modules m WHERE m.nome = 'Outro' LIMIT 1)
                ) as modulo_id,
                COALESCE(NULLIF(a.email, ''), 'nao-informado@sistema.com') as email_solicitante,
                NULLIF(a.tel_zap, '') as telefone_solicitante,
                CASE COALESCE(a.situacao, 'N')
                    WHEN 'S' THEN 'concluido'
                    ELSE 'pendente'
                END as status,
                COALESCE(a.created_at, CONCAT(COALESCE(a.data, CURDATE()), ' 00:00:00')) as created_at,
                a.updated_at
            FROM atualizacoes a
            WHERE a.pedido IS NOT NULL AND TRIM(a.pedido) != ''
        ");

        // 2. Contar registros migrados
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM feature_requests");
        $count = $stmt->fetchColumn();
        echo "Migrados {$count} registros para feature_requests.\n";

        // 3. Renomear tabela antiga para backup
        if ($this->tableExists('atualizacoes') && !$this->tableExists('atualizacoes_bkp')) {
            $this->renameTable('atualizacoes', 'atualizacoes_bkp');
            echo "Tabela 'atualizacoes' renomeada para 'atualizacoes_bkp'.\n";
        }
    }

    public function down(): void
    {
        // Restaurar tabela de backup
        if ($this->tableExists('atualizacoes_bkp') && !$this->tableExists('atualizacoes')) {
            $this->renameTable('atualizacoes_bkp', 'atualizacoes');
        }

        // Limpar dados migrados (apenas os que vieram da migração)
        $this->execute("DELETE FROM feature_requests WHERE usuario_id IS NULL");
    }
};
