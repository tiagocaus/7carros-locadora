<?php

/**
 * Migration 00269: Criar tabela nfse_configuracoes
 *
 * Armazena configuracoes de NFS-e por empresa/filial.
 * Suporta modelos Nacional (SEFIN) e ABRASF (Municipal).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('nfse_configuracoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE nfse_configuracoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',
                id_matriz_filial INT UNSIGNED NOT NULL COMMENT 'FK para matrizes_filiais.id',

                -- Certificado Digital
                certificado_arquivo VARCHAR(100) NULL COMMENT 'Nome do arquivo .pfx do certificado',
                certificado_senha VARCHAR(255) NULL COMMENT 'Senha do certificado (criptografada)',
                certificado_validade DATE NULL COMMENT 'Data de validade do certificado',

                -- Configuracao Geral
                ativo CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'Emissao de NFS-e ativa (S/N)',
                ambiente TINYINT(1) NOT NULL DEFAULT 2 COMMENT '1=Producao, 2=Homologacao',
                tipo_emissao VARCHAR(20) NOT NULL DEFAULT 'nacional' COMMENT 'nacional ou abrasf',
                serie VARCHAR(10) NULL COMMENT 'Serie da DPS/RPS',
                numero_atual INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimo numero de NFS-e emitido (Nacional)',
                emissao_auto CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'Emitir automaticamente ao confirmar pagamento (S/N)',
                enviar_email CHAR(1) NOT NULL DEFAULT 'S' COMMENT 'Enviar PDF por email automaticamente (S/N)',

                -- Municipio
                codigo_municipio VARCHAR(10) NULL COMMENT 'Codigo IBGE do municipio (7 digitos)',

                -- Servico
                codigo_servico VARCHAR(20) DEFAULT '1.1101.11' COMMENT 'Codigo NBS do servico',
                descricao_servico TEXT NULL COMMENT 'Descricao padrao do servico prestado',

                -- Tributacao
                regime_tributario TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Simples Nacional, 2=Lucro Presumido, 3=Lucro Real',
                trib_issqn TINYINT(1) NOT NULL DEFAULT 4 COMMENT '1=Tributavel, 2=Imunidade, 3=Exportacao Servico, 4=Nao Incidencia',
                aliquota_iss DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Aliquota de ISS municipal (%)',
                exigibilidade_iss TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Tipo de exigibilidade do ISS',
                incentivo_fiscal CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'Possui incentivo fiscal (S/N)',

                -- Campos especificos ABRASF
                abrasf_item_lista_servico VARCHAR(10) DEFAULT '' COMMENT 'Item da lista de servico ABRASF',
                abrasf_codigo_cnae VARCHAR(10) DEFAULT '' COMMENT 'Codigo CNAE da atividade economica',
                abrasf_codigo_trib_municipio VARCHAR(20) DEFAULT '' COMMENT 'Codigo de tributacao do municipio',
                abrasf_numero_rps INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Contador independente de RPS para ABRASF',

                -- Auditoria
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                -- Indices
                UNIQUE INDEX idx_nfse_config_filial (chave, id_matriz_filial),
                INDEX idx_nfse_config_ativo (ativo),
                INDEX idx_nfse_config_tipo (tipo_emissao)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Configuracoes de NFS-e por empresa/filial'
        ");
    }

    public function down(): void
    {
        $this->drop('nfse_configuracoes');
    }
};
