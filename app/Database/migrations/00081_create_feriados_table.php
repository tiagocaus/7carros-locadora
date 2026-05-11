<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela feriados
 *
 * Armazena feriados nacionais, estaduais e municipais por tenant.
 * Inclui seed com feriados nacionais brasileiros.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Se ja existe tabela legacy (sem coluna chave), preserva em backup antes
        if ($this->tableExists('feriados') && !$this->columnExists('feriados', 'chave')) {
            if (!$this->tableExists('feriados_legacy_backup')) {
                $this->execute("RENAME TABLE feriados TO feriados_legacy_backup");
            } else {
                // Backup ja existe (re-execucao); descarta a copia legacy atual
                $this->execute("DROP TABLE feriados");
            }
        }

        if (!$this->tableExists('feriados')) {
            $this->create('feriados', function ($table) {
                $table->id();
                $table->string('chave', 50);  // Multi-tenancy
                $table->string('nome', 100);
                $table->addColumn('`mes` TINYINT UNSIGNED NOT NULL');  // 1-12
                $table->addColumn('`dia` TINYINT UNSIGNED NOT NULL');  // 1-31
                $table->enum('tipo', ['nacional', 'estadual', 'municipal', 'ponto_facultativo'])->default('nacional');
                $table->string('estado', 2)->nullable();      // UF (se estadual/municipal)
                $table->string('cidade', 100)->nullable();  // Cidade (se municipal)
                $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
                $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

                $table->index('chave', 'idx_fer_chave');
                $table->index(['mes', 'dia'], 'idx_fer_data');
                $table->index(['chave', 'mes', 'dia'], 'idx_fer_chave_data');
            });
        }

        // Inserir feriados nacionais para todos os tenants existentes
        $this->seedFeriadosNacionais();

        // Migrar feriados unicos do backup legacy (preserva os que nao casam com o seed)
        if ($this->tableExists('feriados_legacy_backup')) {
            $this->migrateLegacyFeriados();
        }
    }

    public function down(): void
    {
        $this->drop('feriados');
    }

    /**
     * Insere feriados nacionais para cada tenant existente
     */
    private function seedFeriadosNacionais(): void
    {
        // Feriados nacionais brasileiros fixos
        $feriadosNacionais = [
            ['mes' => 1, 'dia' => 1, 'nome' => 'Confraternização Universal'],
            ['mes' => 4, 'dia' => 21, 'nome' => 'Tiradentes'],
            ['mes' => 5, 'dia' => 1, 'nome' => 'Dia do Trabalho'],
            ['mes' => 9, 'dia' => 7, 'nome' => 'Independência do Brasil'],
            ['mes' => 10, 'dia' => 12, 'nome' => 'Nossa Senhora Aparecida'],
            ['mes' => 11, 'dia' => 2, 'nome' => 'Finados'],
            ['mes' => 11, 'dia' => 15, 'nome' => 'Proclamação da República'],
            ['mes' => 12, 'dia' => 25, 'nome' => 'Natal'],
        ];

        // Buscar todas as chaves (tenants) existentes
        $chaves = $this->db()->table('matrizes_filiais')->selectRaw('DISTINCT chave')->get();

        foreach ($chaves as $row) {
            $chave = $row['chave'];

            foreach ($feriadosNacionais as $feriado) {
                $this->db()->table('feriados')->insert([
                    'chave' => $chave,
                    'nome' => $feriado['nome'],
                    'mes' => $feriado['mes'],
                    'dia' => $feriado['dia'],
                    'tipo' => 'nacional',
                    'estado' => null,
                    'cidade' => null,
                ]);
            }
        }
    }

    /**
     * Migra feriados do schema legacy (data='d/m', comentario) preservando os
     * que nao duplicam o seed nacional. Insere como global (chave='0') tipo 'ponto_facultativo'.
     */
    private function migrateLegacyFeriados(): void
    {
        $legacy = $this->db()->table('feriados_legacy_backup')
            ->withoutChave()
            ->select(['data', 'comentario'])
            ->get();

        foreach ($legacy as $row) {
            $parts = explode('/', $row['data'] ?? '');
            if (count($parts) !== 2) {
                continue;
            }
            $dia = (int) $parts[0];
            $mes = (int) $parts[1];
            if ($mes < 1 || $mes > 12 || $dia < 1 || $dia > 31) {
                continue;
            }

            // Pula se ja existe um feriado nacional com mesma data (o seed cobre)
            $jaTemNacional = $this->db()->table('feriados')
                ->withoutChave()
                ->whereRaw('mes = ? AND dia = ? AND tipo = ?', [$mes, $dia, 'nacional'])
                ->exists();
            if ($jaTemNacional) {
                continue;
            }

            // Pula se ja existe global com mesma data (re-execucao idempotente)
            $jaGlobal = $this->db()->table('feriados')
                ->withoutChave()
                ->whereRaw("chave = '0' AND mes = ? AND dia = ?", [$mes, $dia])
                ->exists();
            if ($jaGlobal) {
                continue;
            }

            $this->db()->table('feriados')->insert([
                'chave' => '0',
                'nome' => $row['comentario'] ?? 'Feriado legado',
                'mes' => $mes,
                'dia' => $dia,
                'tipo' => 'ponto_facultativo',
                'estado' => null,
                'cidade' => null,
            ]);
        }
    }
};
