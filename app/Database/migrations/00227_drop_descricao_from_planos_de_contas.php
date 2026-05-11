<?php

/**
 * Migration: Remove coluna descricao da tabela planos_de_contas
 *
 * Após migrar as descrições para o campo JSON descricao_i18n,
 * a coluna original não é mais necessária.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        $this->table('planos_de_contas', function ($table) {
            $table->dropColumn('descricao');
        });
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->table('planos_de_contas', function ($table) {
            $table->string('descricao', 100)->after('hierarquia');
        });

        // Restaurar valores de pt_BR do JSON para a coluna descricao
        $planos = $this->db()->table('planos_de_contas')
            ->select(['id', 'descricao_i18n'])
            ->get();

        foreach ($planos as $plano) {
            if (!empty($plano['descricao_i18n'])) {
                $i18n = json_decode($plano['descricao_i18n'], true);
                $descricao = $i18n['pt_BR'] ?? '';

                $this->db()->table('planos_de_contas')
                    ->whereRaw('id = ?', [$plano['id']])
                    ->update(['descricao' => $descricao]);
            }
        }
    }
};
