<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\FormaPagamento;
use App\Models\ComandoParcela;
use App\Services\AuditLogService;

/**
 * Controller de Formas de Pagamento e Comandos de Parcelas
 *
 * Gerencia operacoes CRUD de formas de pagamento,
 * incluindo configuracao de taxas e descontos por antecipacao.
 * Tambem gerencia CRUD de comandos de parcelas.
 */
class FormasPagamentoController
{
    // ==========================================
    // FORMAS DE PAGAMENTO
    // ==========================================

    /**
     * Renderiza a pagina de listagem de formas de pagamento
     *
     * GET /pages/formas-pagamento
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.formas-pagamento.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar forma de pagamento
     *
     * GET /pages/formas-pagamento/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.formas-pagamento.adicionar');
        Response::html($html);
    }

    /**
     * Lista todas as formas de pagamento do tenant (com paginacao e busca)
     *
     * GET /api/formas-pagamento
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new FormaPagamento();

            $formas = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $formas,
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
                'message' => 'Erro ao buscar formas de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista formas de pagamento ativas para selects
     *
     * GET /api/formas-pagamento/select
     * Query params: search, q
     */
    public function indexSelect(Request $request): void
    {
        try {
            $search = $request->query('search', $request->query('q', ''));

            $model = new FormaPagamento();
            $formas = $model->listarParaSelect($search);

            Response::json([
                'success' => true,
                'data' => $formas
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar formas de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma forma de pagamento especifica
     *
     * GET /api/formas-pagamento/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new FormaPagamento();
            $forma = $model->buscarPorId($id);

            if (!$forma) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento não encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($forma['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento não encontrada'
                ], 404);
                return;
            }

            // Buscar filiais vinculadas
            $forma['filiais'] = $model->buscarFiliais($id);

            // Buscar gateways vinculados
            $forma['gateways'] = $model->buscarGateways($id);

            Response::json([
                'success' => true,
                'data' => $forma
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar forma de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova forma de pagamento
     *
     * POST /formas-pagamento/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome é obrigatório'
                ], 400);
                return;
            }

            $model = new FormaPagamento();
            $id = $model->criar($dados);

            // Sincronizar filiais
            if (!empty($dados['filiais_ids'])) {
                $filiaisIds = json_decode($dados['filiais_ids'], true);
                if (is_array($filiaisIds) && count($filiaisIds) > 0) {
                    $model->sincronizarFiliais($id, $filiaisIds, $dados['chave']);
                }
            }

            // Sincronizar gateways
            if (isset($dados['gateways_ids'])) {
                $gatewaysIds = json_decode($dados['gateways_ids'], true);
                if (is_array($gatewaysIds)) {
                    $model->sincronizarGateways($id, $gatewaysIds, $dados['chave']);
                }
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou forma de pagamento [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Forma de pagamento criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar forma de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma forma de pagamento
     *
     * POST /formas-pagamento/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new FormaPagamento();
            $forma = $model->buscarPorId($id);

            if (!$forma) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento não encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($forma['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode editar esta forma de pagamento'
                ], 403);
                return;
            }

            $dados = $request->all();
            $model->atualizar($id, $dados);

            // Sincronizar filiais
            if (isset($dados['filiais_ids'])) {
                $filiaisIds = json_decode($dados['filiais_ids'], true);
                if (is_array($filiaisIds)) {
                    $model->sincronizarFiliais($id, $filiaisIds, $forma['chave']);
                }
            }

            // Sincronizar gateways
            if (isset($dados['gateways_ids'])) {
                $gatewaysIds = json_decode($dados['gateways_ids'], true);
                if (is_array($gatewaysIds)) {
                    $model->sincronizarGateways($id, $gatewaysIds, $forma['chave']);
                }
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou forma de pagamento [{$forma['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Forma de pagamento atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar forma de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma forma de pagamento
     *
     * POST /formas-pagamento/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new FormaPagamento();
            $forma = $model->buscarPorId($id);

            if (!$forma) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento não encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($forma['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode excluir esta forma de pagamento'
                ], 403);
                return;
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu forma de pagamento [{$forma['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Forma de pagamento excluída com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir forma de pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula taxas para um valor e quantidade de parcelas
     *
     * GET /api/formas-pagamento/{id}/calcular-taxas
     * Query params: valor, parcelas
     */
    public function calcularTaxas(Request $request, int $id): void
    {
        try {
            $valor = (float) $request->query('valor', 0);
            $parcelas = (int) $request->query('parcelas', 1);

            if ($valor <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor deve ser maior que zero'
                ], 400);
                return;
            }

            $model = new FormaPagamento();
            $resultado = $model->calcularTaxas($id, $valor, $parcelas);

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao calcular taxas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula desconto por antecipacao
     *
     * GET /api/formas-pagamento/{id}/calcular-desconto
     * Query params: valor, data_vencimento, data_pagamento (opcional)
     */
    public function calcularDesconto(Request $request, int $id): void
    {
        try {
            $valor = (float) $request->query('valor', 0);
            $dataVencimento = $request->query('data_vencimento', '');
            $dataPagamento = $request->query('data_pagamento', null);

            if ($valor <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor deve ser maior que zero'
                ], 400);
                return;
            }

            if (empty($dataVencimento)) {
                Response::json([
                    'success' => false,
                    'message' => 'Data de vencimento é obrigatória'
                ], 400);
                return;
            }

            $model = new FormaPagamento();
            $resultado = $model->calcularDescontoAntecipacao($id, $valor, $dataVencimento, $dataPagamento);

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao calcular desconto: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // CRUD Comandos de Parcelas
    // ==========================================

    /**
     * Renderiza a pagina de CRUD de comandos de parcelas
     *
     * GET /pages/comandos-parcelas
     */
    public function viewComandos(Request $request): void
    {
        $html = Template::render('pages.formas-pagamento.comandos');
        Response::html($html);
    }

    /**
     * Lista comandos de parcelas com paginacao
     *
     * GET /api/comandos-parcelas
     * Query params: page, perPage, search
     */
    public function indexComandos(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new ComandoParcela();

            $comandos = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $comandos,
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
                'message' => 'Erro ao buscar comandos de parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista comandos de parcelas ativos para select
     *
     * GET /api/comandos-parcelas/select
     */
    public function indexComandosParaSelect(Request $request): void
    {
        try {
            $search = $request->query('search', $request->query('q', ''));

            $model = new ComandoParcela();
            $comandos = $model->listarParaSelect($search);

            // Adicionar label inferido para cada comando
            foreach ($comandos as &$cmd) {
                $cmd['label'] = ComandoParcela::inferirLabel($cmd['comando']);
                $cmd['text'] = $cmd['comando'] . (!empty($cmd['descricao']) ? ' - ' . $cmd['descricao'] : '');
            }

            Response::json([
                'success' => true,
                'data' => $comandos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar comandos de parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca um comando de parcelas especifico
     *
     * GET /api/comandos-parcelas/{id}
     */
    public function showComando(Request $request, int $id): void
    {
        try {
            $model = new ComandoParcela();
            $comando = $model->buscarPorId($id);

            if (!$comando) {
                Response::json(['success' => false, 'message' => 'Comando não encontrado'], 404);
                return;
            }

            // Adicionar informacoes de parsing
            $comando['label'] = ComandoParcela::inferirLabel($comando['comando']);
            $comando['parsed'] = ComandoParcela::parseComando($comando['comando']);

            Response::json(['success' => true, 'data' => $comando]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cria um novo comando de parcelas (apenas tenant)
     *
     * POST /comandos-parcelas/salvar
     */
    public function storeComando(Request $request): void
    {
        try {
            $comando = trim($request->input('comando', ''));

            if ($comando === '') {
                Response::json(['success' => false, 'message' => 'Comando é obrigatório'], 400);
                return;
            }

            // Validar se o comando eh parseavel
            $parsed = ComandoParcela::parseComando($comando);
            if ($parsed['tipo'] === 'desconhecido') {
                Response::json(['success' => false, 'message' => 'Formato de comando inválido'], 400);
                return;
            }

            $model = new ComandoParcela();
            $id = $model->criar([
                'chave' => Auth::chave(),
                'comando' => $comando,
                'descricao' => $request->input('descricao', null),
                'status' => $request->input('status', 'A'),
            ]);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou comando de parcelas [{$comando}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Comando de parcelas criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um comando de parcelas (apenas tenant, nao sistema)
     *
     * POST /comandos-parcelas/{id}/atualizar
     */
    public function updateComando(Request $request, int $id): void
    {
        try {
            $dados = [];

            $comando = $request->input('comando');
            if ($comando !== null) {
                $comando = trim($comando);
                if ($comando === '') {
                    Response::json(['success' => false, 'message' => 'Comando é obrigatório'], 400);
                    return;
                }
                $parsed = ComandoParcela::parseComando($comando);
                if ($parsed['tipo'] === 'desconhecido') {
                    Response::json(['success' => false, 'message' => 'Formato de comando inválido'], 400);
                    return;
                }
                $dados['comando'] = $comando;
            }

            if ($request->has('descricao')) {
                $dados['descricao'] = $request->input('descricao');
            }
            if ($request->has('status')) {
                $dados['status'] = $request->input('status');
            }

            $model = new ComandoParcela();
            $model->atualizar($id, $dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou comando de parcelas [#{$id}]"
            );

            Response::json(['success' => true, 'message' => 'Comando atualizado com sucesso']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exclui um comando de parcelas (apenas tenant, nao sistema)
     *
     * POST /comandos-parcelas/{id}/excluir
     */
    public function destroyComando(Request $request, int $id): void
    {
        try {
            $model = new ComandoParcela();
            $comando = $model->buscarPorId($id);

            if (!$comando) {
                Response::json(['success' => false, 'message' => 'Comando não encontrado'], 404);
                return;
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu comando de parcelas [{$comando['comando']}]"
            );

            Response::json(['success' => true, 'message' => 'Comando excluído com sucesso']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}
