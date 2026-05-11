<?php

use App\Database\Migration;

/**
 * Migration: Adicionar módulo "Outros" e migrar dados de atualizacoes
 *
 * - Insere módulo "Outros" (ID 30) com ordem 99 (último)
 * - Migra dados da tabela atualizacoes para feature_requests
 * - Mapeia códigos antigos de página para novos IDs de módulo
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Inserir modulo "Outros" (idempotente: pula se ja existe da 00134)
        $this->execute("
            INSERT IGNORE INTO feature_request_modules (id, nome, translation_key, icone, ordem, ativo)
            VALUES (30, 'Outros', 'outros', 'fas fa-ellipsis-h', 99, 1)
        ");

        // 2. Determinar fonte: atualizacoes (nao migrado ainda) ou atualizacoes_bkp (00133 ja renomeou)
        $sourceTable = $this->tableExists('atualizacoes') ? 'atualizacoes' : 'atualizacoes_bkp';

        if (!$this->tableExists($sourceTable)) {
            // Nao ha o que migrar
            return;
        }

        // 3. Migrar dados para feature_requests
        $this->execute("
            INSERT INTO feature_requests (
                chave, titulo, descricao, modulo_id, email_solicitante,
                telefone_solicitante, status, created_at, updated_at
            )
            SELECT
                a.chave,
                LEFT(a.pedido, 255) as titulo,
                a.pedido as descricao,
                CASE
                    WHEN a.pagina = '' OR a.pagina IS NULL THEN 30
                    WHEN SUBSTRING(a.pagina, 1, 2) = '01' THEN 1
                    WHEN SUBSTRING(a.pagina, 1, 2) = '02' THEN 2
                    WHEN SUBSTRING(a.pagina, 1, 2) = '03' THEN 3
                    WHEN SUBSTRING(a.pagina, 1, 2) = '04' THEN 4
                    WHEN SUBSTRING(a.pagina, 1, 2) = '05' THEN 5
                    WHEN SUBSTRING(a.pagina, 1, 2) = '06' THEN 6
                    WHEN SUBSTRING(a.pagina, 1, 2) = '07' THEN 7
                    WHEN SUBSTRING(a.pagina, 1, 2) = '08' THEN 8
                    WHEN SUBSTRING(a.pagina, 1, 2) = '09' THEN 9
                    WHEN SUBSTRING(a.pagina, 1, 2) = '10' THEN 10
                    WHEN SUBSTRING(a.pagina, 1, 2) = '11' THEN 11
                    WHEN SUBSTRING(a.pagina, 1, 2) = '12' THEN 12
                    WHEN SUBSTRING(a.pagina, 1, 2) = '13' THEN 13
                    WHEN SUBSTRING(a.pagina, 1, 2) = '14' THEN 14
                    WHEN SUBSTRING(a.pagina, 1, 2) = '15' THEN 15
                    WHEN SUBSTRING(a.pagina, 1, 2) = '16' THEN 16
                    WHEN SUBSTRING(a.pagina, 1, 2) = '17' THEN 17
                    WHEN SUBSTRING(a.pagina, 1, 2) = '18' THEN 18
                    WHEN SUBSTRING(a.pagina, 1, 2) = '19' THEN 19
                    WHEN SUBSTRING(a.pagina, 1, 2) = '20' THEN 20
                    WHEN SUBSTRING(a.pagina, 1, 2) = '21' THEN 21
                    WHEN SUBSTRING(a.pagina, 1, 2) = '22' THEN 22
                    WHEN SUBSTRING(a.pagina, 1, 2) = '23' THEN 23
                    WHEN SUBSTRING(a.pagina, 1, 2) = '24' THEN 24
                    WHEN SUBSTRING(a.pagina, 1, 2) = '25' THEN 25
                    WHEN SUBSTRING(a.pagina, 1, 2) = '26' THEN 26
                    WHEN SUBSTRING(a.pagina, 1, 2) = '27' THEN 27
                    WHEN SUBSTRING(a.pagina, 1, 2) = '28' THEN 28
                    WHEN SUBSTRING(a.pagina, 1, 2) = '29' THEN 29
                    ELSE 30
                END as modulo_id,
                COALESCE(a.email, '') as email_solicitante,
                a.tel_zap as telefone_solicitante,
                CASE a.situacao
                    WHEN 'S' THEN 'concluido'
                    WHEN 'N' THEN 'recusado'
                    ELSE 'pendente'
                END as status,
                a.created_at,
                a.updated_at
            FROM {$sourceTable} a
        ");

        // 4. Renomear tabela atualizacoes (apenas se ainda nao foi renomeada pela 00133)
        if ($this->tableExists('atualizacoes') && !$this->tableExists('atualizacoes_bkp')) {
            $this->execute("RENAME TABLE atualizacoes TO atualizacoes_bkp");
        }
    }

    public function down(): void
    {
        // Restaurar nome da tabela
        $this->execute("RENAME TABLE atualizacoes_bkp TO atualizacoes");

        // Remover dados migrados
        $this->execute("DELETE FROM feature_requests");

        // Remover módulo "Outros"
        $this->execute("DELETE FROM feature_request_modules WHERE id = 30");
    }
};
