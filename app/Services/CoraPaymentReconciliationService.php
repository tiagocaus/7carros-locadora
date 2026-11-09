<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Financeiro;
use App\Models\FinanceiroTransacao;
use App\Models\GatewayPagamento;
use App\Models\Locacao;
use App\Models\PagamentoLink;
use App\Services\Gateways\CoraGateway;
use App\Services\Gateways\GatewayFactory;

/** Único caminho Cora para confirmação autenticada, baixa atômica e hooks. */
class CoraPaymentReconciliationService
{
    public function criarGateway(array $transaction): CoraGateway
    {
        $config = (new GatewayPagamento())->buscarPorIdComCredenciaisParaTenant(
            (int) $transaction['id_gateway'], (string) $transaction['chave']
        );
        if (!$config || $config['gateway_code'] !== 'cora') {
            throw new \RuntimeException('Configuração Cora indisponível.');
        }
        return GatewayFactory::create('cora', $config['credentials'], $config['ambiente'] === 'sandbox', (int) $config['id']);
    }

    public function confirmar(array $transaction, CoraGateway $gateway, array $event): array
    {
        if (($transaction['gateway'] ?? '') !== 'cora' || empty($transaction['chave'])
            || empty($transaction['id_financeiro']) || empty($transaction['external_id'])
            || ($event['resource_id'] ?? '') !== $transaction['external_id']) {
            throw new \RuntimeException('Vínculo da cobrança Cora inválido.');
        }
        $previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $transaction['chave'];
        DateHelper::clearCache();
        try {
            $model = new FinanceiroTransacao();
            return $model->comBloqueioCora($transaction['chave'], 'charge:' . $transaction['id_financeiro'], function () use ($model, $transaction, $event, $gateway) {
                $confirmed = $gateway->getChargeStatus($transaction['external_id']);
                if (empty($confirmed['success'])) throw new \RuntimeException('Consulta autenticada da Cora indisponível.');
                if (($confirmed['external_id'] ?? '') !== $transaction['external_id']
                    || !isset($confirmed['raw']['total_amount'])
                    || (int) $confirmed['raw']['total_amount'] !== (int) round((float) $transaction['amount'] * 100)) {
                    throw new \RuntimeException('Identificador ou valor da cobrança Cora divergente.');
                }
                if ($confirmed['status'] === 'paid'
                    && (!isset($confirmed['raw']['total_paid'])
                        || (int) $confirmed['raw']['total_paid'] !== (int) $confirmed['raw']['total_amount'])) {
                    throw new \RuntimeException('Pagamento integral da Cora não confirmado.');
                }
                $model->aplicarWebhookCora($transaction, $confirmed, $event, function (array $row) use ($model, $confirmed) {
                    $financeiro = $model->bloquearFinanceiroCora($row['chave'], (int) $row['id_financeiro']);
                    if (!$financeiro || $financeiro['tipo'] !== 'R') throw new \RuntimeException('Receita Cora não encontrada.');
                    // Uma baixa manual concorrente é preservada, inclusive valor e data.
                    if ($financeiro['pago'] !== 'S') {
                        $paidDate = substr($confirmed['paid_at'] ?? '', 0, 10);
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $paidDate)) {
                            throw new \RuntimeException('Data de pagamento Cora indisponível.');
                        }
                        DateHelper::parseOperationalDateTime($paidDate . ' 00:00:00');
                        $dados = $this->valoresParaBaixa($financeiro, (float) $row['amount'], $paidDate);
                        (new Financeiro())->atualizar((int) $row['id_financeiro'], $dados + ['pago' => 'S', 'data_pago' => $paidDate], true);
                        (new FinanceiroTaxaService())->sincronizar((int) $row['id_financeiro'], (int) $row['id'], true);
                        AuditLogService::registrarComCamposNaTransacao(FinanceiroTransacao::sharedMysqli(), 'Cora: pagamento conciliado [Financeiro #' . $row['id_financeiro'] . ']', [
                            AuditLogService::campo('Transação', null, (int) $row['id']),
                            AuditLogService::campo('Valor recebido', null, (float) $row['amount']),
                            AuditLogService::campo('Data de pagamento', null, $paidDate),
                            AuditLogService::campo('Valor total', $financeiro['valor_total'], $row['amount']),
                        ]);
                    } elseif (!empty($row['_cora_baixa_registrada'])) {
                        // Repara somente a taxa ausente de uma baixa automática já registrada.
                        // Na primeira confirmação de uma baixa manual, mantém a contabilização manual.
                        (new FinanceiroTaxaService())->sincronizar((int) $row['id_financeiro'], (int) $row['id'], true);
                    }
                    $model->marcarLinksPagosCora($row['chave'], (int) $row['id_financeiro'], (int) $row['id']);
                });
                if ($confirmed['status'] === 'paid') $this->aposPagamento($transaction);
                return $confirmed;
            });
        } finally {
            if ($previousChave === null) unset($_SESSION['chave']);
            else $_SESSION['chave'] = $previousChave;
            DateHelper::clearCache();
        }
    }

    /** Só remove encargos posteriores quando o cálculo na data paga confere com o banco. */
    public function valoresParaBaixa(array $financeiro, float $amount, string $paidDate): array
    {
        if ((int) round((float) $financeiro['valor_total'] * 100) === (int) round($amount * 100)) return [];
        $vencimento = DateHelper::parseOperationalDateTime($financeiro['data_venci'] . ' 00:00:00');
        $pagamento = DateHelper::parseOperationalDateTime($paidDate . ' 00:00:00');
        $dias = max(0, (int) $vencimento->diff($pagamento)->format('%r%a'));
        if ($dias > 0 && ($financeiro['cora_multa_percentual'] === null || $financeiro['cora_juros_por_dia'] === null)) {
            throw new \RuntimeException('Encargos da cobrança Cora exigem conferência.');
        }
        $subtotal = (float) $financeiro['valor_subtotal'];
        $multa = $dias > 0 ? round($subtotal * (float) $financeiro['cora_multa_percentual'] / 100, 2) : 0.0;
        $juros = round($subtotal * (float) $financeiro['cora_juros_por_dia'] / 100 * $dias, 2);
        $total = round($subtotal + $multa + $juros - (float) $financeiro['desconto'], 2);
        if ((int) round($total * 100) !== (int) round($amount * 100)
            || $amount > (float) $financeiro['valor_total']
            || $multa > (float) $financeiro['multa'] || $juros > (float) $financeiro['juros']) {
            throw new \RuntimeException('Valor financeiro Cora exige conferência.');
        }
        return ['juros' => $juros, 'multa' => $multa];
    }

    protected function aposPagamento(array $transaction): void
    {
        try {
            (new ComissaoInvestidorService())->processarComissaoPorFinanceiro((int) $transaction['id_financeiro']);
        } catch (\Throwable) { self::log('hook_comissao_falhou', [], $transaction); }
        $link = (new PagamentoLink())->buscarPorTransacaoPagaCora((int) $transaction['id']);
        if (!empty($link['id_locacao'])) {
            $locacao = (new Locacao())->buscarPorId((int) $link['id_locacao']);
            if ($locacao && $locacao['status'] === 'P') {
                (new PagamentoReservaService())->efetivar((int) $link['id_locacao']);
            }
        }
    }

    /** Apenas referências limitadas; nunca registrar payload, documentos ou mensagens brutas. */
    public static function log(string $resultado, array $event = [], ?array $transaction = null, ?\Throwable $error = null): void
    {
        $safe = static fn($value): string => substr(preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string) $value), 0, 100);
        $motivo = $error ? match ($error->getMessage()) {
            'Configuração Cora indisponível.' => 'configuracao_indisponivel',
            'Vínculo da cobrança Cora inválido.' => 'vinculo_invalido',
            'Consulta autenticada da Cora indisponível.' => 'consulta_indisponivel',
            'Identificador ou valor da cobrança Cora divergente.' => 'cobranca_divergente',
            'Pagamento integral da Cora não confirmado.' => 'pagamento_nao_integral',
            'Data de pagamento Cora indisponível.', 'Data/hora operacional invalida.' => 'data_invalida',
            'Valor financeiro Cora exige conferência.', 'Encargos da cobrança Cora exigem conferência.' => 'valor_exige_conferencia',
            'Operação Cora em andamento. Tente novamente.' => 'bloqueio_ocupado',
            default => 'falha_interna',
        } : null;
        $line = '[Cora] ' . json_encode([
            'resultado' => $safe($resultado), 'evento' => $safe($event['event'] ?? ''),
            'evento_id' => $safe($event['event_id'] ?? ''), 'cobranca' => $safe($event['resource_id'] ?? ''),
            'transacao' => (int) ($transaction['id'] ?? 0), 'financeiro' => (int) ($transaction['id_financeiro'] ?? 0),
            'erro' => $error ? get_class($error) : null, 'motivo' => $motivo,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        error_log($line);
        @error_log('[' . now() . '] ' . $line . PHP_EOL, 3, dirname(__DIR__, 2) . '/storage/logs/cora-payments.log');
    }
}
