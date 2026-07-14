<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Financeiro;
use App\Models\FinanceiroTaxa;
use App\Models\FinanceiroTransacao;
use App\Models\FormaPagamento;
use App\Models\Model;
use App\Models\PlanoDeContas;

/**
 * Mantem a despesa da taxa retida sincronizada com a receita paga.
 */
class FinanceiroTaxaService
{
    private const PLANO_PADRAO = '3.4.1.21';

    public function sincronizar(int $idReceita, ?int $idTransacao = null): ?int
    {
        if (!FinanceiroTaxa::schemaDisponivel()) {
            return null;
        }

        $model = new FinanceiroTaxa();
        $receita = $model->buscarReceita($idReceita);

        if (!$receita || ($receita['tipo'] ?? '') !== 'R' || ($receita['pago'] ?? 'N') !== 'S') {
            return null;
        }

        [$valorTaxa, $idGateway] = $this->resolverTaxaEGateway($receita, $idTransacao);
        if ($valorTaxa <= 0) {
            $model->excluirDespesaPorReceita($idReceita);
            return null;
        }

        $idPlano = $this->resolverPlanoDeConta($receita);
        if ($idPlano === null) {
            throw new \RuntimeException('Plano de contas padrao para taxas de meios de pagamento nao encontrado');
        }

        $conexao = Model::sharedMysqli();
        $possuiTransacao = defined('MYSQLI_SERVER_STATUS_IN_TRANS')
            && (($conexao->server_status & MYSQLI_SERVER_STATUS_IN_TRANS) !== 0);

        if (!$possuiTransacao) {
            $conexao->begin_transaction();
        }

        try {
            $dataPagamento = $receita['data_pago'] ?: DateHelper::todayForDatabase();
            $formaNome = trim((string) ($receita['forma_pagamento_nome'] ?? '')) ?: 'Forma de pagamento';
            $descricao = mb_substr("Taxa de meio de pagamento - {$formaNome} - Financeiro #{$idReceita}", 0, 500);

            $model->atualizarOrigem($idReceita, [
                'valor_taxa' => $valorTaxa,
                'id_gateway' => $idGateway,
            ]);

            $dadosDespesa = [
                'id_matriz_filial' => $receita['id_matriz_filial'] ?? null,
                'id_conta' => $receita['id_conta'] ?? null,
                'id_plano_de_conta' => $idPlano,
                'id_gateway' => $idGateway,
                'id_contrato' => $receita['id_contrato'] ?? null,
                'id_locacao' => $receita['id_locacao'] ?? null,
                'id_veiculo' => $receita['id_veiculo'] ?? null,
                'descricao' => $descricao,
                'data_criada' => $dataPagamento,
                'data_venci' => $dataPagamento,
                'data_pago' => $dataPagamento,
                'pago' => 'S',
                'valor_subtotal' => $valorTaxa,
                'valor_total' => $valorTaxa,
            ];

            $despesa = $model->buscarDespesaVinculada($idReceita);
            if ($despesa) {
                $model->atualizarDespesa((int) $despesa['id'], $dadosDespesa);
                $idDespesa = (int) $despesa['id'];
            } else {
                $idDespesa = (new Financeiro())->criar($dadosDespesa + [
                    'chave' => $receita['chave'],
                    'codigo' => $receita['codigo'] ?? null,
                    'tipo' => 'D',
                    'parcela' => 0,
                    'total_parcelas' => 0,
                    'juros' => 0,
                    'multa' => 0,
                    'desconto' => 0,
                    'valor_taxa' => 0,
                    'id_financeiro_taxa_origem' => $idReceita,
                ]);

                $this->registrarAuditoria(
                    ($_SESSION['user_name'] ?? 'Sistema') .
                    ", contabilizou taxa de meio de pagamento [Financeiro #{$idReceita}, Despesa #{$idDespesa}]"
                );
            }

            if (!$possuiTransacao) {
                $conexao->commit();
            }

            return $idDespesa;
        } catch (\Throwable $e) {
            if (!$possuiTransacao) {
                $conexao->rollback();
            }
            throw $e;
        }
    }

    public function estornar(int $idReceita): int
    {
        if (!FinanceiroTaxa::schemaDisponivel()) {
            return 0;
        }

        $excluidos = (new FinanceiroTaxa())->excluirDespesaPorReceita($idReceita);
        if ($excluidos > 0) {
            $this->registrarAuditoria(
                ($_SESSION['user_name'] ?? 'Sistema') .
                ", estornou taxa de meio de pagamento [Financeiro #{$idReceita}]"
            );
        }

        return $excluidos;
    }

    /** @return array{0:float,1:?int} */
    private function resolverTaxaEGateway(array $receita, ?int $idTransacao): array
    {
        $valorTaxa = round((float) ($receita['valor_taxa'] ?? 0), 2);
        $idGateway = !empty($receita['id_gateway']) ? (int) $receita['id_gateway'] : null;

        if ($idTransacao !== null) {
            $transacao = (new FinanceiroTransacao())->buscarPorId($idTransacao);
            if ($transacao && (int) ($transacao['id_financeiro'] ?? 0) === (int) $receita['id']) {
                $idGateway = !empty($transacao['id_gateway']) ? (int) $transacao['id_gateway'] : $idGateway;
                $fee = round((float) ($transacao['fee'] ?? 0), 2);
                if ($fee <= 0 && $transacao['amount'] !== null && $transacao['net_amount'] !== null) {
                    $fee = round((float) $transacao['amount'] - (float) $transacao['net_amount'], 2);
                }
                if ($fee > 0) {
                    $valorTaxa = $fee;
                }
            }
        }

        if ($idGateway === null && !empty($receita['id_forma_pagamento'])) {
            $gateways = (new FormaPagamento())->buscarGateways((int) $receita['id_forma_pagamento']);
            if (count($gateways) === 1) {
                $idGateway = (int) $gateways[0]['id'];
            }
        }

        return [max(0, $valorTaxa), $idGateway];
    }

    private function resolverPlanoDeConta(array $receita): ?int
    {
        if (!empty($receita['id_plano_de_conta_taxa'])) {
            $plano = (new PlanoDeContas())->buscarPorId((int) $receita['id_plano_de_conta_taxa']);
            if ($plano && ($plano['tipo'] ?? '') === 'D') {
                return (int) $plano['id'];
            }
        }

        $padrao = (new PlanoDeContas())->buscarPorHierarquia(self::PLANO_PADRAO);
        return $padrao && ($padrao['tipo'] ?? '') === 'D' ? (int) $padrao['id'] : null;
    }

    private function registrarAuditoria(string $mensagem): void
    {
        try {
            AuditLogService::registrar($mensagem);
        } catch (\Throwable $e) {
            error_log('[FinanceiroTaxa/Auditoria] ' . $e->getMessage());
        }
    }
}
