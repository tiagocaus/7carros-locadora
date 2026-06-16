<?php

/**
 * Migration 00374: Remover suporte ABRASF da configuracao NFS-e
 *
 * ABRASF deixou de ser emissor suportado. Betha Cloud e o caminho municipal
 * mantido no modulo novo.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->dropColumnIfExists('nfse_configuracoes', 'abrasf_item_lista_servico');
        $this->dropColumnIfExists('nfse_configuracoes', 'abrasf_codigo_cnae');
        $this->dropColumnIfExists('nfse_configuracoes', 'abrasf_codigo_trib_municipio');
        $this->dropColumnIfExists('nfse_configuracoes', 'abrasf_numero_rps');
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        if (!$this->columnExists('nfse_configuracoes', 'abrasf_item_lista_servico')) {
            $this->execute("ALTER TABLE nfse_configuracoes ADD COLUMN abrasf_item_lista_servico VARCHAR(10) DEFAULT '' COMMENT 'Item da lista de servico ABRASF'");
        }
        if (!$this->columnExists('nfse_configuracoes', 'abrasf_codigo_cnae')) {
            $this->execute("ALTER TABLE nfse_configuracoes ADD COLUMN abrasf_codigo_cnae VARCHAR(10) DEFAULT '' COMMENT 'Codigo CNAE da atividade economica'");
        }
        if (!$this->columnExists('nfse_configuracoes', 'abrasf_codigo_trib_municipio')) {
            $this->execute("ALTER TABLE nfse_configuracoes ADD COLUMN abrasf_codigo_trib_municipio VARCHAR(20) DEFAULT '' COMMENT 'Codigo de tributacao do municipio'");
        }
        if (!$this->columnExists('nfse_configuracoes', 'abrasf_numero_rps')) {
            $this->execute("ALTER TABLE nfse_configuracoes ADD COLUMN abrasf_numero_rps INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Contador independente de RPS para ABRASF'");
        }
    }
};
