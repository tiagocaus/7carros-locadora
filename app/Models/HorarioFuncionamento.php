<?php

namespace App\Models;

/**
 * Model HorarioFuncionamento
 *
 * Gerencia horários de funcionamento por dia da semana de cada matriz/filial.
 * Suporta múltiplos períodos por dia (ex: manhã e tarde com intervalo).
 */
class HorarioFuncionamento extends Model
{
    /**
     * Nomes dos dias da semana em português
     */
    public const DIAS_SEMANA = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    /**
     * Abreviações dos dias da semana
     */
    public const DIAS_SEMANA_ABREV = [
        0 => 'Dom',
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sáb',
    ];

    /**
     * Lista horários de funcionamento de uma matriz/filial
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @return array Lista de horários agrupados por dia
     */
    public function listarPorMatriz(int $matrizFilialId): array
    {
        $horarios = $this->qb
            ->table('horarios_funcionamento')
            ->select(['id', 'dia_semana', 'abertura', 'fechamento', 'periodo'])
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->orderBy('dia_semana', 'ASC')
            ->orderBy('periodo', 'ASC')
            ->get();

        // Agrupar por dia da semana
        $agrupado = [];
        foreach ($horarios as $h) {
            $dia = (int) $h['dia_semana'];
            if (!isset($agrupado[$dia])) {
                $agrupado[$dia] = [
                    'dia_semana' => $dia,
                    'nome' => self::DIAS_SEMANA[$dia],
                    'abrev' => self::DIAS_SEMANA_ABREV[$dia],
                    'periodos' => [],
                ];
            }
            $agrupado[$dia]['periodos'][] = [
                'id' => $h['id'],
                'abertura' => substr($h['abertura'], 0, 5),  // HH:MM
                'fechamento' => substr($h['fechamento'], 0, 5),
                'periodo' => (int) $h['periodo'],
            ];
        }

        return $agrupado;
    }

    /**
     * Lista horários em formato simples (array plano)
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @return array Lista simples de horários
     */
    public function listarSimples(int $matrizFilialId): array
    {
        return $this->qb
            ->table('horarios_funcionamento')
            ->select(['id', 'dia_semana', 'abertura', 'fechamento', 'periodo'])
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->orderBy('dia_semana', 'ASC')
            ->orderBy('periodo', 'ASC')
            ->get();
    }

    /**
     * Salva horários de funcionamento (substitui todos existentes)
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param array $horarios Array de horários no formato:
     *   [
     *     ['dia_semana' => 1, 'abertura' => '08:00', 'fechamento' => '12:00', 'periodo' => 1],
     *     ['dia_semana' => 1, 'abertura' => '14:00', 'fechamento' => '18:00', 'periodo' => 2],
     *   ]
     * @param bool $gerenciarTransacao False quando participa de uma transacao externa
     * @return bool Sucesso
     */
    public function salvar(
        int $matrizFilialId,
        array $horarios,
        bool $gerenciarTransacao = true
    ): bool
    {
        if ($gerenciarTransacao) {
            $this->qb->beginTransaction();
        }

        try {
            // Remover horários existentes
            $this->qb
                ->table('horarios_funcionamento')
                    ->where('matriz_filial_id', '=', $matrizFilialId)
                ->delete();

            // Inserir novos horários
            foreach ($horarios as $h) {
                // Validar dados mínimos
                if (!isset($h['dia_semana'], $h['abertura'], $h['fechamento'])) {
                    continue;
                }

                $this->qb
                    ->table('horarios_funcionamento')
                    ->insert([
                        'matriz_filial_id' => $matrizFilialId,
                        'dia_semana' => (int) $h['dia_semana'],
                        'abertura' => $h['abertura'],
                        'fechamento' => $h['fechamento'],
                        'periodo' => (int) ($h['periodo'] ?? 1),
                    ]);
            }

            if ($gerenciarTransacao) {
                $this->qb->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($gerenciarTransacao) {
                $this->qb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Adiciona um período a um dia existente
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param int $diaSemana Dia da semana (0-6)
     * @param string $abertura Hora de abertura (HH:MM)
     * @param string $fechamento Hora de fechamento (HH:MM)
     * @return int ID do registro criado
     */
    public function adicionarPeriodo(int $matrizFilialId, int $diaSemana, string $abertura, string $fechamento): int
    {
        // Encontrar próximo número de período
        $ultimoPeriodo = $this->qb
            ->table('horarios_funcionamento')
            ->selectRaw('MAX(periodo) as max_periodo')
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->where('dia_semana', '=', $diaSemana)
            ->first();

        $proximoPeriodo = ($ultimoPeriodo['max_periodo'] ?? 0) + 1;

        return $this->qb
            ->table('horarios_funcionamento')
            ->insert([
                'matriz_filial_id' => $matrizFilialId,
                'dia_semana' => $diaSemana,
                'abertura' => $abertura,
                'fechamento' => $fechamento,
                'periodo' => $proximoPeriodo,
            ]);
    }

    /**
     * Remove um horário específico
     *
     * @param int $id ID do horário
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('horarios_funcionamento')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todos os horários de uma matriz/filial
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @return int Linhas afetadas
     */
    public function excluirPorMatriz(int $matrizFilialId): int
    {
        return $this->qb
            ->table('horarios_funcionamento')
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->delete();
    }

    /**
     * Verifica se a matriz/filial está aberta em determinada data/hora
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param \DateTime|null $dataHora Data/hora a verificar (null = agora)
     * @return bool True se aberto
     */
    public function isAberto(int $matrizFilialId, ?\DateTime $dataHora = null): bool
    {
        $dataHora = $dataHora ?? new \DateTime();
        $diaSemana = (int) $dataHora->format('w');
        $horaAtual = $dataHora->format('H:i:s');

        $horarios = $this->qb
            ->table('horarios_funcionamento')
            ->select(['abertura', 'fechamento'])
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->where('dia_semana', '=', $diaSemana)
            ->get();

        foreach ($horarios as $h) {
            if ($horaAtual >= $h['abertura'] && $horaAtual <= $h['fechamento']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o horário de funcionamento para uma data específica
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param \DateTime|null $data Data a verificar (null = hoje)
     * @return array|null Horários do dia ou null se fechado
     */
    public function getHorarioDia(int $matrizFilialId, ?\DateTime $data = null): ?array
    {
        $data = $data ?? new \DateTime();
        $diaSemana = (int) $data->format('w');

        $horarios = $this->qb
            ->table('horarios_funcionamento')
            ->select(['abertura', 'fechamento', 'periodo'])
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->where('dia_semana', '=', $diaSemana)
            ->orderBy('periodo', 'ASC')
            ->get();

        if (empty($horarios)) {
            return null;
        }

        return [
            'dia_semana' => $diaSemana,
            'nome' => self::DIAS_SEMANA[$diaSemana],
            'periodos' => array_map(function ($h) {
                return [
                    'abertura' => substr($h['abertura'], 0, 5),
                    'fechamento' => substr($h['fechamento'], 0, 5),
                    'periodo' => (int) $h['periodo'],
                ];
            }, $horarios),
        ];
    }

    /**
     * Retorna horários formatados para exibição no frontend
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @return array Horários formatados para todos os dias
     */
    public function getHorariosFormatados(int $matrizFilialId): array
    {
        $horarios = $this->listarPorMatriz($matrizFilialId);

        $resultado = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            if (isset($horarios[$dia])) {
                $resultado[$dia] = $horarios[$dia];
            } else {
                $resultado[$dia] = [
                    'dia_semana' => $dia,
                    'nome' => self::DIAS_SEMANA[$dia],
                    'abrev' => self::DIAS_SEMANA_ABREV[$dia],
                    'periodos' => [],
                    'fechado' => true,
                ];
            }
        }

        return $resultado;
    }
}
