<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Estoque;
use App\Models\MatrizFilial;
use App\Models\Fornecedor;
use App\Helpers\FilialHelper;
use App\Services\AuditLogService;

/**
 * Controller de Estoque
 *
 * Gerencia operacoes CRUD de produtos do estoque.
 */
class EstoqueController
{
    /**
     * Renderiza a pagina de estoque
     *
     * GET /pages/estoque
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.estoque.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar produto
     *
     * GET /pages/estoque/adicionar
     * GET /pages/estoque/{id}/editar
     */
    public function viewAdicionar(Request $request, ?int $id = null): void
    {
        $data = ['id' => $id];
        $html = Template::render('pages.estoque.adicionar', $data);
        Response::html($html);
    }

    /**
     * Lista todos os produtos do tenant (com paginacao e busca)
     *
     * GET /api/estoque
     * Query params: page, perPage, search, filial
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $filial = $request->query('filial', '');
            $idFilial = $filial !== '' ? (int) $filial : null;

            $status = $request->query('status', '');
            $statusFiltro = $status !== '' ? $status : null;

            $model = new Estoque();

            // Buscar produtos paginados
            $produtos = $model->listarPaginado($chave, $page, $perPage, $search, $idFilial, $statusFiltro);

            // Contar total de registros
            $total = $model->contar($chave, $search, $idFilial, $statusFiltro);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $produtos,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar produtos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca produtos do estoque para select
     *
     * GET /api/estoque/buscar
     */
    public function buscar(Request $request): void
    {
        try {
            $search = $request->query('q', '');
            $model = new Estoque();

            // Buscar produtos (limitado a 50 para performance)
            $produtos = $model->listarParaSelect($search);

            // Formatar para chosen
            $data = array_map(function ($p) {
                return [
                    'id' => $p['id'],
                    'text' => $p['produto_codigo'] . ' - ' . $p['produto_nome'],
                    'unidade' => $p['produto_unidade'] ?? '',
                    'valor_venda' => $p['valor_venda'] ?? 0,
                    'estoque_atual' => $p['produto_estoque_atual'] ?? 0,
                    'baixa_automatica' => $p['baixa_automatica'] ?? 'N',
                    'permitir_estoque_negativo' => $p['permitir_estoque_negativo'] ?? 'N'
                ];
            }, $produtos);

            Response::json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exibe um produto especifico
     *
     * GET /api/estoque/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Estoque();
            $produto = $model->buscarPorId($id);

            if (!$produto) {
                Response::json([
                    'success' => false,
                    'message' => 'Produto nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($produto['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Produto nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $produto
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo produto
     *
     * POST /estoque/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao campos obrigatorios
            $erros = $this->validarCampos($dados);
            if (!empty($erros)) {
                Response::json([
                    'success' => false,
                    'message' => implode(', ', $erros)
                ], 400);
                return;
            }

            $model = new Estoque();
            $id = $model->criar($dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Estoque',
                'criar',
                $id,
                $dados['produto_nome'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Produto criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um produto
     *
     * POST /estoque/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Estoque();
            $produto = $model->buscarPorId($id);

            if (!$produto) {
                Response::json([
                    'success' => false,
                    'message' => 'Produto nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($produto['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este produto'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao campos obrigatorios
            $erros = $this->validarCampos($dados);
            if (!empty($erros)) {
                Response::json([
                    'success' => false,
                    'message' => implode(', ', $erros)
                ], 400);
                return;
            }

            $model->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Estoque',
                'editar',
                $id,
                $produto['produto_nome'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Produto atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um produto
     *
     * POST /estoque/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Estoque();
            $produto = $model->buscarPorId($id);

            if (!$produto) {
                Response::json([
                    'success' => false,
                    'message' => 'Produto nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($produto['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este produto'
                ], 403);
                return;
            }

            // Verificar vinculos
            $vinculos = $model->verificarVinculos($id);
            if (!empty($vinculos)) {
                // Tem vinculo: inativar em vez de excluir
                if (($produto['status'] ?? 'A') === 'I') {
                    Response::json([
                        'success' => false,
                        'message' => t('modules.estoque.messages.already_inactive')
                    ], 400);
                    return;
                }

                $model->inativar($id);

                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", inativou produto do estoque [{$produto['produto_nome']}]"
                );

                Response::json([
                    'success' => true,
                    'message' => t('modules.estoque.messages.inactivated')
                ]);
                return;
            }

            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu produto do estoque [{$produto['produto_nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Produto excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir produto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reativa um produto inativo
     *
     * POST /estoque/{id}/reativar
     */
    public function reativar(Request $request, int $id): void
    {
        try {
            $model = new Estoque();
            $produto = $model->buscarPorId($id);

            if (!$produto) {
                Response::json([
                    'success' => false,
                    'message' => 'Produto nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($produto['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode reativar este produto'
                ], 403);
                return;
            }

            $model->reativar($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", reativou produto do estoque [{$produto['produto_nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.estoque.messages.reactivated')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao reativar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida campos obrigatorios
     *
     * @param array $dados Dados a validar
     * @return array Lista de erros
     */
    private function validarCampos(array $dados): array
    {
        $erros = [];

        if (empty($dados['produto_codigo'])) {
            $erros[] = 'Codigo do produto e obrigatorio';
        }
        if (empty($dados['produto_nome'])) {
            $erros[] = 'Nome do produto e obrigatorio';
        }
        if (empty($dados['produto_marca'])) {
            $erros[] = 'Marca e obrigatoria';
        }
        if (empty($dados['produto_modelo'])) {
            $erros[] = 'Modelo e obrigatorio';
        }
        if (empty($dados['produto_unidade'])) {
            $erros[] = 'Unidade e obrigatoria';
        }
        if (!isset($dados['produto_estoque_atual']) || $dados['produto_estoque_atual'] === '') {
            $erros[] = 'Estoque atual e obrigatorio';
        }
        if (!isset($dados['valor_compra']) || $dados['valor_compra'] === '') {
            $erros[] = 'Valor de compra e obrigatorio';
        }
        if (empty($dados['id_matriz_filial'])) {
            $erros[] = 'Filial e obrigatoria';
        }

        return $erros;
    }
}
