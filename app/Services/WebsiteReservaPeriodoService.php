<?php

namespace App\Services;

use App\Helpers\DateHelper;

/**
 * Calcula a duracao faturavel de reservas feitas pelo website publico.
 *
 * Mantem a regra isolada do fluxo interno de locacoes: blocos de 24 horas,
 * descontando a tolerancia configurada para o grupo/filial.
 */
class WebsiteReservaPeriodoService
{
    public function calcularDias(string $dataSaida, string $dataPrevista, int $minutosTolerancia = 0): int
    {
        $saida = DateHelper::parseOperationalDateTime($dataSaida);
        $prevista = DateHelper::parseOperationalDateTime($dataPrevista);
        $diferencaSegundos = $prevista->getTimestamp() - $saida->getTimestamp();

        if ($diferencaSegundos <= 0) {
            throw new \InvalidArgumentException('A devolucao deve ser posterior a retirada.');
        }

        $diferencaMinutos = (int) ceil($diferencaSegundos / 60);
        $minutosCobrados = max(0, $diferencaMinutos - max(0, $minutosTolerancia));

        return max(1, (int) ceil($minutosCobrados / 1440));
    }
}
