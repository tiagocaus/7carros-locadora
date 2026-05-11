<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\GatewayPagamento;
use App\Services\AuditLogService;
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
            $gateways = GatewayFactory::getAvailableGateways();

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
            if (!empty($gateway['credentials'])) {
                $gateway['credentials'] = $this->maskCredentials($gateway['credentials']);
            }

            // Adicionar schema do gateway
            if (GatewayFactory::exists($gateway['gateway_code'])) {
                $gateway['config_schema'] = GatewayFactory::getGatewayInfo($gateway['gateway_code'])['config_schema'] ?? [];
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

            // Gerar webhook URL e secret
            $model = new GatewayPagamento();
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
                    'webhook_url' => $dados['webhook_url']
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

            $result = $gatewayInstance->validateCredentials($gateway['credentials'] ?? []);

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
}
