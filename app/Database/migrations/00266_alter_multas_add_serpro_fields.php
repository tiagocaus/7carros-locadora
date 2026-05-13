<?php

/**
 * Migration 00266: Adicionar campos SERPRO na tabela multas
 *
 * Novos campos para integracao com consultas online:
 * - Chaves de identificacao da infracao (codigo_orgao, numero_ait, codigo_infracao)
 * - Origem do registro (manual, serpro_consulta, serpro_evento)
 * - Status de processamento (indicacao, transferencia de pontos)
 * - PDFs de NA e NP
 * - Datas de notificacao
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Chaves SERPRO
        $this->addColumnIfNotExists('multas', 'codigo_orgao', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'foto'
        ]);

        $this->addColumnIfNotExists('multas', 'numero_ait', 'VARCHAR(30)', [
            'null' => true,
            'after' => 'codigo_orgao'
        ]);

        $this->addColumnIfNotExists('multas', 'codigo_infracao', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'numero_ait'
        ]);

        // Origem do registro (ENUM via execute para evitar strtoupper nos valores)
        if (!$this->columnExists('multas', 'origem')) {
            $this->execute("
                ALTER TABLE `multas`
                ADD COLUMN `origem` ENUM('manual', 'serpro_consulta', 'serpro_evento')
                NOT NULL DEFAULT 'manual'
                AFTER `codigo_infracao`
            ");
        }

        // Status de processamento (ENUM via execute para evitar strtoupper nos valores)
        if (!$this->columnExists('multas', 'status_processamento')) {
            $this->execute("
                ALTER TABLE `multas`
                ADD COLUMN `status_processamento` ENUM('novo', 'pendente_indicacao', 'indicacao_enviada', 'indicacao_aceita', 'indicacao_rejeitada', 'transferido', 'pago', 'cancelado')
                NOT NULL DEFAULT 'novo'
                AFTER `origem`
            ");
        }

        // Valor com desconto 40%
        $this->addColumnIfNotExists('multas', 'valor_desconto_40', 'DECIMAL(10,2)', [
            'null' => true,
            'after' => 'status_processamento'
        ]);

        // PDFs SERPRO
        $this->addColumnIfNotExists('multas', 'na_pdf_path', 'VARCHAR(255)', [
            'null' => true,
            'after' => 'valor_desconto_40'
        ]);

        $this->addColumnIfNotExists('multas', 'np_pdf_path', 'VARCHAR(255)', [
            'null' => true,
            'after' => 'na_pdf_path'
        ]);

        // Datas de notificacao
        $this->addColumnIfNotExists('multas', 'data_notificacao_autuacao', 'DATE', [
            'null' => true,
            'after' => 'np_pdf_path'
        ]);

        $this->addColumnIfNotExists('multas', 'data_notificacao_penalidade', 'DATE', [
            'null' => true,
            'after' => 'data_notificacao_autuacao'
        ]);

        // Data de sincronizacao com SERPRO
        $this->addColumnIfNotExists('multas', 'serpro_sync_at', 'DATETIME', [
            'null' => true,
            'after' => 'data_notificacao_penalidade'
        ]);

        // Indices
        $this->addIndexIfNotExists('multas', 'codigo_orgao', 'idx_multas_codigo_orgao');
        $this->addIndexIfNotExists('multas', 'numero_ait', 'idx_multas_numero_ait');
        $this->addIndexIfNotExists('multas', 'origem', 'idx_multas_origem');
        $this->addIndexIfNotExists('multas', 'status_processamento', 'idx_multas_status_proc');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('multas', 'idx_multas_status_proc');
        $this->dropIndexIfExists('multas', 'idx_multas_origem');
        $this->dropIndexIfExists('multas', 'idx_multas_numero_ait');
        $this->dropIndexIfExists('multas', 'idx_multas_codigo_orgao');

        $this->dropColumnIfExists('multas', 'serpro_sync_at');
        $this->dropColumnIfExists('multas', 'data_notificacao_penalidade');
        $this->dropColumnIfExists('multas', 'data_notificacao_autuacao');
        $this->dropColumnIfExists('multas', 'np_pdf_path');
        $this->dropColumnIfExists('multas', 'na_pdf_path');
        $this->dropColumnIfExists('multas', 'valor_desconto_40');
        $this->dropColumnIfExists('multas', 'status_processamento');
        $this->dropColumnIfExists('multas', 'origem');
        $this->dropColumnIfExists('multas', 'codigo_infracao');
        $this->dropColumnIfExists('multas', 'numero_ait');
        $this->dropColumnIfExists('multas', 'codigo_orgao');
    }
};
