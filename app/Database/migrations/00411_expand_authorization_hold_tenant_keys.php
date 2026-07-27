<?php

use App\Database\Migration;

/**
 * Corrige chaves de tenant truncadas nas tabelas de pre-autorizacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->expandirERestaurar(
            'locacoes_bloqueios',
            'locacoes',
            'id_locacao'
        );
        $this->expandirERestaurar(
            'contratos_bloqueios',
            'contratos',
            'id_contrato'
        );
    }

    public function down(): void
    {
        // Irreversivel com seguranca: reduzir para VARCHAR(20) truncaria chaves.
    }

    private function expandirERestaurar(
        string $tabelaBloqueios,
        string $tabelaPai,
        string $colunaPai
    ): void {
        if (
            !$this->tableExists($tabelaBloqueios)
            || !$this->columnExists($tabelaBloqueios, 'chave')
        ) {
            return;
        }

        $this->modifyColumn($tabelaBloqueios, 'chave', 'VARCHAR(45)', [
            'null' => false,
        ]);

        $bloqueios = $this->db()
            ->table($tabelaBloqueios, 'b')
            ->select([
                'b.id',
                'p.chave AS chave_pai',
            ])
            ->innerJoin($tabelaPai, 'p', "b.{$colunaPai}", '=', 'p.id')
            ->get();

        foreach ($bloqueios as $bloqueio) {
            $chavePai = trim((string) ($bloqueio['chave_pai'] ?? ''));
            if ($chavePai === '') {
                continue;
            }

            $this->db()
                ->table($tabelaBloqueios)
                ->where('id', '=', (int) $bloqueio['id'])
                ->update(['chave' => $chavePai]);
        }
    }
};
