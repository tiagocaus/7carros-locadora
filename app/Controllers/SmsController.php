<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sms;
use App\Helpers\PlanoLimiteHelper;
use App\Services\AuditLogService;
use App\Services\Sms\SmsProviderFactory;

/**
 * Controller de SMS
 *
 * Gerencia conexoes SMS via provedores externos (ClickSend, etc).
 */
class SmsController
{
    /**
     * Lista todas as conexoes SMS do tenant
     *
     * GET /api/sms
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new Sms();

            $conexoes = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Remover api_key dos dados retornados
            $conexoes = array_map(function ($c) {
                unset($c['api_key']);
                return $c;
            }, $conexoes);

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
                'message' => 'Erro ao buscar conexoes SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma conexao especifica
     *
     * GET /api/sms/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
                ], 404);
                return;
            }

            // Remover api_key da resposta
            unset($conexao['api_key']);

            // Incluir filiais vinculadas
            $conexao['filiais'] = $model->getFiliais($id);

            Response::json([
                'success' => true,
                'data' => $conexao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar conexao SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista IDs das filiais ja vinculadas a alguma conexao SMS
     *
     * GET /api/sms/filiais-ocupadas
     */
    public function filiaisOcupadas(Request $request): void
    {
        try {
            $model = new Sms();
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
     * Cria uma nova conexao SMS
     *
     * POST /sms/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar limite do plano
            if (!PlanoLimiteHelper::podeAdicionar('sms')) {
                $usage = PlanoLimiteHelper::getUsage('sms');
                Response::json([
                    'success' => false,
                    'message' => "Limite de conexões SMS atingido. Seu plano {$usage['plano']} permite apenas {$usage['limite']} conexões SMS.",
                    'limite_atingido' => true,
                    'redirect_url' => PlanoLimiteHelper::getRedirectSeAtingido('sms')
                ], 403);
                return;
            }

            $dados = $request->all();
            $chave = Auth::chave();

            // Validar campos obrigatorios
            $provider = trim($dados['provider'] ?? 'clicksend');
            $senderId = trim($dados['sender_id'] ?? '');
            $username = trim($dados['username'] ?? '');
            $apiKey = trim($dados['api_key'] ?? '');

            if (empty($senderId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Sender ID (Remetente) e obrigatorio'
                ], 400);
                return;
            }

            // Validar Sender ID (max 11 chars alfanumericos)
            if (!preg_match('/^[a-zA-Z0-9]{1,11}$/', $senderId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Sender ID deve ter no maximo 11 caracteres alfanumericos'
                ], 400);
                return;
            }

            if (empty($username)) {
                Response::json([
                    'success' => false,
                    'message' => 'Username e obrigatorio'
                ], 400);
                return;
            }

            if (strlen($username) < 3) {
                Response::json([
                    'success' => false,
                    'message' => 'Username deve ter pelo menos 3 caracteres'
                ], 400);
                return;
            }

            if (empty($apiKey)) {
                Response::json([
                    'success' => false,
                    'message' => 'API Key e obrigatoria'
                ], 400);
                return;
            }

            if (strlen($apiKey) < 20) {
                Response::json([
                    'success' => false,
                    'message' => 'API Key deve ter no minimo 20 caracteres'
                ], 400);
                return;
            }

            // Receber array de filiais selecionadas
            $filialIds = $dados['filiais_ids'] ?? [];
            if (is_string($filialIds)) {
                $filialIds = json_decode($filialIds, true) ?? [];
            }

            if (empty($filialIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma empresa/filial'
                ], 400);
                return;
            }

            // Verificar se Sender ID ja existe no tenant
            $model = new Sms();
            if ($model->senderIdExiste($senderId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Ja existe uma conexao SMS com este Sender ID'
                ], 400);
                return;
            }

            // Validar credenciais com o provedor
            $validationStatus = 'pending';
            $validationError = null;

            try {
                $providerInstance = SmsProviderFactory::create($provider, $username, $apiKey);
                $validationResult = $providerInstance->validateCredentials();

                if ($validationResult['success']) {
                    $validationStatus = 'validated';
                } else {
                    $validationStatus = 'invalid';
                    $validationError = $validationResult['message'] ?? 'Credenciais invalidas';
                }
            } catch (\Exception $e) {
                $validationStatus = 'invalid';
                $validationError = $e->getMessage();
            }

            // Criptografar API Key
            $encryptedApiKey = encrypt($apiKey);

            // Salvar no banco
            $id = $model->criar([
                'chave' => $chave,
                'provider' => $provider,
                'sender_id' => strtoupper($senderId),
                'username' => $username,
                'api_key' => $encryptedApiKey,
                'status' => $validationStatus,
                'validated_at' => $validationStatus === 'validated' ? now() : null,
                'last_error' => $validationError,
            ]);

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", criou conexao SMS [{$senderId}] (status: {$validationStatus})"
            );

            $responseMessage = $validationStatus === 'validated'
                ? 'Conexao SMS criada e validada com sucesso'
                : 'Conexao SMS criada, mas validacao falhou: ' . $validationError;

            Response::json([
                'success' => true,
                'message' => $responseMessage,
                'data' => [
                    'id' => $id,
                    'status' => $validationStatus,
                    'error' => $validationError
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar conexao SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma conexao SMS
     *
     * POST /sms/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
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

            if (empty($filialIds)) {
                Response::json([
                    'success' => false,
                    'message' => 'Selecione pelo menos uma empresa/filial'
                ], 400);
                return;
            }

            // Atualizar dados se fornecidos
            $dadosUpdate = [];

            if (!empty($dados['sender_id'])) {
                $senderId = trim($dados['sender_id']);
                if (!preg_match('/^[a-zA-Z0-9]{1,11}$/', $senderId)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Sender ID deve ter no maximo 11 caracteres alfanumericos'
                    ], 400);
                    return;
                }
                if ($model->senderIdExiste($senderId, $id)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Ja existe uma conexao SMS com este Sender ID'
                    ], 400);
                    return;
                }
                $dadosUpdate['sender_id'] = strtoupper($senderId);
            }

            if (!empty($dados['username'])) {
                $username = trim($dados['username']);
                if (strlen($username) < 3) {
                    Response::json([
                        'success' => false,
                        'message' => 'Username deve ter pelo menos 3 caracteres'
                    ], 400);
                    return;
                }
                $dadosUpdate['username'] = $username;
            }

            if (!empty($dados['api_key'])) {
                $apiKey = trim($dados['api_key']);
                if (strlen($apiKey) < 20) {
                    Response::json([
                        'success' => false,
                        'message' => 'API Key deve ter no minimo 20 caracteres'
                    ], 400);
                    return;
                }
                $dadosUpdate['api_key'] = encrypt($apiKey);
                $dadosUpdate['status'] = 'pending'; // Revalidar ao alterar credenciais
            }

            // Atualizar dados da conexao
            if (!empty($dadosUpdate)) {
                $model->atualizar($id, $dadosUpdate);
            }

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou conexao SMS [{$conexao['sender_id']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao SMS atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar conexao SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma conexao SMS
     *
     * POST /sms/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
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

            // Excluir do banco
            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu conexao SMS [{$conexao['sender_id']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao SMS excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir conexao SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida credenciais de uma conexao
     *
     * POST /sms/{id}/validate
     */
    public function validate(Request $request, int $id): void
    {
        try {
            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
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

            // Descriptografar API Key
            $apiKey = decrypt($conexao['api_key']);

            // Validar credenciais
            $provider = SmsProviderFactory::create(
                $conexao['provider'],
                $conexao['username'],
                $apiKey
            );

            $result = $provider->validateCredentials();

            if ($result['success']) {
                $model->atualizarStatus($id, 'validated');

                Response::json([
                    'success' => true,
                    'message' => 'Credenciais validadas com sucesso',
                    'data' => [
                        'status' => 'validated',
                        'balance' => $result['balance'] ?? null
                    ]
                ]);
            } else {
                $model->atualizarStatus($id, 'invalid', $result['message']);

                Response::json([
                    'success' => false,
                    'message' => 'Validacao falhou: ' . ($result['message'] ?? 'Credenciais invalidas'),
                    'data' => [
                        'status' => 'invalid'
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao validar credenciais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consulta saldo da conta
     *
     * GET /api/sms/{id}/balance
     */
    public function balance(Request $request, int $id): void
    {
        try {
            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
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

            // Descriptografar API Key
            $apiKey = decrypt($conexao['api_key']);

            // Consultar saldo
            $provider = SmsProviderFactory::create(
                $conexao['provider'],
                $conexao['username'],
                $apiKey
            );

            $result = $provider->getBalance();

            if ($result['success']) {
                Response::json([
                    'success' => true,
                    'data' => [
                        'balance' => $result['balance'],
                        'currency' => $result['currency']
                    ]
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao consultar saldo: ' . ($result['message'] ?? 'Erro desconhecido')
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao consultar saldo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia SMS de teste
     *
     * POST /sms/test
     */
    public function testSend(Request $request): void
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

            $model = new Sms();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao encontrada'
                ], 400);
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

            if ($conexao['status'] !== 'validated') {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMS nao esta validada. Valide as credenciais primeiro.'
                ], 400);
                return;
            }

            // Numero de teste
            $telefone = $dados['telefone'] ?? Database::env('SMS_TEST_PHONE', Database::env('APP_COMPANY_TELEFONE_WHATSAPP', ''));
            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Telefone de teste nao informado'
                ], 400);
                return;
            }

            // Descriptografar API Key
            $apiKey = decrypt($conexao['api_key']);

            // Criar provedor e enviar
            $provider = SmsProviderFactory::create(
                $conexao['provider'],
                $conexao['username'],
                $apiKey
            );

            $message = 'Teste de SMS - 7Carros. Conexao: ' . $conexao['sender_id'];
            $result = $provider->send($telefone, $message, $conexao['sender_id']);

            if ($result['success']) {
                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", enviou SMS de teste via [{$conexao['sender_id']}] para {$telefone}"
                );

                Response::json([
                    'success' => true,
                    'message' => 'SMS de teste enviado com sucesso para ' . $this->formatarTelefone($telefone)
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao enviar SMS: ' . ($result['message'] ?? 'Erro desconhecido')
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar SMS de teste: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna provedores disponiveis
     *
     * GET /api/sms/providers
     */
    public function providers(Request $request): void
    {
        Response::json([
            'success' => true,
            'data' => SmsProviderFactory::getAvailableProviders()
        ]);
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
