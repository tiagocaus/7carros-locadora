<?php

/**
 * Teste unitario da politica de liberacao de pre-autorizacoes.
 *
 * Execute: php tests/test_authorization_hold_release_service.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Exceptions\AuthorizationHoldReleaseException;
use App\Models\ContratoBloqueio;
use App\Models\GatewayPagamento;
use App\Models\LocacaoBloqueio;
use App\Services\AuthorizationHoldReleaseService;
use App\Services\Gateways\AuthorizationHoldInterface;

$falhas = 0;

function verificarHold(bool $condicao, string $mensagem): void
{
    global $falhas;

    echo ($condicao ? 'PASS' : 'FAIL') . " - {$mensagem}\n";
    if (!$condicao) {
        $falhas++;
    }
}

final class GatewayHoldFake implements AuthorizationHoldInterface
{
    public function __construct(
        private readonly array $release,
        private readonly array $status = ['success' => false]
    ) {
    }

    public function supportsAuthorizationHold(): bool
    {
        return true;
    }

    public function createHold(array $data): array
    {
        return ['success' => false];
    }

    public function captureHold(string $externalId, ?float $amount = null): array
    {
        return ['success' => false];
    }

    public function releaseHold(string $externalId): array
    {
        return $this->release;
    }

    public function getHoldStatus(string $externalId): array
    {
        return $this->status;
    }
}

$locacaoModel = new class extends LocacaoBloqueio {
    public array $bloqueios = [];
    public array $updates = [];

    public function __construct()
    {
    }

    public function listarLiberaveisPorLocacao(int $idLocacao, ?string $chave = null): array
    {
        return $this->bloqueios;
    }

    public function listarLiberaveisPorTenant(string $chave): array
    {
        return $this->bloqueios;
    }

    public function atualizarStatus(
        int $id,
        string $status,
        array $extras = [],
        ?string $chave = null
    ): int {
        $this->updates[] = compact('id', 'status', 'extras', 'chave');
        return 1;
    }
};

$contratoModel = new class extends ContratoBloqueio {
    public array $bloqueios = [];
    public array $updates = [];

    public function __construct()
    {
    }

    public function listarLiberaveisPorContrato(int $idContrato, ?string $chave = null): array
    {
        return $this->bloqueios;
    }

    public function listarLiberaveisPorTenant(string $chave): array
    {
        return $this->bloqueios;
    }

    public function atualizarStatus(
        int $id,
        string $status,
        array $extras = [],
        ?string $chave = null
    ): int {
        $this->updates[] = compact('id', 'status', 'extras', 'chave');
        return 1;
    }
};

$gatewayModel = new class extends GatewayPagamento {
    public function __construct()
    {
    }

    public function buscarPorIdComCredenciaisParaTenant(int $id, string $chave): ?array
    {
        return [
            'id' => $id,
            'gateway_code' => 'stripe',
            'credentials' => [],
            'ambiente' => 'sandbox',
        ];
    }

    public function buscarPorIdComCredenciais(int $id): ?array
    {
        return $this->buscarPorIdComCredenciaisParaTenant($id, 'TEST');
    }
};

$gateways = [];
$service = new AuthorizationHoldReleaseService(
    $locacaoModel,
    $contratoModel,
    $gatewayModel,
    static function (array $config) use (&$gateways) {
        return $gateways[(int) $config['id']];
    }
);

$locacaoModel->bloqueios = [[
    'id' => 10,
    'id_gateway' => 1,
    'external_id' => 'pi_release',
    'valor' => 1500,
]];
$gateways[1] = new GatewayHoldFake([
    'success' => true,
    'status' => 'released',
    'raw' => ['id' => 'pi_release'],
]);

$resultado = $service->liberarDaLocacao(100, 'TENANT_32_CHARACTERS_1234567890');
verificarHold($resultado['released'] === 1 && $resultado['failed'] === 0, 'liberacao confirmada e contabilizada');
verificarHold(
    ($locacaoModel->updates[0]['status'] ?? null) === 'released'
    && ($locacaoModel->updates[0]['chave'] ?? null) === 'TENANT_32_CHARACTERS_1234567890',
    'status local e atualizado com a chave explicita'
);

$locacaoModel->updates = [];
$locacaoModel->bloqueios = [[
    'id' => 11,
    'id_gateway' => 2,
    'external_id' => 'pi_cancelled',
    'valor' => 500,
]];
$gateways[2] = new GatewayHoldFake(
    ['success' => false, 'message' => 'already cancelled'],
    ['success' => true, 'status' => 'cancelled', 'raw' => ['id' => 'pi_cancelled']]
);

$resultado = $service->liberarDaLocacao(100, 'TENANT');
verificarHold($resultado['already_safe'] === 1, 'bloqueio ja cancelado e considerado seguro');
verificarHold(
    ($locacaoModel->updates[0]['status'] ?? null) === 'released',
    'cancelamento remoto e reconciliado como released'
);

$locacaoModel->updates = [];
$locacaoModel->bloqueios = [[
    'id' => 12,
    'id_gateway' => 3,
    'external_id' => 'pi_active',
    'valor' => 700,
]];
$gateways[3] = new GatewayHoldFake(
    ['success' => false],
    ['success' => true, 'status' => 'authorized']
);

try {
    $service->liberarDaLocacao(100, 'TENANT');
    verificarHold(false, 'bloqueio ainda ativo deveria impedir exclusao');
} catch (AuthorizationHoldReleaseException $e) {
    verificarHold(
        ($e->getResult()['failed'] ?? 0) === 1 && $locacaoModel->updates === [],
        'falha estrita preserva o registro local'
    );
}

$contratoModel->bloqueios = [[
    'id' => 20,
    'id_gateway' => 4,
    'external_id' => 'pi_paid',
    'valor' => 900,
]];
$gateways[4] = new GatewayHoldFake(
    ['success' => false],
    ['success' => true, 'status' => 'paid', 'captured_amount' => 900]
);

$resultado = $service->liberarDoContrato(200, 'TENANT');
verificarHold($resultado['already_safe'] === 1, 'bloqueio capturado nao impede exclusao');
verificarHold(
    ($contratoModel->updates[0]['status'] ?? null) === 'captured',
    'captura remota e reconciliada localmente'
);

$locacaoModel->bloqueios = [[
    'id' => 13,
    'id_gateway' => 3,
    'external_id' => 'pi_active',
    'valor' => 700,
]];
$contratoModel->bloqueios = [];
$resultado = $service->liberarDoTenant('TENANT');
verificarHold(
    $resultado['failed'] === 1,
    'modo WHMCS retorna a falha sem interromper o processamento'
);

exit($falhas > 0 ? 1 : 0);
