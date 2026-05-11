<?php

use App\Database\Migration;

/**
 * Migration: Atualizar status dos checklists finalizados de 4 para 2
 *
 * Novo padrao de status:
 * - 1 = Pendente (em preenchimento)
 * - 2 = Finalizado (assinado/concluido)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->execute("UPDATE checklist SET status = '2' WHERE status = '4'");
    }

    public function down(): void
    {
        $this->execute("UPDATE checklist SET status = '4' WHERE status = '2'");
    }
};
