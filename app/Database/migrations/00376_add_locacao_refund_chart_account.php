<?php

/**
 * Migration 00376: Plano de contas para devolucao/reembolso de locacao
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            return;
        }

        $exists = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['3.4.1.22'])
            ->first();

        if ($exists) {
            return;
        }

        $this->db()->table('planos_de_contas')->insert([
            'chave' => '0',
            'hierarquia' => '3.4.1.22',
            'descricao_i18n' => '{"pt_BR":"Devolução/Reembolso de locação","pt_PT":"Devolução/Reembolso de aluguer","en_US":"Rental refund","es_ES":"Reembolso de alquiler","it_IT":"Rimborso noleggio"}',
            'tipo' => 'D',
        ]);
    }

    public function down(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            return;
        }

        $this->db()->table('planos_de_contas')
            ->whereRaw('hierarquia = ?', ['3.4.1.22'])
            ->delete();
    }
};
