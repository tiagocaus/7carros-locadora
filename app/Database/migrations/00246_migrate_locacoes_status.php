<?php

use App\Database\Migration;

/**
 * Migration: Migrar status da tabela locacoes
 *
 * Status antigos: R (Reserva), S (Saida), C (Concluida)
 * Status novos:   R (Reserva), A (Aberto), F (Fechado)
 *
 * S -> A (locacoes em andamento)
 * C -> F (locacoes finalizadas)
 * R permanece R
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migrar S (Saida) -> A (Aberto)
        $this->db()
            ->table('locacoes')
            ->withoutChave()
            ->whereRaw("situacao = 'S'")
            ->update(['situacao' => 'A']);

        // Migrar C (Concluida) -> F (Fechado)
        $this->db()
            ->table('locacoes')
            ->withoutChave()
            ->whereRaw("situacao = 'C'")
            ->update(['situacao' => 'F']);
    }

    public function down(): void
    {
        // Reverter A (Aberto) -> S (Saida)
        $this->db()
            ->table('locacoes')
            ->withoutChave()
            ->whereRaw("situacao = 'A'")
            ->update(['situacao' => 'S']);

        // Reverter F (Fechado) -> C (Concluida)
        $this->db()
            ->table('locacoes')
            ->withoutChave()
            ->whereRaw("situacao = 'F'")
            ->update(['situacao' => 'C']);
    }
};
