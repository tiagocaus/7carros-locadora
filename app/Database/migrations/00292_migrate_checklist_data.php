<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados existentes de checklists para formato normalizado
 *
 * 1. Avulsos (tipo=A): copiar campos _saida para novos campos unificados
 * 2. Vinculados (tipo=V): split em 2 registros (saida + chegada separados),
 *    popular FKs id_locacao/id_contrato pelo match de codigo
 * 3. Registros NULL/vazio: tratar como avulso
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->columnExists('checklist', 'momento')) {
            return;
        }

        // ---------------------------------------------------------------
        // 1. Registros NULL/vazio: normalizar tipo para 'A'
        // ---------------------------------------------------------------
        $this->execute("
            UPDATE checklist
            SET tipo = 'A'
            WHERE tipo IS NULL OR tipo = ''
        ");

        // ---------------------------------------------------------------
        // 2. Avulsos (tipo=A): copiar campos _saida para novos campos
        // ---------------------------------------------------------------
        $this->execute("
            UPDATE checklist
            SET
                momento = 'N',
                questoes = questoes_saida,
                vistoria = vistoria_saida,
                assinatura_unica = assinatura,
                obs_unica = obs,
                data_checklist = data_saida
            WHERE tipo = 'A'
              AND momento IS NULL
        ");

        // ---------------------------------------------------------------
        // 3. Vinculados (tipo=V): atualizar registro original como SAIDA
        // ---------------------------------------------------------------

        // 3a. Popular id_locacao pelo match de codigo
        $this->execute("
            UPDATE checklist c
            INNER JOIN locacoes l ON l.codigo = c.codigo AND l.chave = c.chave
            SET c.id_locacao = l.id
            WHERE c.tipo = 'V'
              AND c.id_locacao IS NULL
              AND c.codigo IS NOT NULL
              AND c.codigo != ''
        ");

        // 3b. Popular id_contrato pelo match de codigo (onde nao achou locacao)
        $this->execute("
            UPDATE checklist c
            INNER JOIN contratos ct ON ct.codigo = c.codigo AND ct.chave = c.chave
            SET c.id_contrato = ct.id
            WHERE c.tipo = 'V'
              AND c.id_locacao IS NULL
              AND c.id_contrato IS NULL
              AND c.codigo IS NOT NULL
              AND c.codigo != ''
        ");

        // 3c. Converter registro original para SAIDA
        $this->execute("
            UPDATE checklist
            SET
                momento = 'S',
                questoes = questoes_saida,
                vistoria = vistoria_saida,
                assinatura_unica = assinatura,
                obs_unica = obs,
                data_checklist = data_saida
            WHERE tipo = 'V'
              AND momento IS NULL
        ");

        // ---------------------------------------------------------------
        // 4. Vinculados com dados de chegada: INSERT novos registros CHEGADA
        // ---------------------------------------------------------------
        $this->execute("
            INSERT INTO checklist (
                chave, tipo, momento, codigo,
                id_modelo, id_veiculo, id_locacao, id_contrato,
                questoes, vistoria, assinatura_unica, obs_unica,
                data_checklist, status,
                questoes_saida, vistoria_saida, data_saida,
                questoes_chegada, vistoria_chegada, data_chegada,
                assinatura, assinatura_chegada, obs, obs_chegada,
                created_at, updated_at
            )
            SELECT
                c.chave, 'V', 'C', CONCAT(c.codigo, '-C'),
                c.id_modelo, c.id_veiculo, c.id_locacao, c.id_contrato,
                c.questoes_chegada, c.vistoria_chegada, c.assinatura_chegada, c.obs_chegada,
                c.data_chegada, c.status,
                c.questoes_saida, c.vistoria_saida, c.data_saida,
                c.questoes_chegada, c.vistoria_chegada, c.data_chegada,
                c.assinatura, c.assinatura_chegada, c.obs, c.obs_chegada,
                c.created_at, NOW()
            FROM checklist c
            WHERE c.tipo = 'V'
              AND c.momento = 'S'
              AND c.data_chegada IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (!$this->columnExists('checklist', 'momento')) {
            return;
        }

        // Remover registros de chegada criados pelo split
        $this->execute("
            DELETE FROM checklist
            WHERE tipo = 'V'
              AND momento = 'C'
              AND codigo LIKE '%-C'
        ");

        // Limpar campos normalizados
        $this->execute("
            UPDATE checklist
            SET
                momento = NULL,
                questoes = NULL,
                vistoria = NULL,
                assinatura_unica = NULL,
                obs_unica = NULL,
                data_checklist = NULL,
                id_locacao = NULL,
                id_contrato = NULL
        ");
    }
};
