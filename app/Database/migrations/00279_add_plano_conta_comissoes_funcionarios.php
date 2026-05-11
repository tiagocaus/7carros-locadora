<?php

/**
 * Migration 00279: Criar plano de conta para comissões de funcionários
 *
 * Adiciona "Comissões Funcionários" como plano de conta de despesa (tipo D)
 * no nível do sistema (chave = '0'), visível para todos os tenants.
 * Posicionado sob "Custos da mão de obra" (hierarquia 3.1.2.03).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $hierarquia = '3.1.2.03';

        // Verificar se já existe (idempotência)
        $exists = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ? AND chave = ?', [$hierarquia, '0'])
            ->first();

        if (!$exists) {
            $this->db()->table('planos_de_contas')->insert([
                'chave' => '0',
                'hierarquia' => $hierarquia,
                'descricao_i18n' => json_encode([
                    'pt_BR' => 'Comissões Funcionários',
                    'pt_PT' => 'Comissões de Funcionários',
                    'en_US' => 'Employee Commissions',
                    'es_ES' => 'Comisiones de Empleados',
                    'it_IT' => 'Commissioni Dipendenti',
                ], JSON_UNESCAPED_UNICODE),
                'tipo' => 'D',
            ]);
        }
    }

    public function down(): void
    {
        $this->db()->table('planos_de_contas')
            ->whereRaw('hierarquia = ? AND chave = ?', ['3.1.2.03', '0'])
            ->delete();
    }
};
