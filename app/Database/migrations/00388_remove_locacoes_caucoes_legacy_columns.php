<?php

/**
 * Migration 00388: Remover colunas legadas de caucao em locacoes.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumnIfExists('locacoes', 'caucao_valor');
        $this->dropColumnIfExists('locacoes', 'caucao_tipo');
        $this->dropColumnIfExists('locacoes', 'id_conta_caucao');
        $this->dropColumnIfExists('locacoes', 'caucao_prazo_devolucao');
        $this->dropColumnIfExists('locacoes', 'caucao_data_devolucao');
        $this->dropColumnIfExists('locacoes', 'id_cartao_caucao');
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('locacoes', 'caucao_valor', 'DECIMAL(10,2)', [
            'null' => true,
            'default' => '0.00',
            'after' => 'bloqueio_data_devolucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_tipo', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'caucao_valor',
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_conta_caucao', 'INT UNSIGNED', [
            'null' => true,
            'after' => 'caucao_tipo',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_prazo_devolucao', 'INT', [
            'null' => true,
            'after' => 'id_conta_caucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'caucao_data_devolucao', 'DATE', [
            'null' => true,
            'after' => 'caucao_prazo_devolucao',
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_cartao_caucao', 'INT UNSIGNED', [
            'null' => true,
            'after' => 'caucao_data_devolucao',
        ]);

        if ($this->tableExists('locacoes_caucoes')) {
            $this->execute("
                UPDATE locacoes l
                INNER JOIN (
                    SELECT lc1.*
                    FROM locacoes_caucoes lc1
                    INNER JOIN (
                        SELECT id_locacao, MAX(id) AS id
                        FROM locacoes_caucoes
                        GROUP BY id_locacao
                    ) ult ON ult.id = lc1.id
                ) lc ON lc.id_locacao = l.id
                SET
                    l.caucao_valor = lc.valor,
                    l.caucao_tipo = COALESCE(lc.legacy_tipo, CAST(lc.id_forma_pagamento AS CHAR)),
                    l.id_conta_caucao = lc.id_conta,
                    l.caucao_prazo_devolucao = lc.prazo_devolucao,
                    l.caucao_data_devolucao = lc.data_devolucao,
                    l.id_cartao_caucao = lc.id_cartao
            ");
        }
    }
};
