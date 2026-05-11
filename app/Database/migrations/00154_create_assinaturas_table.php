<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela assinaturas
 *
 * Centraliza assinaturas digitais de contratos e locações.
 * Migra dados existentes de contratos.assinatura e locacoes.assinatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar tabela assinaturas
        $this->create('assinaturas', function ($table) {
            $table->id();
            // chave segue padrao das demais tabelas multi-tenant (45 chars)
            $table->string('chave', 45);

            // FKs (apenas uma de id_contrato/id_locacao preenchida)
            $table->integer('id_contrato')->unsigned()->nullable();
            $table->integer('id_locacao')->unsigned()->nullable();
            $table->integer('id_cliente')->unsigned()->nullable();

            // Dados da assinatura (LONGTEXT comporta tanto path/URL quanto base64 inline)
            $table->addColumn('`arquivo` LONGTEXT NOT NULL');
            $table->string('hash_arquivo', 64)->nullable();

            // Dados técnicos (auditoria)
            $table->string('ip_address', 45)->default('0.0.0.0');
            $table->text('user_agent')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Verificação
            $table->string('token_verificacao', 64)->nullable();
            $table->datetime('verificado_em')->nullable();

            // Metadados
            $table->enum('tipo', ['cliente', 'testemunha', 'fiador', 'avalista'])->default('cliente');
            $table->text('observacao')->nullable();

            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices
            $table->index('chave', 'idx_assinaturas_chave');
            $table->index('id_contrato', 'idx_assinaturas_contrato');
            $table->index('id_locacao', 'idx_assinaturas_locacao');
            $table->index('id_cliente', 'idx_assinaturas_cliente');
            $table->index('token_verificacao', 'idx_assinaturas_token');

            // Foreign keys
            $table->foreign('id_contrato')
                ->on('contratos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_locacao')
                ->on('locacoes')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_cliente')
                ->on('clientes')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });

        // 2. Migrar dados de contratos.assinatura
        if ($this->columnExists('contratos', 'assinatura')) {
            $contratos = $this->db()
                ->table('contratos')
                ->select(['id', 'chave', 'id_cliente', 'assinatura', 'created_at'])
                ->whereRaw('assinatura IS NOT NULL AND assinatura != ""')
                ->get();

            foreach ($contratos as $contrato) {
                $this->db()->table('assinaturas')->insert([
                    'chave' => $contrato['chave'],
                    'id_contrato' => $contrato['id'],
                    'id_cliente' => $contrato['id_cliente'],
                    'arquivo' => $contrato['assinatura'],
                    'ip_address' => '0.0.0.0',
                    'tipo' => 'cliente',
                    'created_at' => $contrato['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            // Remover coluna assinatura de contratos
            $this->dropColumnIfExists('contratos', 'assinatura');
        }

        // 3. Migrar dados de locacoes.assinatura
        if ($this->columnExists('locacoes', 'assinatura')) {
            $locacoes = $this->db()
                ->table('locacoes')
                ->select(['id', 'chave', 'id_cliente', 'assinatura', 'created_at'])
                ->whereRaw('assinatura IS NOT NULL AND assinatura != ""')
                ->get();

            foreach ($locacoes as $locacao) {
                $this->db()->table('assinaturas')->insert([
                    'chave' => $locacao['chave'],
                    'id_locacao' => $locacao['id'],
                    'id_cliente' => $locacao['id_cliente'],
                    'arquivo' => $locacao['assinatura'],
                    'ip_address' => '0.0.0.0',
                    'tipo' => 'cliente',
                    'created_at' => $locacao['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            // Remover coluna assinatura de locacoes
            $this->dropColumnIfExists('locacoes', 'assinatura');
        }
    }

    public function down(): void
    {
        // 1. Recriar colunas de assinatura
        $this->addColumnIfNotExists('contratos', 'assinatura', 'LONGTEXT', ['null' => true]);
        $this->addColumnIfNotExists('locacoes', 'assinatura', 'LONGTEXT', ['null' => true]);

        // 2. Restaurar dados de contratos
        $assinaturasContratos = $this->db()
            ->table('assinaturas')
            ->whereRaw('id_contrato IS NOT NULL')
            ->get();

        foreach ($assinaturasContratos as $assinatura) {
            $this->db()
                ->table('contratos')
                ->where('id', '=', $assinatura['id_contrato'])
                ->update(['assinatura' => $assinatura['arquivo']]);
        }

        // 3. Restaurar dados de locacoes
        $assinaturasLocacoes = $this->db()
            ->table('assinaturas')
            ->whereRaw('id_locacao IS NOT NULL')
            ->get();

        foreach ($assinaturasLocacoes as $assinatura) {
            $this->db()
                ->table('locacoes')
                ->where('id', '=', $assinatura['id_locacao'])
                ->update(['assinatura' => $assinatura['arquivo']]);
        }

        // 4. Dropar tabela assinaturas
        $this->drop('assinaturas');
    }
};
