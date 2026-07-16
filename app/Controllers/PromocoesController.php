<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Promocao;
use App\Models\PromocaoValorFilial;
use App\Helpers\FilialHelper;
use App\Helpers\DateHelper;
use App\Services\AuditLogService;
use App\Services\PromocaoAplicacaoService;
use App\Models\MatrizFilial;
use App\Models\Grupo;

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
     * Valida e calcula uma promocao no contexto do sistema interno.
     */
    public function validar(Request $request): void
    {
        try {
            $dados = $request->all();
            $resultado = (new PromocaoAplicacaoService())->validarECalcular(
                (string) ($dados['codigo'] ?? ''),
                (int) ($dados['filial_id'] ?? 0),
                (int) ($dados['dias'] ?? 0),
                (float) ($dados['total_original'] ?? 0),
                'SIS',
                (int) ($dados['grupo_id'] ?? 0)
            );
            Response::json(['success' => true, 'data' => $resultado]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao validar promocao.'], 500);
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
            [$dados, $filiaisIds, $gruposIds] = $this->validarDadosCadastro($request->all());
            $dados['chave'] = Auth::chave();

            // Validar codigo unico
            $model = new Promocao();
            if ($model->codigoExiste($dados['codigo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Este codigo ja esta em uso por outra promocao'
                ], 400);
                return;
            }

            $id = $model->executarEmTransacao(function () use ($model, $dados, $filiaisIds, $gruposIds): int {
                $id = $model->criar($dados);
                $model->sincronizarFiliais($id, $filiaisIds, $dados['chave']);
                $model->sincronizarGrupos($id, $gruposIds, $dados['chave']);
                $this->salvarValoresFiliais($id, $dados, $filiaisIds);
                return $id;
            });

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou promocao [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Promocao criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
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
            $this->validarAcessoTodasFiliais($promocao['filiais'] ?? []);
            [$dados, $filiaisIds, $gruposIds] = $this->validarDadosCadastro($request->all());

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

            $dados['chave'] = $chave;
            $model->executarEmTransacao(function () use ($model, $id, $dados, $filiaisIds, $gruposIds, $chave): void {
                $model->atualizar($id, $dados);
                $model->sincronizarFiliais($id, $filiaisIds, $chave);
                $model->sincronizarGrupos($id, $gruposIds, $chave);
                $this->salvarValoresFiliais($id, $dados, $filiaisIds);
            });

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

            $this->validarAcessoTodasFiliais($promocao['filiais'] ?? []);

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
    private function parseIds(string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $ids = json_decode($json, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * @return array{0:array,1:array<int>,2:array<int>}
     */
    private function validarDadosCadastro(array $dados): array
    {
        $dados['codigo'] = PromocaoAplicacaoService::normalizarCodigo($dados['codigo'] ?? '');
        $dados['nome'] = trim((string) ($dados['nome'] ?? ''));
        if ($dados['codigo'] === '' || mb_strlen($dados['codigo']) > 15) {
            throw new \InvalidArgumentException('Informe um codigo com ate 15 caracteres.');
        }
        if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 100) {
            throw new \InvalidArgumentException('Informe o nome da promocao com ate 100 caracteres.');
        }

        $tipo = strtoupper((string) ($dados['tipo'] ?? ''));
        if (!in_array($tipo, ['DFIX', 'DPOR'], true)) {
            throw new \InvalidArgumentException('Tipo de desconto invalido.');
        }
        $dados['tipo'] = $tipo;

        $status = strtoupper((string) ($dados['status'] ?? ''));
        if (!in_array($status, ['A', 'D'], true)) {
            throw new \InvalidArgumentException('Status da promocao invalido.');
        }
        $dados['status'] = $status;

        $dias = filter_var($dados['dias'] ?? 0, FILTER_VALIDATE_INT);
        if ($dias === false || $dias < 0 || $dias > 999) {
            throw new \InvalidArgumentException('A diaria minima deve estar entre 0 e 999.');
        }
        $dados['dias'] = $dias;

        $validade = trim((string) ($dados['validade'] ?? ''));
        if ($validade !== '') {
            $partesData = array_map('intval', explode('-', $validade));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade)
                || count($partesData) !== 3
                || !checkdate($partesData[1], $partesData[2], $partesData[0])) {
                throw new \InvalidArgumentException('Data de validade invalida.');
            }
        }
        if ($status === 'A' && $validade !== '' && $validade < DateHelper::todayForDatabase()) {
            throw new \InvalidArgumentException('Uma promocao ativa nao pode ter validade vencida.');
        }
        $dados['validade'] = $validade !== '' ? $validade : null;

        $canais = array_values(array_unique(array_filter(array_map(
            static fn(string $canal): string => strtoupper(trim($canal)),
            explode(',', (string) ($dados['onde_exibir'] ?? ''))
        ))));
        $canais = array_values(array_filter(['SIS', 'SITE', 'APP'], static fn(string $canal): bool => in_array($canal, $canais, true)));
        if (!$canais) {
            throw new \InvalidArgumentException('Selecione pelo menos um canal de uso.');
        }
        $dados['onde_exibir'] = implode(',', $canais);

        $filiaisIds = $this->parseIds((string) ($dados['filiais_ids'] ?? ''));
        if (!$filiaisIds) {
            throw new \InvalidArgumentException('Selecione pelo menos uma filial.');
        }
        foreach ($filiaisIds as $filialId) {
            $filial = (new MatrizFilial())->buscarPorId($filialId);
            if (!$filial || !FilialHelper::temAcessoFilial($filialId)) {
                throw new \InvalidArgumentException('Uma das filiais selecionadas e invalida ou nao esta acessivel.');
            }
        }

        $gruposIds = $this->parseIds((string) ($dados['grupos_ids'] ?? '[]'));
        foreach ($gruposIds as $grupoId) {
            if (!(new Grupo())->buscarPorId($grupoId)) {
                throw new \InvalidArgumentException('Um dos grupos selecionados e invalido ou pertence a outro tenant.');
            }
        }
        $dados['todos_grupos'] = $gruposIds ? 0 : 1;

        if ($tipo === 'DPOR') {
            $valor = currency_parse($dados['valor'] ?? 0);
            if ($valor <= 0 || $valor > 100) {
                throw new \InvalidArgumentException('O percentual deve ser maior que 0 e menor ou igual a 100.');
            }
            $dados['valor'] = $valor;
            $dados['valores_filiais'] = [];
        } else {
            $valores = is_array($dados['valores_filiais'] ?? null) ? $dados['valores_filiais'] : [];
            $normalizados = [];
            foreach ($filiaisIds as $filialId) {
                $valor = currency_parse($valores[$filialId] ?? 0);
                if ($valor <= 0) {
                    throw new \InvalidArgumentException('Informe um valor fixo positivo para todas as filiais selecionadas.');
                }
                $normalizados[$filialId] = $valor;
            }
            $dados['valores_filiais'] = $normalizados;
            $dados['valor'] = reset($normalizados);
        }

        return [$dados, $filiaisIds, $gruposIds];
    }

    private function validarAcessoTodasFiliais(array $filiais): void
    {
        foreach ($filiais as $filial) {
            if (!FilialHelper::temAcessoFilial((int) ($filial['id'] ?? 0))) {
                throw new \InvalidArgumentException('A promocao esta vinculada a filial sem acesso para este usuario.');
            }
        }
    }
}
