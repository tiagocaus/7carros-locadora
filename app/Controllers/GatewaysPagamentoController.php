<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\GatewayPagamento;
use App\Services\AuditLogService;
use App\Services\Gateways\GatewayCertificateService;
use App\Services\Gateways\GatewayFactory;

/**
 * Controller de Gateways de Pagamento
 *
 * Gerencia operações CRUD de gateways de pagamento,
 * incluindo configuração de credenciais e teste de conexão.
 */
class GatewaysPagamentoController
{
    /**
     * Renderiza a página de listagem de gateways
     *
     * GET /pages/gateways-pagamento
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.gateways-pagamento.index');
        Response::html($html);
    }

    /**
     * Renderiza a página de adicionar/editar gateway
     *
     * GET /pages/gateways-pagamento/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.gateways-pagamento.adicionar');
        Response::html($html);
    }

    /**
     * Lista todos os gateways do tenant (com paginação e busca)
     *
     * GET /api/gateways-pagamento
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new GatewayPagamento();

            $gateways = $this->listarGatewaysConfiguradosEDisponiveis($model, $search);
            $total = count($gateways);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;
            $gatewaysPagina = array_slice($gateways, $offset, $perPage);

            Response::json([
                'success' => true,
                'data' => $gatewaysPagina,
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
                'message' => 'Erro ao buscar gateways: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mescla gateways já configurados no tenant com os gateways suportados pelo sistema.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listarGatewaysConfiguradosEDisponiveis(GatewayPagamento $model, string $search = ''): array
    {
        $disponiveis = GatewayFactory::getAvailableGateways();
        $configurados = $model->listarComFiliais();
        $configuradosPorCodigo = [];

        foreach ($configurados as $gateway) {
            $codigo = strtolower((string) ($gateway['gateway_code'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            $gateway['configured'] = true;
            $gateway['country'] = null;
            $gateway['_factory_order'] = 999;
            $configuradosPorCodigo[$codigo] = $gateway;
        }

        $resultado = [];

        foreach ($disponiveis as $index => $gatewayDisponivel) {
            $codigo = strtolower((string) ($gatewayDisponivel['code'] ?? ''));
            if ($codigo === '') {
                continue;
            }

            if (isset($configuradosPorCodigo[$codigo])) {
                $item = $configuradosPorCodigo[$codigo];
                $item['country'] = $gatewayDisponivel['country'] ?? null;
                $item['supported_methods'] = $gatewayDisponivel['methods'] ?? [];
                $item['supported_currencies'] = $gatewayDisponivel['supported_currencies'] ?? [];
                $item['_factory_order'] = $index;
                $resultado[] = $item;
                unset($configuradosPorCodigo[$codigo]);
                continue;
            }

            $methods = $gatewayDisponivel['methods'] ?? [];
            $currencies = $gatewayDisponivel['supported_currencies'] ?? ['BRL'];

            $resultado[] = [
                'id' => null,
                'gateway_code' => $codigo,
                'currencies' => $currencies,
                'nome' => $gatewayDisponivel['name'] ?? strtoupper($codigo),
                'ambiente' => null,
                'status' => null,
                'pix_enabled' => in_array('pix', $methods, true) ? 1 : 0,
                'boleto_enabled' => in_array('boleto', $methods, true) ? 1 : 0,
                'credit_card_enabled' => in_array('credit_card', $methods, true) ? 1 : 0,
                'debit_card_enabled' => in_array('debit_card', $methods, true) ? 1 : 0,
                'ordem' => 999,
                'created_at' => null,
                'filiais_nomes' => null,
                'configured' => false,
                'country' => $gatewayDisponivel['country'] ?? null,
                'supported_methods' => $methods,
                'supported_currencies' => $currencies,
                '_factory_order' => $index,
            ];
        }

        foreach ($configuradosPorCodigo as $gateway) {
            $resultado[] = $gateway;
        }

        if ($search !== '') {
            $resultado = array_values(array_filter($resultado, function (array $gateway) use ($search) {
                $haystack = strtolower(implode(' ', [
                    $gateway['gateway_code'] ?? '',
                    $gateway['nome'] ?? '',
                    $gateway['country'] ?? '',
                    implode(' ', $gateway['supported_methods'] ?? []),
                    implode(' ', $gateway['supported_currencies'] ?? []),
                ]));

                return str_contains($haystack, strtolower($search));
            }));
        }

        usort($resultado, function (array $a, array $b) {
            $aConfigured = !empty($a['configured']) ? 0 : 1;
            $bConfigured = !empty($b['configured']) ? 0 : 1;

            return [$aConfigured, (int) ($a['ordem'] ?? 999), (int) ($a['_factory_order'] ?? 999), (string) ($a['nome'] ?? '')]
                <=>
                [$bConfigured, (int) ($b['ordem'] ?? 999), (int) ($b['_factory_order'] ?? 999), (string) ($b['nome'] ?? '')];
        });

        return array_map(function (array $gateway) {
            unset($gateway['_factory_order']);
            return $gateway;
        }, $resultado);
    }

    /**
     * Lista gateways disponíveis para configuração
     *
     * GET /api/gateways-pagamento/disponiveis
     */
    public function disponiveis(Request $request): void
    {
        try {
            $gateways = array_map(function (array $gateway): array {
                $gateway['config_schema'] = $this->decorateConfigSchema($gateway['config_schema'] ?? []);
                return $gateway;
            }, GatewayFactory::getAvailableGateways());

            Response::json([
                'success' => true,
                'data' => $gateways
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar gateways disponíveis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna gateways filtrados por país
     *
     * GET /api/gateways-pagamento/por-pais/{country}
     */
    public function porPais(Request $request, string $country): void
    {
        try {
            $gateways = GatewayFactory::getGatewaysByCountry($country);

            Response::json([
                'success' => true,
                'data' => $gateways
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar gateways: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um gateway específico
     *
     * GET /api/gateways-pagamento/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorIdComCredenciais($id);

            if (!$gateway) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($gateway['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            // Mascarar credenciais sensíveis
            $credentialsRaw = $gateway['credentials'] ?? [];
            $gatewayInfo = GatewayFactory::getGatewayInfo((string) ($gateway['gateway_code'] ?? ''));
            if (!empty($gatewayInfo['certificate_config'])) {
                $gateway['certificado'] = $this->getGatewayCertificateInfo($credentialsRaw);
                $gateway['certificado_config'] = $gatewayInfo['certificate_config'];
            }

            if (!empty($gateway['credentials'])) {
                $gateway['credentials'] = $this->maskCredentials(
                    $this->withoutCertificateCredentials($gateway['credentials'])
                );
            }

            // Adicionar schema do gateway
            if (GatewayFactory::exists($gateway['gateway_code'])) {
                $gateway['config_schema'] = $this->decorateConfigSchema($gatewayInfo['config_schema'] ?? []);
            }

            // Buscar filiais vinculadas
            $gateway['filiais'] = $model->buscarFiliais($id);

            Response::json([
                'success' => true,
                'data' => $gateway
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo gateway
     *
     * POST /gateways-pagamento/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validações
            if (empty($dados['gateway_code'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Código do gateway é obrigatório'
                ], 400);
                return;
            }

            if (!GatewayFactory::exists($dados['gateway_code'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway inválido'
                ], 400);
                return;
            }

            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome é obrigatório'
                ], 400);
                return;
            }

            $dados = $this->normalizeSupportedPaymentMethods((string) $dados['gateway_code'], $dados);

            // Gerar webhook URL e secret
            $model = new GatewayPagamento();
            $certificateRequired = $this->requiresCertificate((string) $dados['gateway_code'], $dados);
            $requestedStatus = (string) ($dados['status'] ?? 'I');
            if ($certificateRequired) {
                $dados['status'] = 'I';
            }

            $dados['credentials'] = $this->normalizeCredentialsForGateway(
                (string) $dados['gateway_code'],
                is_array($dados['credentials'] ?? null) ? $dados['credentials'] : []
            );
            $configurationError = $this->validateGatewayMethodConfiguration((string) $dados['gateway_code'], $dados);
            if ($configurationError !== null) {
                Response::json(['success' => false, 'message' => $configurationError], 422);
                return;
            }
            $dados['webhook_url'] = $model->gerarWebhookUrl(0, $dados['gateway_code']);
            $dados['webhook_secret'] = $model->gerarWebhookSecret();

            $id = $model->criar($dados);

            // Sincronizar filiais
            $filiaisIds = $dados['filiais_ids'] ?? [];
            if (is_string($filiaisIds)) {
                $filiaisIds = json_decode($filiaisIds, true) ?: [];
            }
            if (!empty($filiaisIds)) {
                $model->sincronizarFiliais($id, $filiaisIds, $dados['chave']);
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou gateway [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Gateway criado com sucesso',
                'data' => [
                    'id' => $id,
                    'webhook_url' => $dados['webhook_url'],
                    'certificate_required' => $certificateRequired,
                    'activate_after_certificate' => $requestedStatus === 'A',
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um gateway
     *
     * POST /gateways-pagamento/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorId($id);

            if (!$gateway) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($gateway['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode editar este gateway'
                ], 403);
                return;
            }

            $dados = $request->all();
            $dados = $this->normalizeSupportedPaymentMethods((string) $gateway['gateway_code'], $dados);

            // Se credentials estiver vazio ou com valores mascarados, manter as existentes
            if (isset($dados['credentials']) && is_array($dados['credentials'])) {
                $gatewayComCreds = $model->buscarPorIdComCredenciais($id);
                $credsExistentes = $gatewayComCreds['credentials'] ?? [];

                foreach ($dados['credentials'] as $key => $value) {
                    // Se o valor estiver mascarado ou vazio, manter o existente
                    if (empty($value) || $this->isValueMasked($value)) {
                        if (isset($credsExistentes[$key])) {
                            $dados['credentials'][$key] = $credsExistentes[$key];
                        }
                    }
                }

                $dados['credentials'] = $this->normalizeCredentialsForGateway(
                    (string) $gateway['gateway_code'],
                    $dados['credentials'],
                    $credsExistentes
                );
            }

            $configurationError = $this->validateGatewayMethodConfiguration((string) $gateway['gateway_code'], $dados);
            if ($configurationError !== null) {
                Response::json(['success' => false, 'message' => $configurationError], 422);
                return;
            }

            if (($dados['status'] ?? null) === 'A') {
                $credentials = $dados['credentials'] ?? ($model->buscarPorIdComCredenciais($id)['credentials'] ?? []);
                if ($this->requiresCertificate((string) $gateway['gateway_code'], array_merge($gateway, $dados)) && !$this->hasUsableCertificate($credentials)) {
                    Response::json(['success' => false, 'message' => 'Envie o certificado digital antes de ativar este gateway.'], 422);
                    return;
                }
            }

            $model->atualizar($id, $dados);

            // Sincronizar filiais
            $filiaisIds = $dados['filiais_ids'] ?? [];
            if (is_string($filiaisIds)) {
                $filiaisIds = json_decode($filiaisIds, true) ?: [];
            }
            $model->sincronizarFiliais($id, $filiaisIds, $chave);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou gateway [{$gateway['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Gateway atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um gateway
     *
     * POST /gateways-pagamento/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorId($id);

            if (!$gateway) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($gateway['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode excluir este gateway'
                ], 403);
                return;
            }

            if ($this->getCertificateConfig((string) ($gateway['gateway_code'] ?? '')) !== null) {
                $gatewayComCreds = $model->buscarPorIdComCredenciais($id);
                $credentials = $gatewayComCreds['credentials'] ?? [];
                $this->removeStoredCertificateFiles($credentials);
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu gateway [{$gateway['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Gateway excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testa conexão com o gateway
     *
     * POST /api/gateways-pagamento/{id}/testar
     */
    public function testar(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorIdComCredenciais($id);

            if (!$gateway) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($gateway['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            if (!GatewayFactory::exists($gateway['gateway_code'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não suportado'
                ], 400);
                return;
            }

            $isSandbox = $gateway['ambiente'] === 'sandbox';
            $gatewayInstance = GatewayFactory::create(
                $gateway['gateway_code'],
                $gateway['credentials'] ?? [],
                $isSandbox,
                $id
            );

            $credentialsToTest = $gateway['credentials'] ?? [];
            $credentialsToTest['_pix_enabled'] = !empty($gateway['pix_enabled']);
            $credentialsToTest['_boleto_enabled'] = !empty($gateway['boleto_enabled']);
            $result = $gatewayInstance->validateCredentials($credentialsToTest);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", testou conexão do gateway [{$gateway['nome']}]: " .
                ($result['valid'] ? 'sucesso' : 'falha')
            );

            Response::json([
                'success' => $result['valid'],
                'message' => $result['message'] ?? ($result['valid'] ? 'Conexão bem sucedida' : 'Falha na conexão')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao testar gateway: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia certificado digital de um gateway bancário com suporte a mTLS.
     *
     * POST /gateways-pagamento/{id}/certificado
     */
    public function uploadCertificado(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorIdComCredenciais($id);

            if (!$gateway || $gateway['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => 'Gateway não encontrado'], 404);
                return;
            }

            $gatewayCode = (string) ($gateway['gateway_code'] ?? '');
            if ($this->getCertificateConfig($gatewayCode) === null) {
                Response::json(['success' => false, 'message' => 'Este gateway não utiliza certificado digital'], 400);
                return;
            }

            $senha = (string) $request->input('certificado_senha', '');
            $mode = (string) $request->input('certificado_modo', '');
            if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
                Response::json(['success' => false, 'message' => 'Arquivo do certificado não enviado'], 400);
                return;
            }

            $certService = new GatewayCertificateService();
            $credentials = $gateway['credentials'] ?? [];
            $oldCredentials = $credentials;
            $privateKey = isset($_FILES['chave_privada']) ? $_FILES['chave_privada'] : null;
            $result = $certService->upload($_FILES['certificado'], $id, Auth::chave(), $senha, $privateKey, $mode);
            if (!$result['success']) {
                Response::json(['success' => false, 'message' => $result['message']], 422);
                return;
            }

            $credentials['certificado_arquivo'] = $result['filename'];
            $credentials['certificado_chave_arquivo'] = $result['key_filename'];
            $credentials['certificado_formato'] = $result['format'];
            $credentials['certificado_modo'] = $result['format'] === 'pem' ? 'pem_pair' : 'pkcs12';
            $credentials['certificado_senha'] = $result['password_encrypted'];
            $credentials['certificado_validade'] = $result['data']['valido_ate'] ?? null;
            $credentials['certificado_razao_social'] = $result['data']['razao_social'] ?? null;
            $credentials['certificado_documento'] = $result['data']['documento'] ?? null;
            $credentials['certificado_emissor'] = $result['data']['emissor'] ?? null;
            $credentials['certificado_serial'] = $result['data']['serial'] ?? null;
            $credentials['certificado_subject'] = $result['data']['subject'] ?? null;

            $update = ['credentials' => $this->normalizeCredentialsForGateway($gatewayCode, $credentials, $credentials)];
            if ((string) $request->input('ativar_apos_upload', '0') === '1') {
                $update['status'] = 'A';
            }
            $model->atualizar($id, $update);
            $this->removeStoredCertificateFiles($oldCredentials, $result['filename'], $result['key_filename']);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou certificado do gateway [{$gateway['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => $result['message'],
                'data' => $this->getGatewayCertificateInfo($credentials),
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar certificado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove certificado digital de um gateway bancário.
     *
     * POST /gateways-pagamento/{id}/certificado/remover
     */
    public function removerCertificado(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorIdComCredenciais($id);

            if (!$gateway || $gateway['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => 'Gateway não encontrado'], 404);
                return;
            }

            $gatewayCode = (string) ($gateway['gateway_code'] ?? '');
            $certificateConfig = $this->getCertificateConfig($gatewayCode);
            if ($certificateConfig === null) {
                Response::json(['success' => false, 'message' => 'Este gateway não utiliza certificado digital'], 400);
                return;
            }

            $credentials = $gateway['credentials'] ?? [];
            $this->removeStoredCertificateFiles($credentials);

            foreach ($this->getCertificateCredentialKeys() as $key) {
                unset($credentials[$key]);
            }
            foreach ($this->getLegacyCertificateCredentialKeys() as $key) {
                unset($credentials[$key]);
            }

            $update = ['credentials' => $this->normalizeCredentialsForGateway($gatewayCode, $credentials)];
            if ($this->requiresCertificate($gatewayCode, $gateway)) {
                $update['status'] = 'I';
            }
            $model->atualizar($id, $update);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", removeu certificado do gateway [{$gateway['nome']}]"
            );

            Response::json(['success' => true, 'message' => 'Certificado removido com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao remover certificado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Altera status do gateway (ativo/inativo)
     *
     * POST /gateways-pagamento/{id}/status
     */
    public function alterarStatus(Request $request, int $id): void
    {
        try {
            $model = new GatewayPagamento();
            $gateway = $model->buscarPorId($id);

            if (!$gateway) {
                Response::json([
                    'success' => false,
                    'message' => 'Gateway não encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($gateway['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode alterar este gateway'
                ], 403);
                return;
            }

            $novoStatus = $gateway['status'] === 'A' ? 'I' : 'A';
            if ($novoStatus === 'A' && $this->requiresCertificate((string) $gateway['gateway_code'], $gateway)) {
                $gatewayComCredenciais = $model->buscarPorIdComCredenciais($id);
                if (!$this->hasUsableCertificate($gatewayComCredenciais['credentials'] ?? [])) {
                    Response::json(['success' => false, 'message' => 'Envie o certificado digital antes de ativar este gateway.'], 422);
                    return;
                }
            }
            $model->atualizar($id, ['status' => $novoStatus]);

            $statusLabel = $novoStatus === 'A' ? 'ativado' : 'desativado';

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", {$statusLabel} gateway [{$gateway['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => "Gateway {$statusLabel} com sucesso",
                'data' => ['status' => $novoStatus]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao alterar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mascara valores de credenciais para exibição
     *
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    private function maskCredentials(array $credentials): array
    {
        $masked = [];

        foreach ($credentials as $key => $value) {
            if (is_string($value) && strlen($value) > 8) {
                $masked[$key] = substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
            } elseif (is_string($value) && strlen($value) > 0) {
                $masked[$key] = str_repeat('*', strlen($value));
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Verifica se valor está mascarado
     *
     * @param mixed $value
     * @return bool
     */
    private function isValueMasked($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Verifica se o valor contém apenas asteriscos ou padrão de mascaramento
        return preg_match('/^\*+$/', $value) === 1 ||
               preg_match('/^.{4}\*+.{4}$/', $value) === 1;
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function normalizeCredentialsForGateway(string $gatewayCode, array $credentials, array $existing = []): array
    {
        $normalized = array_merge($existing, $credentials);
        $legacyKeys = $this->getLegacyCertificateCredentialKeys();

        foreach ($legacyKeys as $key) {
            if (array_key_exists($key, $credentials) && !array_key_exists($key, $existing)) {
                unset($normalized[$key]);
            }
        }

        if (!empty($normalized['certificado_arquivo'])) {
            foreach ($legacyKeys as $key) {
                unset($normalized[$key]);
            }
        }

        if ($gatewayCode === 'sicoob') {
            unset($normalized['api_base_url'], $normalized['auth_url']);
        }

        return $normalized;
    }

    /**
     * Mantém habilitados somente os métodos efetivamente implementados pelo gateway.
     *
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private function normalizeSupportedPaymentMethods(string $gatewayCode, array $dados): array
    {
        $gatewayInfo = GatewayFactory::getGatewayInfo($gatewayCode);
        $supportedMethods = $gatewayInfo['methods'] ?? [];
        $fieldsByMethod = [
            'pix' => 'pix_enabled',
            'boleto' => 'boleto_enabled',
            'credit_card' => 'credit_card_enabled',
            'debit_card' => 'debit_card_enabled',
        ];

        foreach ($fieldsByMethod as $method => $field) {
            if (!in_array($method, $supportedMethods, true)) {
                $dados[$field] = 0;
            }
        }

        return $dados;
    }

    /** @param array<string, mixed> $dados */
    private function validateGatewayMethodConfiguration(string $gatewayCode, array $dados): ?string
    {
        if ($gatewayCode !== 'bradesco' || empty($dados['boleto_enabled'])) {
            return null;
        }

        $credentials = is_array($dados['credentials'] ?? null) ? $dados['credentials'] : [];
        $required = [
            'boleto_client_id' => 'Client ID do Boleto',
            'boleto_client_secret' => 'Client Secret do Boleto',
            'boleto_beneficiary_document' => 'CNPJ do Beneficiário',
            'boleto_product' => 'Carteira / ID do Produto',
            'boleto_negotiation' => 'Número da Negociação',
        ];
        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($credentials[$field])) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            return 'Para ativar Boleto Bradesco, preencha: ' . implode(', ', $missing) . '.';
        }

        $beneficiary = preg_replace('/\D+/', '', (string) $credentials['boleto_beneficiary_document']);
        $product = preg_replace('/\D+/', '', (string) $credentials['boleto_product']);
        $negotiation = preg_replace('/\D+/', '', (string) $credentials['boleto_negotiation']);
        if (strlen($beneficiary) !== 14) {
            return 'O CNPJ do Beneficiário do Boleto Bradesco deve ter 14 dígitos.';
        }
        if ($product === '' || strlen($product) > 2) {
            return 'A Carteira / ID do Produto do Boleto Bradesco deve ter até 2 dígitos.';
        }
        if (strlen($negotiation) !== 18) {
            return 'O Número da Negociação do Boleto Bradesco deve ter 18 dígitos.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>|null
     */
    private function getGatewayCertificateInfo(array $credentials): ?array
    {
        if (empty($credentials['certificado_arquivo'])) {
            if (!$this->hasLegacyCertificate($credentials)) {
                return null;
            }

            return [
                'arquivo' => null,
                'formato' => 'legado',
                'legado' => true,
                'validade' => null,
            ];
        }

        return [
            'arquivo' => $credentials['certificado_arquivo'] ?? null,
            'chave_privada' => $credentials['certificado_chave_arquivo'] ?? null,
            'formato' => $credentials['certificado_formato'] ?? 'pkcs12',
            'modo' => $credentials['certificado_modo']
                ?? (($credentials['certificado_formato'] ?? 'pkcs12') === 'pem' ? 'pem_pair' : 'pkcs12'),
            'legado' => false,
            'validade' => $credentials['certificado_validade'] ?? null,
            'razao_social' => $credentials['certificado_razao_social'] ?? null,
            'documento' => $credentials['certificado_documento'] ?? null,
            'emissor' => $credentials['certificado_emissor'] ?? null,
            'serial' => $credentials['certificado_serial'] ?? null,
        ];
    }

    /**
     * @return array<string>
     */
    private function getCertificateCredentialKeys(): array
    {
        return [
            'certificado_arquivo',
            'certificado_chave_arquivo',
            'certificado_formato',
            'certificado_modo',
            'certificado_senha',
            'certificado_validade',
            'certificado_razao_social',
            'certificado_documento',
            'certificado_emissor',
            'certificado_serial',
            'certificado_subject',
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function decorateConfigSchema(array $schema): array
    {
        foreach ($schema as $key => $config) {
            if (!is_array($config) || empty($config['help'])) {
                continue;
            }

            $schema[$key]['help_html'] = aviso(htmlspecialchars((string) $config['help'], ENT_QUOTES, 'UTF-8'));
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCertificateConfig(string $gatewayCode): ?array
    {
        $info = GatewayFactory::getGatewayInfo($gatewayCode);
        $config = $info['certificate_config'] ?? null;
        return is_array($config) ? $config : null;
    }

    /** @param array<string, mixed> $configuration */
    private function requiresCertificate(string $gatewayCode, array $configuration = []): bool
    {
        $config = $this->getCertificateConfig($gatewayCode);
        if ($config === null) {
            return false;
        }

        $requiredEnvironments = is_array($config['required_environments'] ?? null)
            ? $config['required_environments']
            : [];
        $environment = (string) ($configuration['ambiente'] ?? 'production');
        if ($requiredEnvironments !== [] && !in_array($environment, $requiredEnvironments, true)) {
            return false;
        }

        $requiredMethods = is_array($config['required_methods'] ?? null)
            ? $config['required_methods']
            : [];
        if ($requiredMethods !== []) {
            foreach ($requiredMethods as $method) {
                if (!empty($configuration[$method . '_enabled'])) {
                    return true;
                }
            }
            return false;
        }

        return !empty($config['required']);
    }

    /** @param array<string, mixed> $credentials */
    private function hasUsableCertificate(array $credentials): bool
    {
        return !empty($credentials['certificado_arquivo']) || $this->hasLegacyCertificate($credentials);
    }

    /** @param array<string, mixed> $credentials */
    private function hasLegacyCertificate(array $credentials): bool
    {
        return !empty($credentials['certificate_path']);
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    private function withoutCertificateCredentials(array $credentials): array
    {
        foreach (array_merge($this->getCertificateCredentialKeys(), $this->getLegacyCertificateCredentialKeys()) as $key) {
            unset($credentials[$key]);
        }

        return $credentials;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function removeStoredCertificateFiles(
        array $credentials,
        ?string $exceptCertificate = null,
        ?string $exceptPrivateKey = null
    ): void {
        $service = new GatewayCertificateService();
        $certificate = (string) ($credentials['certificado_arquivo'] ?? '');
        $privateKey = (string) ($credentials['certificado_chave_arquivo'] ?? '');

        if ($certificate !== '' && $certificate !== $exceptCertificate) {
            $service->remove($certificate);
        }
        if ($privateKey !== '' && $privateKey !== $exceptPrivateKey) {
            $service->remove($privateKey);
        }
    }

    /** @return array<string> */
    private function getLegacyCertificateCredentialKeys(): array
    {
        return [
            'certificate_path',
            'private_key_path',
            'certificate_password',
            'private_key_password',
            'x_client_certificate',
        ];
    }
}
