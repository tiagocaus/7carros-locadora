<?php

namespace App\Services;

use App\Exceptions\AuthorizationHoldReleaseException;
use App\Helpers\DateHelper;
use App\Models\ContratoBloqueio;
use App\Models\GatewayPagamento;
use App\Models\LocacaoBloqueio;
use App\Services\Gateways\AuthorizationHoldInterface;
use App\Services\Gateways\GatewayFactory;
use Closure;
use Throwable;

/**
 * Centraliza a liberacao segura de pre-autorizacoes no gateway.
 */
class AuthorizationHoldReleaseService
{
    private Closure $gatewayResolver;

    public function __construct(
        private readonly ?LocacaoBloqueio $locacaoBloqueioModel = null,
        private readonly ?ContratoBloqueio $contratoBloqueioModel = null,
        private readonly ?GatewayPagamento $gatewayModel = null,
        ?callable $gatewayResolver = null
    ) {
        $this->gatewayResolver = $gatewayResolver !== null
            ? Closure::fromCallable($gatewayResolver)
            : static fn(array $config) => GatewayFactory::create(
                $config['gateway_code'],
                $config['credentials'] ?? [],
                ($config['ambiente'] ?? 'producao') === 'sandbox',
                (int) $config['id']
            );
    }

    public function liberarDaLocacao(
        int $idLocacao,
        ?string $chave = null,
        bool $estrito = true
    ): array {
        $model = $this->locacaoBloqueioModel ?? new LocacaoBloqueio();
        $bloqueios = $model->listarLiberaveisPorLocacao($idLocacao, $chave);

        return $this->processar($bloqueios, 'locacao', $model, $chave, $estrito);
    }

    public function liberarDoContrato(
        int $idContrato,
        ?string $chave = null,
        bool $estrito = true
    ): array {
        $model = $this->contratoBloqueioModel ?? new ContratoBloqueio();
        $bloqueios = $model->listarLiberaveisPorContrato($idContrato, $chave);

        return $this->processar($bloqueios, 'contrato', $model, $chave, $estrito);
    }

    /**
     * Melhor esforco para encerramento completo de tenant.
     */
    public function liberarDoTenant(string $chave): array
    {
        $locacaoModel = $this->locacaoBloqueioModel ?? new LocacaoBloqueio();
        $contratoModel = $this->contratoBloqueioModel ?? new ContratoBloqueio();

        $locacoes = $this->processar(
            $locacaoModel->listarLiberaveisPorTenant($chave),
            'locacao',
            $locacaoModel,
            $chave,
            false
        );
        $contratos = $this->processar(
            $contratoModel->listarLiberaveisPorTenant($chave),
            'contrato',
            $contratoModel,
            $chave,
            false
        );

        return [
            'total' => $locacoes['total'] + $contratos['total'],
            'released' => $locacoes['released'] + $contratos['released'],
            'already_safe' => $locacoes['already_safe'] + $contratos['already_safe'],
            'failed' => $locacoes['failed'] + $contratos['failed'],
            'failures' => array_merge($locacoes['failures'], $contratos['failures']),
        ];
    }

    private function processar(
        array $bloqueios,
        string $origem,
        LocacaoBloqueio|ContratoBloqueio $model,
        ?string $chave,
        bool $estrito
    ): array {
        $result = [
            'total' => count($bloqueios),
            'released' => 0,
            'already_safe' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($bloqueios as $bloqueio) {
            try {
                $outcome = $this->liberarBloqueio($bloqueio, $model, $chave);
                $result[$outcome]++;
            } catch (Throwable $e) {
                $result['failed']++;
                $result['failures'][] = [
                    'origem' => $origem,
                    'id' => (int) ($bloqueio['id'] ?? 0),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($estrito && $result['failed'] > 0) {
            throw new AuthorizationHoldReleaseException($result);
        }

        return $result;
    }

    /**
     * @return string released|already_safe
     */
    private function liberarBloqueio(
        array $bloqueio,
        LocacaoBloqueio|ContratoBloqueio $model,
        ?string $chave
    ): string {
        $externalId = trim((string) ($bloqueio['external_id'] ?? ''));
        if ($externalId === '') {
            throw new \RuntimeException('Bloqueio sem identificador externo.');
        }

        $gatewayModel = $this->gatewayModel ?? new GatewayPagamento();
        $gatewayConfig = $chave !== null
            ? $gatewayModel->buscarPorIdComCredenciaisParaTenant((int) $bloqueio['id_gateway'], $chave)
            : $gatewayModel->buscarPorIdComCredenciais((int) $bloqueio['id_gateway']);

        if (!$gatewayConfig) {
            throw new \RuntimeException('Gateway do bloqueio nao encontrado.');
        }

        $gateway = ($this->gatewayResolver)($gatewayConfig);
        if (!$gateway instanceof AuthorizationHoldInterface || !$gateway->supportsAuthorizationHold()) {
            throw new \RuntimeException('Gateway nao suporta liberacao de bloqueio.');
        }

        $release = $gateway->releaseHold($externalId);
        if (($release['success'] ?? false) === true) {
            $model->atualizarStatus((int) $bloqueio['id'], 'released', [
                'liberado_em' => DateHelper::nowForDatabase(),
                'payload' => $release['raw'] ?? null,
            ], $chave);
            return 'released';
        }

        $remote = $gateway->getHoldStatus($externalId);
        if (($remote['success'] ?? false) !== true) {
            throw new \RuntimeException('Gateway nao confirmou a liberacao do bloqueio.');
        }

        $status = strtolower((string) ($remote['status'] ?? ''));
        if (in_array($status, ['released', 'cancelled', 'canceled', 'expired'], true)) {
            $localStatus = $status === 'expired' ? 'expired' : 'released';
            $extras = ['payload' => $remote['raw'] ?? null];
            if ($localStatus === 'released') {
                $extras['liberado_em'] = DateHelper::nowForDatabase();
            }
            $model->atualizarStatus((int) $bloqueio['id'], $localStatus, $extras, $chave);
            return 'already_safe';
        }

        if (in_array($status, ['captured', 'paid', 'succeeded'], true)) {
            $model->atualizarStatus((int) $bloqueio['id'], 'captured', [
                'capturado_em' => DateHelper::nowForDatabase(),
                'valor_capturado' => (float) ($remote['captured_amount'] ?? $bloqueio['valor'] ?? 0),
                'payload' => $remote['raw'] ?? null,
            ], $chave);
            return 'already_safe';
        }

        throw new \RuntimeException('Bloqueio permanece ativo no gateway.');
    }
}
