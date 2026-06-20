<?php

namespace App\Services;

use App\Models\SerproSaldo;
use App\Models\SerproTransacao;
use App\Models\Model;

/**
 * Service para gestao de saldo prepago de consultas online
 *
 * Responsavel por:
 * - Calcular precos (SERPRO + markup)
 * - Debitar saldo para consultas/eventos
 * - Creditar saldo para recargas
 * - Verificar e disparar auto-recarga Stripe
 *
 * Operacoes de saldo sao transacionais (SELECT FOR UPDATE)
 * para evitar race conditions.
 */
class SerproSaldoService
{
    private SerproSaldo $saldoModel;
    private SerproTransacao $transacaoModel;

    public function __construct()
    {
        $this->saldoModel = new SerproSaldo();
        $this->transacaoModel = new SerproTransacao();
    }

    // =========================================================================
    // PRECOS
    // =========================================================================

    /**
     * Retorna preco de uma consulta para o tenant (SERPRO + markup)
     */
    public function getPrecoConsulta(): float
    {
        $precoSerpro = (float) env('SERPRO_PRECO_CONSULTA', 0.43);
        $markup = (float) env('SERPRO_MARKUP_PERCENT', 10);

        return round($precoSerpro * (1 + $markup / 100), 2);
    }

    /**
     * Retorna preco de um evento para o tenant (SERPRO + markup)
     */
    public function getPrecoEvento(): float
    {
        $precoSerpro = (float) env('SERPRO_PRECO_EVENTO', 1.07);
        $markup = (float) env('SERPRO_MARKUP_PERCENT', 10);

        return round($precoSerpro * (1 + $markup / 100), 2);
    }

    /**
     * Retorna preco de uma indicacao para o tenant (SERPRO + markup)
     */
    public function getPrecoIndicacao(): float
    {
        $precoSerpro = (float) env('SERPRO_PRECO_INDICACAO', 1.07);
        $markup = (float) env('SERPRO_MARKUP_PERCENT', 10);

        return round($precoSerpro * (1 + $markup / 100), 2);
    }

    /**
     * Retorna preco de consulta de dados do veiculo (SERPRO + markup)
     */
    public function getPrecoConsultaDadosVeiculo(): float
    {
        return $this->calcularPrecoTotal($this->getPrecoSerproConsultaDadosVeiculo());
    }

    /**
     * Retorna preco de consulta CRLV (SERPRO + markup)
     */
    public function getPrecoConsultaCrlv(): float
    {
        return $this->calcularPrecoTotal($this->getPrecoSerproConsultaCrlv());
    }

    /**
     * Retorna preco SERPRO puro de uma consulta (sem markup)
     */
    public function getPrecoSerproConsulta(): float
    {
        return (float) env('SERPRO_PRECO_CONSULTA', 0.43);
    }

    /**
     * Retorna preco SERPRO puro de um evento (sem markup)
     */
    public function getPrecoSerproEvento(): float
    {
        return (float) env('SERPRO_PRECO_EVENTO', 1.07);
    }

    /**
     * Retorna preco SERPRO puro de uma indicacao (sem markup)
     */
    public function getPrecoSerproIndicacao(): float
    {
        return (float) env('SERPRO_PRECO_INDICACAO', 1.07);
    }

    /**
     * Retorna preco SERPRO puro da consulta de dados do veiculo (sem markup)
     */
    public function getPrecoSerproConsultaDadosVeiculo(): float
    {
        return $this->getPrecoSerproObrigatorio('SERPRO_PRECO_COSULTA_DADOSVEICULO');
    }

    /**
     * Retorna preco SERPRO puro da consulta CRLV (sem markup)
     */
    public function getPrecoSerproConsultaCrlv(): float
    {
        return $this->getPrecoSerproObrigatorio('SERPRO_PRECO_COSULTA_CRLV');
    }

    /**
     * Retorna o markup em porcentagem
     */
    public function getMarkupPercent(): float
    {
        return (float) env('SERPRO_MARKUP_PERCENT', 10);
    }

    /**
     * Retorna valor minimo de recarga
     */
    public function getRecargaMinima(): float
    {
        return (float) env('SERPRO_RECARGA_MINIMA', 100.00);
    }

    private function calcularPrecoTotal(float $precoSerpro): float
    {
        return round($precoSerpro * (1 + $this->getMarkupPercent() / 100), 2);
    }

    private function getPrecoSerproObrigatorio(string $envName): float
    {
        $valor = env($envName, null);
        if ($valor === null || $valor === '') {
            throw new \RuntimeException("Configuracao {$envName} nao encontrada.");
        }

        $preco = (float) str_replace(',', '.', (string) $valor);
        if ($preco <= 0) {
            throw new \RuntimeException("Configuracao {$envName} invalida.");
        }

        return $preco;
    }

    // =========================================================================
    // SALDO
    // =========================================================================

    /**
     * Retorna saldo atual do tenant
     */
    public function getSaldo(): float
    {
        return $this->saldoModel->getSaldo();
    }

    /**
     * Verifica se tenant tem saldo para N consultas
     */
    public function temSaldoParaConsultas(int $quantidade = 1): bool
    {
        $valorNecessario = $this->getPrecoConsulta() * $quantidade;
        return $this->saldoModel->temSaldoSuficiente($valorNecessario);
    }

    /**
     * Verifica se tenant tem saldo para N eventos
     */
    public function temSaldoParaEventos(int $quantidade = 1): bool
    {
        $valorNecessario = $this->getPrecoEvento() * $quantidade;
        return $this->saldoModel->temSaldoSuficiente($valorNecessario);
    }

    /**
     * Verifica se tenant tem saldo para N indicacoes
     */
    public function temSaldoParaIndicacoes(int $quantidade = 1): bool
    {
        $valorNecessario = $this->getPrecoIndicacao() * $quantidade;
        return $this->saldoModel->temSaldoSuficiente($valorNecessario);
    }

    /**
     * Verifica se tenant tem saldo para consulta de dados do veiculo
     */
    public function temSaldoParaConsultaDadosVeiculo(): bool
    {
        return $this->saldoModel->temSaldoSuficiente($this->getPrecoConsultaDadosVeiculo());
    }

    /**
     * Verifica se tenant tem saldo para consulta CRLV
     */
    public function temSaldoParaConsultaCrlv(): bool
    {
        return $this->saldoModel->temSaldoSuficiente($this->getPrecoConsultaCrlv());
    }

    /**
     * Garante que registro de saldo existe para o tenant
     */
    public function inicializarSaldo(): int
    {
        return $this->saldoModel->criarSeNaoExiste();
    }

    // =========================================================================
    // DEBITO (CONSULTAS E EVENTOS)
    // =========================================================================

    /**
     * Debita saldo por uma consulta SERPRO
     *
     * @param string $descricao Ex: "Consulta infracoes placa ABC1D23"
     * @param string|null $referencia Ex: "ABC1D23" (placa)
     * @return array ['transacao_id' => int, 'saldo_anterior' => float, 'saldo_posterior' => float]
     * @throws \RuntimeException Se saldo insuficiente
     */
    public function debitarConsulta(string $descricao, ?string $referencia = null): array
    {
        $precoSerpro = $this->getPrecoSerproConsulta();
        $precoTotal = $this->getPrecoConsulta();
        $markup = round($precoTotal - $precoSerpro, 4);

        return $this->debitar('consulta', $precoSerpro, $markup, $precoTotal, $descricao, $referencia);
    }

    /**
     * Debita saldo por um evento SERPRO
     *
     * @param string $descricao Ex: "Evento: nova multa detectada placa ABC1D23"
     * @param string|null $referencia Ex: "ABC1D23" (placa)
     * @return array ['transacao_id' => int, 'saldo_anterior' => float, 'saldo_posterior' => float]
     * @throws \RuntimeException Se saldo insuficiente
     */
    public function debitarEvento(string $descricao, ?string $referencia = null): array
    {
        $precoSerpro = $this->getPrecoSerproEvento();
        $precoTotal = $this->getPrecoEvento();
        $markup = round($precoTotal - $precoSerpro, 4);

        return $this->debitar('evento', $precoSerpro, $markup, $precoTotal, $descricao, $referencia);
    }

    /**
     * Debita saldo por uma indicacao SERPRO
     *
     * @param string $descricao Ex: "Indicacao principal condutor placa ABC1D23"
     * @param string|null $referencia Ex: "ABC1D23" ou chave da indicacao
     * @return array ['transacao_id' => int, 'saldo_anterior' => float, 'saldo_posterior' => float]
     * @throws \RuntimeException Se saldo insuficiente
     */
    public function debitarIndicacao(string $descricao, ?string $referencia = null): array
    {
        $precoSerpro = $this->getPrecoSerproIndicacao();
        $precoTotal = $this->getPrecoIndicacao();
        $markup = round($precoTotal - $precoSerpro, 4);

        return $this->debitar('indicacao', $precoSerpro, $markup, $precoTotal, $descricao, $referencia);
    }

    /**
     * Debita saldo por consulta de dados do veiculo.
     */
    public function debitarConsultaDadosVeiculo(string $descricao, ?string $referencia = null): array
    {
        $precoSerpro = $this->getPrecoSerproConsultaDadosVeiculo();
        $precoTotal = $this->getPrecoConsultaDadosVeiculo();
        $markup = round($precoTotal - $precoSerpro, 4);

        return $this->debitar('consulta', $precoSerpro, $markup, $precoTotal, $descricao, $referencia);
    }

    /**
     * Debita saldo por consulta de CRLV.
     */
    public function debitarConsultaCrlv(string $descricao, ?string $referencia = null): array
    {
        $precoSerpro = $this->getPrecoSerproConsultaCrlv();
        $precoTotal = $this->getPrecoConsultaCrlv();
        $markup = round($precoTotal - $precoSerpro, 4);

        return $this->debitar('consulta', $precoSerpro, $markup, $precoTotal, $descricao, $referencia);
    }

    /**
     * Executa debito transacional
     *
     * @throws \RuntimeException Se saldo insuficiente
     */
    private function debitar(
        string $tipo,
        float $valorSerpro,
        float $valorMarkup,
        float $valorTotal,
        string $descricao,
        ?string $referencia
    ): array {
        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();

        try {
            // Debita saldo com lock (FOR UPDATE)
            $saldos = $this->saldoModel->debitar($valorTotal);

            // Registra transacao
            $transacaoId = $this->transacaoModel->criarDebito(
                $tipo,
                $valorSerpro,
                $valorMarkup,
                $valorTotal,
                $saldos['saldo_anterior'],
                $saldos['saldo_posterior'],
                $descricao,
                $referencia
            );

            $mysqli->commit();

            // Verifica auto-recarga apos debito (fora da transacao principal)
            $this->verificarAutoRecarga();

            return [
                'transacao_id' => $transacaoId,
                'saldo_anterior' => $saldos['saldo_anterior'],
                'saldo_posterior' => $saldos['saldo_posterior'],
            ];
        } catch (\Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Estorna um debito confirmado do tenant atual.
     *
     * Usado quando a Consulta Online rejeita/falha depois do debito local.
     */
    public function estornarDebito(int $transacaoId): ?array
    {
        $transacao = $this->transacaoModel->buscarPorId($transacaoId);

        if (!$transacao) {
            throw new \RuntimeException('Transacao de debito nao encontrada para estorno');
        }

        if ($transacao['status'] === 'estornado') {
            return null;
        }

        if ($transacao['status'] !== 'confirmado') {
            throw new \RuntimeException('Somente transacoes confirmadas podem ser estornadas');
        }

        if (!in_array($transacao['tipo'], ['consulta', 'evento', 'indicacao'], true)) {
            throw new \RuntimeException('Tipo de transacao nao permitido para estorno automatico');
        }

        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();

        try {
            $saldos = $this->saldoModel->creditar((float) $transacao['valor_total']);
            $atualizadas = $this->transacaoModel->marcarEstornado($transacaoId);

            if ($atualizadas < 1) {
                throw new \RuntimeException('Nao foi possivel marcar a transacao como estornada');
            }

            $mysqli->commit();

            return [
                'saldo_anterior' => $saldos['saldo_anterior'],
                'saldo_posterior' => $saldos['saldo_posterior'],
            ];
        } catch (\Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    // =========================================================================
    // CREDITO (RECARGAS)
    // =========================================================================

    /**
     * Credita saldo por recarga confirmada (chamado por webhook)
     *
     * @param int $transacaoId ID da transacao pendente
     * @param string|null $chaveOverride Chave do tenant (para webhook cross-tenant)
     * @return array ['saldo_anterior' => float, 'saldo_posterior' => float]
     */
    public function confirmarRecarga(int $transacaoId, ?string $chaveOverride = null): array
    {
        // Buscar transacao pendente
        $transacao = $this->transacaoModel->buscarPorId($transacaoId);

        if (!$transacao) {
            throw new \RuntimeException('Transacao nao encontrada');
        }

        if ($transacao['status'] === 'confirmado') {
            // Ja confirmada (idempotencia)
            return [
                'saldo_anterior' => (float) $transacao['saldo_anterior'],
                'saldo_posterior' => (float) $transacao['saldo_posterior'],
            ];
        }

        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();

        try {
            $valor = (float) $transacao['valor_total'];
            $saldos = $this->saldoModel->creditar($valor, $chaveOverride);

            // Atualiza transacao com saldos corretos
            $this->transacaoModel->confirmarRecarga(
                $transacaoId,
                $saldos['saldo_anterior'],
                $saldos['saldo_posterior']
            );

            $mysqli->commit();

            return $saldos;
        } catch (\Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Confirma recarga por external_id (para webhook PIX/Stripe)
     * Verifica idempotencia pelo external_id
     *
     * @return array|null Retorna saldos ou null se ja processado
     */
    public function confirmarRecargaPorExternalId(string $externalId, ?string $chaveOverride = null): ?array
    {
        $transacao = $this->transacaoModel->buscarPorExternalId($externalId);

        if (!$transacao) {
            return null; // Transacao nao encontrada
        }

        if ($transacao['status'] === 'confirmado') {
            return null; // Ja confirmada (idempotencia)
        }

        return $this->confirmarRecarga((int) $transacao['id'], $chaveOverride ?? $transacao['chave']);
    }

    /**
     * Confirma recarga PIX por codigoSolicitacao ou txid do Banco Inter.
     */
    public function confirmarRecargaPixPorIdentificador(string $identificador, ?string $chaveOverride = null): ?array
    {
        $transacao = $this->transacaoModel->buscarRecargaPixPorIdentificador($identificador);

        if (!$transacao) {
            return null;
        }

        if ($transacao['status'] === 'confirmado') {
            return null;
        }

        return $this->confirmarRecarga((int) $transacao['id'], $chaveOverride ?? $transacao['chave']);
    }

    // =========================================================================
    // AUTO-RECARGA
    // =========================================================================

    /**
     * Verifica se auto-recarga deve ser disparada e executa
     *
     * Chamado apos cada debito. Se saldo caiu abaixo do limite
     * e auto-recarga esta ativa, cobra o cartao salvo via Stripe.
     */
    public function verificarAutoRecarga(): void
    {
        $autoRecarga = $this->saldoModel->verificarAutoRecarga();

        if ($autoRecarga === null) {
            return;
        }

        try {
            // Cria transacao de recarga pendente
            $transacaoId = $this->transacaoModel->criarRecarga(
                'recarga_cartao',
                $autoRecarga['valor'],
                'Auto-recarga (saldo abaixo de R$ ' . number_format($autoRecarga['saldo_atual'], 2, ',', '.') . ')',
                null,
                'credit_card'
            );

            // Cobra via Stripe usando payment method salvo
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int) ($autoRecarga['valor'] * 100), // centavos
                'currency' => 'brl',
                'customer' => $autoRecarga['stripe_customer_id'],
                'payment_method' => $autoRecarga['stripe_payment_method_id'],
                'off_session' => true,
                'confirm' => true,
                'description' => 'Auto-recarga de consultas online - 7Carros',
                'metadata' => [
                    'transacao_id' => $transacaoId,
                    'tipo' => 'auto_recarga_serpro',
                ],
            ]);

            if ($paymentIntent->status === 'succeeded') {
                $this->confirmarRecarga($transacaoId);
            }
        } catch (\Exception $e) {
            // Falha na auto-recarga nao deve bloquear operacao principal
            error_log('SerproSaldoService::verificarAutoRecarga - Erro: ' . $e->getMessage());

            if (isset($transacaoId)) {
                $this->transacaoModel->marcarFalha($transacaoId);
            }
        }
    }
}
