<?php

use App\Database\Migration;

/**
 * Migration: adiciona comando padrao w4-Seg para pagamento semanal na segunda-feira.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('formas_pagamento_comandos')) {
            echo "  [SKIP] Tabela formas_pagamento_comandos nao encontrada\n";
            return;
        }

        $existe = $this->db()
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->where('chave', '=', '0')
            ->where('comando', '=', 'w4-Seg')
            ->first();

        if ($existe) {
            echo "  [SKIP] Comando w4-Seg ja existe\n";
            return;
        }

        $this->db()
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->insert([
                'chave' => '0',
                'comando' => 'w4-Seg',
                'descricao' => '4 pagamento semanal com vencimento na segunda-feira',
                'status' => 'A',
            ]);

        echo "  Comando w4-Seg inserido\n";
    }

    public function down(): void
    {
        if (!$this->tableExists('formas_pagamento_comandos')) {
            return;
        }

        $this->db()
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->where('chave', '=', '0')
            ->where('comando', '=', 'w4-Seg')
            ->delete();
    }
};
