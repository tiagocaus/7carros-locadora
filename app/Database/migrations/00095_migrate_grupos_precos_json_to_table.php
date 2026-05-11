<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados JSON para tabela grupos_precos_dias
 *
 * Extrai os dados das colunas tabela_diarias, tabela_km_controlado
 * e tabela_km_livre (JSON) e insere na tabela normalizada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grupos = $this->db()->withoutChave()->select(
            'grupos',
            ['id', 'chave', 'tabela_diarias', 'tabela_km_controlado', 'tabela_km_livre']
        );

        $inserted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($grupos as $grupo) {
            $tiposPlanos = [
                'diaria' => $grupo['tabela_diarias'],
                'km_controlado' => $grupo['tabela_km_controlado'],
                'km_livre' => $grupo['tabela_km_livre'],
            ];

            foreach ($tiposPlanos as $tipo => $json) {
                if (empty($json)) {
                    continue;
                }

                $faixas = json_decode($json, true);
                if (!is_array($faixas)) {
                    $errors[] = "Grupo {$grupo['id']}: JSON inválido em {$tipo}";
                    continue;
                }

                foreach ($faixas as $faixa) {
                    // Formato: [dia_inicio, dia_fim, valor]
                    $diaInicio = isset($faixa[0]) && is_numeric($faixa[0]) && $faixa[0] !== '' ? (int)$faixa[0] : null;
                    $diaFim = isset($faixa[1]) && is_numeric($faixa[1]) && $faixa[1] !== '' ? (int)$faixa[1] : null;
                    $valor = isset($faixa[2]) && is_numeric($faixa[2]) ? (float)$faixa[2] : null;

                    // Validar dados mínimos
                    if ($diaInicio === null || $diaInicio < 1 || $valor === null || $valor <= 0) {
                        $skipped++;
                        continue;
                    }

                    // Inserir na nova tabela
                    $this->db()->table('grupos_precos_dias')->withoutChave()->insert([
                        'chave' => $grupo['chave'],
                        'id_grupo' => $grupo['id'],
                        'tipo_plano' => $tipo,
                        'dia_inicio' => $diaInicio,
                        'dia_fim' => $diaFim,
                        'valor' => $valor
                    ]);
                    $inserted++;
                }
            }
        }

        // Log de migração
        if (!empty($errors)) {
            error_log("Migration grupos_precos: Errors - " . implode('; ', $errors));
        }
        error_log("Migration grupos_precos: {$inserted} inserted, {$skipped} skipped");
    }

    public function down(): void
    {
        // Limpa todos os dados da tabela (não restaura JSON)
        $this->db()->withoutChave()->table('grupos_precos_dias')->whereRaw('1=1')->delete();
    }
};
