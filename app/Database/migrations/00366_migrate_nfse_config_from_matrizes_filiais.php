<?php

/**
 * Migration 00366: Migrar configuracoes de NFS-e para nfse_configuracoes
 *
 * Centraliza as configuracoes fiscais que estavam em matrizes_filiais.
 * Os arquivos de certificado NAO sao movidos aqui; mover arquivos deve ser
 * feito por rotina operacional separada.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $legacyColumns = [
        'certificado_arquivo',
        'certificado_senha',
        'certificado_validade',
        'nfse_ambiente',
        'nfse_serie',
        'nfse_numero_atual',
        'nfse_abrasf_numero_rps',
        'nfse_codigo_servico',
        'nfse_item_lista_servico',
        'nfse_codigo_cnae',
        'nfse_codigo_trib_municipio',
        'nfse_descricao_servico',
        'nfse_aliquota_iss',
        'nfse_trib_issqn',
        'nfse_exigibilidade_iss',
        'nfse_regime_tributario',
        'nfse_reg_apuracao_sn',
        'nfse_enviar_im',
        'nfse_incentivo_fiscal',
        'nfse_codigo_municipio',
        'nfse_emissao_auto',
        'nfse_ativo',
        'nfse_tipo_emissao',
        'nfse_enviar_email',
    ];

    public function up(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            throw new \RuntimeException('Tabela nfse_configuracoes nao existe. Execute a migration 00269 primeiro.');
        }

        if (!$this->tableExists('matrizes_filiais')) {
            throw new \RuntimeException('Tabela matrizes_filiais nao existe.');
        }

        if ($this->columnExists('nfse_configuracoes', 'descricao_servico')
            && $this->getColumnType('nfse_configuracoes', 'descricao_servico') !== 'mediumtext') {
            $this->modifyColumn('nfse_configuracoes', 'descricao_servico', 'MEDIUMTEXT', ['null' => true]);
        }

        $this->addColumnIfNotExists('nfse_configuracoes', 'reg_apuracao_sn', 'TINYINT(1)', [
            'null' => false,
            'default' => 1,
            'comment' => 'Regime de apuracao do Simples Nacional',
            'after' => 'regime_tributario',
        ]);

        $this->addColumnIfNotExists('nfse_configuracoes', 'enviar_im', 'CHAR(1)', [
            'null' => false,
            'default' => 'N',
            'comment' => 'Enviar inscricao municipal no XML (S/N)',
            'after' => 'exigibilidade_iss',
        ]);

        if ($this->hasLegacyColumns()) {
            $this->migrateData();
        }

        $this->dropLegacyColumns();
    }

    public function down(): void
    {
        if (!$this->tableExists('matrizes_filiais')) {
            throw new \RuntimeException('Tabela matrizes_filiais nao existe.');
        }

        $this->restoreLegacyColumns();
        $this->restoreData();
    }

    private function migrateData(): void
    {
        $columns = [
            'chave',
            'id_matriz_filial',
            'certificado_arquivo',
            'certificado_senha',
            'certificado_validade',
            'ativo',
            'ambiente',
            'tipo_emissao',
            'serie',
            'numero_atual',
            'emissao_auto',
            'enviar_email',
            'codigo_municipio',
            'codigo_servico',
            'descricao_servico',
            'regime_tributario',
            'reg_apuracao_sn',
            'trib_issqn',
            'aliquota_iss',
            'exigibilidade_iss',
            'enviar_im',
            'incentivo_fiscal',
            'abrasf_item_lista_servico',
            'abrasf_codigo_cnae',
            'abrasf_codigo_trib_municipio',
            'abrasf_numero_rps',
            'created_at',
            'updated_at',
        ];

        $selects = [
            'mf.chave',
            'mf.id',
            $this->mfExpr('certificado_arquivo', 'NULL'),
            $this->mfExpr('certificado_senha', 'NULL'),
            $this->mfExpr('certificado_validade', 'NULL'),
            $this->mfExpr('nfse_ativo', "'N'"),
            $this->mfExpr('nfse_ambiente', '2'),
            $this->mfExpr('nfse_tipo_emissao', "'nacional'"),
            $this->mfExpr('nfse_serie', 'NULL'),
            $this->mfExpr('nfse_numero_atual', '0'),
            $this->mfExpr('nfse_emissao_auto', "'N'"),
            $this->mfExpr('nfse_enviar_email', "'S'"),
            $this->mfExpr('nfse_codigo_municipio', 'NULL'),
            $this->mfExpr('nfse_codigo_servico', "'1.1101.11'"),
            $this->mfExpr('nfse_descricao_servico', 'NULL'),
            $this->mfExpr('nfse_regime_tributario', '1'),
            $this->mfExpr('nfse_reg_apuracao_sn', '1'),
            $this->mfExpr('nfse_trib_issqn', '4'),
            $this->mfExpr('nfse_aliquota_iss', '0.00'),
            $this->mfExpr('nfse_exigibilidade_iss', '1'),
            $this->mfExpr('nfse_enviar_im', "'N'"),
            $this->mfExpr('nfse_incentivo_fiscal', "'N'"),
            $this->mfExpr('nfse_item_lista_servico', "''"),
            $this->mfExpr('nfse_codigo_cnae', "''"),
            $this->mfExpr('nfse_codigo_trib_municipio', "''"),
            $this->mfExpr('nfse_abrasf_numero_rps', '0'),
            'COALESCE(mf.created_at, NOW())',
            'NOW()',
        ];

        $updates = array_filter($columns, fn ($column) => !in_array($column, ['chave', 'id_matriz_filial', 'created_at'], true));
        $updateSql = implode(",\n                ", array_map(
            fn ($column) => "`{$column}` = VALUES(`{$column}`)",
            $updates
        ));

        $this->execute("
            INSERT INTO nfse_configuracoes (`" . implode('`, `', $columns) . "`)
            SELECT
                " . implode(",\n                ", $selects) . "
            FROM matrizes_filiais mf
            ON DUPLICATE KEY UPDATE
                {$updateSql}
        ");
    }

    private function dropLegacyColumns(): void
    {
        foreach ($this->legacyColumns as $column) {
            $this->dropColumnIfExists('matrizes_filiais', $column);
        }
    }

    private function restoreLegacyColumns(): void
    {
        $this->addColumnIfNotExists('matrizes_filiais', 'certificado_arquivo', 'VARCHAR(100)', ['null' => true, 'default' => null]);
        $this->addColumnIfNotExists('matrizes_filiais', 'certificado_senha', 'VARCHAR(255)', ['null' => true, 'default' => null]);
        $this->addColumnIfNotExists('matrizes_filiais', 'certificado_validade', 'DATE', ['null' => true, 'default' => null]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_ambiente', 'TINYINT(1)', ['null' => true, 'default' => 2]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_serie', 'VARCHAR(10)', ['null' => true, 'default' => null]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_numero_atual', 'INT', ['unsigned' => true, 'null' => true, 'default' => 0]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_abrasf_numero_rps', 'INT', ['null' => true, 'default' => 0]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_codigo_servico', 'VARCHAR(20)', ['null' => true, 'default' => '1.1101.11']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_item_lista_servico', 'VARCHAR(10)', ['null' => true, 'default' => '']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_codigo_cnae', 'VARCHAR(10)', ['null' => true, 'default' => '']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_codigo_trib_municipio', 'VARCHAR(20)', ['null' => true, 'default' => '']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_descricao_servico', 'MEDIUMTEXT', ['null' => true]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_aliquota_iss', 'DECIMAL(5,2)', ['null' => true, 'default' => 0.00]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_trib_issqn', 'TINYINT(1)', ['null' => false, 'default' => 4]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_exigibilidade_iss', 'TINYINT(4)', ['null' => true, 'default' => 1]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_regime_tributario', 'TINYINT(1)', ['null' => true, 'default' => 1]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_reg_apuracao_sn', 'TINYINT(1)', ['null' => true, 'default' => 1]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_enviar_im', 'CHAR(1)', ['null' => true, 'default' => 'N']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_incentivo_fiscal', 'CHAR(1)', ['null' => true, 'default' => 'N']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_codigo_municipio', 'VARCHAR(10)', ['null' => true, 'default' => null]);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_emissao_auto', 'CHAR(1)', ['null' => true, 'default' => 'N']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_ativo', 'CHAR(1)', ['null' => true, 'default' => 'N']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_tipo_emissao', 'VARCHAR(20)', ['null' => true, 'default' => 'nacional']);
        $this->addColumnIfNotExists('matrizes_filiais', 'nfse_enviar_email', 'CHAR(1)', ['null' => true, 'default' => 'S']);
    }

    private function restoreData(): void
    {
        if (!$this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->execute("
            UPDATE matrizes_filiais mf
            INNER JOIN nfse_configuracoes nc
                ON nc.chave = mf.chave
                AND nc.id_matriz_filial = mf.id
            SET
                mf.certificado_arquivo = nc.certificado_arquivo,
                mf.certificado_senha = nc.certificado_senha,
                mf.certificado_validade = nc.certificado_validade,
                mf.nfse_ambiente = nc.ambiente,
                mf.nfse_serie = nc.serie,
                mf.nfse_numero_atual = nc.numero_atual,
                mf.nfse_abrasf_numero_rps = nc.abrasf_numero_rps,
                mf.nfse_codigo_servico = nc.codigo_servico,
                mf.nfse_item_lista_servico = nc.abrasf_item_lista_servico,
                mf.nfse_codigo_cnae = nc.abrasf_codigo_cnae,
                mf.nfse_codigo_trib_municipio = nc.abrasf_codigo_trib_municipio,
                mf.nfse_descricao_servico = nc.descricao_servico,
                mf.nfse_aliquota_iss = nc.aliquota_iss,
                mf.nfse_trib_issqn = nc.trib_issqn,
                mf.nfse_exigibilidade_iss = nc.exigibilidade_iss,
                mf.nfse_regime_tributario = nc.regime_tributario,
                mf.nfse_reg_apuracao_sn = nc.reg_apuracao_sn,
                mf.nfse_enviar_im = nc.enviar_im,
                mf.nfse_incentivo_fiscal = nc.incentivo_fiscal,
                mf.nfse_codigo_municipio = nc.codigo_municipio,
                mf.nfse_emissao_auto = nc.emissao_auto,
                mf.nfse_ativo = nc.ativo,
                mf.nfse_tipo_emissao = nc.tipo_emissao,
                mf.nfse_enviar_email = nc.enviar_email
        ");
    }

    private function mfExpr(string $column, string $default): string
    {
        if (!$this->columnExists('matrizes_filiais', $column)) {
            return $default;
        }

        return "COALESCE(mf.`{$column}`, {$default})";
    }

    private function hasLegacyColumns(): bool
    {
        foreach ($this->legacyColumns as $column) {
            if ($this->columnExists('matrizes_filiais', $column)) {
                return true;
            }
        }

        return false;
    }
};
