<?php

namespace App\Services;

use App\Models\Financeiro;
use App\Models\FinanceiroTransacao;
use App\Models\GatewayPagamento;
use App\Models\PagamentoLink;
use App\Services\Gateways\GatewayFactory;

/**
 * Mantem links publicos e cobrancas externas coerentes com o financeiro.
 */
class PagamentoLinkSyncService
{
    private const STATUS_TERMINAIS = ['paid', 'cancelled', 'refunded', 'failed', 'expired'];

    private PagamentoLink $linkModel;
    private FinanceiroTransacao $transacaoModel;
    private GatewayPagamento $gatewayModel;
    private Financeiro $financeiroModel;

    public function __construct()
    {
        $this->linkModel = new PagamentoLink();
        $this->transacaoModel = new FinanceiroTransacao();
        $this->gatewayModel = new GatewayPagamento();
        $this->financeiroModel = new Financeiro();
    }

    /**
     * Garante que o link publico reflete o valor atual do financeiro.
     *
     * @return array{url:string,link_id:int,created:bool}
     */
    public function obterOuCriarLinkAtualizado(int $idFinanceiro, string $chave, int $diasExpiracao = 30, array $dadosExtras = []): array
    {
        $financeiro = $this->buscarFinanceiroElegivel($idFinanceiro, $chave);
        $linkExistente = $this->linkModel->buscarReutilizavelPorFinanceiro($idFinanceiro);

        if ($linkExistente) {
            if (!$this->linkEstaAtualizado($linkExistente, $financeiro) && $this->temCobrancaAberta($idFinanceiro)) {
                $this->invalidarCobrancasExternasAbertas($idFinanceiro, $chave);
            }

            $this->sincronizarLinkComFinanceiro($linkExistente, $financeiro, $diasExpiracao, $dadosExtras);

            return [
                'url' => $this->linkModel->getUrl($linkExistente['codigo']),
                'link_id' => (int) $linkExistente['id'],
                'created' => false,
            ];
        }

        if ($this->temCobrancaAberta($idFinanceiro)) {
            $this->invalidarCobrancasExternasAbertas($idFinanceiro, $chave);
        }

        $linkId = $this->linkModel->criar([
            'chave' => $chave,
            'id_financeiro' => $idFinanceiro,
            'id_locacao' => $dadosExtras['id_locacao'] ?? null,
            'id_cliente' => $financeiro['id_cliente'] ?? null,
            'valor' => $financeiro['valor_total'],
            'descricao' => $dadosExtras['descricao'] ?? $financeiro['descricao'],
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$diasExpiracao} days")),
        ]);

        $novoLink = $this->linkModel->buscarPorId($linkId);
        if (!$novoLink) {
            throw new \RuntimeException('Link de pagamento criado, mas nao localizado para retorno.');
        }

        return [
            'url' => $this->linkModel->getUrl($novoLink['codigo']),
            'link_id' => $linkId,
            'created' => true,
        ];
    }

    /**
     * Cancela cobrancas externas abertas de uma receita pendente e mantem o
     * link publico reaproveitavel.
     *
     * @return array{links_cancelados:int,cobrancas_canceladas:int}
     */
    public function invalidarLinksPendentes(int $idFinanceiro, string $chave): array
    {
        $financeiro = $this->financeiroModel->buscarPorId($idFinanceiro);
        if (!$financeiro || ($financeiro['chave'] ?? null) !== $chave) {
            throw new \InvalidArgumentException('Lancamento nao encontrado para sincronizar link de pagamento.');
        }

        if (($financeiro['tipo'] ?? '') !== 'R' || ($financeiro['pago'] ?? 'N') === 'S') {
            return ['links_cancelados' => 0, 'cobrancas_canceladas' => 0];
        }

        $cobrancasCanceladas = $this->invalidarCobrancasExternasAbertas($idFinanceiro, $chave);
        $link = $this->linkModel->buscarReutilizavelPorFinanceiro($idFinanceiro);

        if ($link) {
            $this->sincronizarLinkComFinanceiro($link, $financeiro);
        }

        return [
            'links_cancelados' => 0,
            'cobrancas_canceladas' => $cobrancasCanceladas,
        ];
    }

    private function invalidarCobrancasExternasAbertas(int $idFinanceiro, string $chave): int
    {
        $transacoes = $this->transacaoModel->listarCobrancasAbertasPorFinanceiro($idFinanceiro);
        if (empty($transacoes)) {
            return 0;
        }

        $cobrancasCanceladas = 0;
        foreach ($transacoes as $transacao) {
            if ($this->cancelarCobrancaAberta($transacao, $chave)) {
                $cobrancasCanceladas++;
            }
        }

        return $cobrancasCanceladas;
    }

    /**
     * Invalida links quando algum campo que afeta cobranca foi enviado.
     */
    public function invalidarSeDadosAfetamCobranca(int $idFinanceiro, string $chave, array $dados): array
    {
        $financeiro = $this->financeiroModel->buscarPorId($idFinanceiro);
        if (!$financeiro || ($financeiro['chave'] ?? null) !== $chave) {
            throw new \InvalidArgumentException('Lancamento nao encontrado para sincronizar link de pagamento.');
        }

        if (($financeiro['tipo'] ?? '') !== 'R' || ($financeiro['pago'] ?? 'N') === 'S') {
            return ['links_cancelados' => 0, 'cobrancas_canceladas' => 0];
        }

        if ($this->dadosAlteramCobranca($financeiro, $dados, $idFinanceiro)) {
            return $this->invalidarLinksPendentes($idFinanceiro, $chave);
        }

        return ['links_cancelados' => 0, 'cobrancas_canceladas' => 0];
    }

    private function dadosAlteramCobranca(array $financeiro, array $dados, int $idFinanceiro): bool
    {
        foreach (['valor_subtotal', 'valor_total', 'juros', 'multa', 'desconto'] as $campo) {
            if (array_key_exists($campo, $dados) && $this->valorMudou($financeiro[$campo] ?? 0, $dados[$campo])) {
                return true;
            }
        }

        foreach (['id_forma_pagamento', 'id_cliente'] as $campo) {
            if (array_key_exists($campo, $dados) && (string) ($financeiro[$campo] ?? '') !== (string) ($dados[$campo] ?? '')) {
                return true;
            }
        }

        if (array_key_exists('data_venci', $dados) && (string) ($financeiro['data_venci'] ?? '') !== (string) ($dados['data_venci'] ?? '')) {
            return true;
        }

        if (array_key_exists('pago', $dados) && ($financeiro['pago'] ?? 'N') !== ($dados['pago'] ?? 'N')) {
            return true;
        }

        if (array_key_exists('itens', $dados) && is_array($dados['itens'])) {
            $somaItens = $this->somarItensEnviados($dados['itens']);
            return abs($somaItens - (float) ($financeiro['valor_subtotal'] ?? 0)) > 0.009;
        }

        return false;
    }

    private function valorMudou(mixed $valorAtual, mixed $valorNovo): bool
    {
        return abs(currency_parse($valorAtual) - currency_parse($valorNovo)) > 0.009;
    }

    private function somarItensEnviados(array $itens): float
    {
        $total = 0.0;
        foreach ($itens as $item) {
            $valor = currency_parse($item['valor'] ?? 0);
            if ($valor <= 0 && empty($item['descricao'])) {
                continue;
            }
            $total += $valor;
        }

        return round($total, 2);
    }

    private function buscarFinanceiroElegivel(int $idFinanceiro, string $chave): array
    {
        $financeiro = $this->financeiroModel->buscarPorId($idFinanceiro);
        if (!$financeiro || ($financeiro['chave'] ?? null) !== $chave) {
            throw new \InvalidArgumentException('Lancamento nao encontrado');
        }

        if (($financeiro['tipo'] ?? '') !== 'R') {
            throw new \InvalidArgumentException('Link de pagamento disponivel apenas para receitas');
        }

        if (($financeiro['pago'] ?? 'N') === 'S') {
            throw new \InvalidArgumentException('Este lancamento ja foi pago');
        }

        return $financeiro;
    }

    private function linkEstaAtualizado(array $link, array $financeiro): bool
    {
        $valorLink = round((float) ($link['valor'] ?? 0), 2);
        $valorFinanceiro = round((float) ($financeiro['valor_total'] ?? 0), 2);
        $clienteLink = $link['id_cliente'] ?? null;
        $clienteFinanceiro = $financeiro['id_cliente'] ?? null;

        return abs($valorLink - $valorFinanceiro) < 0.01
            && (string) $clienteLink === (string) $clienteFinanceiro;
    }

    private function sincronizarLinkComFinanceiro(array $link, array $financeiro, int $diasExpiracao = 30, array $dadosExtras = []): void
    {
        $expiresAt = $link['expires_at'] ?? null;
        if (empty($expiresAt) || strtotime((string) $expiresAt) < time() || ($link['status'] ?? '') !== 'pending') {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$diasExpiracao} days"));
        }

        $dados = [
            'id_cliente' => $financeiro['id_cliente'] ?? null,
            'valor' => $financeiro['valor_total'] ?? 0,
            'descricao' => $dadosExtras['descricao'] ?? ($financeiro['descricao'] ?? 'Cobranca'),
            'expires_at' => $expiresAt,
        ];

        if (array_key_exists('id_locacao', $dadosExtras)) {
            $dados['id_locacao'] = $dadosExtras['id_locacao'];
        }

        $this->linkModel->atualizarDadosCobranca((int) $link['id'], $dados, $financeiro['chave'] ?? null);
    }

    private function temCobrancaAberta(int $idFinanceiro): bool
    {
        return !empty($this->transacaoModel->listarCobrancasAbertasPorFinanceiro($idFinanceiro));
    }

    private function cancelarCobrancaAberta(array $transacao, string $chave): bool
    {
        $externalId = (string) ($transacao['external_id'] ?? '');
        $gatewayId = (int) ($transacao['id_gateway'] ?? 0);

        if ($externalId === '' || $gatewayId <= 0) {
            $this->transacaoModel->atualizarStatusPorId((int) $transacao['id'], 'cancelled');
            return true;
        }

        $gatewayConfig = $this->gatewayModel->buscarPorIdComCredenciais($gatewayId);
        if (!$gatewayConfig || ($gatewayConfig['chave'] ?? null) !== $chave) {
            throw new \RuntimeException('Gateway da cobranca pendente nao foi localizado para cancelamento.');
        }

        $gateway = GatewayFactory::create(
            $gatewayConfig['gateway_code'],
            $gatewayConfig['credentials'] ?? [],
            $gatewayConfig['ambiente'] === 'sandbox',
            $gatewayId
        );

        $statusAntes = $gateway->getChargeStatus($externalId);
        if (($statusAntes['success'] ?? false) === true) {
            $status = $this->normalizarStatus((string) ($statusAntes['status'] ?? ''));
            if ($this->tratarStatusTerminal($transacao, $status, $statusAntes['paid_at'] ?? null)) {
                return $status === 'cancelled' || $status === 'failed' || $status === 'expired';
            }
        }

        $cancelamento = $gateway->cancel($externalId);
        if (($cancelamento['success'] ?? false) === true) {
            $this->transacaoModel->atualizarStatusPorId((int) $transacao['id'], 'cancelled');
            return true;
        }

        $statusDepois = $gateway->getChargeStatus($externalId);
        if (($statusDepois['success'] ?? false) === true) {
            $status = $this->normalizarStatus((string) ($statusDepois['status'] ?? ''));
            if ($this->tratarStatusTerminal($transacao, $status, $statusDepois['paid_at'] ?? null)) {
                return $status === 'cancelled' || $status === 'failed' || $status === 'expired';
            }
        }

        $mensagem = $cancelamento['message'] ?? 'nao foi possivel cancelar a cobranca no gateway';
        throw new \RuntimeException('Nao foi possivel cancelar a cobranca antiga no gateway: ' . $mensagem);
    }

    private function tratarStatusTerminal(array $transacao, string $status, ?string $paidAt = null): bool
    {
        if ($status === 'paid') {
            $this->transacaoModel->atualizarStatusPorId((int) $transacao['id'], 'paid', $paidAt ?: date('Y-m-d H:i:s'));
            throw new \RuntimeException('A cobranca antiga ja consta como paga no gateway. Atualize o financeiro pelo fluxo de pagamento antes de gerar outro link.');
        }

        if (in_array($status, self::STATUS_TERMINAIS, true)) {
            $this->transacaoModel->atualizarStatusPorId((int) $transacao['id'], $status);
            return true;
        }

        return false;
    }

    private function normalizarStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'received', 'confirmed', 'succeeded' => 'paid',
            'canceled', 'cancelado', 'baixado' => 'cancelled',
            'overdue' => 'pending',
            default => $status,
        };
    }
}
