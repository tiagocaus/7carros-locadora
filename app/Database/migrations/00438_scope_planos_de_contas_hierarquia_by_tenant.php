<?php

use App\Database\Migration;

return new class extends Migration
{
    private const INDEX = 'uniq_planos_contas_chave_hierarquia';

    public function up(): void
    {
        // Criar a nova protecao antes de remover a restricao global.
        if (!$this->indexExists('planos_de_contas', self::INDEX)) {
            $this->table('planos_de_contas', function ($table) {
                $table->unique(['chave', 'hierarquia'], self::INDEX);
            });
        }

        $this->dropIndexIfExists('planos_de_contas', 'hierarquia');
    }

    public function down(): void
    {
        // O rollback nao pode apagar contas para restaurar a unicidade global.
        $duplicada = $this->db()
            ->table('planos_de_contas')
            ->withoutChave()
            ->select(['hierarquia'])
            ->groupBy('hierarquia')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicada !== null) {
            throw new RuntimeException(
                'Rollback bloqueado: existem hierarquias repetidas entre tenants. Nenhuma conta foi alterada.'
            );
        }

        if (!$this->indexExists('planos_de_contas', 'hierarquia')) {
            $this->table('planos_de_contas', function ($table) {
                $table->unique('hierarquia', 'hierarquia');
            });
        }

        $this->dropIndexIfExists('planos_de_contas', self::INDEX);
    }
};
