<?php

/**
 * Migration 00270: Criar tabela nfse
 *
 * Tabela principal de Notas Fiscais de Servico Eletronicas emitidas.
 * Armazena dados do prestador, tomador, servico, tributos, XML e controle.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('nfse')) {
            return;
        }

        $this->execute("
            CREATE TABLE nfse (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',
                id_matriz_filial INT UNSIGNED NOT NULL COMMENT 'ID da empresa/filial emissora',
                id_financeiro INT UNSIGNED NULL COMMENT 'ID do lancamento financeiro vinculado',
                id_locacao INT UNSIGNED NULL COMMENT 'ID da locacao vinculada',
                id_contrato INT UNSIGNED NULL COMMENT 'ID do contrato vinculado',

                -- Identificacao da NFS-e
                numero INT UNSIGNED NULL COMMENT 'Numero da NFS-e',
                serie VARCHAR(10) NULL COMMENT 'Serie da NFS-e',
                codigo_verificacao VARCHAR(50) NULL COMMENT 'Codigo de verificacao retornado pela SEFIN',
                chave_acesso VARCHAR(60) NULL COMMENT 'Chave de acesso da NFS-e',

                -- Dados do Prestador (copia para historico)
                prestador_cnpj VARCHAR(18) NULL COMMENT 'CNPJ do prestador',
                prestador_razao_social VARCHAR(255) NULL COMMENT 'Razao social do prestador',
                prestador_inscricao_municipal VARCHAR(20) NULL COMMENT 'Inscricao municipal do prestador',

                -- Dados do Tomador (cliente)
                tomador_cpf_cnpj VARCHAR(18) NULL COMMENT 'CPF ou CNPJ do tomador',
                tomador_nome VARCHAR(255) NULL COMMENT 'Nome ou razao social do tomador',
                tomador_email VARCHAR(100) NULL COMMENT 'Email do tomador',
                tomador_endereco TEXT NULL COMMENT 'Endereco completo em JSON',

                -- Servico
                codigo_servico VARCHAR(20) NULL COMMENT 'Codigo NBS do servico',
                descricao_servico TEXT NULL COMMENT 'Descricao detalhada do servico prestado',
                valor_servicos DECIMAL(12,2) NULL COMMENT 'Valor total dos servicos',
                valor_deducoes DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor das deducoes',
                itens_nao_tributaveis TEXT NULL COMMENT 'JSON: [{descricao, valor}]',
                base_calculo DECIMAL(12,2) NULL COMMENT 'Base de calculo = valor_servicos - valor_deducoes',

                -- Tributos
                aliquota_iss DECIMAL(5,2) NULL COMMENT 'Aliquota de ISS (%)',
                valor_iss DECIMAL(12,2) NULL COMMENT 'Valor do ISS',
                aliquota_ibs DECIMAL(5,2) NOT NULL DEFAULT 0.10 COMMENT 'Aliquota IBS - 0,1% em 2026',
                valor_ibs DECIMAL(12,2) NULL COMMENT 'Valor do IBS',
                aliquota_cbs DECIMAL(5,2) NOT NULL DEFAULT 0.90 COMMENT 'Aliquota CBS - 0,9% em 2026',
                valor_cbs DECIMAL(12,2) NULL COMMENT 'Valor do CBS',
                ambiente TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '1=Producao, 2=Homologacao',

                -- Retencoes
                iss_retido CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'ISS retido na fonte (S/N)',
                valor_iss_retido DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do ISS retido',

                -- Controle
                status ENUM('pendente','processando','autorizada','rejeitada','cancelada') NOT NULL DEFAULT 'pendente',
                tipo_emissao VARCHAR(20) NOT NULL DEFAULT 'nacional' COMMENT 'nacional ou betha',
                motivo_rejeicao TEXT NULL COMMENT 'Motivo da rejeicao pela SEFIN',
                xml_envio LONGTEXT NULL COMMENT 'XML/DPS enviado para SEFIN',
                xml_retorno LONGTEXT NULL COMMENT 'XML de retorno da SEFIN',
                pdf_url VARCHAR(255) NULL COMMENT 'Caminho do PDF (DANFSE)',

                -- Datas
                data_emissao DATETIME NULL COMMENT 'Data e hora de emissao',
                data_competencia DATE NULL COMMENT 'Data de competencia do servico',
                data_cancelamento DATETIME NULL COMMENT 'Data e hora do cancelamento',
                motivo_cancelamento TEXT NULL COMMENT 'Motivo do cancelamento',

                -- Controle de email
                email_enviado DATETIME NULL COMMENT 'Data/hora do envio do email',
                email_destinatario VARCHAR(100) NULL COMMENT 'Email para onde foi enviado',
                tentativas_envio TINYINT NOT NULL DEFAULT 1 COMMENT 'Numero de tentativas de envio API',

                -- Auditoria
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                -- Indices
                INDEX idx_nfse_chave (chave),
                INDEX idx_nfse_filial (id_matriz_filial),
                INDEX idx_nfse_financeiro (id_financeiro),
                INDEX idx_nfse_locacao (id_locacao),
                INDEX idx_nfse_status (status),
                INDEX idx_nfse_numero (numero, serie),
                INDEX idx_nfse_data_emissao (data_emissao),
                INDEX idx_nfse_tomador (tomador_cpf_cnpj),
                INDEX idx_nfse_ambiente (ambiente),
                INDEX idx_nfse_email_pendente (status, email_enviado)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Notas Fiscais de Servico Eletronicas emitidas'
        ");
    }

    public function down(): void
    {
        $this->drop('nfse');
    }
};
