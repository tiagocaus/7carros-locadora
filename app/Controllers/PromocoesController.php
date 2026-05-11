<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Promocao;
use App\Models\PromocaoValorFilial;
use App\Helpers\FilialHelper;
use App\Services\AuditLogService;

/**
 * Controller de Promocoes
 *
 * Gerencia operacoes CRUD de promocoes disponiveis para locacoes.
 * Suporta multi-filial com relacionamento N:N.
 */
class PromocoesController
{
    /**
     * Renderiza a pagina de listagem de promocoes
     *
     * GET /pages/promocoes
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.promocoes.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar promocao
     *
     * GET /pages/promocoes/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.promocoes.adicionar');
        Response::html($html);
    }

    /**
     * Lista todas as promocoes do tenant (com paginacao, busca e filtro de filiais)
     *
     * GET /api/promocoes
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Filtro de filiais
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $model = new Promocao();

            $promocoes = $model->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);
            $total = $model->contar($search, $filialWhere, $filialParams);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $promocoes,
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
                'message' => 'Erro ao buscar promocoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca promocoes para chosen-select server-side
     *
     * GET /api/promocoes/buscar
     * Query params: q (termo de busca)
     */
    public function buscar(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('q', '');

            $model = new Promocao();
            $promocoes = $model->listarParaSelect($chave, $search);

            // Formatar para chosen-select (text ao inves de nome)
            $resultado = array_map(fn($p) => [
                'id' => $p['id'],
                'text' => $p['codigo'] . ' - ' . $p['nome'],
                'codigo' => $p['codigo'],
                'nome' => $p['nome'],
                'valor' => $p['valor'],
                'tipo' => $p['tipo'],
                'dias' => $p['dias']
            ], $promocoes);

            Response::json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar promocoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma promocao especifica
     *
     * GET /api/promocoes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Promocao();
            $promocao = $model->buscarPorId($id);

            if (!$promocao) {
                Response::json([
                    'success' => false,
                    'message' => 'Promocao nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promocao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Promocao nao encontrada'
                ], 404);
                return;
            }

            // Valores por filial (so faz sentido quando tipo=DFIX, mas sempre retorna
            // pro front decidir o que exibir)
            $valoresMap = [];
            foreach ((new PromocaoValorFilial())->listarPorPromocao($id) as $row) {
                $valoresMap[(int) $row['id_matriz_filial']] = (float) $row['valor'];
            }
            $promocao['valores_filiais'] = $valoresMap;

            Response::json([
                'success' => true,
                'data' => $promocao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar promocao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova promocao
     *
     * POST /promocoes/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validar campos obrigatorios
            if (empty($dados['codigo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Codigo e obrigatorio'
                ], 400);
                return;
            }

            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome e obrigatorio'
                ], 400);
                return;
            }

            // Validar filiais
            $filiaisIds = $this->parseFiliaisIds($dados['filiais_ids'] ?? '');
            if (empty($filiaisIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma filial'
                ], 400);
                return;
            }

            // Validar codigo unico
            $model = new Promocao();
            if ($model->codigoExiste($dados['codigo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Este codigo ja esta em uso por outra promocao'
                ], 400);
                return;
            }

            $id = $model->criar($dados);

            // Sincronizar filiais
            if (!empty($filiaisIds)) {
                $model->sincronizarFiliais($id, $filiaisIds, $dados['chave']);
            }

            // Valores por filial (so DFIX). Filtra filiais participantes.
            $this->salvarValoresFiliais($id, $dados, $filiaisIds);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou promocao [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Promocao criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar promocao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma promocao
     *
     * POST /promocoes/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Promocao();
            $promocao = $model->buscarPorId($id);

            if (!$promocao) {
                Response::json([
                    'success' => false,
                    'message' => 'Promocao nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promocao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta promocao'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validar filiais
            $filiaisIds = $this->parseFiliaisIds($dados['filiais_ids'] ?? '');
            if (empty($filiaisIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma filial'
                ], 400);
                return;
            }

            // Validar codigo unico (se mudou)
            if (!empty($dados['codigo']) && $dados['codigo'] !== $promocao['codigo']) {
                if ($model->codigoExiste($dados['codigo'], $id)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Este codigo ja esta em uso por outra promocao'
                    ], 400);
                    return;
                }
            }

            $model->atualizar($id, $dados);

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filiaisIds, $chave);

            // Valores por filial. Se tipo mudou pra DPOR, limpa entries antigas.
            $dados['chave'] = $chave;
            $this->salvarValoresFiliais($id, $dados, $filiaisIds);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou promocao [{$promocao['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Promocao atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar promocao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Persiste promocoes_valores_filiais:
     *  - Se tipo = DFIX: upsert das linhas recebidas em $dados['valores_filiais']
     *    (filtrando apenas filiais participantes de filiais_ids)
     *  - Se tipo != DFIX (ex: DPOR): limpa todas as entries dessa promocao
     */
    private function salvarValoresFiliais(int $promocaoId, array $dados, array $filiaisIds): void
    {
        $tipo  = strtoupper($dados['tipo'] ?? '');
        $pvf   = new PromocaoValorFilial();
        $chave = $dados['chave'] ?? Auth::chave();

        if ($tipo !== 'DFIX') {
            // Tipo percentual (ou indefinido) — limpa todas entries
            $pvf->excluirPorPromocao($promocaoId);
            return;
        }

        $valoresFiliais = $dados['valores_filiais'] ?? [];
        if (!is_array($valoresFiliais)) {
            return;
        }

        $filiaisSet = array_flip(array_map('intval', $filiaisIds));

        foreach ($valoresFiliais as $filialId => $valor) {
            $filialId = (int) $filialId;
            // Ignora filial que nao esta no pivot (operador pode ter desmarcado)
            if (!isset($filiaisSet[$filialId])) {
                continue;
            }
            $pvf->upsert([
                'chave' => $chave,
                'id_promocao' => $promocaoId,
                'id_matriz_filial' => $filialId,
                'valor' => $valor,
            ]);
        }

        // Remove entries de filiais que sairam do pivot
        $pvf->excluirFiliaisRemovidas($promocaoId, $filiaisIds);
    }

    /**
     * Exclui uma promocao
     *
     * POST /promocoes/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Promocao();
            $promocao = $model->buscarPorId($id);

            if (!$promocao) {
                Response::json([
                    'success' => false,
                    'message' => 'Promocao nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promocao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta promocao'
                ], 403);
                return;
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu promocao [{$promocao['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Promocao excluida com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir promocao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Converte string JSON de IDs para array
     *
     * @param string $json String JSON com array de IDs
     * @return array Lista de IDs
     */
    private function parseFiliaisIds(string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $ids = json_decode($json, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_filter(array_map('intval', $ids));
    }
}
