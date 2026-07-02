<?php

/**
 * Migration 00384: Adicionar configuracoes ISSNet/ABRASF para NFS-e.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->addColumnIfNotExists('nfse_configuracoes', 'item_lista_servico', 'VARCHAR(10)', [
            'null' => true,
            'after' => 'codigo_servico',
            'comment' => 'Item da lista de servico ABRASF/ISSNet',
        ]);
        $this->addColumnIfNotExists('nfse_configuracoes', 'codigo_cnae', 'VARCHAR(10)', [
            'null' => true,
            'after' => 'item_lista_servico',
            'comment' => 'Codigo CNAE ABRASF/ISSNet',
        ]);
        $this->addColumnIfNotExists('nfse_configuracoes', 'codigo_tributacao_municipio', 'VARCHAR(30)', [
            'null' => true,
            'after' => 'codigo_cnae',
            'comment' => 'Codigo de tributacao municipal ABRASF/ISSNet',
        ]);
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->dropColumnIfExists('nfse_configuracoes', 'codigo_tributacao_municipio');
        $this->dropColumnIfExists('nfse_configuracoes', 'codigo_cnae');
        $this->dropColumnIfExists('nfse_configuracoes', 'item_lista_servico');
    }
};
