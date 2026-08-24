<?php

use App\Database\Migration;

/**
 * Adiciona os dados declaratorios de IBS/CBS exigidos pela DPS Nacional 1.01.
 *
 * As aliquotas e os valores do documento autorizado sao calculados pela SEFIN;
 * estes campos identificam a operacao e a classificacao tributaria declarada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->addColumnIfNotExists('nfse_configuracoes', 'c_ind_op_ibscbs', 'VARCHAR(6)', [
            'null' => true,
            'after' => 'preencher_ibscbs',
            'comment' => 'Codigo indicador da operacao IBS/CBS (cIndOp)',
        ]);
        $this->addColumnIfNotExists('nfse_configuracoes', 'cst_ibscbs', 'VARCHAR(3)', [
            'null' => true,
            'after' => 'c_ind_op_ibscbs',
            'comment' => 'CST do IBS/CBS declarado na DPS Nacional',
        ]);
        $this->addColumnIfNotExists('nfse_configuracoes', 'c_class_trib_ibscbs', 'VARCHAR(6)', [
            'null' => true,
            'after' => 'cst_ibscbs',
            'comment' => 'Classificacao tributaria IBS/CBS (cClassTrib)',
        ]);
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->dropColumnIfExists('nfse_configuracoes', 'c_class_trib_ibscbs');
        $this->dropColumnIfExists('nfse_configuracoes', 'cst_ibscbs');
        $this->dropColumnIfExists('nfse_configuracoes', 'c_ind_op_ibscbs');
    }
};
