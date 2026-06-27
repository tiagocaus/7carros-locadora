<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Changelog;

/**
 * Controller de Changelog
 *
 * Sistema GLOBAL: todos os tenants podem visualizar.
 * Apenas admin 7Carros (APP_ADMINISTRADORES) pode adicionar/editar/excluir.
 */
class ChangelogController
{
    /**
     * Renderiza a página de listagem
     *
     * GET /pages/changelog
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.changelog.index', [
            'isAdmin' => $this->isAdmin7Carros(),
        ]);
        Response::html($html);
    }

    /**
     * Lista changelogs agrupados por versão (API)
     *
     * GET /api/changelog
     */
    public function index(Request $request): void
    {
        try {
            $model = new Changelog();
            $changelogs = $model->listarAgrupado();

            // Adicionar labels e cores aos tipos
            $versoes = [];
            foreach ($changelogs as $versao => $itens) {
                $itensFormatados = [];
                foreach ($itens as $item) {
                    $item['tipo_label'] = Changelog::TIPOS[$item['tipo']] ?? $item['tipo'];
                    $item['tipo_cor'] = Changelog::TIPO_CORES[$item['tipo']] ?? 'bg-gray-100 text-gray-800';
                    $itensFormatados[] = $item;
                }
                $versoes[] = [
                    'versao' => $versao,
                    'itens' => $itensFormatados,
                ];
            }

            Response::json([
                'success' => true,
                'data' => $versoes,
                'isAdmin' => $this->isAdmin7Carros(),
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar changelog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca um changelog por ID (API)
     *
     * GET /api/changelog/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Changelog();
            $changelog = $model->buscarPorId($id);

            if (!$changelog) {
                Response::json([
                    'success' => false,
                    'message' => 'Changelog não encontrado.',
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $changelog,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar changelog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um novo changelog (API)
     *
     * POST /api/changelog
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissão
            if (!$this->isAdmin7Carros()) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado. Apenas administradores podem criar changelog.',
                ], 403);
                return;
            }

            $dados = [
                'versao' => trim($request->post('versao', '')),
                'tipo' => trim($request->post('tipo', '')),
                'data' => trim($request->post('data', '')),
                'mensagem' => trim($request->post('mensagem', '')),
            ];

            $model = new Changelog();

            // Validar dados
            $erros = $model->validar($dados);
            if (!empty($erros)) {
                Response::json([
                    'success' => false,
                    'message' => 'Dados inválidos.',
                    'errors' => $erros,
                ], 422);
                return;
            }

            $id = $model->criar($dados);

            if ($id === false) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao criar changelog.',
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Changelog criado com sucesso.',
                'data' => ['id' => $id],
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar changelog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza um changelog existente (API)
     *
     * POST /api/changelog/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!$this->isAdmin7Carros()) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado. Apenas administradores podem editar changelog.',
                ], 403);
                return;
            }

            $model = new Changelog();

            // Verificar se existe
            $changelog = $model->buscarPorId($id);
            if (!$changelog) {
                Response::json([
                    'success' => false,
                    'message' => 'Changelog não encontrado.',
                ], 404);
                return;
            }

            $dados = [
                'versao' => trim($request->post('versao', '')),
                'tipo' => trim($request->post('tipo', '')),
                'data' => trim($request->post('data', '')),
                'mensagem' => trim($request->post('mensagem', '')),
            ];

            // Validar dados
            $erros = $model->validar($dados);
            if (!empty($erros)) {
                Response::json([
                    'success' => false,
                    'message' => 'Dados inválidos.',
                    'errors' => $erros,
                ], 422);
                return;
            }

            $sucesso = $model->atualizar($id, $dados);

            if (!$sucesso) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao atualizar changelog.',
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Changelog atualizado com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar changelog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exclui um changelog (API)
     *
     * POST /api/changelog/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!$this->isAdmin7Carros()) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado. Apenas administradores podem excluir changelog.',
                ], 403);
                return;
            }

            $model = new Changelog();

            // Verificar se existe
            $changelog = $model->buscarPorId($id);
            if (!$changelog) {
                Response::json([
                    'success' => false,
                    'message' => 'Changelog não encontrado.',
                ], 404);
                return;
            }

            $sucesso = $model->excluir($id);

            if (!$sucesso) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao excluir changelog.',
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Changelog excluído com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir changelog: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista changelogs públicos para tela de login (API pública)
     *
     * GET /api/public/changelog?limite=50&offset=0
     */
    public function publicIndex(Request $request): void
    {
        try {
            $limite = (int) $request->query('limite', 50);
            $offset = (int) $request->query('offset', 0);

            // Limitar para evitar abusos
            $limite = min($limite, 100);

            $model = new Changelog();
            $versoes = $model->listarUltimasVersoes($limite, $offset);

            $resultado = [];
            $primeiraVersao = ($offset === 0);

            foreach ($versoes as $versaoData) {
                $itensFormatados = [];

                foreach ($versaoData['itens'] as $item) {
                    $itensFormatados[] = [
                        'tipo' => $item['tipo'],
                        'tipo_label' => Changelog::TIPOS[$item['tipo']] ?? $item['tipo'],
                        'mensagem' => $item['mensagem'],
                        'data' => $item['data'],
                    ];
                }

                $resultado[] = [
                    'versao' => $versaoData['versao'],
                    'data' => $versaoData['data'],
                    'destaque' => $primeiraVersao,
                    'itens' => $itensFormatados,
                ];

                $primeiraVersao = false;
            }

            Response::json([
                'success' => true,
                'data' => $resultado,
                'hasMore' => count($versoes) === $limite,
                'offset' => $offset,
                'limite' => $limite,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar changelog.',
            ], 500);
        }
    }

    /**
     * Verifica se o usuário logado é admin 7Carros
     *
     * @return bool
     */
    private function isAdmin7Carros(): bool
    {
        $usuarioLogado = $_SESSION['user_usuario'] ?? '';

        if (empty($usuarioLogado)) {
            return false;
        }

        $administradores = env('APP_ADMINISTRADORES', '');

        if (empty($administradores)) {
            return false;
        }

        $listaAdmins = array_map('trim', explode(',', $administradores));

        return in_array($usuarioLogado, $listaAdmins);
    }
}
