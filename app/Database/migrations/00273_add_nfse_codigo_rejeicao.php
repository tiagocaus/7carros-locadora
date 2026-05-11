<?php

/**
 * Migration 00273: Adicionar coluna codigo_rejeicao na tabela nfse
 *
 * Armazena o codigo interno do erro (ex: CONN_CURL, CNPJ_INVALIDO)
 * para permitir filtragem de erros recuperaveis no reenvio automatico.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('nfse', 'codigo_rejeicao')) {
            return;
        }

        $this->execute("
            ALTER TABLE nfse
            ADD COLUMN codigo_rejeicao VARCHAR(50) NULL COMMENT 'Codigo interno do erro (ex: CONN_CURL, CNPJ_INVALIDO)'
            AFTER motivo_rejeicao
        ");

        $this->execute("
            CREATE INDEX idx_nfse_codigo_rejeicao ON nfse (codigo_rejeicao)
        ");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX idx_nfse_codigo_rejeicao ON nfse");
        $this->execute("ALTER TABLE nfse DROP COLUMN codigo_rejeicao");
    }
};
