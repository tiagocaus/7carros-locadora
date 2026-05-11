<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Smtp;
use App\Helpers\PlanoLimiteHelper;
use App\Services\AuditLogService;
use App\Services\SmtpService;
use App\Services\Smtp\SmtpProviderFactory;

/**
 * Controller de SMTP
 *
 * Gerencia conexoes SMTP para envio de emails (Gmail, Outlook, SendGrid, etc).
 */
class SmtpController
{
    /**
     * Lista todas as conexoes SMTP do tenant
     *
     * GET /api/smtp
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new Smtp();

            $conexoes = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Remover password dos dados retornados
            $conexoes = array_map(function ($c) {
                unset($c['password']);
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
                'message' => 'Erro ao buscar conexoes SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma conexao especifica
     *
     * GET /api/smtp/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Smtp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conexao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
                ], 404);
                return;
            }

            // Remover password da resposta
            unset($conexao['password']);

            // Incluir filiais vinculadas
            $conexao['filiais'] = $model->getFiliais($id);

            Response::json([
                'success' => true,
                'data' => $conexao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar conexao SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista IDs das filiais ja vinculadas a alguma conexao SMTP
     *
     * GET /api/smtp/filiais-ocupadas
     */
    public function filiaisOcupadas(Request $request): void
    {
        try {
            $model = new Smtp();
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
     * Retorna provedores disponiveis com configuracoes
     *
     * GET /api/smtp/providers
     */
    public function providers(Request $request): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'providers' => SmtpProviderFactory::getAvailableProviders(),
                'ports' => SmtpProviderFactory::getAvailablePorts(),
                'encryptions' => SmtpProviderFactory::getAvailableEncryptions(),
            ]
        ]);
    }

    /**
     * Cria uma nova conexao SMTP
     *
     * POST /smtp/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar limite do plano
            if (!PlanoLimiteHelper::podeAdicionar('smtp')) {
                $usage = PlanoLimiteHelper::getUsage('smtp');
                Response::json([
                    'success' => false,
                    'message' => "Limite de conexoes SMTP atingido. Seu plano {$usage['plano']} permite apenas {$usage['limite']} conexoes SMTP.",
                    'limite_atingido' => true,
                    'redirect_url' => PlanoLimiteHelper::getRedirectSeAtingido('smtp')
                ], 403);
                return;
            }

            $dados = $request->all();
            $chave = Auth::chave();

            // Validar campos obrigatorios
            $provider = trim($dados['provider'] ?? 'smtp_custom');
            $nome = trim($dados['nome'] ?? '');
            $username = trim($dados['username'] ?? '');
            $password = trim($dados['password'] ?? '');
            $fromEmail = trim($dados['from_email'] ?? '');
            $fromName = trim($dados['from_name'] ?? '');

            // Validar nome
            if (empty($nome)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome da conexao e obrigatorio'
                ], 400);
                return;
            }

            if (strlen($nome) > 100) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome da conexao deve ter no maximo 100 caracteres'
                ], 400);
                return;
            }

            // Validar username (email)
            if (empty($username)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email de autenticacao e obrigatorio'
                ], 400);
                return;
            }

            // Validar senha
            if (empty($password)) {
                Response::json([
                    'success' => false,
                    'message' => 'Senha e obrigatoria'
                ], 400);
                return;
            }

            // Validar from_email
            if (empty($fromEmail)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email remetente e obrigatorio'
                ], 400);
                return;
            }

            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email remetente invalido'
                ], 400);
                return;
            }

            // Validar from_name
            if (empty($fromName)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome remetente e obrigatorio'
                ], 400);
                return;
            }

            // Obter configuracoes do provedor
            $providerDefaults = SmtpProviderFactory::getProviderDefaults($provider);
            $isCustom = SmtpProviderFactory::isCustomProvider($provider);

            // Host
            $host = $isCustom
                ? trim($dados['host'] ?? '')
                : $providerDefaults['host'];

            if (empty($host)) {
                Response::json([
                    'success' => false,
                    'message' => 'Servidor SMTP e obrigatorio'
                ], 400);
                return;
            }

            // Porta
            $port = $isCustom
                ? (int) ($dados['port'] ?? 587)
                : ($providerDefaults['port'] ?? 587);

            // Criptografia
            $encryption = $isCustom
                ? ($dados['encryption'] ?? 'tls')
                : ($providerDefaults['encryption'] ?? 'tls');

            // Campos opcionais
            $replyToEmail = trim($dados['reply_to_email'] ?? '') ?: null;
            $replyToName = trim($dados['reply_to_name'] ?? '') ?: null;
            $dailyLimit = !empty($dados['daily_limit']) ? (int) $dados['daily_limit'] : null;

            // Validar reply_to_email se fornecido
            if ($replyToEmail && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email de resposta invalido'
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

            // Verificar se nome ja existe no tenant
            $model = new Smtp();
            if ($model->nomeExiste($nome)) {
                Response::json([
                    'success' => false,
                    'message' => 'Ja existe uma conexao SMTP com este nome'
                ], 400);
                return;
            }

            // Validar conexao SMTP
            $validationStatus = 'pending';
            $validationError = null;

            $smtpService = new SmtpService();
            $validationResult = $smtpService->testConnection([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
            ]);

            if ($validationResult['success']) {
                $validationStatus = 'validated';
            } else {
                $validationStatus = 'invalid';
                $validationError = $validationResult['message'] ?? 'Falha na conexao SMTP';
            }

            // Criptografar senha
            $encryptedPassword = encrypt($password);

            // Salvar no banco
            $id = $model->criar([
                'chave' => $chave,
                'provider' => $provider,
                'nome' => $nome,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $encryptedPassword,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'reply_to_email' => $replyToEmail,
                'reply_to_name' => $replyToName,
                'daily_limit' => $dailyLimit,
                'status' => $validationStatus,
                'validated_at' => $validationStatus === 'validated' ? date('Y-m-d H:i:s') : null,
                'last_error' => $validationError,
            ]);

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", criou conexao SMTP [{$nome}] (status: {$validationStatus})"
            );

            $responseMessage = $validationStatus === 'validated'
                ? 'Conexao SMTP criada e validada com sucesso'
                : 'Conexao SMTP criada, mas validacao falhou: ' . $validationError;

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
                'message' => 'Erro ao criar conexao SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma conexao SMTP
     *
     * POST /smtp/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Smtp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
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
            $needsRevalidation = false;

            if (!empty($dados['nome'])) {
                $nome = trim($dados['nome']);
                if (strlen($nome) > 100) {
                    Response::json([
                        'success' => false,
                        'message' => 'Nome da conexao deve ter no maximo 100 caracteres'
                    ], 400);
                    return;
                }
                if ($model->nomeExiste($nome, $id)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Ja existe uma conexao SMTP com este nome'
                    ], 400);
                    return;
                }
                $dadosUpdate['nome'] = $nome;
            }

            if (!empty($dados['provider'])) {
                $dadosUpdate['provider'] = $dados['provider'];
            }

            if (!empty($dados['host'])) {
                $dadosUpdate['host'] = trim($dados['host']);
                $needsRevalidation = true;
            }

            if (!empty($dados['port'])) {
                $dadosUpdate['port'] = (int) $dados['port'];
                $needsRevalidation = true;
            }

            if (!empty($dados['encryption'])) {
                $dadosUpdate['encryption'] = $dados['encryption'];
                $needsRevalidation = true;
            }

            if (!empty($dados['username'])) {
                $dadosUpdate['username'] = trim($dados['username']);
                $needsRevalidation = true;
            }

            if (!empty($dados['password'])) {
                $dadosUpdate['password'] = encrypt(trim($dados['password']));
                $needsRevalidation = true;
            }

            if (!empty($dados['from_email'])) {
                $fromEmail = trim($dados['from_email']);
                if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Email remetente invalido'
                    ], 400);
                    return;
                }
                $dadosUpdate['from_email'] = $fromEmail;
            }

            if (!empty($dados['from_name'])) {
                $dadosUpdate['from_name'] = trim($dados['from_name']);
            }

            if (array_key_exists('reply_to_email', $dados)) {
                $replyToEmail = trim($dados['reply_to_email'] ?? '') ?: null;
                if ($replyToEmail && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Email de resposta invalido'
                    ], 400);
                    return;
                }
                $dadosUpdate['reply_to_email'] = $replyToEmail;
            }

            if (array_key_exists('reply_to_name', $dados)) {
                $dadosUpdate['reply_to_name'] = trim($dados['reply_to_name'] ?? '') ?: null;
            }

            if (array_key_exists('daily_limit', $dados)) {
                $dadosUpdate['daily_limit'] = !empty($dados['daily_limit']) ? (int) $dados['daily_limit'] : null;
            }

            // Se alterou credenciais, marcar para revalidacao
            if ($needsRevalidation) {
                $dadosUpdate['status'] = 'pending';
                $dadosUpdate['last_error'] = null;
            }

            // Atualizar dados da conexao
            if (!empty($dadosUpdate)) {
                $model->atualizar($id, $dadosUpdate);
            }

            // Sincronizar filiais
            $model->sincronizarFiliais($id, $filialIds, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou conexao SMTP [{$conexao['nome']}]"
            );

            $message = 'Conexao SMTP atualizada com sucesso';
            if ($needsRevalidation) {
                $message .= '. Credenciais alteradas - revalidacao necessaria.';
            }

            Response::json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar conexao SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma conexao SMTP
     *
     * POST /smtp/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Smtp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
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
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu conexao SMTP [{$conexao['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conexao SMTP excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir conexao SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida conexao SMTP
     *
     * POST /smtp/{id}/validate
     */
    public function validate(Request $request, int $id): void
    {
        try {
            $model = new Smtp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
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

            // Descriptografar senha
            $password = decrypt($conexao['password']);

            // Testar conexao
            $smtpService = new SmtpService();
            $result = $smtpService->testConnection([
                'host' => $conexao['host'],
                'port' => (int) $conexao['port'],
                'encryption' => $conexao['encryption'],
                'username' => $conexao['username'],
                'password' => $password,
            ]);

            if ($result['success']) {
                $model->atualizarStatus($id, 'validated');

                Response::json([
                    'success' => true,
                    'message' => 'Conexao SMTP validada com sucesso',
                    'data' => [
                        'status' => 'validated'
                    ]
                ]);
            } else {
                $model->atualizarStatus($id, 'invalid', $result['message']);

                Response::json([
                    'success' => false,
                    'message' => 'Validacao falhou: ' . ($result['message'] ?? 'Erro de conexao'),
                    'data' => [
                        'status' => 'invalid'
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao validar conexao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia email de teste
     *
     * POST /smtp/test
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

            $model = new Smtp();
            $conexao = $model->buscarPorId($id);

            if (!$conexao) {
                Response::json([
                    'success' => false,
                    'message' => 'Conexao SMTP nao encontrada'
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
                    'message' => 'Conexao SMTP nao esta validada. Valide a conexao primeiro.'
                ], 400);
                return;
            }

            // Email de destino do teste
            $toEmail = trim($dados['email'] ?? '');
            if (empty($toEmail)) {
                // Usar email do usuario logado como padrao
                $toEmail = $_SESSION['user_email'] ?? '';
            }

            if (empty($toEmail)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email de destino nao informado'
                ], 400);
                return;
            }

            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email de destino invalido'
                ], 400);
                return;
            }

            // Descriptografar senha
            $password = decrypt($conexao['password']);

            // Enviar email de teste
            $smtpService = new SmtpService();
            $result = $smtpService->sendTestEmail(
                [
                    'host' => $conexao['host'],
                    'port' => (int) $conexao['port'],
                    'encryption' => $conexao['encryption'],
                    'username' => $conexao['username'],
                    'password' => $password,
                ],
                $toEmail,
                $conexao['from_email'],
                $conexao['from_name']
            );

            if ($result['success']) {
                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", enviou email de teste via SMTP [{$conexao['nome']}] para {$toEmail}"
                );

                Response::json([
                    'success' => true,
                    'message' => "Email de teste enviado com sucesso para {$toEmail}"
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao enviar email: ' . ($result['message'] ?? 'Erro desconhecido')
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar email de teste: ' . $e->getMessage()
            ], 500);
        }
    }
}
