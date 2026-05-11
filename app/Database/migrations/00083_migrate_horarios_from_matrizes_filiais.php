<?php

use App\Database\Migration;

/**
 * Migration: Migrar horários de matrizes_filiais para horarios_funcionamento
 *
 * Converte o modelo antigo (colunas na tabela matrizes_filiais) para o novo
 * modelo normalizado (tabela horarios_funcionamento).
 *
 * Modelo antigo:
 * - dias_uteis: "seg,ter,qua,qui,sex,sab,dom,fer"
 * - hora_ini, hora_fim: Seg-Sex
 * - hora_ini_sd, hora_fim_sd: Sab-Dom
 * - hora_ini_f, hora_fim_f: Feriados (não usado no novo modelo)
 */
return new class extends Migration
{
    /**
     * Mapeamento de dias da semana
     */
    private array $diasMap = [
        'dom' => 0,
        'seg' => 1,
        'ter' => 2,
        'qua' => 3,
        'qui' => 4,
        'sex' => 5,
        'sab' => 6,
    ];

    public function up(): void
    {
        // Migrar dados existentes
        $this->migrateHorarios();

        // Remover colunas antigas (depois de garantir que a migração funcionou)
        $this->table('matrizes_filiais', function ($table) {
            $table->dropColumn('dias_uteis');
            $table->dropColumn('hora_ini');
            $table->dropColumn('hora_fim');
            $table->dropColumn('hora_ini_sd');
            $table->dropColumn('hora_fim_sd');
            $table->dropColumn('hora_ini_f');
            $table->dropColumn('hora_fim_f');
        });
    }

    public function down(): void
    {
        // Recriar colunas antigas
        $this->table('matrizes_filiais', function ($table) {
            $table->string('dias_uteis', 100)->nullable();
            $table->time('hora_ini')->nullable();
            $table->time('hora_fim')->nullable();
            $table->time('hora_ini_sd')->nullable();
            $table->time('hora_fim_sd')->nullable();
            $table->time('hora_ini_f')->nullable();
            $table->time('hora_fim_f')->nullable();
        });

        // Restaurar dados da tabela horarios_funcionamento
        $this->restoreHorarios();

        // Limpar tabela de horários funcionamento
        $this->execute('DELETE FROM horarios_funcionamento');
    }

    /**
     * Migra horários do modelo antigo para o novo
     */
    private function migrateHorarios(): void
    {
        // Buscar todas as matrizes/filiais com horários configurados (sem filtro multi-tenant; carrega chave para propagar)
        $matrizes = $this->db()->table('matrizes_filiais')->withoutChave()->select(['id', 'chave', 'dias_uteis', 'hora_ini', 'hora_fim', 'hora_ini_sd', 'hora_fim_sd'])->get();

        foreach ($matrizes as $matriz) {
            $diasUteis = $matriz['dias_uteis'] ? explode(',', $matriz['dias_uteis']) : [];
            $diasUteis = array_map('trim', $diasUteis);

            foreach ($diasUteis as $diaAbrev) {
                // Ignorar 'fer' (feriados) pois serão tratados via exceções
                if ($diaAbrev === 'fer' || !isset($this->diasMap[$diaAbrev])) {
                    continue;
                }

                $diaSemana = $this->diasMap[$diaAbrev];

                // Determinar horários baseado no dia
                if (in_array($diaAbrev, ['sab', 'dom'])) {
                    // Fim de semana
                    $abertura = $matriz['hora_ini_sd'];
                    $fechamento = $matriz['hora_fim_sd'];
                } else {
                    // Dias úteis (seg-sex)
                    $abertura = $matriz['hora_ini'];
                    $fechamento = $matriz['hora_fim'];
                }

                // Normaliza typos comuns (; -> :) e valida formato H:i ou H:i:s
                $abertura = $this->normalizeTime($abertura);
                $fechamento = $this->normalizeTime($fechamento);

                // Só inserir se tiver horários definidos e válidos
                if ($abertura && $fechamento) {
                    $this->db()->table('horarios_funcionamento')->insert([
                        'chave' => $matriz['chave'],
                        'matriz_filial_id' => $matriz['id'],
                        'dia_semana' => $diaSemana,
                        'abertura' => $abertura,
                        'fechamento' => $fechamento,
                        'periodo' => 1,
                    ]);
                }
            }
        }
    }

    /**
     * Restaura horários do novo modelo para o antigo (rollback)
     */
    private function restoreHorarios(): void
    {
        // Buscar todas as matrizes
        $matrizes = $this->db()->table('matrizes_filiais')->select(['id'])->get();

        foreach ($matrizes as $matriz) {
            $matrizId = $matriz['id'];

            // Buscar horários da nova tabela
            $horarios = $this->db()->table('horarios_funcionamento')->select(['dia_semana', 'abertura', 'fechamento'])->whereRaw('matriz_filial_id = ? AND periodo = 1', [$matrizId])->get();

            if (empty($horarios)) {
                continue;
            }

            $diasUteis = [];
            $horaIni = null;
            $horaFim = null;
            $horaIniSd = null;
            $horaFimSd = null;

            // Mapeamento reverso
            $diasReverseMap = array_flip($this->diasMap);

            foreach ($horarios as $h) {
                $dia = $h['dia_semana'];
                $diaAbrev = $diasReverseMap[$dia] ?? null;

                if (!$diaAbrev) {
                    continue;
                }

                $diasUteis[] = $diaAbrev;

                // Determinar qual grupo de horários usar
                if (in_array($diaAbrev, ['sab', 'dom'])) {
                    $horaIniSd = $h['abertura'];
                    $horaFimSd = $h['fechamento'];
                } else {
                    $horaIni = $h['abertura'];
                    $horaFim = $h['fechamento'];
                }
            }

            // Atualizar matriz com dados restaurados
            $this->db()->table('matrizes_filiais')->whereRaw('id = ?', [$matrizId])->update([
                'dias_uteis' => implode(',', $diasUteis),
                'hora_ini' => $horaIni,
                'hora_fim' => $horaFim,
                'hora_ini_sd' => $horaIniSd,
                'hora_fim_sd' => $horaFimSd,
            ]);
        }
    }

    /**
     * Normaliza valor de hora: corrige separador `;` -> `:` e valida formato H:i[:s].
     * Retorna null se invalido.
     */
    private function normalizeTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace(';', ':', trim($value));
        if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $clean)) {
            return null;
        }
        return $clean;
    }
};
