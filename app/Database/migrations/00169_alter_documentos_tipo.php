<?php

use App\Database\Migration;

/**
 * Migration: Normalizar coluna tipo da tabela documentos
 *
 * Mapeamento legado -> novo (shift -1):
 *   1 (Ambos)    -> 0
 *   2 (Contrato) -> 1
 *   3 (Locacao)  -> 2
 *   4 (Multa)    -> 3
 *
 * Comentario final da coluna: [0]Ambos [1]Contrato [2]Locação [3]Multa
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remapear todos os valores num unico UPDATE com CASE
        //    (CASE avalia o valor original de cada linha, evitando colisoes)
        $this->execute("
            UPDATE documentos
            SET tipo = CASE tipo
                WHEN 1 THEN 0
                WHEN 2 THEN 1
                WHEN 3 THEN 2
                WHEN 4 THEN 3
                ELSE tipo
            END
            WHERE tipo IN (1, 2, 3, 4)
        ");

        $affected = $this->db()->getMysqli()->affected_rows;
        echo "  - Registros remapeados (1->0, 2->1, 3->2, 4->3): {$affected}\n";

        // 2. Alterar coluna para adicionar comentário descritivo
        $this->execute("
            ALTER TABLE documentos
            MODIFY COLUMN tipo TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '[0]Contrato/Locação [1]Contrato [2]Locação [3]Multa'
        ");
        echo "  - Comentário da coluna tipo atualizado.\n";
    }

    public function down(): void
    {
        // Reverter mapeamento: shift +1
        $this->execute("
            UPDATE documentos
            SET tipo = CASE tipo
                WHEN 0 THEN 1
                WHEN 1 THEN 2
                WHEN 2 THEN 3
                WHEN 3 THEN 4
                ELSE tipo
            END
            WHERE tipo IN (0, 1, 2, 3)
        ");

        $this->execute("
            ALTER TABLE documentos
            MODIFY COLUMN tipo INT(1) NOT NULL
        ");
        echo "  - Coluna tipo revertida (valores e comentário).\n";
    }
};
