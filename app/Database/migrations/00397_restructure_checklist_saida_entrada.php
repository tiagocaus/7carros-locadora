<?php

use App\Database\Migration;

/**
 * Reestrutura checklist digital para manter saida e entrada no mesmo registro.
 *
 * Mantem uma copia integral em checklist_clone antes de remover registros de
 * chegada pareados. Migrations operam cross-tenant por definicao.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->criarBackup();
        $this->adicionarColunas();
        $this->migrarAvulsos();
        $this->migrarVinculados();
        $this->removerColunasVeiculo();
        $this->adicionarIndices();
    }

    public function down(): void
    {
        // A reversao completa dependeria de reconstruir registros removidos a
        // partir de checklist_clone. Mantemos o backup operacional intacto.
        $this->dropIndexIfExists('checklist', 'idx_checklist_chave_tipo_status');
        $this->dropIndexIfExists('checklist', 'idx_checklist_data_saida');
        $this->dropIndexIfExists('checklist', 'idx_checklist_data_entrada');

        if (!$this->columnExists('checklist', 'tanque')) {
            $this->addColumnIfNotExists('checklist', 'tanque', 'VARCHAR(10)', ['null' => true]);
        }

        if (!$this->columnExists('checklist', 'odometro')) {
            $this->addColumnIfNotExists('checklist', 'odometro', 'INT UNSIGNED', ['null' => true]);
        }
    }

    private function criarBackup(): void
    {
        if (!$this->tableExists('checklist_clone')) {
            $this->execute('CREATE TABLE `checklist_clone` LIKE `checklist`');
            $this->execute('INSERT INTO `checklist_clone` SELECT * FROM `checklist`');
        }
    }

    private function adicionarColunas(): void
    {
        $afterBase = $this->columnExists('checklist', 'odometro') ? 'odometro' : 'momento';

        $this->addColumnIfNotExists('checklist', 'questoes_saida', 'MEDIUMTEXT', ['null' => true, 'after' => $afterBase]);
        $this->addColumnIfNotExists('checklist', 'vistoria_saida', 'LONGTEXT', ['null' => true, 'after' => 'questoes_saida']);
        $this->addColumnIfNotExists('checklist', 'observacoes_saida', 'MEDIUMTEXT', ['null' => true, 'after' => 'vistoria_saida']);
        $this->addColumnIfNotExists('checklist', 'data_saida', 'DATETIME', ['null' => true, 'after' => 'observacoes_saida']);
        $this->addColumnIfNotExists('checklist', 'assinatura_saida', 'MEDIUMTEXT', ['null' => true, 'after' => 'data_saida']);

        $this->addColumnIfNotExists('checklist', 'questoes_entrada', 'MEDIUMTEXT', ['null' => true, 'after' => 'assinatura_saida']);
        $this->addColumnIfNotExists('checklist', 'vistoria_entrada', 'LONGTEXT', ['null' => true, 'after' => 'questoes_entrada']);
        $this->addColumnIfNotExists('checklist', 'observacoes_entrada', 'MEDIUMTEXT', ['null' => true, 'after' => 'vistoria_entrada']);
        $this->addColumnIfNotExists('checklist', 'data_entrada', 'DATETIME', ['null' => true, 'after' => 'observacoes_entrada']);
        $this->addColumnIfNotExists('checklist', 'assinatura_entrada', 'MEDIUMTEXT', ['null' => true, 'after' => 'data_entrada']);
    }

    private function migrarAvulsos(): void
    {
        $this->execute("
            UPDATE checklist
            SET
                tipo = 'A',
                momento = 'N',
                questoes_saida = COALESCE(questoes_saida, questoes),
                vistoria_saida = COALESCE(vistoria_saida, vistoria),
                observacoes_saida = COALESCE(observacoes_saida, obs_unica),
                data_saida = CASE
                    WHEN data_saida IS NOT NULL THEN data_saida
                    WHEN data_checklist IS NOT NULL AND data_checklist <> '0000-00-00 00:00:00' THEN data_checklist
                    ELSE NULL
                END,
                assinatura_saida = COALESCE(assinatura_saida, assinatura_unica),
                status = CASE
                    WHEN status IN ('2', '4', '6')
                      OR (assinatura_unica IS NOT NULL AND assinatura_unica <> '')
                      OR (assinatura_saida IS NOT NULL AND assinatura_saida <> '')
                    THEN '2'
                    ELSE '1'
                END
            WHERE tipo = 'A' OR tipo IS NULL OR tipo = ''
        ");
    }

    private function migrarVinculados(): void
    {
        $this->execute("
            UPDATE checklist
            SET
                questoes_saida = COALESCE(questoes_saida, questoes),
                vistoria_saida = COALESCE(vistoria_saida, vistoria),
                observacoes_saida = COALESCE(observacoes_saida, obs_unica),
                data_saida = CASE
                    WHEN data_saida IS NOT NULL THEN data_saida
                    WHEN data_checklist IS NOT NULL AND data_checklist <> '0000-00-00 00:00:00' THEN data_checklist
                    ELSE NULL
                END,
                assinatura_saida = COALESCE(assinatura_saida, assinatura_unica)
            WHERE tipo = 'V' AND momento = 'S'
        ");

        $this->execute('DROP TEMPORARY TABLE IF EXISTS checklist_pairs');
        $this->execute("
            CREATE TEMPORARY TABLE checklist_pairs (
                saida_id INT UNSIGNED NOT NULL PRIMARY KEY,
                entrada_id INT UNSIGNED NOT NULL,
                INDEX idx_entrada_id (entrada_id)
            ) ENGINE=MEMORY
        ");

        $this->execute("
            INSERT INTO checklist_pairs (saida_id, entrada_id)
            SELECT s.id, MIN(c.id) AS entrada_id
            FROM checklist s
            INNER JOIN checklist c ON c.chave = s.chave
                AND c.tipo = 'V'
                AND c.momento = 'C'
                AND (
                    c.codigo = CONCAT(s.codigo, '-C')
                    OR (
                        s.id_locacao IS NOT NULL
                        AND c.id_locacao = s.id_locacao
                        AND COALESCE(c.id_veiculo, 0) = COALESCE(s.id_veiculo, 0)
                    )
                    OR (
                        s.id_contrato IS NOT NULL
                        AND c.id_contrato = s.id_contrato
                        AND COALESCE(c.id_veiculo, 0) = COALESCE(s.id_veiculo, 0)
                    )
                )
            WHERE s.tipo = 'V'
              AND s.momento = 'S'
            GROUP BY s.id
        ");

        $this->execute("
            UPDATE checklist s
            INNER JOIN checklist_pairs p ON p.saida_id = s.id
            INNER JOIN checklist c ON c.id = p.entrada_id
            SET
                s.questoes_entrada = COALESCE(s.questoes_entrada, c.questoes),
                s.vistoria_entrada = COALESCE(s.vistoria_entrada, c.vistoria),
                s.observacoes_entrada = COALESCE(s.observacoes_entrada, c.obs_unica),
                s.data_entrada = CASE
                    WHEN s.data_entrada IS NOT NULL THEN s.data_entrada
                    WHEN c.data_checklist IS NOT NULL AND c.data_checklist <> '0000-00-00 00:00:00' THEN c.data_checklist
                    ELSE NULL
                END,
                s.assinatura_entrada = COALESCE(s.assinatura_entrada, c.assinatura_unica),
                s.status = CASE
                    WHEN c.status IN ('2', '4', '6')
                      OR (c.assinatura_unica IS NOT NULL AND c.assinatura_unica <> '')
                    THEN '6'
                    ELSE '5'
                END,
                s.updated_at = COALESCE(s.updated_at, c.updated_at)
        ");

        $this->execute("
            DELETE c
            FROM checklist c
            INNER JOIN checklist_pairs p ON p.entrada_id = c.id
        ");

        $this->execute("
            UPDATE checklist
            SET
                questoes_entrada = COALESCE(questoes_entrada, questoes),
                vistoria_entrada = COALESCE(vistoria_entrada, vistoria),
                observacoes_entrada = COALESCE(observacoes_entrada, obs_unica),
                data_entrada = CASE
                    WHEN data_entrada IS NOT NULL THEN data_entrada
                    WHEN data_checklist IS NOT NULL AND data_checklist <> '0000-00-00 00:00:00' THEN data_checklist
                    ELSE NULL
                END,
                assinatura_entrada = COALESCE(assinatura_entrada, assinatura_unica),
                status = CASE
                    WHEN status IN ('2', '4', '6')
                      OR (assinatura_unica IS NOT NULL AND assinatura_unica <> '')
                      OR (assinatura_entrada IS NOT NULL AND assinatura_entrada <> '')
                    THEN '6'
                    ELSE '5'
                END
            WHERE tipo = 'V' AND momento = 'C'
        ");

        $this->execute("
            UPDATE checklist
            SET status = CASE
                WHEN status IN ('2', '4', '6')
                  OR (assinatura_saida IS NOT NULL AND assinatura_saida <> '')
                THEN '4'
                ELSE '3'
            END
            WHERE tipo = 'V'
              AND momento = 'S'
              AND (questoes_entrada IS NULL OR questoes_entrada = '')
              AND (vistoria_entrada IS NULL OR vistoria_entrada = '')
              AND (assinatura_entrada IS NULL OR assinatura_entrada = '')
              AND data_entrada IS NULL
        ");
    }

    private function removerColunasVeiculo(): void
    {
        $this->dropColumnIfExists('checklist', 'tanque');
        $this->dropColumnIfExists('checklist', 'odometro');
    }

    private function adicionarIndices(): void
    {
        $this->addIndexIfNotExists('checklist', ['chave', 'tipo', 'status'], 'idx_checklist_chave_tipo_status');
        $this->addIndexIfNotExists('checklist', ['data_saida'], 'idx_checklist_data_saida');
        $this->addIndexIfNotExists('checklist', ['data_entrada'], 'idx_checklist_data_entrada');
    }
};
