<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Whatsapp;
use App\Helpers\PlanoLimiteHelper;
use App\Services\AuditLogService;

/**
 * Controller de WhatsApp
 *
 * Gerencia conexoes WhatsApp atraves de um provedor externo (configurado via WHATSAPP_API_*).
 * Cada conexao mapeia para um "user" no provedor: o instanceName e usado simultaneamente
 * como nome (display) e como token de autenticacao das chamadas de sessao/mensagem;
 * o instanceId guarda o UUID que o provedor devolve na criacao.
 */
class WhatsappController
{
    private string $baseUrl;
    private string $adminToken;
    private array $proxyConfig;

    public function __construct()
    {
        $this->baseUrl = Database::env('WHATSAPP_API_URL', '');
        $this->adminToken = Database::env('WHATSAPP_API_ADMIN_TOKEN', '');

        // Configuracao do proxy
        $this->proxyConfig = [
            'protocol' => Database::env('WHATSAPP_API_PROXY_PROTOCOL', 'http'),
            'host' => Database::env('WHATSAPP_API_PROXY_HOST', ''),
            'port' => Database::env('WHATSAPP_API_PROXY_PORT', ''),
            'username' => Database::env('WHATSAPP_API_PROXY_USERNAME', ''),
            'password' => Database::env('WHATSAPP_API_PROXY_PASSWORD', ''),
        ];
    }

    /**
     * Renderiza a pagina de Mensageria (WhatsApp, SMS e SMTP)
     *
     * GET /pages/mensageria
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.mensageria.index');
        Response::html($html);
    }

    // ===== Formularios WhatsApp (offcanvas iframe) =====

    public function viewWhatsappAdicionar(Request $request): void
    {
        $html = Template::render('pages.mensageria.whatsapp.adicionar');
        Response::html($html);
    }

    public function viewWhatsappEditar(Request $request): void
    {
        $html = Template::render('pages.mensageria.whatsapp.editar');
        Response::html($html);
    }

    public function viewWhatsappTestar(Request $request): void
    {
        $html = Template::render('pages.mensageria.whatsapp.testar');
        Response::html($html);
    }

    public function viewWhatsappQrcode(Request $request): void
    {
        $html = Template::render('pages.mensageria.whatsapp.qrcode');
        Response::html($html);
    }

    // ===== Formularios SMS (offcanvas iframe) =====

    public function viewSmsAdicionar(Request $request): void
    {
        $html = Template::render('pages.mensageria.sms.adicionar');
        Response::html($html);
    }

    public function viewSmsEditar(Request $request): void
    {
        $html = Template::render('pages.mensageria.sms.editar');
        Response::html($html);
    }

    public function viewSmsTestar(Request $request): void
    {
        $html = Template::render('pages.mensageria.sms.testar');
        Response::html($html);
    }

    // ===== Formularios SMTP (offcanvas iframe) =====

    public function viewSmtpAdicionar(Request $request): void
    {
        $html = Template::render('pages.mensageria.smtp.adicionar');
        Response::html($html);
    }

    public function viewSmtpEditar(Request $request): void
    {
        $html = Template::render('pages.mensageria.smtp.editar');
        Response::html($html);
    }

    public function viewSmtpTestar(Request $request): void
    {
        $html = Template::render('pages.mensageria.smtp.testar');
        Response::html($html);
    }

    /**
     * Lista todas as conexoes do tenant
     *
     * GET /api/whatsapp
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new Whatsapp();

            $conexoes = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $conexoes,
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
                'message' => 'Erro ao buscar conexoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma conexao especifica
     *
     * GET /api/whatsapp/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Incluir filiais vinculadas
            $conexao['filiais'] = $model->getFiliais($id);

            Response::json([
                'success' => true,
                'data' => $conexao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar conexao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista IDs das filiais ja vinculadas a alguma conexao WhatsApp
     *
     * GET /api/whatsapp/filiais-ocupadas
     */
    public function filiaisOcupadas(Request $request): void
    {
        try {
            $model = new Whatsapp();
            $ocupadas = $model->getFiliaisOcupadas();

            Response::json([
                'success' => true,
                'data' => $ocupadas
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar filiais ocupadas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova conexao WhatsApp
     *
     * POST /whatsapp/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar limite do plano
            if (!PlanoLimiteHelper::podeAdicionar('whatsapp')) {
                $usage = PlanoLimiteHelper::getUsage('whatsapp');
                Response::json([
                    'success' => false,
                    'message' => "Limite de conexões WhatsApp atingido. Seu plano {$usage['plano']} permite apenas {$usage['limite']} conexões WhatsApp.",
                    'limite_atingido' => true,
                    'redirect_url' => PlanoLimiteHelper::getRedirectSeAtingido('whatsapp')
                ], 403);
                return;
            }

            $dados = $request->all();
            $chave = Auth::chave();

            // Receber array de filiais selecionadas
            $filialIds = $dados['filiais_ids'] ?? [];
            if (is_string($filialIds)) {
                $filialIds = json_decode($filialIds, true) ?? [];
            }

            // Validar que pelo menos uma filial foi selecionada
            if (empty($filialIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma empresa/filial'
                ], 400);
                return;
            }

            // Gerar instanceName automaticamente
            $model = new Whatsapp();
            $instanceName = Whatsapp::gerarInstanceName();

            // Garantir unicidade (caso raro de colisao)
            $tentativas = 0;
            while ($model->instanceNameExiste($instanceName) && $tentativas < 5) {
                $instanceName = Whatsapp::gerarInstanceName();
                $tentativas++;
            }

            if ($model->instanceNameExiste($instanceName)) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao gerar nome unico para a instancia. Tente novamente.'
                ], 500);
                return;
            }

            // Criar instancia na API do provedor de WhatsApp
            $apiResponse = $this->createInstance($instanceName);

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao criar instancia: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            // Configurar proxy se definido (aguardar instancia ficar pronta)
            if (!empty($this->proxyConfig['host'])) {
                if ($this->waitForInstance($instanceName)) {
                    $this->setProxy($instanceName);
                }
            }

            // Salvar no banco
            $id = $model->criar([
                'chave' => $chave,
                'instanceName' => $instanceName,
                'instanceId' => $apiResponse['data']['instance']['instanceId'] ?? '',
                'status' => 'connecting',
            ]);

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", criou conexao WhatsApp [{$instanceName}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao criada com sucesso',
                'data' => ['id' => $id, 'instanceName' => $instanceName]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar conexao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza filiais de uma conexao
     *
     * POST /whatsapp/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta conexao'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Receber array de filiais selecionadas
            $filialIds = $dados['filiais_ids'] ?? [];
            if (is_string($filialIds)) {
                $filialIds = json_decode($filialIds, true) ?? [];
            }

            // Validar que pelo menos uma filial foi selecionada
            if (empty($filialIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma empresa/filial'
                ], 400);
                return;
            }

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou vinculos da conexao WhatsApp [{$conexao['instanceName']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar conexao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma conexao WhatsApp
     *
     * POST /whatsapp/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta conexao'
                ], 403);
                return;
            }

            // Excluir instancia na API do provedor de WhatsApp de forma segura
            // (logout primeiro, depois delete)
            $this->safeDeleteInstance($conexao['instanceName']);

            // Excluir do banco
            $model->excluir($id);

            // Log de auditoria
            $numeroFormatado = !empty($conexao['remoteJid'])
                ? $this->formatarTelefone($conexao['remoteJid'])
                : 'sem numero';

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu conexao WhatsApp [{$numeroFormatado}] (instancia: {$conexao['instanceName']})"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir conexao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inicia conexao e retorna QR Code
     *
     * POST /whatsapp/{id}/connect
     */
    public function connect(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Gerar QR Code via API do provedor de WhatsApp
            $apiResponse = $this->getQRCode($conexao['instanceName']);

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao gerar QR Code: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            // Atualizar status para connecting
            $model->atualizarStatus($id, 'connecting');

            Response::json([
                'success' => true,
                'data' => [
                    'qrcode' => $apiResponse['data']['base64'] ?? $apiResponse['data']['qrcode']['base64'] ?? null,
                    'pairingCode' => $apiResponse['data']['pairingCode'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica status da conexao
     *
     * GET /api/whatsapp/{id}/status
     */
    public function status(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar status via API do provedor de WhatsApp
            $apiResponse = $this->getConnectionState($conexao['instanceName']);

            $state = $apiResponse['data']['state'] ?? 'close';
            $remoteJid = null;

            // Mapear status da API para nosso status
            $statusMap = [
                'open' => 'connected',
                'connecting' => 'connecting',
                'close' => 'disconnected',
                'closed' => 'disconnected',
            ];

            $newStatus = $statusMap[$state] ?? 'disconnected';

            // JID ja vem no proprio status quando conectado
            if ($newStatus === 'connected') {
                $remoteJid = $apiResponse['data']['owner'] ?: null;
            }

            // Atualizar status no banco se mudou
            if ($conexao['status'] !== $newStatus || ($remoteJid && $conexao['remoteJid'] !== $remoteJid)) {
                $model->atualizarStatus($id, $newStatus, $remoteJid);
            }

            Response::json([
                'success' => true,
                'data' => [
                    'status' => $newStatus,
                    'remoteJid' => $remoteJid ?? $conexao['remoteJid'],
                    'state' => $state,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao verificar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desconecta a conexao WhatsApp
     *
     * POST /whatsapp/{id}/disconnect
     */
    public function disconnect(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Desconectar via API do provedor de WhatsApp
            $apiResponse = $this->logoutInstance($conexao['instanceName']);

            // Atualizar status no banco
            $model->atualizarStatus($id, 'disconnected');

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", desconectou WhatsApp [{$conexao['instanceName']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Desconectado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao desconectar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reinicia uma conexao existente
     *
     * POST /whatsapp/{id}/restart
     */
    public function restart(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Reiniciar via API do provedor de WhatsApp
            $apiResponse = $this->restartInstance($conexao['instanceName']);

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao reiniciar: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            // Atualizar status para connecting
            $model->atualizarStatus($id, 'connecting');

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", reiniciou conexao WhatsApp [{$conexao['instanceName']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao reiniciada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao reiniciar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recria uma instancia que foi excluida da API
     *
     * POST /whatsapp/{id}/recreate
     */
    public function recreate(Request $request, int $id): void
    {
        try {
            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar se instancia existe na API
            $fetchResponse = $this->fetchInstance($conexao['instanceName']);

            // Se instancia NÃO existe, criar
            if (!$fetchResponse['success'] || empty($fetchResponse['data'])) {
                // Criar nova instancia na API do provedor de WhatsApp com o mesmo nome
                $apiResponse = $this->createInstance($conexao['instanceName']);

                if (!$apiResponse['success']) {
                    Response::json([
                        'success' => false,
                        'message' => 'Erro ao recriar instancia: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                    ], 500);
                    return;
                }

                // Configurar proxy se definido (aguardar instancia ficar pronta)
                if (!empty($this->proxyConfig['host'])) {
                    if ($this->waitForInstance($conexao['instanceName'])) {
                        $this->setProxy($conexao['instanceName']);
                    }
                }

                // Atualizar instanceId e status para connecting
                $newInstanceId = $apiResponse['data']['instance']['instanceId'] ?? '';
                $model->atualizar($id, [
                    'instanceId' => $newInstanceId,
                    'status' => 'connecting',
                ]);
            } else {
                // Instancia ja existe na API, apenas atualizar status para connecting
                $model->atualizarStatus($id, 'connecting');
            }

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", recriou instancia WhatsApp [{$conexao['instanceName']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Instancia recriada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao recriar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia mensagem de texto de teste
     *
     * POST /whatsapp/test/text
     */
    public function testText(Request $request): void
    {
        try {
            $dados = $request->all();
            $id = (int) ($dados['id'] ?? 0);

            if (!$id) {
                Response::json([
                    'success' => false,
                    'message' => 'ID da conexao e obrigatorio'
                ], 400);
                return;
            }

            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao || strcasecmp($conexao['status'], 'connected') !== 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada ou nao conectada'
                ], 400);
                return;
            }

            // Numero de teste
            $telefone = Database::env('APP_COMPANY_TELEFONE_WHATSAPP', '');
            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Telefone de teste nao configurado (APP_COMPANY_TELEFONE_WHATSAPP)'
                ], 400);
                return;
            }

            // Enviar mensagem
            $apiResponse = $this->sendText($conexao['instanceName'], $telefone, "*[7Carros]*\nTeste de conexao WhatsApp");

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao enviar mensagem: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Mensagem de texto enviada com sucesso para ' . $this->formatarTelefone($telefone)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar teste: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia imagem de teste
     *
     * POST /whatsapp/test/image
     */
    public function testImage(Request $request): void
    {
        try {
            $dados = $request->all();
            $id = (int) ($dados['id'] ?? 0);

            if (!$id) {
                Response::json([
                    'success' => false,
                    'message' => 'ID da conexao e obrigatorio'
                ], 400);
                return;
            }

            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao || strcasecmp($conexao['status'], 'connected') !== 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada ou nao conectada'
                ], 400);
                return;
            }

            // Numero de teste
            $telefone = Database::env('APP_COMPANY_TELEFONE_WHATSAPP', '');
            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Telefone de teste nao configurado'
                ], 400);
                return;
            }

            // Caminho da imagem de teste
            $imagePath = dirname(__DIR__, 2) . '/public/assets/img/whatsapp.teste.png';
            if (!file_exists($imagePath)) {
                Response::json([
                    'success' => false,
                    'message' => 'Imagem de teste nao encontrada'
                ], 400);
                return;
            }

            // Enviar imagem via base64
            $apiResponse = $this->sendMedia($conexao['instanceName'], $telefone, $imagePath, 'image', "*[7Carros]*\nTeste de imagem");

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao enviar imagem: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Imagem enviada com sucesso para ' . $this->formatarTelefone($telefone)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar teste: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia documento de teste
     *
     * POST /whatsapp/test/document
     */
    public function testDocument(Request $request): void
    {
        try {
            $dados = $request->all();
            $id = (int) ($dados['id'] ?? 0);

            if (!$id) {
                Response::json([
                    'success' => false,
                    'message' => 'ID da conexao e obrigatorio'
                ], 400);
                return;
            }

            $model = new Whatsapp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao || strcasecmp($conexao['status'], 'connected') !== 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao nao encontrada ou nao conectada'
                ], 400);
                return;
            }

            // Numero de teste
            $telefone = Database::env('APP_COMPANY_TELEFONE_WHATSAPP', '');
            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Telefone de teste nao configurado'
                ], 400);
                return;
            }

            // Caminho do documento de teste
            $docPath = dirname(__DIR__, 2) . '/public/assets/img/whatsapp.teste.pdf';
            if (!file_exists($docPath)) {
                Response::json([
                    'success' => false,
                    'message' => 'Documento de teste nao encontrado'
                ], 400);
                return;
            }

            // Enviar documento via base64
            // Wuzapi nao suporta caption em /chat/send/document; mandamos a assinatura/descricao em mensagem separada antes do arquivo.
            $this->sendText($conexao['instanceName'], $telefone, "*[7Carros]*\nTeste de documento");
            $apiResponse = $this->sendMedia($conexao['instanceName'], $telefone, $docPath, 'document', '');

            if (!$apiResponse['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao enviar documento: ' . ($apiResponse['message'] ?? 'Erro desconhecido')
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Documento enviado com sucesso para ' . $this->formatarTelefone($telefone)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar teste: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== WHATSAPP API METHODS ===============
    //
    // Os helpers abaixo retornam dados normalizados para que as funcoes
    // publicas continuem usando o mesmo formato esperado:
    //   - createInstance: data.instance.instanceId (UUID do user no provedor)
    //   - getQRCode:      data.base64 (data URI da imagem do QR)
    //   - getConnectionState: data.state ('open'|'connecting'|'close')
    //   - fetchInstance:  data.instance.owner (jid quando logado)
    //
    // Convencao de auth: helpers que tocam /admin/* usam admin token; os demais
    // (sessao/mensagem) usam o instanceName como header `token` do provedor.

    /**
     * Cria nova instancia (user) no provedor.
     */
    private function createInstance(string $instanceName): array
    {
        $url = rtrim($this->baseUrl, '/') . '/admin/users';
        $data = [
            'name' => $instanceName,
            'token' => $instanceName, // mesmo valor: usado como header `token` nas chamadas seguintes
            'events' => 'All',
        ];

        $response = $this->makeRequest($url, 'POST', $data, 'admin');

        if ($response['success']) {
            $payload = $response['data']['data'] ?? $response['data'] ?? [];
            $userId = $payload['id'] ?? '';
            $response['data'] = ['instance' => ['instanceId' => $userId]];
        }

        return $response;
    }

    /**
     * Configura proxy de saida para a instancia.
     */
    private function setProxy(string $instanceName): array
    {
        $proxyUrl = $this->buildProxyUrl();
        if ($proxyUrl === null) {
            return ['success' => true, 'data' => null, 'message' => 'Proxy nao configurado'];
        }

        $url = rtrim($this->baseUrl, '/') . '/session/proxy';
        $data = [
            'proxy_url' => $proxyUrl,
            'enable' => true,
        ];

        return $this->makeRequest($url, 'POST', $data, 'user', $instanceName);
    }

    /**
     * Monta a URL completa do proxy a partir das vars do .env, ou null se nao configurado.
     */
    private function buildProxyUrl(): ?string
    {
        if (empty($this->proxyConfig['host'])) {
            return null;
        }

        $protocol = $this->proxyConfig['protocol'] ?: 'http';
        $host = $this->proxyConfig['host'];
        $port = $this->proxyConfig['port'];
        $user = $this->proxyConfig['username'] ?? '';
        $pass = $this->proxyConfig['password'] ?? '';

        $auth = '';
        if ($user !== '') {
            $auth = rawurlencode($user);
            if ($pass !== '') {
                $auth .= ':' . rawurlencode($pass);
            }
            $auth .= '@';
        }

        $hostPort = $host . ($port !== '' ? ':' . $port : '');
        return $protocol . '://' . $auth . $hostPort;
    }

    /**
     * Aguarda a instancia estar disponivel apos criacao.
     */
    private function waitForInstance(string $instanceName, int $maxAttempts = 10, int $waitMs = 1000): bool
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->fetchInstance($instanceName);
            if ($response['success'] && !empty($response['data'])) {
                return true;
            }
            usleep($waitMs * 1000);
        }
        return false;
    }

    /**
     * Conecta a sessao e retorna o QR code para escaneamento.
     */
    private function getQRCode(string $instanceName): array
    {
        $connectUrl = rtrim($this->baseUrl, '/') . '/session/connect';
        $connectResp = $this->makeRequest(
            $connectUrl,
            'POST',
            ['Subscribe' => ['Message'], 'Immediate' => true],
            'user',
            $instanceName
        );

        if (!$connectResp['success']) {
            return $connectResp;
        }

        // O QR pode levar 1-2 ciclos para ser gerado, fazemos algumas tentativas curtas
        $qrUrl = rtrim($this->baseUrl, '/') . '/session/qr';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            usleep(500 * 1000);
            $qrResp = $this->makeRequest($qrUrl, 'GET', [], 'user', $instanceName);
            if ($qrResp['success']) {
                $payload = $qrResp['data']['data'] ?? $qrResp['data'] ?? [];
                $base64 = $payload['QRCode'] ?? '';
                if ($base64 !== '') {
                    return [
                        'success' => true,
                        'data' => ['base64' => $base64],
                    ];
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Nao foi possivel obter o QR code',
            'data' => null,
        ];
    }

    /**
     * Verifica estado da conexao.
     *
     * O provedor pode retornar campos em camelCase ou PascalCase, aceitamos ambos.
     * Tambem expomos o `jid` (owner) quando logado, para evitar uma chamada extra.
     */
    private function getConnectionState(string $instanceName): array
    {
        $url = rtrim($this->baseUrl, '/') . '/session/status';
        $response = $this->makeRequest($url, 'GET', [], 'user', $instanceName);

        if ($response['success']) {
            $data = $response['data']['data'] ?? $response['data'] ?? [];
            $loggedIn = !empty($data['LoggedIn']) || !empty($data['loggedIn']);
            $connected = !empty($data['Connected']) || !empty($data['connected']);
            $state = $loggedIn ? 'open' : ($connected ? 'connecting' : 'close');
            $owner = $data['Jid'] ?? $data['jid'] ?? '';
            $response['data'] = ['state' => $state, 'owner' => $owner];
        }

        return $response;
    }

    /**
     * Busca dados da instancia (existencia + jid).
     *
     * Resolve instanceId via Model: o caller passa instanceName (token) mas o
     * endpoint admin precisa do UUID retornado na criacao.
     */
    private function fetchInstance(string $instanceName): array
    {
        $model = new Whatsapp();
        $conexao = $model->buscarPorInstanceName($instanceName);
        $instanceId = $conexao['instanceId'] ?? '';

        if ($instanceId === '') {
            return ['success' => false, 'message' => 'instanceId nao definido', 'data' => null];
        }

        $url = rtrim($this->baseUrl, '/') . '/admin/users/' . urlencode($instanceId);
        $response = $this->makeRequest($url, 'GET', [], 'admin');

        if ($response['success']) {
            $payload = $response['data']['data'] ?? $response['data'] ?? [];
            // Pode vir como objeto (single) ou array (lista). Normalizar para objeto unico.
            if (is_array($payload) && isset($payload[0])) {
                $payload = $payload[0];
            }

            if (empty($payload)) {
                return ['success' => false, 'message' => 'Instancia nao encontrada', 'data' => null];
            }

            $jid = $payload['jid'] ?? '';
            $response['data'] = ['instance' => ['owner' => $jid]];
        }

        return $response;
    }

    /**
     * Desconecta a sessao (logout).
     */
    private function logoutInstance(string $instanceName): array
    {
        $url = rtrim($this->baseUrl, '/') . '/session/logout';
        return $this->makeRequest($url, 'POST', [], 'user', $instanceName);
    }

    /**
     * Reinicia a sessao: disconnect + connect.
     */
    private function restartInstance(string $instanceName): array
    {
        $disconnectUrl = rtrim($this->baseUrl, '/') . '/session/disconnect';
        $this->makeRequest($disconnectUrl, 'POST', [], 'user', $instanceName);
        usleep(500 * 1000);

        $connectUrl = rtrim($this->baseUrl, '/') . '/session/connect';
        return $this->makeRequest(
            $connectUrl,
            'POST',
            ['Subscribe' => ['Message'], 'Immediate' => true],
            'user',
            $instanceName
        );
    }

    /**
     * Exclui o user no provedor.
     */
    private function deleteInstance(string $instanceName): array
    {
        $model = new Whatsapp();
        $conexao = $model->buscarPorInstanceName($instanceName);
        $instanceId = $conexao['instanceId'] ?? '';

        if ($instanceId === '') {
            return ['success' => true, 'message' => 'instanceId vazio, nada a excluir', 'data' => null];
        }

        $url = rtrim($this->baseUrl, '/') . '/admin/users/' . urlencode($instanceId);
        return $this->makeRequest($url, 'DELETE', [], 'admin');
    }

    /**
     * Logout + delete em sequencia.
     */
    private function safeDeleteInstance(string $instanceName, int $maxAttempts = 5, int $waitMs = 1000): array
    {
        $steps = [];

        // Passo 1: Verificar estado atual
        $stateResponse = $this->getConnectionState($instanceName);
        $currentState = $stateResponse['data']['state'] ?? 'unknown';
        $steps[] = ['action' => 'check_state', 'state' => $currentState];

        // Passo 2: Se conectado/connecting, logout
        if (in_array($currentState, ['open', 'connecting'], true)) {
            $logoutResponse = $this->logoutInstance($instanceName);
            $steps[] = ['action' => 'logout', 'success' => $logoutResponse['success']];

            if (!$logoutResponse['success']) {
                $steps[] = ['action' => 'logout_failed', 'message' => $logoutResponse['message'] ?? 'Erro desconhecido'];
            } else {
                // Passo 3: Aguardar logout
                $disconnected = false;
                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    usleep($waitMs * 1000);
                    $checkResponse = $this->getConnectionState($instanceName);
                    $newState = $checkResponse['data']['state'] ?? 'unknown';
                    $steps[] = ['action' => 'verify_logout', 'attempt' => $attempt, 'state' => $newState];

                    if (in_array($newState, ['close', 'closed', 'unknown'], true)) {
                        $disconnected = true;
                        break;
                    }
                }

                if (!$disconnected) {
                    $steps[] = ['action' => 'logout_timeout', 'message' => 'Timeout aguardando desconexao'];
                }
            }
        }

        // Passo 4: Excluir o user no provedor
        $deleteResponse = $this->deleteInstance($instanceName);
        $steps[] = ['action' => 'delete', 'success' => $deleteResponse['success']];

        if (!$deleteResponse['success']) {
            return [
                'success' => false,
                'message' => 'Erro ao excluir instancia: ' . ($deleteResponse['message'] ?? 'Erro desconhecido'),
                'steps' => $steps,
            ];
        }

        return [
            'success' => true,
            'message' => 'Instancia excluida com sucesso',
            'steps' => $steps,
        ];
    }

    /**
     * Envia mensagem de texto.
     */
    private function sendText(string $instanceName, string $telefone, string $message): array
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/send/text';
        $data = [
            'Phone' => $this->formatarTelefoneAPI($telefone),
            'Body' => $message,
        ];
        return $this->makeRequest($url, 'POST', $data, 'user', $instanceName);
    }

    /**
     * Envia midia (imagem ou documento) — provedor exige base64 (data URI).
     */
    private function sendMedia(string $instanceName, string $telefone, string $filePath, string $mediaType, string $caption = ''): array
    {
        $fileContent = file_get_contents($filePath);
        $base64 = base64_encode($fileContent);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileName = basename($filePath);
        $dataUri = "data:{$mimeType};base64,{$base64}";

        $isImage = ($mediaType === 'image') || str_starts_with($mimeType, 'image/');

        if ($isImage) {
            $url = rtrim($this->baseUrl, '/') . '/chat/send/image';
            $data = [
                'Phone' => $this->formatarTelefoneAPI($telefone),
                'Image' => $dataUri,
            ];
            if ($caption !== '') {
                $data['Caption'] = $caption;
            }
        } else {
            $url = rtrim($this->baseUrl, '/') . '/chat/send/document';
            $data = [
                'Phone' => $this->formatarTelefoneAPI($telefone),
                'Document' => $dataUri,
                'FileName' => $fileName,
            ];
        }

        return $this->makeRequest($url, 'POST', $data, 'user', $instanceName);
    }

    /**
     * Faz requisicao HTTP.
     *
     * @param string $url URL completa
     * @param string $method 'GET'|'POST'|'PUT'|'DELETE'
     * @param array $data Body (para POST/PUT)
     * @param string $auth 'admin' (header Authorization) ou 'user' (header token)
     * @param string $instanceToken Token de instancia (obrigatorio se auth='user')
     */
    private function makeRequest(string $url, string $method = 'GET', array $data = [], string $auth = 'admin', string $instanceToken = ''): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Content-Type: application/json'];
        if ($auth === 'user') {
            $headers[] = 'token: ' . $instanceToken;
        } else {
            $headers[] = 'Authorization: ' . $this->adminToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "Erro cURL: {$error}",
                'data' => null,
            ];
        }

        $response = json_decode($body, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $response,
            ];
        }

        return [
            'success' => false,
            'message' => $response['message'] ?? $response['error'] ?? "HTTP {$httpCode}",
            'data' => $response,
        ];
    }

    /**
     * Formata telefone para API (com codigo do pais)
     */
    private function formatarTelefoneAPI(string $telefone): string
    {
        // Remove caracteres nao numericos
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        // Adiciona codigo do pais (55 = Brasil) se nao tiver
        if (strlen($telefone) === 11 || strlen($telefone) === 10) {
            $telefone = '55' . $telefone;
        }

        return $telefone;
    }

    /**
     * Formata telefone para exibicao
     */
    private function formatarTelefone(string $telefone): string
    {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);

        // Remove codigo do pais se tiver
        if (strlen($telefone) === 13 && str_starts_with($telefone, '55')) {
            $telefone = substr($telefone, 2);
        }

        // Formatar (XX) XXXXX-XXXX
        if (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
        }

        return $telefone;
    }
}
