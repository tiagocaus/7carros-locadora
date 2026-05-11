<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Feriado;

/**
 * Controller de Feriados
 *
 * Gerencia operacoes relacionadas a feriados
 */
class FeriadoController
{
    /**
     * Busca proximos feriados por pais/estado/cidade
     *
     * GET /api/feriados/buscar
     */
    public function buscar(Request $request): void
    {
        try {
            $pais = $request->query('pais') ?: null;
            $estado = $request->query('estado') ?: null;
            $cidade = $request->query('cidade') ?: null;

            $feriadoModel = new Feriado();
            $feriados = $feriadoModel->listarProximos(5, $estado, $cidade, $pais);

            Response::json([
                'success' => true,
                'data' => $feriados
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar feriados: ' . $e->getMessage()
            ], 500);
        }
    }
}
