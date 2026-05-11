<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\TaxaServico;
use App\Models\TaxaServicoValorFilial;
use App\Helpers\FilialHelper;
use App\Services\AuditLogService;

/**
 * Controller de Taxas e Servicos
 *
 * Gerencia operacoes CRUD de taxas e servicos disponiveis para locacoes.
 * Suporta multi-filial com relacionamento N:N.
 */
class TaxasServicosController
{
    /**
     * Renderiza a pagina de listagem de taxas e servicos
     *
     * GET /pages/taxas-e-servicos
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.taxas-e-servicos.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar taxa ou servico
     *
     * GET /pages/taxas-e-servicos/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.taxas-e-servicos.adicionar');
        Response::html($html);
    }

    /**
     * Lista todas as taxas e servicos do tenant (com paginacao, busca e filtro de filiais)
     *
     * GET /api/taxas-e-servicos
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

            $model = new TaxaServico();

            $taxas = $model->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);
            $total = $model->contar($search, $filialWhere, $filialParams);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $taxas,
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
                'message' => 'Erro ao buscar taxas e servicos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista taxas para uso em selects (sem paginacao)
     *
     * GET /api/taxas-e-servicos/select
     * Query params: search, id_filial
     */
    public function selectOptions(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('search', '');
            $filialId = $request->query('id_filial');

            $model = new TaxaServico();
            $taxas = $model->listarParaSelect($chave, $search, $filialId ? (int) $filialId : null);

            Response::json(['success' => true, 'data' => $taxas]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar taxas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca taxas para chosen-select server-side
     *
     * GET /api/taxas-e-servicos/buscar
     * Query params: q (termo de busca)
     */
    public function buscar(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('q', '');
            $filialId = $request->query('id_filial');

            $model = new TaxaServico();
            $taxas = $model->listarParaSelect($chave, $search, $filialId ? (int) $filialId : null);

            // Formatar para chosen-select (text ao inves de nome)
            $resultado = array_map(fn($t) => [
                'id' => $t['id'],
                'text' => $t['nome'],
                'valor' => $t['valor'],
                'base_calculo' => $t['base_calculo'],
                'tipo_valor' => $t['tipo_valor']
            ], $taxas);

            Response::json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar taxas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista taxas com aplicar='S' e onde_usar contendo 'SIS'
     *
     * GET /api/taxas-e-servicos/auto-aplicar
     * Query params: filial_id (opcional)
     */
    public function autoAplicar(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $filialId = $request->query('filial_id');

            $model = new TaxaServico();
            $taxas = $model->listarAutoAplicar($chave, $filialId ? (int) $filialId : null);

            $resultado = array_map(fn($t) => [
                'id' => $t['id'],
                'text' => $t['nome'],
                'valor' => $t['valor'],
                'base_calculo' => $t['base_calculo'],
                'tipo_valor' => $t['tipo_valor']
            ], $taxas);

            Response::json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar taxas auto-aplicar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma taxa ou servico especifico
     *
     * GET /api/taxas-e-servicos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new TaxaServico();
            $taxa = $model->buscarPorId($id);

            if (!$taxa) {
                Response::json([
                    'success' => false,
                    'message' => 'Taxa/servico nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($taxa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Taxa/servico nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $taxa
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar taxa/servico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova taxa ou servico
     *
     * POST /taxas-e-servicos/salvar
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

            // Validar filiais
            $filiaisIds = $this->parseFiliaisIds($dados['filiais_ids'] ?? '');
            if (empty($filiaisIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma filial'
                ], 400);
                return;
            }

            // Validar valor (so obrigatorio quando tipo_valor=POR; para MON vem das valores_filiais)
            $tipoValor = $dados['tipo_valor'] ?? 'MON';
            if ($tipoValor !== 'MON') {
                $valorConvertido = (float) str_replace(',', '.', $dados['valor'] ?? '');
                if (!isset($dados['valor']) || $dados['valor'] === '' || $valorConvertido <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Valor e obrigatorio'
                    ], 400);
                    return;
                }
            }

            $model = new TaxaServico();
            $id = $model->criar($dados);

            // Sincronizar filiais
            if (!empty($filiaisIds)) {
                $model->sincronizarFiliais($id, $filiaisIds, $dados['chave']);
            }

            // Persistir valores por filial (so quando tipo_valor=MON)
            if ($tipoValor === 'MON') {
                $this->salvarValoresFiliais($id, $filiaisIds, $dados, (string) $dados['chave']);
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou taxa/servico [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Taxa/servico criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar taxa/servico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma taxa ou servico
     *
     * POST /taxas-e-servicos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new TaxaServico();
            $taxa = $model->buscarPorId($id);

            if (!$taxa) {
                Response::json([
                    'success' => false,
                    'message' => 'Taxa/servico nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($taxa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta taxa/servico'
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

            // Validar valor (so obrigatorio quando tipo_valor=POR; para MON vem das valores_filiais)
            $tipoValor = $dados['tipo_valor'] ?? ($taxa['tipo_valor'] ?? 'MON');
            if ($tipoValor !== 'MON') {
                $valorConvertido = (float) str_replace(',', '.', $dados['valor'] ?? '');
                if (!isset($dados['valor']) || $dados['valor'] === '' || $valorConvertido <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Valor e obrigatorio'
                    ], 400);
                    return;
                }
            }

            $model->atualizar($id, $dados);

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filiaisIds, $chave);

            // Persistir valores por filial + limpar o que nao pertence mais
            $valoresModel = new TaxaServicoValorFilial();
            if ($tipoValor === 'MON') {
                $this->salvarValoresFiliais($id, $filiaisIds, $dados, $chave);
                // Remove filiais que sairam do pivot
                $valoresModel->excluirFiliaisRemovidas($id, $filiaisIds);
            } else {
                // tipo_valor=POR -> nao precisa valores por filial
                $valoresModel->excluirPorTaxa($id);
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou taxa/servico [{$taxa['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Taxa/servico atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar taxa/servico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma taxa ou servico
     *
     * POST /taxas-e-servicos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new TaxaServico();
            $taxa = $model->buscarPorId($id);

            if (!$taxa) {
                Response::json([
                    'success' => false,
                    'message' => 'Taxa/servico nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($taxa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta taxa/servico'
                ], 403);
                return;
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu taxa/servico [{$taxa['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Taxa/servico excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir taxa/servico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Persiste valores da taxa por filial (tabela taxaseservicos_valores_filiais).
     *
     * Payload esperado em $dados['valores_filiais'] = { id_filial: valor, ... }.
     * Se vier vazio, cria entry zerada para cada filial participante.
     */
    private function salvarValoresFiliais(int $taxaId, array $filiaisIds, array $dados, string $chave): void
    {
        $mapa = is_array($dados['valores_filiais'] ?? null) ? $dados['valores_filiais'] : [];
        $model = new TaxaServicoValorFilial();
        foreach ($filiaisIds as $fid) {
            $fid = (int) $fid;
            if ($fid <= 0) continue;
            $valor = $mapa[$fid] ?? ($mapa[(string) $fid] ?? 0);
            $model->upsert([
                'chave' => $chave,
                'id_taxaservico' => $taxaId,
                'id_matriz_filial' => $fid,
                'valor' => $valor,
            ]);
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
