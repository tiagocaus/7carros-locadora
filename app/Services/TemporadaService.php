<?php

namespace App\Services;

use App\Models\Temporada;
use App\Models\TemporadaGrupo;

/**
 * Service para calculo de ajustes de preco por temporada
 *
 * Fornece metodos para calcular e aplicar ajustes percentuais
 * de preco baseados em temporadas (alta/baixa).
 */
class TemporadaService
{
    private Temporada $temporadaModel;
    private TemporadaGrupo $temporadaGrupoModel;
    private string $chave;
    private array $ajustesAtivosPorGrupo = [];

    public function __construct(string $chave)
    {
        $this->chave = $chave;
        $this->temporadaModel = new Temporada();
        $this->temporadaGrupoModel = new TemporadaGrupo();
    }

    /**
     * Retorna o ajuste percentual para um grupo em uma data especifica
     *
     * @param int $grupoId ID do grupo de veiculos
     * @param \DateTime|string $data Data a verificar
     * @return float|null Percentual (30.0 = +30%) ou null se nao houver temporada
     */
    public function getAjusteParaData(int $grupoId, $data): ?float
    {
        if (is_string($data)) {
            $data = new \DateTime($data);
        }

        $temporada = $this->getTemporadaComAjusteParaData($grupoId, $data);
        return $temporada !== null ? (float) $temporada['ajuste_percentual'] : null;
    }

    /**
     * Aplica ajuste de temporada ao valor
     *
     * @param float $valorBase Valor base antes do ajuste
     * @param int $grupoId ID do grupo de veiculos
     * @param \DateTime|string $data Data a verificar
     * @return float Valor com ajuste aplicado
     */
    public function aplicarAjuste(float $valorBase, int $grupoId, $data): float
    {
        $percentual = $this->getAjusteParaData($grupoId, $data);

        if ($percentual === null) {
            return $valorBase;
        }

        return $valorBase * (1 + ($percentual / 100));
    }

    /**
     * Calcula o valor total de uma locacao considerando temporadas
     *
     * Para periodos que cruzam temporadas diferentes, calcula o ajuste
     * proporcional para cada dia.
     *
     * @param float $valorDiaria Valor base da diaria
     * @param int $grupoId ID do grupo de veiculos
     * @param \DateTime|string $dataInicio Data de inicio da locacao
     * @param \DateTime|string $dataFim Data de fim da locacao
     * @return array [
     *     'valor_total' => float,
     *     'dias' => int,
     *     'detalhes' => array de valores por dia,
     *     'tem_ajuste' => bool
     * ]
     */
    public function calcularPeriodo(float $valorDiaria, int $grupoId, $dataInicio, $dataFim): array
    {
        if (is_string($dataInicio)) {
            $dataInicio = new \DateTime($dataInicio);
        }
        if (is_string($dataFim)) {
            $dataFim = new \DateTime($dataFim);
        }

        $detalhes = [];
        $valorTotal = 0;
        $temAjuste = false;

        $dataAtual = clone $dataInicio;
        while ($dataAtual <= $dataFim) {
            $temporada = $this->getTemporadaComAjusteParaData($grupoId, $dataAtual);
            $percentual = $temporada !== null ? (float) $temporada['ajuste_percentual'] : 0.0;
            $ajuste = abs($percentual) > 0.00001 ? $percentual : null;
            $valorDia = $ajuste === null
                ? $valorDiaria
                : $valorDiaria * (1 + ($ajuste / 100));

            $detalhe = [
                'data' => $dataAtual->format('Y-m-d'),
                'valor_base' => $valorDiaria,
                'valor_final' => $valorDia,
                'ajuste_percentual' => $ajuste,
            ];

            if ($ajuste !== null) {
                $temAjuste = true;
                $detalhe['temporada'] = [
                    'id' => (int) $temporada['temporada_id'],
                    'nome' => (string) $temporada['temporada_nome'],
                ];
            }

            $detalhes[] = $detalhe;
            $valorTotal += $valorDia;

            $dataAtual->modify('+1 day');
        }

        return [
            'valor_total' => round($valorTotal, 2),
            'dias' => count($detalhes),
            'detalhes' => $detalhes,
            'tem_ajuste' => $temAjuste,
        ];
    }

    /**
     * Localiza a primeira temporada ativa com ajuste para o grupo na data.
     * As configuracoes sao carregadas uma vez por grupo para evitar consultas
     * repetidas durante cotacoes de periodos longos.
     */
    private function getTemporadaComAjusteParaData(int $grupoId, \DateTimeInterface $data): ?array
    {
        if (!array_key_exists($grupoId, $this->ajustesAtivosPorGrupo)) {
            $this->ajustesAtivosPorGrupo[$grupoId] =
                $this->temporadaGrupoModel->listarAtivasComAjustePorGrupo($grupoId);
        }

        $mesDia = ((int) $data->format('n') * 100) + (int) $data->format('j');
        foreach ($this->ajustesAtivosPorGrupo[$grupoId] as $temporada) {
            $inicio = ((int) $temporada['mes_inicio'] * 100) + (int) $temporada['dia_inicio'];
            $fim = ((int) $temporada['mes_fim'] * 100) + (int) $temporada['dia_fim'];
            $estaNoPeriodo = $inicio <= $fim
                ? ($mesDia >= $inicio && $mesDia <= $fim)
                : ($mesDia >= $inicio || $mesDia <= $fim);

            if ($estaNoPeriodo) {
                return $temporada;
            }
        }

        return null;
    }

    /**
     * Retorna resumo dos ajustes aplicados em um periodo
     *
     * @param int $grupoId ID do grupo de veiculos
     * @param \DateTime|string $dataInicio Data de inicio
     * @param \DateTime|string $dataFim Data de fim
     * @return array [
     *     'temporadas' => array de temporadas aplicadas,
     *     'dias_com_ajuste' => int,
     *     'dias_sem_ajuste' => int
     * ]
     */
    public function resumoAjustesPeriodo(int $grupoId, $dataInicio, $dataFim): array
    {
        if (is_string($dataInicio)) {
            $dataInicio = new \DateTime($dataInicio);
        }
        if (is_string($dataFim)) {
            $dataFim = new \DateTime($dataFim);
        }

        $temporadasAplicadas = [];
        $diasComAjuste = 0;
        $diasSemAjuste = 0;

        $dataAtual = clone $dataInicio;
        while ($dataAtual <= $dataFim) {
            $temporada = $this->temporadaModel->getTemporadaParaData($this->chave, $dataAtual);

            if ($temporada) {
                $ajuste = $this->temporadaGrupoModel->buscarPorTemporadaGrupo(
                    (int) $temporada['id'],
                    $grupoId
                );

                if ($ajuste) {
                    $diasComAjuste++;
                    $key = $temporada['id'];
                    if (!isset($temporadasAplicadas[$key])) {
                        $temporadasAplicadas[$key] = [
                            'id' => $temporada['id'],
                            'nome' => $temporada['nome'],
                            'ajuste_percentual' => (float) $ajuste['ajuste_percentual'],
                            'periodo' => $this->temporadaModel->formatarPeriodo($temporada),
                            'dias_aplicados' => 0,
                        ];
                    }
                    $temporadasAplicadas[$key]['dias_aplicados']++;
                } else {
                    $diasSemAjuste++;
                }
            } else {
                $diasSemAjuste++;
            }

            $dataAtual->modify('+1 day');
        }

        return [
            'temporadas' => array_values($temporadasAplicadas),
            'dias_com_ajuste' => $diasComAjuste,
            'dias_sem_ajuste' => $diasSemAjuste,
        ];
    }

    /**
     * Verifica se uma data esta em temporada alta
     *
     * @param \DateTime|string $data Data a verificar
     * @return bool True se estiver em temporada com ajuste positivo
     */
    public function isTemporadaAlta($data): bool
    {
        $temporada = $this->temporadaModel->getTemporadaParaData($this->chave, $data);
        return $temporada !== null;
    }

    /**
     * Lista todas as temporadas ativas com seus ajustes para um grupo
     *
     * @param int $grupoId ID do grupo de veiculos
     * @return array Lista de temporadas com ajustes
     */
    public function listarTemporadasComAjustes(int $grupoId): array
    {
        $temporadas = $this->temporadaModel->listarAtivas($this->chave);
        $resultado = [];

        foreach ($temporadas as $temporada) {
            $ajuste = $this->temporadaGrupoModel->buscarPorTemporadaGrupo(
                (int) $temporada['id'],
                $grupoId
            );

            $resultado[] = [
                'id' => $temporada['id'],
                'nome' => $temporada['nome'],
                'periodo' => $this->temporadaModel->formatarPeriodo($temporada),
                'pais' => $temporada['pais'],
                'ajuste_percentual' => $ajuste ? (float) $ajuste['ajuste_percentual'] : null,
            ];
        }

        return $resultado;
    }

    /**
     * Metodo estatico para aplicar ajuste rapidamente
     *
     * @param string $chave Chave do tenant
     * @param float $valorBase Valor base
     * @param int $grupoId ID do grupo
     * @param \DateTime|string $data Data
     * @return float Valor com ajuste
     */
    public static function aplicar(string $chave, float $valorBase, int $grupoId, $data): float
    {
        $service = new self($chave);
        return $service->aplicarAjuste($valorBase, $grupoId, $data);
    }

    /**
     * Metodo estatico para obter ajuste rapidamente
     *
     * @param string $chave Chave do tenant
     * @param int $grupoId ID do grupo
     * @param \DateTime|string $data Data
     * @return float|null Percentual ou null
     */
    public static function getAjuste(string $chave, int $grupoId, $data): ?float
    {
        $service = new self($chave);
        return $service->getAjusteParaData($grupoId, $data);
    }
}
