<?php

/**
 * Migration 00383: Adicionar configuracao explicita de IBS/CBS para NFS-e.
 *
 * IBS/CBS nao deve ser calculado por padrao. O preenchimento precisa ser
 * ativado por configuracao da filial quando o emissor/provedor aceitar o bloco.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('nfse_configuracoes')) {
            $this->addColumnIfNotExists('nfse_configuracoes', 'preencher_ibscbs', 'CHAR(1)', [
                'null' => false,
                'default' => 'N',
                'after' => 'aliquota_iss',
                'comment' => 'Preencher dados IBS/CBS na DPS (S/N)',
            ]);
            $this->addColumnIfNotExists('nfse_configuracoes', 'aliquota_ibs', 'DECIMAL(5,2)', [
                'null' => false,
                'default' => '0.00',
                'after' => 'preencher_ibscbs',
                'comment' => 'Aliquota IBS (%) quando IBS/CBS estiver ativo',
            ]);
            $this->addColumnIfNotExists('nfse_configuracoes', 'aliquota_cbs', 'DECIMAL(5,2)', [
                'null' => false,
                'default' => '0.00',
                'after' => 'aliquota_ibs',
                'comment' => 'Aliquota CBS (%) quando IBS/CBS estiver ativo',
            ]);
        }

        if ($this->tableExists('nfse')) {
            if ($this->columnExists('nfse', 'aliquota_ibs')) {
                $this->modifyColumn('nfse', 'aliquota_ibs', 'DECIMAL(5,2)', [
                    'null' => false,
                    'default' => '0.00',
                    'comment' => 'Aliquota IBS (%)',
                ]);
            }
            if ($this->columnExists('nfse', 'aliquota_cbs')) {
                $this->modifyColumn('nfse', 'aliquota_cbs', 'DECIMAL(5,2)', [
                    'null' => false,
                    'default' => '0.00',
                    'comment' => 'Aliquota CBS (%)',
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->tableExists('nfse_configuracoes')) {
            $this->dropColumnIfExists('nfse_configuracoes', 'aliquota_cbs');
            $this->dropColumnIfExists('nfse_configuracoes', 'aliquota_ibs');
            $this->dropColumnIfExists('nfse_configuracoes', 'preencher_ibscbs');
        }

        if ($this->tableExists('nfse')) {
            if ($this->columnExists('nfse', 'aliquota_ibs')) {
                $this->modifyColumn('nfse', 'aliquota_ibs', 'DECIMAL(5,2)', [
                    'null' => false,
                    'default' => '0.10',
                    'comment' => 'Aliquota IBS - 0,1% em 2026',
                ]);
            }
            if ($this->columnExists('nfse', 'aliquota_cbs')) {
                $this->modifyColumn('nfse', 'aliquota_cbs', 'DECIMAL(5,2)', [
                    'null' => false,
                    'default' => '0.90',
                    'comment' => 'Aliquota CBS - 0,9% em 2026',
                ]);
            }
        }
    }
};
