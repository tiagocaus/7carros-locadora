<?php

use App\Database\Migration;

/**
 * Separa cTribNac do NBS e impede que uma chave autorizada seja vinculada
 * a mais de uma tentativa dentro do mesmo tenant.
 *
 * A migracao falha deliberadamente se houver duplicatas historicas: elas
 * devem ser saneadas e auditadas antes da criacao da restricao.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'uniq_nfse_chave_acesso';

    public function up(): void
    {
        if ($this->tableExists('nfse_configuracoes')) {
            $this->addColumnIfNotExists('nfse_configuracoes', 'codigo_tributacao_nacional', 'VARCHAR(6)', [
                'null' => true,
                'after' => 'codigo_servico',
                'comment' => 'Codigo cTribNac da DPS Nacional, separado do NBS',
            ]);
        }

        if ($this->tableExists('nfse_eventos') && $this->columnExists('nfse_eventos', 'codigo_retorno')) {
            $this->modifyColumn('nfse_eventos', 'codigo_retorno', 'VARCHAR(50)', ['null' => true]);
        }

        if (!$this->tableExists('nfse') || $this->indexExists('nfse', self::INDEX_NAME)) {
            return;
        }

        $duplicatas = (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM (
                SELECT chave, chave_acesso
                FROM nfse
                WHERE chave_acesso IS NOT NULL AND chave_acesso <> ''
                GROUP BY chave, chave_acesso
                HAVING COUNT(*) > 1
            ) AS duplicadas
        ")->fetchColumn();

        if ($duplicatas > 0) {
            throw new RuntimeException(
                'Existem chaves de acesso NFS-e duplicadas. Execute o saneamento auditado antes desta migracao.'
            );
        }

        $this->pdo->exec(
            'ALTER TABLE `nfse` ADD UNIQUE INDEX `' . self::INDEX_NAME . '` (`chave`, `chave_acesso`)'
        );
    }

    public function down(): void
    {
        if ($this->tableExists('nfse')) {
            $this->dropIndexIfExists('nfse', self::INDEX_NAME);
        }
        if ($this->tableExists('nfse_configuracoes')) {
            $this->dropColumnIfExists('nfse_configuracoes', 'codigo_tributacao_nacional');
        }
        // codigo_retorno permanece VARCHAR(50): reduzi-lo poderia truncar auditoria ja gravada.
    }
};
