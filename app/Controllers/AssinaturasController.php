<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Assinatura;

/**
 * API autenticada de assinaturas digitais.
 */
class AssinaturasController
{
    private const TIPOS_VALIDOS = ['todos', 'contrato', 'locacao', 'promissoria'];

    /**
     * Lista contratos, locacoes e promissorias ainda sem assinatura.
     *
     * GET /api/assinaturas/pendentes
     */
    public function pendentes(Request $request): void
    {
        try {
            $tipo = strtolower((string) $request->query('tipo', 'todos'));
            if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Tipo invalido. Use todos, contrato, locacao ou promissoria.'
                ], 400);
                return;
            }

            $tiposPermitidos = $this->tiposPermitidos($tipo);
            if (empty($tiposPermitidos)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar documentos assinaveis'
                ], 403);
                return;
            }

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));
            $search = (string) $request->query('search', '');

            $model = new Assinatura();
            $resultado = $model->listarPendentesParaApp(
                Auth::chave(),
                $tiposPermitidos,
                $search,
                $page,
                $perPage,
                Auth::filiaisPermitidas()
            );

            $total = $resultado['total'];
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $resultado['data'],
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1,
                ],
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar documentos pendentes de assinatura: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function tiposPermitidos(string $tipo): array
    {
        $map = [
            'contrato' => 'contratos.visualizar',
            'locacao' => 'locacoes.visualizar',
            'promissoria' => 'promissorias.visualizar',
        ];

        $tipos = $tipo === 'todos' ? array_keys($map) : [$tipo];

        return array_values(array_filter($tipos, static function (string $tipo) use ($map): bool {
            return Auth::can($map[$tipo]);
        }));
    }
}
