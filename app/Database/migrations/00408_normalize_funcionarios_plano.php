<?php

use App\Database\Migration;
use App\Models\Funcionario;

/**
 * Normaliza funcionarios sem plano usando o unico plano valido do tenant.
 *
 * Tenants sem plano valido ou com mais de um plano valido sao ignorados para
 * impedir que a migration escolha uma assinatura de forma arbitraria.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('funcionarios') || !$this->columnExists('funcionarios', 'plano')) {
            return;
        }

        $funcionarios = $this->db()
            ->table('funcionarios')
            ->select(['id', 'chave', 'plano'])
            ->get();

        foreach (Funcionario::agruparIdsParaNormalizacaoPlano($funcionarios) as $plano => $ids) {
            $this->db()
                ->table('funcionarios')
                ->whereIn('id', $ids)
                ->whereRaw("TRIM(plano) = ''")
                ->update(['plano' => $plano]);
        }
    }

    public function down(): void
    {
        // Normalizacao de dados nao reversivel: restaurar vazio invalidaria o plano do tenant.
    }
};
