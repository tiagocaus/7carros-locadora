<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\LocacaoBloqueio;
use App\Models\ContratoBloqueio;
use App\Models\ClienteCartao;
use App\Models\GatewayPagamento;
use App\Services\Gateways\GatewayFactory;
use App\Services\Gateways\AuthorizationHoldInterface;
use mysqli;

/**
 * Job: Rotacao automatica de authorization holds
 *
 * Holds no Stripe expiram em 7 dias (ou 31 com extended).
 * Este job verifica holds proximos de expirar (2 dias antes)
 * e faz a rotacao: captura o hold atual e cria um novo.
 * Isso mantem o bloqueio ativo indefinidamente para locacoes longas.
 *
 * Tambem atualiza status de holds expirados.
 */
class RotateAuthorizationHoldsJob extends BaseJob
{
    protected string $name = 'RotateAuthorizationHolds';
    protected string $description = 'Rotaciona holds proximos de expirar e atualiza status de expirados';

    /** Dias antes da expiracao para rotacionar */
    private const DAYS_BEFORE_EXPIRY = 2;

    protected function handle(): array
    {
        $mysqli = new mysqli(
            Database::env('DB_HOST'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );
        $mysqli->set_charset('utf8mb4');

        $qb = new QueryBuilder($mysqli);
        $qb->withoutChave();

        $rotacionados = 0;
        $expirados = 0;
        $erros = [];

        // 1. Buscar holds que expiram nos proximos DAYS_BEFORE_EXPIRY dias
        $limiteExpiracao = \App\Helpers\DateHelper::addDaysForDatabase(self::DAYS_BEFORE_EXPIRY, null, 'Y-m-d H:i:s');
        $agora = now();

        $holdsParaRotacionar = $qb->withoutChave()
            ->table('locacoes_bloqueios')
            ->select(['*'])
            ->where('status', '=', 'authorized')
            ->whereRaw('expira_em IS NOT NULL')
            ->whereRaw('expira_em <= ?', [$limiteExpiracao])
            ->whereRaw('expira_em > ?', [$agora])
            ->get();

        $this->log("Encontrados " . count($holdsParaRotacionar) . " holds para rotacionar");

        foreach ($holdsParaRotacionar as $hold) {
            try {
                $this->setContextoTenant($hold['chave']);
                $this->rotacionarHold($hold);
                $rotacionados++;
                $this->log("Hold #{$hold['id']} (locacao #{$hold['id_locacao']}) rotacionado");
            } catch (\Exception $e) {
                $erros[] = [
                    'hold_id' => $hold['id'],
                    'locacao_id' => $hold['id_locacao'],
                    'erro' => $e->getMessage(),
                ];
                $this->log("Erro ao rotacionar hold #{$hold['id']}: {$e->getMessage()}", 'ERROR');
            } finally {
                $this->limparContextoTenant();
            }
        }

        // 2. Marcar holds expirados (expira_em < agora e status ainda authorized) - Locacoes
        $holdsExpirados = $qb->withoutChave()
            ->table('locacoes_bloqueios')
            ->select(['id', 'id_locacao', 'chave'])
            ->where('status', '=', 'authorized')
            ->whereRaw('expira_em IS NOT NULL')
            ->whereRaw('expira_em < ?', [$agora])
            ->get();

        foreach ($holdsExpirados as $hold) {
            $qb->withoutChave()
                ->table('locacoes_bloqueios')
                ->where('id', '=', $hold['id'])
                ->update(['status' => 'expired']);
            $expirados++;
            $this->log("Hold #{$hold['id']} (locacao) marcado como expirado");
        }

        // 3. Buscar holds de CONTRATOS que expiram nos proximos DAYS_BEFORE_EXPIRY dias
        $holdsContratosRotacionar = $qb->withoutChave()
            ->table('contratos_bloqueios')
            ->select(['*'])
            ->where('status', '=', 'authorized')
            ->whereRaw('expira_em IS NOT NULL')
            ->whereRaw('expira_em <= ?', [$limiteExpiracao])
            ->whereRaw('expira_em > ?', [$agora])
            ->get();

        $this->log("Encontrados " . count($holdsContratosRotacionar) . " holds de contratos para rotacionar");

        foreach ($holdsContratosRotacionar as $hold) {
            try {
                $this->setContextoTenant($hold['chave']);
                $this->rotacionarHoldContrato($hold);
                $rotacionados++;
                $this->log("Hold #{$hold['id']} (contrato #{$hold['id_contrato']}) rotacionado");
            } catch (\Exception $e) {
                $erros[] = [
                    'hold_id' => $hold['id'],
                    'contrato_id' => $hold['id_contrato'],
                    'erro' => $e->getMessage(),
                ];
                $this->log("Erro ao rotacionar hold #{$hold['id']} (contrato): {$e->getMessage()}", 'ERROR');
            } finally {
                $this->limparContextoTenant();
            }
        }

        // 4. Marcar holds expirados - Contratos
        $holdsContratosExpirados = $qb->withoutChave()
            ->table('contratos_bloqueios')
            ->select(['id', 'id_contrato', 'chave'])
            ->where('status', '=', 'authorized')
            ->whereRaw('expira_em IS NOT NULL')
            ->whereRaw('expira_em < ?', [$agora])
            ->get();

        foreach ($holdsContratosExpirados as $hold) {
            $qb->withoutChave()
                ->table('contratos_bloqueios')
                ->where('id', '=', $hold['id'])
                ->update(['status' => 'expired']);
            $expirados++;
            $this->log("Hold #{$hold['id']} (contrato) marcado como expirado");
        }

        $this->log("Finalizado: {$rotacionados} rotacionados, {$expirados} expirados, " . count($erros) . " erros");

        return [
            'success' => empty($erros),
            'status' => empty($erros)
                ? self::STATUS_SUCCESS
                : (($rotacionados + $expirados) > 0 ? self::STATUS_PARTIAL : self::STATUS_FAILED),
            'message' => "{$rotacionados} hold(s) rotacionado(s), {$expirados} expirado(s)",
            'data' => [
                'rotacionados' => $rotacionados,
                'expirados' => $expirados,
                'erros' => $erros,
            ],
        ];
    }

    /**
     * Rotaciona um hold: libera o atual e cria um novo
     */
    private function rotacionarHold(array $hold): void
    {
        // Buscar cartao e gateway
        $cartaoModel = new ClienteCartao();
        $cartao = $cartaoModel->buscarPorId((int) $hold['id_cartao']);
        if (!$cartao) {
            throw new \RuntimeException("Cartao #{$hold['id_cartao']} nao encontrado");
        }

        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $hold['id_gateway']);
        if (!$gatewayConfig) {
            throw new \RuntimeException("Gateway #{$hold['id_gateway']} nao encontrado");
        }

        $gateway = GatewayFactory::create(
            $gatewayConfig['gateway_code'],
            $gatewayConfig['credentials'] ?? [],
            $gatewayConfig['ambiente'] === 'sandbox',
            (int) $gatewayConfig['id']
        );

        if (!($gateway instanceof AuthorizationHoldInterface)) {
            throw new \RuntimeException("Gateway nao suporta authorization holds");
        }

        // 1. Liberar o hold atual
        $releaseResult = $gateway->releaseHold($hold['external_id']);
        if (!$releaseResult['success']) {
            throw new \RuntimeException("Falha ao liberar hold: " . ($releaseResult['message'] ?? 'erro desconhecido'));
        }

        // Atualizar status do hold antigo
        $bloqueioModel = new LocacaoBloqueio();
        $bloqueioModel->atualizarStatus((int) $hold['id'], 'released', [
            'liberado_em' => now(),
            'payload' => $releaseResult['raw'] ?? null,
        ]);

        // 2. Criar novo hold com mesmo valor
        $createResult = $gateway->createHold([
            'chave' => $hold['chave'],
            'payment_method_id' => $cartao['token'],
            'id_cartao_registro' => (int) $hold['id_cartao'],
            'amount' => (float) $hold['valor'],
            'currency' => $hold['moeda'] ?? 'brl',
            'description' => 'Bloqueio (rotacao automatica) - Locacao #' . $hold['id_locacao'],
            'metadata' => [
                'id_locacao' => $hold['id_locacao'],
                'id_cliente' => $hold['id_cliente'],
                'rotacao_de' => $hold['id'],
            ],
        ]);

        if (!$createResult['success']) {
            throw new \RuntimeException("Falha ao criar novo hold: " . ($createResult['message'] ?? 'erro desconhecido'));
        }

        // 3. Salvar novo hold
        $novoId = $bloqueioModel->criar([
            'chave' => $hold['chave'],
            'id_locacao' => (int) $hold['id_locacao'],
            'id_cliente' => (int) $hold['id_cliente'],
            'id_cartao' => (int) $hold['id_cartao'],
            'id_gateway' => (int) $hold['id_gateway'],
            'gateway_code' => $hold['gateway_code'],
            'external_id' => $createResult['external_id'],
            'valor' => (float) $hold['valor'],
            'moeda' => $hold['moeda'] ?? 'BRL',
            'status' => $createResult['status'] === 'authorized' ? 'authorized' : 'pending',
            'autorizado_em' => $createResult['status'] === 'authorized' ? now() : null,
            'expira_em' => $createResult['expires_at'] ?? null,
            'payload' => $createResult['raw'] ?? null,
        ]);

        // 4. Atualizar referencia na locacao
        $locacaoQb = new QueryBuilder(self::getMysqli());
        $locacaoQb->table('locacoes')
            ->where('id', '=', $hold['id_locacao'])
            ->update(['id_bloqueio_ativo' => $novoId]);
    }

    /**
     * Rotaciona um hold de contrato: libera o atual e cria um novo
     */
    private function rotacionarHoldContrato(array $hold): void
    {
        $cartaoModel = new ClienteCartao();
        $cartao = $cartaoModel->buscarPorId((int) $hold['id_cartao']);
        if (!$cartao) {
            throw new \RuntimeException("Cartao #{$hold['id_cartao']} nao encontrado");
        }

        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $hold['id_gateway']);
        if (!$gatewayConfig) {
            throw new \RuntimeException("Gateway #{$hold['id_gateway']} nao encontrado");
        }

        $gateway = GatewayFactory::create(
            $gatewayConfig['gateway_code'],
            $gatewayConfig['credentials'] ?? [],
            $gatewayConfig['ambiente'] === 'sandbox',
            (int) $gatewayConfig['id']
        );

        if (!($gateway instanceof AuthorizationHoldInterface)) {
            throw new \RuntimeException("Gateway nao suporta authorization holds");
        }

        // 1. Liberar o hold atual
        $releaseResult = $gateway->releaseHold($hold['external_id']);
        if (!$releaseResult['success']) {
            throw new \RuntimeException("Falha ao liberar hold: " . ($releaseResult['message'] ?? 'erro desconhecido'));
        }

        $bloqueioModel = new ContratoBloqueio();
        $bloqueioModel->atualizarStatus((int) $hold['id'], 'released', [
            'liberado_em' => now(),
            'payload' => $releaseResult['raw'] ?? null,
        ]);

        // 2. Criar novo hold com mesmo valor
        $createResult = $gateway->createHold([
            'chave' => $hold['chave'],
            'payment_method_id' => $cartao['token'],
            'id_cartao_registro' => (int) $hold['id_cartao'],
            'amount' => (float) $hold['valor'],
            'currency' => $hold['moeda'] ?? 'brl',
            'description' => 'Bloqueio (rotacao automatica) - Contrato #' . $hold['id_contrato'],
            'metadata' => [
                'id_contrato' => $hold['id_contrato'],
                'id_cliente' => $hold['id_cliente'],
                'rotacao_de' => $hold['id'],
            ],
        ]);

        if (!$createResult['success']) {
            throw new \RuntimeException("Falha ao criar novo hold: " . ($createResult['message'] ?? 'erro desconhecido'));
        }

        // 3. Salvar novo hold
        $novoId = $bloqueioModel->criar([
            'chave' => $hold['chave'],
            'id_contrato' => (int) $hold['id_contrato'],
            'id_cliente' => (int) $hold['id_cliente'],
            'id_cartao' => (int) $hold['id_cartao'],
            'id_gateway' => (int) $hold['id_gateway'],
            'gateway_code' => $hold['gateway_code'],
            'external_id' => $createResult['external_id'],
            'valor' => (float) $hold['valor'],
            'moeda' => $hold['moeda'] ?? 'BRL',
            'status' => $createResult['status'] === 'authorized' ? 'authorized' : 'pending',
            'autorizado_em' => $createResult['status'] === 'authorized' ? now() : null,
            'expira_em' => $createResult['expires_at'] ?? null,
            'payload' => $createResult['raw'] ?? null,
        ]);

        // 4. Atualizar referencia no contrato
        $contratoQb = new QueryBuilder(self::getMysqli());
        $contratoQb->table('contratos')
            ->where('id', '=', $hold['id_contrato'])
            ->update(['id_bloqueio_ativo' => $novoId]);
    }

    private static ?mysqli $mysqli = null;

    private static function getMysqli(): mysqli
    {
        if (self::$mysqli === null || !self::$mysqli->ping()) {
            self::$mysqli = new mysqli(
                Database::env('DB_HOST'),
                Database::env('DB_USERNAME'),
                Database::env('DB_PASSWORD'),
                Database::env('DB_DATABASE'),
                (int) Database::env('DB_PORT', '3306')
            );
            self::$mysqli->set_charset('utf8mb4');
        }
        return self::$mysqli;
    }

    private function setContextoTenant(string $chave): void
    {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Sistema';
    }

    private function limparContextoTenant(): void
    {
        unset($_SESSION['chave'], $_SESSION['user_id'], $_SESSION['user_name']);
    }
}
