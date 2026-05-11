<?php

use App\Database\Migration;

/**
 * Migration: Consolidar feriados nacionais com chave='0'
 *
 * Feriados com chave='0' sao globais e visiveis para todas as empresas.
 * Isso evita duplicacao de feriados nacionais para cada tenant.
 *
 * Regra:
 * - chave = '0' -> Feriados globais (nacionais brasileiros)
 * - chave = 'tenant_xyz' -> Feriados especificos do tenant (estaduais, municipais)
 */
return new class extends Migration
{
    /**
     * Feriados nacionais brasileiros fixos
     */
    private array $feriadosNacionais = [
        ['mes' => 1, 'dia' => 1, 'nome' => 'Confraternizacao Universal'],
        ['mes' => 4, 'dia' => 21, 'nome' => 'Tiradentes'],
        ['mes' => 5, 'dia' => 1, 'nome' => 'Dia do Trabalho'],
        ['mes' => 9, 'dia' => 7, 'nome' => 'Independencia do Brasil'],
        ['mes' => 10, 'dia' => 12, 'nome' => 'Nossa Senhora Aparecida'],
        ['mes' => 11, 'dia' => 2, 'nome' => 'Finados'],
        ['mes' => 11, 'dia' => 15, 'nome' => 'Proclamacao da Republica'],
        ['mes' => 12, 'dia' => 25, 'nome' => 'Natal'],
    ];

    public function up(): void
    {
        // 1. Inserir feriados nacionais com chave='0' (globais)
        foreach ($this->feriadosNacionais as $feriado) {
            // Verificar se ja existe
            $existe = $this->db()->table('feriados')->select(['id'])->whereRaw('chave = ? AND mes = ? AND dia = ?', ['0', $feriado['mes'], $feriado['dia']])->get();

            if (empty($existe)) {
                $this->db()->table('feriados')->insert([
                    'chave' => '0',
                    'nome' => $feriado['nome'],
                    'mes' => $feriado['mes'],
                    'dia' => $feriado['dia'],
                    'tipo' => 'nacional',
                    'estado' => null,
                    'cidade' => null,
                ]);
            }
        }

        // 2. Remover feriados nacionais duplicados (onde chave != '0')
        $this->execute(
            "DELETE FROM feriados WHERE tipo = 'nacional' AND chave != '0'"
        );
    }

    public function down(): void
    {
        // 1. Buscar todas as chaves (tenants) existentes
        $chaves = $this->db()->table('matrizes_filiais')->selectRaw('DISTINCT chave')->get();

        // 2. Duplicar feriados globais para cada tenant
        foreach ($chaves as $row) {
            $chave = $row['chave'];

            foreach ($this->feriadosNacionais as $feriado) {
                // Verificar se ja existe para este tenant
                $existe = $this->db()->table('feriados')->select(['id'])->whereRaw('chave = ? AND mes = ? AND dia = ?', [$chave, $feriado['mes'], $feriado['dia']])->get();

                if (empty($existe)) {
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

        // 3. Remover feriados globais
        $this->execute("DELETE FROM feriados WHERE chave = '0'");
    }
};
