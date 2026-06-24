<?php

/**
 * Migration 00385: Adicionar pagador da multa.
 *
 * Define se a multa sera cobrada do cliente (receita) ou absorvida pela
 * empresa (despesa). Multas antigas sao preenchidas a partir do financeiro
 * vinculado quando existir.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('multas')) {
            return;
        }

        $this->addColumnIfNotExists('multas', 'pagador', 'VARCHAR(20)', [
            'null' => false,
            'default' => 'cliente',
            'after' => 'id_financeiro',
        ]);

        if ($this->tableExists('financeiro')) {
            $this->execute("
                UPDATE multas m
                INNER JOIN financeiro f
                    ON f.id = m.id_financeiro
                    AND f.chave = m.chave
                SET m.pagador = CASE
                    WHEN f.tipo = 'D' THEN 'empresa'
                    ELSE 'cliente'
                END
                WHERE m.id_financeiro IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if ($this->tableExists('multas')) {
            $this->dropColumnIfExists('multas', 'pagador');
        }
    }
};
