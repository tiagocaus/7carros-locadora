<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\ChecklistModelo;
use App\Services\AuditLogService;

/**
 * Controller de Checklist Modelos
 *
 * Gerencia operacoes CRUD de modelos de checklist para vistorias.
 */
class ChecklistModelosController
{
    /**
     * Renderiza a pagina de listagem de modelos
     *
     * GET /pages/checklist-modelos
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.checklist-modelos.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar modelo
     *
     * GET /pages/checklist-modelos/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.checklist-modelos.adicionar');
        Response::html($html);
    }

    /**
     * Lista todos os modelos do tenant (com paginacao e busca)
     *
     * GET /api/checklist-modelos
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new ChecklistModelo();

            $modelos = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $modelos,
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
                'message' => 'Erro ao buscar modelos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um modelo especifico
     *
     * GET /api/checklist-modelos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new ChecklistModelo();
            $modelo = $model->buscarPorId($id);

            if (!$modelo) {
                Response::json([
                    'success' => false,
                    'message' => 'Modelo nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($modelo['chave'] !== $chave && $modelo['chave'] !== '0') {
                Response::json([
                    'success' => false,
                    'message' => 'Modelo nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $modelo
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar modelo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista modelos para chosen-select server-side
     *
     * GET /api/checklist-modelos/buscar
     * Query params: q (termo de busca)
     */
    public function buscar(Request $request): void
    {
        try {
            $search = $request->query('q', '');

            $model = new ChecklistModelo();
            $modelos = $model->listarParaSelect($search);

            // Formatar para chosen-select (text ao inves de nome)
            $resultado = array_map(fn($m) => [
                'id' => $m['id'],
                'text' => $m['nome'],
                'tipo' => $m['tipo']
            ], $modelos);

            Response::json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar modelos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo modelo
     *
     * POST /checklist-modelos/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome e obrigatorio'
                ], 400);
                return;
            }

            // Validar e sanitizar JSON das questoes
            if (!empty($dados['questoes'])) {
                $questoes = json_decode($dados['questoes'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::json([
                        'success' => false,
                        'message' => 'Formato invalido para questoes'
                    ], 400);
                    return;
                }
                $questoes = $this->normalizarItensModelo($this->sanitizarArray($questoes));
                $dados['questoes'] = json_encode($questoes, JSON_UNESCAPED_UNICODE);
            } else {
                $dados['questoes'] = '[]';
            }

            // Validar e sanitizar JSON da vistoria
            if (!empty($dados['vistoria'])) {
                $vistoria = json_decode($dados['vistoria'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::json([
                        'success' => false,
                        'message' => 'Formato invalido para vistoria'
                    ], 400);
                    return;
                }
                $vistoria = $this->normalizarItensModelo($this->sanitizarArray($vistoria));
                $dados['vistoria'] = json_encode($vistoria, JSON_UNESCAPED_UNICODE);
            } else {
                $dados['vistoria'] = '[]';
            }

            $model = new ChecklistModelo();
            $id = $model->criar($dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou modelo de checklist [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Modelo criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar modelo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um modelo
     *
     * POST /checklist-modelos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new ChecklistModelo();
            $modelo = $model->buscarPorId($id);

            if (!$modelo) {
                Response::json([
                    'success' => false,
                    'message' => 'Modelo nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($modelo['chave'] !== $chave && $modelo['chave'] !== '0') {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este modelo'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validar e sanitizar JSON das questoes
            if (!empty($dados['questoes'])) {
                $questoes = json_decode($dados['questoes'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::json([
                        'success' => false,
                        'message' => 'Formato invalido para questoes'
                    ], 400);
                    return;
                }
                $questoes = $this->normalizarItensModelo($this->sanitizarArray($questoes));
                $dados['questoes'] = json_encode($questoes, JSON_UNESCAPED_UNICODE);
            }

            // Validar e sanitizar JSON da vistoria
            if (!empty($dados['vistoria'])) {
                $vistoria = json_decode($dados['vistoria'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::json([
                        'success' => false,
                        'message' => 'Formato invalido para vistoria'
                    ], 400);
                    return;
                }
                $vistoria = $this->normalizarItensModelo($this->sanitizarArray($vistoria));
                $dados['vistoria'] = json_encode($vistoria, JSON_UNESCAPED_UNICODE);
            }

            $novoId = null;
            if ($modelo['chave'] === '0') {
                $dados['chave'] = $chave;
                $novoId = $model->criar($dados);
            } else {
                $model->atualizar($id, $dados);
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou modelo de checklist [{$modelo['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => $novoId
                    ? 'Modelo do sistema copiado para sua empresa e salvo com sucesso'
                    : 'Modelo atualizado com sucesso',
                'data' => $novoId ? ['id' => $novoId] : null
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar modelo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um modelo
     *
     * POST /checklist-modelos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new ChecklistModelo();
            $modelo = $model->buscarPorId($id);

            if (!$modelo) {
                Response::json([
                    'success' => false,
                    'message' => 'Modelo nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($modelo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este modelo'
                ], 403);
                return;
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu modelo de checklist [{$modelo['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Modelo excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir modelo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sanitiza recursivamente os valores de um array (remove tags HTML)
     *
     * @param array $dados Array a ser sanitizado
     * @return array Array sanitizado
     */
    private function sanitizarArray(array $dados): array
    {
        $resultado = [];
        foreach ($dados as $key => $value) {
            if (is_array($value)) {
                $resultado[$key] = $this->sanitizarArray($value);
            } elseif (is_string($value)) {
                // Remove tags HTML e espaços extras
                $resultado[$key] = trim(strip_tags($value));
            } else {
                $resultado[$key] = $value;
            }
        }
        return $resultado;
    }

    /**
     * Padroniza itens de questoes/vistoria para gravar apenas "name" como campo canonico.
     */
    private function normalizarItensModelo(array $itens): array
    {
        foreach ($itens as &$item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['name']) || trim((string) $item['name']) === '') {
                foreach (['content', 'pergunta', 'label'] as $campoLegado) {
                    if (isset($item[$campoLegado]) && trim((string) $item[$campoLegado]) !== '') {
                        $item['name'] = trim((string) $item[$campoLegado]);
                        break;
                    }
                }
            }

            unset($item['content'], $item['pergunta'], $item['label']);

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->normalizarItensModelo($item['children']);
            }
        }
        unset($item);

        return $itens;
    }
}
