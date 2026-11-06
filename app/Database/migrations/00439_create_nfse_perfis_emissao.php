<?php

use App\Database\Migration;

/**
 * Preserva perfis municipais/nacionais e suas sequencias antes do roteamento por competencia.
 */
return new class extends Migration
{
    private const CAMPOS_FISCAIS = [
        'codigo_municipio', 'codigo_servico', 'codigo_tributacao_nacional',
        'item_lista_servico', 'codigo_cnae', 'codigo_tributacao_municipio',
        'descricao_servico', 'regime_tributario', 'reg_apuracao_sn', 'trib_issqn',
        'aliquota_iss', 'preencher_ibscbs', 'c_ind_op_ibscbs', 'cst_ibscbs',
        'c_class_trib_ibscbs', 'aliquota_ibs', 'aliquota_cbs', 'exigibilidade_iss',
        'enviar_im', 'incentivo_fiscal',
    ];

    public function up(): void
    {
        if (!$this->tableExists('nfse_configuracoes') || !$this->tableExists('nfse')) {
            throw new \RuntimeException('NFS-e exige as tabelas nfse_configuracoes e nfse.');
        }

        if (!$this->tableExists('nfse_perfis_emissao')) {
            $this->create('nfse_perfis_emissao', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_matriz_filial')->unsigned();
                $table->string('tipo_emissao', 20);
                $table->boolean('ambiente');
                $table->string('serie', 10)->nullable();
                $table->integer('numero_atual')->unsigned()->default(0);
                $table->json('configuracao');
                $table->addColumn("`confirmado` CHAR(1) NOT NULL DEFAULT 'N'");
                $table->timestamps();
                $table->unique(['chave', 'id_matriz_filial', 'tipo_emissao', 'ambiente'], 'uniq_nfse_perfil_contexto');
            });
        }

        if (!$this->tableExists('nfse_sequencias')) {
            $this->create('nfse_sequencias', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->integer('id_matriz_filial')->unsigned();
                $table->string('tipo_emissao', 20);
                $table->boolean('ambiente');
                $table->string('serie', 10);
                $table->integer('numero_atual')->unsigned()->default(0);
                $table->unique(['chave', 'id_matriz_filial', 'tipo_emissao', 'ambiente', 'serie'], 'uniq_nfse_sequencia_contexto');
            });
        }

        $this->addColumnIfNotExists('nfse', 'contexto_fiscal', 'JSON', ['null' => true]);

        $json = implode(', ', array_map(
            static fn (string $campo): string => "'{$campo}', c.`{$campo}`",
            self::CAMPOS_FISCAIS
        ));

        // INSERT SELECT/JSON_OBJECT conserva os tipos SQL e copia somente a allowlist fiscal.
        // SQL em lote evita materializar XMLs, certificados ou segredos de todos os tenants.
        $this->execute("
            INSERT INTO nfse_perfis_emissao
                (chave, id_matriz_filial, tipo_emissao, ambiente, serie, numero_atual,
                 configuracao, confirmado, created_at, updated_at)
            SELECT c.chave, c.id_matriz_filial, c.tipo_emissao, c.ambiente,
                   NULLIF(TRIM(c.serie), ''), c.numero_atual,
                   JSON_OBJECT({$json}), CASE WHEN c.ativo = 'S' THEN 'S' ELSE 'N' END, c.created_at, c.updated_at
            FROM nfse_configuracoes c
            ON DUPLICATE KEY UPDATE numero_atual = GREATEST(nfse_perfis_emissao.numero_atual, VALUES(numero_atual))
        ");

        $this->execute("
            INSERT INTO nfse_sequencias
                (chave, id_matriz_filial, tipo_emissao, ambiente, serie, numero_atual)
            SELECT chave, id_matriz_filial, tipo_emissao, ambiente,
                   CASE WHEN tipo_emissao = 'nacional' THEN
                       COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(serie)), ''), '0')
                   ELSE TRIM(serie) END, MAX(numero_atual)
            FROM nfse_perfis_emissao
            WHERE serie IS NOT NULL AND TRIM(serie) <> ''
            GROUP BY chave, id_matriz_filial, tipo_emissao, ambiente,
                     CASE WHEN tipo_emissao = 'nacional' THEN
                         COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(serie)), ''), '0')
                     ELSE TRIM(serie) END
            ON DUPLICATE KEY UPDATE numero_atual = GREATEST(nfse_sequencias.numero_atual, VALUES(numero_atual))
        ");

        // numero pode ser o numero oficial autorizado: usamos o maior valor conservadoramente,
        // sem alterar ou renumerar notas, inclusive protocolos em processamento/rejeicoes.
        $this->execute("
            INSERT INTO nfse_sequencias
                (chave, id_matriz_filial, tipo_emissao, ambiente, serie, numero_atual)
            SELECT chave, id_matriz_filial, tipo_emissao, ambiente,
                   CASE WHEN tipo_emissao = 'nacional' THEN
                       COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(serie)), ''), '0')
                   ELSE TRIM(serie) END, COALESCE(MAX(numero), 0)
            FROM nfse
            WHERE serie IS NOT NULL AND TRIM(serie) <> ''
              AND tipo_emissao IS NOT NULL AND ambiente IS NOT NULL
            GROUP BY chave, id_matriz_filial, tipo_emissao, ambiente,
                     CASE WHEN tipo_emissao = 'nacional' THEN
                         COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(serie)), ''), '0')
                     ELSE TRIM(serie) END
            ON DUPLICATE KEY UPDATE numero_atual = GREATEST(nfse_sequencias.numero_atual, VALUES(numero_atual))
        ");

        $this->execute("
            UPDATE nfse_perfis_emissao p
            INNER JOIN nfse_sequencias s
                ON s.chave = p.chave AND s.id_matriz_filial = p.id_matriz_filial
               AND s.tipo_emissao = p.tipo_emissao AND s.ambiente = p.ambiente
               AND s.serie = CASE WHEN p.tipo_emissao = 'nacional' THEN
                   COALESCE(NULLIF(TRIM(LEADING '0' FROM TRIM(p.serie)), ''), '0')
                   ELSE TRIM(p.serie) END
            SET p.numero_atual = GREATEST(p.numero_atual, s.numero_atual)
        ");

        // Notas antigas continuam com contexto NULL: a configuracao atual nao prova o XML historico.
    }

    public function down(): void
    {
        $this->dropColumnIfExists('nfse', 'contexto_fiscal');
        $this->drop('nfse_sequencias');
        $this->drop('nfse_perfis_emissao');
    }
};
