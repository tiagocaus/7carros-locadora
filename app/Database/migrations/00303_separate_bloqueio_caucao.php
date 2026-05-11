<?php

/**
 * Migration 00303: Separar Bloqueio e Caucao em locacoes
 *
 * Bloqueio = pre-autorizacao no cartao (nao gera financeiro)
 * Caucao = deposito de garantia real (gera financeiro)
 *
 * 1. Cria tabela locacoes_bloqueios para authorization holds
 * 2. Adiciona colunas caucao_* em locacoes
 * 3. Migra dados existentes bloqueio_* -> caucao_* (eram efetivamente caucoes)
 * 4. Atualiza plano de contas no financeiro de Bloqueio -> Caucao
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar tabela locacoes_bloqueios (authorization holds)
        $this->create('locacoes_bloqueios', function ($table) {
            $table->id();
            $table->addColumn('`chave` VARCHAR(20) NOT NULL');
            $table->addColumn('`id_locacao` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_cliente` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_cartao` INT UNSIGNED NOT NULL COMMENT "FK clientes_cartoes"');
            $table->addColumn('`id_gateway` INT UNSIGNED NOT NULL COMMENT "FK gateways_pagamento"');
            $table->addColumn('`gateway_code` VARCHAR(50) NOT NULL COMMENT "stripe, square"');
            $table->addColumn('`external_id` VARCHAR(255) NULL COMMENT "pi_xxx ou payment_id"');
            $table->addColumn('`valor` DECIMAL(10,2) NOT NULL');
            $table->addColumn('`moeda` VARCHAR(3) NOT NULL DEFAULT \'BRL\'');
            $table->addColumn('`status` ENUM(\'pending\',\'authorized\',\'captured\',\'released\',\'expired\',\'failed\') NOT NULL DEFAULT \'pending\'');
            $table->addColumn('`autorizado_em` DATETIME NULL');
            $table->addColumn('`capturado_em` DATETIME NULL');
            $table->addColumn('`liberado_em` DATETIME NULL');
            $table->addColumn('`expira_em` DATETIME NULL COMMENT "Data/hora que o hold expira"');
            $table->addColumn('`valor_capturado` DECIMAL(10,2) NULL COMMENT "Captura parcial"');
            $table->addColumn('`payload` JSON NULL COMMENT "Resposta raw do gateway"');
            $table->addColumn('`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
            $table->addColumn('`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

            $table->index('id_locacao', 'idx_loc_bloq_locacao');
            $table->index('status', 'idx_loc_bloq_status');
            $table->index('external_id', 'idx_loc_bloq_external');
            $table->index(['chave', 'id_locacao'], 'idx_loc_bloq_chave_locacao');

            $table->foreign('id_locacao')
                ->on('locacoes')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_cartao')
                ->on('clientes_cartoes')
                ->references('id')
                ->onDelete('RESTRICT')
                ->onUpdate('CASCADE');

            $table->foreign('id_gateway')
                ->on('gateways_pagamento')
                ->references('id')
                ->onDelete('RESTRICT')
                ->onUpdate('CASCADE');
        });

        echo "Tabela locacoes_bloqueios criada\n";

        // 2. Adicionar colunas de caucao em locacoes
        $this->addColumnIfNotExists('locacoes', 'caucao_valor', 'DECIMAL(10,2)', [
            'default' => '0',
            'after' => 'bloqueio_data_devolucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_tipo', 'VARCHAR(20)', [
            'nullable' => true,
            'after' => 'caucao_valor',
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_conta_caucao', 'INT UNSIGNED', [
            'nullable' => true,
            'after' => 'caucao_tipo',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_prazo_devolucao', 'INT', [
            'nullable' => true,
            'after' => 'id_conta_caucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_data_devolucao', 'DATE', [
            'nullable' => true,
            'after' => 'caucao_prazo_devolucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_cartao_caucao', 'INT UNSIGNED', [
            'nullable' => true,
            'after' => 'caucao_data_devolucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_bloqueio_ativo', 'INT UNSIGNED', [
            'nullable' => true,
            'after' => 'id_cartao_caucao',
        ]);

        echo "Colunas caucao_* e id_bloqueio_ativo adicionadas em locacoes\n";

        // 3. Migrar dados existentes: bloqueio_* -> caucao_*
        // Dados existentes com bloqueio_valor > 0 eram efetivamente caucoes (geravam financeiro)
        $migrados = $this->db()
            ->table('locacoes')
            ->whereRaw('bloqueio_valor > 0')
            ->whereRaw('caucao_valor = 0')
            ->count();

        if ($migrados > 0) {
            $this->execute("
                UPDATE locacoes
                SET caucao_valor = bloqueio_valor,
                    caucao_tipo = bloqueio_tipo,
                    id_conta_caucao = id_conta_bloqueio,
                    caucao_prazo_devolucao = bloqueio_prazo_devolucao,
                    caucao_data_devolucao = bloqueio_data_devolucao,
                    bloqueio_valor = 0,
                    bloqueio_tipo = NULL,
                    id_conta_bloqueio = NULL,
                    bloqueio_prazo_devolucao = NULL,
                    bloqueio_data_devolucao = NULL
                WHERE bloqueio_valor > 0
                  AND caucao_valor = 0
            ");

            echo "Locacoes migradas de bloqueio -> caucao: {$migrados}\n";
        }

        // 4. Atualizar plano de contas no financeiro: Bloqueio -> Caucao
        // Buscar IDs de caucao pela hierarquia (podem variar entre deploys)
        $caucaoEntrada = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['1.1.6.01'])
            ->first();

        $caucaoSaida = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['1.1.6.02'])
            ->first();

        if ($caucaoEntrada && $caucaoSaida) {
            $caucaoEntradaId = (int) $caucaoEntrada['id'];
            $caucaoSaidaId = (int) $caucaoSaida['id'];

            // Atualizar financeiro de locacoes com plano 117 (Bloqueio entrada) -> Caucao entrada
            $updEntrada = $this->execute("
                UPDATE financeiro
                SET id_plano_de_conta = {$caucaoEntradaId}
                WHERE id_plano_de_conta = 117
                  AND id_locacao IS NOT NULL
            ");

            // Atualizar financeiro de locacoes com plano 118 (Bloqueio saida) -> Caucao saida
            $updSaida = $this->execute("
                UPDATE financeiro
                SET id_plano_de_conta = {$caucaoSaidaId}
                WHERE id_plano_de_conta = 118
                  AND id_locacao IS NOT NULL
            ");

            echo "Financeiro atualizado: entrada -> plano {$caucaoEntradaId}, saida -> plano {$caucaoSaidaId}\n";
        } else {
            echo "AVISO: Planos de conta de Caucao nao encontrados (1.1.6.01 / 1.1.6.02)\n";
        }
    }

    public function down(): void
    {
        // Reverter dados caucao -> bloqueio
        $this->execute("
            UPDATE locacoes
            SET bloqueio_valor = caucao_valor,
                bloqueio_tipo = caucao_tipo,
                id_conta_bloqueio = id_conta_caucao,
                bloqueio_prazo_devolucao = caucao_prazo_devolucao,
                bloqueio_data_devolucao = caucao_data_devolucao
            WHERE caucao_valor > 0
              AND bloqueio_valor = 0
        ");

        // Reverter plano de contas no financeiro
        $caucaoEntrada = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['1.1.6.01'])
            ->first();

        $caucaoSaida = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['1.1.6.02'])
            ->first();

        if ($caucaoEntrada && $caucaoSaida) {
            $this->execute("
                UPDATE financeiro
                SET id_plano_de_conta = 117
                WHERE id_plano_de_conta = {$caucaoEntrada['id']}
                  AND id_locacao IS NOT NULL
            ");

            $this->execute("
                UPDATE financeiro
                SET id_plano_de_conta = 118
                WHERE id_plano_de_conta = {$caucaoSaida['id']}
                  AND id_locacao IS NOT NULL
            ");
        }

        // Remover colunas caucao
        $this->dropColumnIfExists('locacoes', 'id_bloqueio_ativo');
        $this->dropColumnIfExists('locacoes', 'id_cartao_caucao');
        $this->dropColumnIfExists('locacoes', 'caucao_data_devolucao');
        $this->dropColumnIfExists('locacoes', 'caucao_prazo_devolucao');
        $this->dropColumnIfExists('locacoes', 'id_conta_caucao');
        $this->dropColumnIfExists('locacoes', 'caucao_tipo');
        $this->dropColumnIfExists('locacoes', 'caucao_valor');

        // Remover tabela
        $this->drop('locacoes_bloqueios');
    }
};
