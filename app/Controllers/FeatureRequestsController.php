<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\FeatureRequest;
use App\Models\FeatureRequestVote;
use App\Models\FeatureRequestFollower;
use App\Services\AuditLogService;

/**
 * Controller de Feature Requests (Pedidos de Recursos)
 *
 * Sistema CROSS-TENANT: todos os tenants podem ver e votar em todos os pedidos.
 * Apenas admin 7Carros pode alterar status (verificar via chave especial ou permissão).
 */
class FeatureRequestsController
{
    /**
     * Renderiza a página de listagem
     *
     * GET /pages/feature-requests
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.feature-requests.index');
        Response::html($html);
    }

    /**
     * Renderiza a página de criar/editar
     *
     * GET /pages/feature-requests/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.feature-requests.adicionar');
        Response::html($html);
    }

    /**
     * Renderiza a página de detalhes
     *
     * GET /pages/feature-requests/detalhes
     */
    public function viewDetalhes(Request $request): void
    {
        $html = Template::render('pages.feature-requests.detalhes');
        Response::html($html);
    }

    /**
     * Lista pedidos com paginação e filtros (API)
     *
     * GET /api/feature-requests
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $email = $_SESSION['user_email'] ?? '';

            // Parâmetros de paginação
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));

            // Filtros
            $filtros = [
                'status' => $request->query('status', ''),
                'modulo_id' => $request->query('modulo_id', ''),
                'search' => $request->query('search', ''),
                'ordenar' => $request->query('ordenar', 'recentes'),
                'meus_pedidos' => filter_var($request->query('meus_pedidos', false), FILTER_VALIDATE_BOOLEAN),
                'chave' => $chave,
            ];

            $model = new FeatureRequest();
            $voteModel = new FeatureRequestVote();
            $followerModel = new FeatureRequestFollower();

            // Buscar pedidos paginados
            $pedidos = $model->listarPaginado($page, $perPage, $filtros);

            // Obter IDs votados e seguidos pelo usuário atual
            $votados = $email ? $voteModel->listarPedidosVotados($email) : [];
            $seguidos = $email ? $followerModel->listarPedidosSeguidos($email) : [];

            // Adicionar flags aos pedidos
            foreach ($pedidos as &$pedido) {
                $pedido['votei'] = in_array($pedido['id'], $votados);
                $pedido['sigo'] = in_array($pedido['id'], $seguidos);
                $pedido['status_label'] = FeatureRequest::STATUS_LABELS[$pedido['status']] ?? $pedido['status'];
                if ($pedido['status'] === 'aguardando_info') {
                    $pedido['status_label'] = 'Aguardando';
                }
                $pedido['status_cor'] = FeatureRequest::STATUS_CORES[$pedido['status']] ?? 'bg-gray-100 text-gray-800';
            }
            unset($pedido);

            // Contar total
            $total = $model->contar($filtros);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $pedidos,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar pedidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exibe um pedido específico (API)
     *
     * GET /api/feature-requests/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            // Verificar se usuário votou/segue
            $voteModel = new FeatureRequestVote();
            $followerModel = new FeatureRequestFollower();

            $pedido['votei'] = $email ? $voteModel->jaVotou($id, $email) : false;
            $pedido['sigo'] = $email ? $followerModel->jaSegue($id, $email) : false;

            // Dados do seguidor (se seguir)
            if ($pedido['sigo']) {
                $pedido['seguidor'] = $followerModel->buscar($id, $email);
            }

            // Verificar se é admin 7Carros
            $pedido['pode_editar_status'] = $this->isAdmin7Carros();

            // Verificar se pode editar o pedido (é autor OU tem permissão do tenant)
            $userId = $_SESSION['user_id'] ?? null;
            $chave = Auth::chave();
            $isProprietario = $pedido['usuario_id'] == $userId;
            $temPermissaoEditar = Auth::can('feature_requests.edit_own') && $pedido['chave'] === $chave;
            $pedido['pode_editar'] = $isProprietario || $temPermissaoEditar || $this->isAdmin7Carros();

            // Labels de status
            $pedido['status_label'] = FeatureRequest::STATUS_LABELS[$pedido['status']] ?? $pedido['status'];
            $pedido['status_cor'] = FeatureRequest::STATUS_CORES[$pedido['status']] ?? 'bg-gray-100 text-gray-800';

            Response::json([
                'success' => true,
                'data' => $pedido,
                'is_admin' => $this->isAdmin7Carros(),
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca pedidos similares (para busca inteligente)
     *
     * GET /api/feature-requests/similares
     */
    public function similares(Request $request): void
    {
        try {
            $termo = $request->query('termo', '');

            if (strlen($termo) < 3) {
                Response::json([
                    'success' => true,
                    'data' => [],
                ]);
                return;
            }

            $model = new FeatureRequest();
            $similares = $model->buscarSimilares($termo, 5);

            Response::json([
                'success' => true,
                'data' => $similares,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na busca: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista módulos disponíveis
     *
     * GET /api/feature-requests/modulos
     */
    public function modulos(Request $request): void
    {
        try {
            $model = new FeatureRequest();
            $modulos = $model->listarModulos();

            Response::json([
                'success' => true,
                'data' => $modulos,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar módulos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtém estatísticas
     *
     * GET /api/feature-requests/estatisticas
     */
    public function estatisticas(Request $request): void
    {
        try {
            $chave = $request->query('meus') ? Auth::chave() : null;

            $model = new FeatureRequest();
            $stats = $model->estatisticas($chave);

            Response::json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar estatísticas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna IDs dos pedidos que o usuário votou
     *
     * GET /api/feature-requests/meus-votos
     */
    public function meusVotos(Request $request): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => true,
                    'data' => [],
                ]);
                return;
            }

            $voteModel = new FeatureRequestVote();
            $votados = $voteModel->listarPedidosVotados($email);

            Response::json([
                'success' => true,
                'data' => $votados,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar votos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna IDs dos pedidos que o usuário segue
     *
     * GET /api/feature-requests/meus-seguidos
     */
    public function meusSeguidos(Request $request): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => true,
                    'data' => [],
                ]);
                return;
            }

            $followerModel = new FeatureRequestFollower();
            $seguidos = $followerModel->listarPedidosSeguidos($email);

            Response::json([
                'success' => true,
                'data' => $seguidos,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar seguidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista seguidores de um pedido (admin only)
     *
     * GET /api/feature-requests/{id}/seguidores
     */
    public function seguidores(Request $request, int $id): void
    {
        try {
            // Verificar se é admin 7Carros
            if (!$this->isAdmin7Carros()) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado',
                ], 403);
                return;
            }

            $followerModel = new FeatureRequestFollower();
            $seguidores = $followerModel->listarPorPedido($id);

            Response::json([
                'success' => true,
                'data' => $seguidores,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar seguidores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um novo pedido
     *
     * POST /feature-requests/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();
            $dados['usuario_id'] = $_SESSION['user_id'] ?? null;
            $dados['nome_solicitante'] = $_SESSION['user_name'] ?? null;
            $dados['email_solicitante'] = $_SESSION['user_email'] ?? '';

            // Validação
            if (empty($dados['titulo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Título é obrigatório',
                ], 400);
                return;
            }

            if (empty($dados['descricao'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Descrição é obrigatória',
                ], 400);
                return;
            }

            if (empty($dados['email_solicitante'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Email é obrigatório',
                ], 400);
                return;
            }

            $model = new FeatureRequest();
            $id = $model->criar($dados);

            // Se marcou para seguir automaticamente
            if (!empty($dados['seguir_automaticamente'])) {
                $followerModel = new FeatureRequestFollower();
                $followerModel->seguir([
                    'feature_request_id' => $id,
                    'chave' => $dados['chave'],
                    'usuario_id' => $dados['usuario_id'],
                    'email' => $dados['email_solicitante'],
                    'telefone' => $dados['telefone_solicitante'] ?? null,
                    'notificar_email' => 1,
                    'notificar_whatsapp' => 1,
                ]);
            }

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", criou pedido de recurso [{$dados['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'data' => ['id' => $id],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza um pedido
     *
     * POST /feature-requests/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            // Verificar se pode editar (é autor OU tem permissão do tenant OU é admin)
            $userId = $_SESSION['user_id'] ?? null;
            $chave = Auth::chave();
            $isProprietario = $pedido['usuario_id'] == $userId;
            $temPermissaoEditar = Auth::can('feature_requests.edit_own') && $pedido['chave'] === $chave;
            $isAdmin = $this->isAdmin7Carros();

            if (!$isProprietario && !$temPermissaoEditar && !$isAdmin) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode editar este pedido',
                ], 403);
                return;
            }

            $dados = $request->all();
            $model->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou pedido de recurso [{$pedido['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Pedido atualizado com sucesso',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza status do pedido (apenas admin 7Carros)
     *
     * PUT /feature-requests/{id}/status
     */
    public function atualizarStatus(Request $request, int $id): void
    {
        try {
            // Verificar se é admin 7Carros
            if (!$this->isAdmin7Carros()) {
                Response::json([
                    'success' => false,
                    'message' => 'Apenas administradores 7Carros podem alterar o status',
                ], 403);
                return;
            }

            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            $dados = $request->all();
            $novoStatus = $dados['status'] ?? $pedido['status'];
            $novaPrioridade = $dados['prioridade'] ?? $pedido['prioridade'];
            $resposta = $dados['resposta_admin'] ?? $dados['resposta'] ?? null;
            $notificarCriador = ($dados['notificar'] ?? 0) == 1;
            $notificarSeguidores = ($dados['notificar_seguidores'] ?? 0) == 1;
            $respondidoPor = $_SESSION['user_id'] ?? null;

            // Validar status
            $statusValidos = array_keys(FeatureRequest::STATUS_LABELS);
            if (!in_array($novoStatus, $statusValidos, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Status inválido',
                ], 400);
                return;
            }

            // Validar prioridade
            $prioridadesValidas = ['baixa', 'normal', 'alta', 'critica'];
            if (!in_array($novaPrioridade, $prioridadesValidas, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Prioridade inválida',
                ], 400);
                return;
            }

            $statusAnterior = $pedido['status'];
            $respostaAnterior = trim((string) ($pedido['resposta_admin'] ?? ''));
            $respostaAtual = trim((string) ($resposta ?? ''));
            $statusMudou = $novoStatus !== $statusAnterior;
            $respostaMudou = $respostaAtual !== '' && $respostaAtual !== $respostaAnterior;

            $model->atualizarStatus($id, $novoStatus, $resposta, $respondidoPor, $novaPrioridade);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", alterou status do pedido [{$pedido['titulo']}] de [{$statusAnterior}] para [{$novoStatus}]"
            );

            if (($notificarCriador || $notificarSeguidores) && ($statusMudou || $respostaMudou)) {
                $this->enviarNotificacoes($id, $novoStatus, $resposta, $notificarCriador, $notificarSeguidores);
            }

            $pedidoAtualizado = $model->buscarPorId($id);

            Response::json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $pedidoAtualizado,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vota em um pedido
     *
     * POST /feature-requests/{id}/votar
     */
    public function votar(Request $request, int $id): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você precisa estar logado para votar',
                ], 401);
                return;
            }

            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            $voteModel = new FeatureRequestVote();
            $resultado = $voteModel->votar([
                'feature_request_id' => $id,
                'chave' => Auth::chave(),
                'usuario_id' => $_SESSION['user_id'] ?? null,
                'email_votante' => $email,
            ]);

            if ($resultado === false) {
                Response::json([
                    'success' => false,
                    'message' => 'Você já votou neste pedido',
                ], 400);
                return;
            }

            // Buscar novo total
            $pedidoAtualizado = $model->buscarPorId($id);

            Response::json([
                'success' => true,
                'message' => 'Voto registrado com sucesso',
                'data' => [
                    'total_votos' => $pedidoAtualizado['total_votos'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao registrar voto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove voto de um pedido
     *
     * POST /feature-requests/{id}/remover-voto
     */
    public function removerVoto(Request $request, int $id): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você precisa estar logado',
                ], 401);
                return;
            }

            $voteModel = new FeatureRequestVote();
            $removeu = $voteModel->removerVoto($id, $email);

            if (!$removeu) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tinha votado neste pedido',
                ], 400);
                return;
            }

            // Buscar novo total
            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            Response::json([
                'success' => true,
                'message' => 'Voto removido com sucesso',
                'data' => [
                    'total_votos' => $pedido['total_votos'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover voto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Segue um pedido
     *
     * POST /feature-requests/{id}/seguir
     */
    public function seguir(Request $request, int $id): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você precisa estar logado para seguir',
                ], 401);
                return;
            }

            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            $dados = $request->all();
            $followerModel = new FeatureRequestFollower();
            $resultado = $followerModel->seguir([
                'feature_request_id' => $id,
                'chave' => Auth::chave(),
                'usuario_id' => $_SESSION['user_id'] ?? null,
                'email' => $email,
                'telefone' => $dados['telefone'] ?? ($_SESSION['user_phone'] ?? null),
                'notificar_email' => $dados['notificar_email'] ?? 1,
                'notificar_whatsapp' => $dados['notificar_whatsapp'] ?? 1,
            ]);

            if ($resultado === false) {
                Response::json([
                    'success' => false,
                    'message' => 'Você já segue este pedido',
                ], 400);
                return;
            }

            // Buscar novo total
            $pedidoAtualizado = $model->buscarPorId($id);

            Response::json([
                'success' => true,
                'message' => 'Você agora segue este pedido',
                'data' => [
                    'total_seguidores' => $pedidoAtualizado['total_seguidores'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao seguir pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deixa de seguir um pedido
     *
     * POST /feature-requests/{id}/deixar-de-seguir
     */
    public function deixarDeSeguir(Request $request, int $id): void
    {
        try {
            $email = $_SESSION['user_email'] ?? '';

            if (empty($email)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você precisa estar logado',
                ], 401);
                return;
            }

            $followerModel = new FeatureRequestFollower();
            $removeu = $followerModel->deixarDeSeguir($id, $email);

            if (!$removeu) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não estava seguindo este pedido',
                ], 400);
                return;
            }

            // Buscar novo total
            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            Response::json([
                'success' => true,
                'message' => 'Você não segue mais este pedido',
                'data' => [
                    'total_seguidores' => $pedido['total_seguidores'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deixar de seguir: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exclui um pedido
     *
     * POST /feature-requests/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new FeatureRequest();
            $pedido = $model->buscarPorId($id);

            if (!$pedido) {
                Response::json([
                    'success' => false,
                    'message' => 'Pedido não encontrado',
                ], 404);
                return;
            }

            // Verificar se é o autor ou admin 7Carros
            $chave = Auth::chave();
            $isAutor = $pedido['chave'] === $chave;
            $isAdmin = $this->isAdmin7Carros();

            if (!$isAutor && !$isAdmin) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode excluir este pedido',
                ], 403);
                return;
            }

            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu pedido de recurso [{$pedido['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Pedido excluído com sucesso',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifica se o usuário atual é admin 7Carros
     *
     * Utiliza a variável APP_ADMINISTRADORES do .env que contém
     * os nomes de usuário (login) dos administradores separados por vírgula.
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

        // Divide a lista e compara
        $listaAdmins = array_map('trim', explode(',', $administradores));

        return in_array($usuarioLogado, $listaAdmins);
    }

    /**
     * Envia notificações para seguidores
     *
     * @param int $featureRequestId ID do pedido
     * @param string $status Novo status
     * @param string|null $resposta Resposta do admin
     */
    private function enviarNotificacoes(
        int $featureRequestId,
        string $status,
        ?string $resposta = null,
        bool $notificarCriador = true,
        bool $notificarSeguidores = false
    ): void
    {
        $model = new FeatureRequest();
        $pedido = $model->buscarPorId($featureRequestId);

        if (!$pedido) {
            return;
        }

        // Preparar mensagens
        $statusLabel = FeatureRequest::STATUS_LABELS[$status] ?? $status;

        $assuntoEmail = $status === 'concluido'
            ? "Recurso Concluído: {$pedido['titulo']}"
            : "Atualização sobre seu pedido: {$pedido['titulo']}";

        $corpoEmailCriador = $this->gerarCorpoEmail($pedido, $status, $resposta, 'criou este pedido de recurso');
        $corpoEmailSeguidor = $this->gerarCorpoEmail($pedido, $status, $resposta, 'segue este pedido de recurso');
        $mensagemWhatsApp = $this->gerarMensagemWhatsApp($pedido, $status, $resposta);

        $chavePedido = $pedido['chave'] ?? null;

        // Notificacoes por email
        $emailsCriador = [];
        if ($notificarCriador) {
            $emailSolicitante = trim((string) ($pedido['email_solicitante'] ?? ''));
            if ($emailSolicitante !== '' && filter_var($emailSolicitante, FILTER_VALIDATE_EMAIL)) {
                $emailsCriador[strtolower($emailSolicitante)] = $emailSolicitante;
            }
        }

        $emailsSeguidores = [];
        if ($notificarSeguidores) {
            $followerModel = new FeatureRequestFollower();
            $seguidoresEmail = $followerModel->listarParaNotificacaoEmail($featureRequestId);
            foreach ($seguidoresEmail as $seguidor) {
                $email = trim((string) ($seguidor['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if (!isset($emailsCriador[strtolower($email)])) {
                    $emailsSeguidores[strtolower($email)] = $email;
                }
            }
        }

        foreach ($emailsCriador as $email) {
            try {
                queue_system_message('email', [
                    'to' => $email,
                    'subject' => $assuntoEmail,
                    'body' => $corpoEmailCriador,
                ], $chavePedido, true);
            } catch (\Throwable $e) {
                error_log("Erro ao enfileirar email de pedido de recurso {$featureRequestId} para {$email}: " . $e->getMessage());
            }
        }

        foreach ($emailsSeguidores as $email) {
            try {
                queue_system_message('email', [
                    'to' => $email,
                    'subject' => $assuntoEmail,
                    'body' => $corpoEmailSeguidor,
                ], $chavePedido, true);
            } catch (\Throwable $e) {
                error_log("Erro ao enfileirar email de pedido de recurso {$featureRequestId} para {$email}: " . $e->getMessage());
            }
        }

        // Notificacoes por WhatsApp
        $whatsappsNotificados = [];
        if ($notificarCriador) {
            $telefoneSolicitante = trim((string) ($pedido['telefone_solicitante'] ?? ''));
            if ($telefoneSolicitante !== '') {
                $whatsappsNotificados[preg_replace('/\D/', '', $telefoneSolicitante)] = $telefoneSolicitante;
            }
        }

        if ($notificarSeguidores) {
            $followerModel = $followerModel ?? new FeatureRequestFollower();
            $seguidoresWhatsApp = $followerModel->listarParaNotificacaoWhatsApp($featureRequestId);
            foreach ($seguidoresWhatsApp as $seguidor) {
                $telefone = trim((string) ($seguidor['telefone'] ?? ''));
                if ($telefone === '') {
                    continue;
                }

                $whatsappsNotificados[preg_replace('/\D/', '', $telefone)] = $telefone;
            }
        }

        foreach ($whatsappsNotificados as $telefone) {
            try {
                queue_system_message('whatsapp', [
                    'to' => $telefone,
                    'message' => $mensagemWhatsApp,
                ], $chavePedido, true);
            } catch (\Throwable $e) {
                error_log("Erro ao enfileirar WhatsApp de pedido de recurso {$featureRequestId} para {$telefone}: " . $e->getMessage());
            }
        }
    }

    /**
     * Gera corpo do email de notificação
     */
    private function gerarCorpoEmail(array $pedido, string $status, ?string $resposta, string $motivo): string
    {
        $statusLabel = FeatureRequest::STATUS_LABELS[$status] ?? $status;

        $html = "<h2>{$pedido['titulo']}</h2>";
        $html .= "<p><strong>Status:</strong> {$statusLabel}</p>";

        if ($status === 'concluido') {
            $html .= "<p>O recurso que você solicitou foi implementado!</p>";
        } elseif ($status === 'aguardando_info') {
            $html .= "<p>Precisamos de mais informações para entender melhor sua solicitação.</p>";
        }

        if ($resposta) {
            $html .= "<p><strong>Mensagem da equipe 7Carros:</strong></p>";
            $html .= "<blockquote>" . nl2br(htmlspecialchars($resposta)) . "</blockquote>";
        }

        $html .= "<hr>";
        $html .= "<p><em>Você está recebendo esta mensagem porque {$motivo}.</em></p>";

        return $html;
    }

    /**
     * Gera mensagem WhatsApp de notificação
     */
    private function gerarMensagemWhatsApp(array $pedido, string $status, ?string $resposta): string
    {
        $statusLabel = FeatureRequest::STATUS_LABELS[$status] ?? $status;

        $msg = "*7Carros - Atualização de Recurso*\n\n";
        $msg .= "*{$pedido['titulo']}*\n";
        $msg .= "Status: {$statusLabel}\n\n";

        if ($status === 'concluido') {
            $msg .= "O recurso que você solicitou foi implementado!\n";
        } elseif ($status === 'aguardando_info') {
            $msg .= "Precisamos de mais informações sobre sua solicitação.\n";
        }

        if ($resposta) {
            $msg .= "\n_Mensagem da equipe:_\n{$resposta}";
        }

        return $msg;
    }
}
