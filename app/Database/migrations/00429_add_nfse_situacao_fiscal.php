<?php

use App\Database\Migration;

/**
 * Permite reconciliar cancelamentos e substituicoes registrados fora do sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        // SQL literal para preservar o case dos valores; modifyColumn() aplica strtoupper ao tipo.
        $this->execute(
            "ALTER TABLE `nfse` MODIFY COLUMN `status` "
            . "ENUM('pendente','processando','autorizada','rejeitada','cancelada','substituida') "
            . "NULL DEFAULT 'pendente'"
        );
        $this->addColumnIfNotExists('nfse', 'situacao_fiscal', 'CHAR(1)', [
            'null' => true,
            'after' => 'cancelamento_solicitado_em',
            'comment' => 'Situacao confirmada no ADN: N=normal, C=cancelada, S=substituida',
        ]);
        $this->addColumnIfNotExists('nfse', 'situacao_fiscal_consultada_em', 'DATETIME', [
            'null' => true,
            'after' => 'situacao_fiscal',
            'comment' => 'Data da ultima consulta fiscal bem-sucedida no ADN',
        ]);
        $this->addIndexIfNotExists(
            'nfse',
            ['tipo_emissao', 'status', 'situacao_fiscal_consultada_em'],
            'idx_nfse_situacao_fiscal_betha'
        );
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        $this->execute("UPDATE `nfse` SET `status` = 'cancelada' WHERE `status` = 'substituida'");
        $this->dropIndexIfExists('nfse', 'idx_nfse_situacao_fiscal_betha');
        $this->dropColumnIfExists('nfse', 'situacao_fiscal_consultada_em');
        $this->dropColumnIfExists('nfse', 'situacao_fiscal');
        $this->execute(
            "ALTER TABLE `nfse` MODIFY COLUMN `status` "
            . "ENUM('pendente','processando','autorizada','rejeitada','cancelada') "
            . "NULL DEFAULT 'pendente'"
        );
    }
};
