<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Contrato;
use App\Services\AuditLogService;
use App\Services\InvoiceBatchNotificationService;
use mysqli;

class RenovarContratosJob extends BaseJob
{
    protected string $name = 'RenovarContratos';
    protected string $description = 'Renova contratos com auto-renovação ativa e gera lançamentos financeiros';

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

        // Buscar chaves distintas que possuem contratos para renovar
        $chaves = $this->carregarChavesComContratosVencidos($qb);

        $renovados = 0;
        $tenantsProcessados = 0;
        $erros = [];

        foreach ($chaves as $chave) {
            $this->setContextoTenant($chave);
            $tenantsProcessados++;

            $this->log("Processando tenant {$chave}");

            $contratos = $this->carregarContratosParaRenovar($qb, $chave);

            foreach ($contratos as $contrato) {
                try {
                    $envios = $this->renovarContrato($contrato, $chave);
                    $renovados++;
                    $this->log("Contrato #{$contrato['codigo']} renovado com sucesso");
                    foreach ($envios as $envio) {
                        $status = !empty($envio['success']) ? 'OK' : 'FALHA';
                        $this->log("  -> Cobranca {$envio['canal']} parcela {$envio['parcela_id']}: {$status} " . ($envio['message'] ?? ''));
                    }
                } catch (\Exception $e) {
                    $erros[] = [
                        'tenant' => $chave,
                        'contrato' => $contrato['codigo'] ?? $contrato['id'],
                        'erro' => $e->getMessage(),
                    ];
                    $this->log("Erro ao renovar contrato #{$contrato['codigo']}: {$e->getMessage()}", 'ERROR');
                }
            }

            $this->limparContextoTenant();
        }

        $this->log("Finalizado: {$tenantsProcessados} tenants, {$renovados} contratos renovados, " . count($erros) . " erros");

        return [
            'success' => true,
            'message' => "{$renovados} contrato(s) renovado(s) em {$tenantsProcessados} tenant(s)",
            'data' => [
                'tenants_processados' => $tenantsProcessados,
                'contratos_renovados' => $renovados,
                'erros' => $erros,
            ]
        ];
    }

    /**
     * Busca chaves distintas com contratos vencidos para renovação
     */
    private function carregarChavesComContratosVencidos(QueryBuilder $qb): array
    {
        $rows = $qb->withoutChave()
            ->table('contratos')
            ->select(['chave'])
            ->distinct()
            ->whereRaw("auto_renovacao = 'auto'")
            ->whereRaw("status = 'A'")
            ->whereNotNull('data_renovacao')
            ->whereRaw("data_renovacao <> '0000-00-00'")
            ->whereRaw('data_renovacao <= ?', [date('Y-m-d')])
            ->orderBy('chave')
            ->get();
        return array_column($rows, 'chave');
    }

    /**
     * Carrega contratos de um tenant prontos para renovação
     */
    private function carregarContratosParaRenovar(QueryBuilder $qb, string $chave): array
    {
        return $qb->withoutChave()
            ->table('contratos')
            ->select(['*'])
            ->where('chave', '=', $chave)
            ->whereRaw("auto_renovacao = 'auto'")
            ->whereRaw("status = 'A'")
            ->whereNotNull('data_renovacao')
            ->whereRaw("data_renovacao <> '0000-00-00'")
            ->whereRaw('data_renovacao <= ?', [date('Y-m-d')])
            ->orderBy('data_renovacao', 'ASC')
            ->get();
    }

    /**
     * Executa a renovação de um contrato individual
     */
    private function renovarContrato(array $contrato, string $chave): array
    {
        $contratoModel = new Contrato();
        $regularizacao = $contratoModel->calcularRegularizacaoAutorenovacao($contrato);
        $idsParcelas = [];
        $envios = [];

        // Atualizar somente a proxima data de renovacao.
        // data_ini/data_fim sao o periodo original do contrato.
        $contratoModel->atualizar($contrato['id'], [
            'data_renovacao' => $regularizacao['nova_data_renovacao'],
        ]);

        // Gerar novas parcelas financeiras
        $idFormaPagamento = (int) ($contrato['id_forma_pagamento'] ?? 0);
        if ($idFormaPagamento > 0) {
            $primeiroVencimento = $regularizacao['periodo_cobranca_ini'];

            $config = [
                'id_forma_pagamento' => $idFormaPagamento,
                'id_comando_parcela' => $contrato['id_comando_parcela'] ?? 0,
                'id_conta' => $contrato['id_conta'] ?? 0,
                'primeiro_vencimento' => $primeiroVencimento,
                'data_fim' => $regularizacao['periodo_cobranca_fim'],
                'valor_desconto' => 0,
            ];

            $preview = $contratoModel->gerarPreviewParcelas($contrato['id'], $config);

            if (!empty($preview['parcelas'])) {
                $idsParcelas = $contratoModel->salvarParcelasContrato(
                    $contrato['id'],
                    $preview['parcelas'],
                    $chave
                );
            }

            $this->log("  -> {$preview['resumo']['num_parcelas']} parcela(s) gerada(s)");
            if (!empty($idsParcelas)) {
                $envios = $this->enfileirarCobrancasParcelas($idsParcelas, $contrato, $chave);
            }
        } else {
            $this->log("  -> Sem forma de pagamento definida, parcelas nao geradas", 'WARNING');
        }

        // Log de auditoria
        AuditLogService::registrarComCampos(
            "Sistema, renovação automática do contrato [{$contrato['codigo']}]",
            [
                AuditLogService::campo('Ciclos aplicados', '0', (string) $regularizacao['ciclos']),
                AuditLogService::campo('Período de Cobrança', $regularizacao['periodo_cobranca_ini'], $regularizacao['periodo_cobranca_fim']),
                AuditLogService::campo('Data Renovação', $contrato['data_renovacao'], $regularizacao['nova_data_renovacao']),
            ]
        );

        return $envios;
    }

    /**
     * Enfileira cobrancas das parcelas geradas na renovacao automatica.
     */
    private function enfileirarCobrancasParcelas(array $idsParcelas, array $contrato, string $chave): array
    {
        return (new InvoiceBatchNotificationService())->sendInstallmentBatch($idsParcelas, [
            'chave' => $chave,
            'id_cliente' => (int) ($contrato['id_cliente'] ?? 0),
            'id_matriz_filial' => (int) ($contrato['id_matriz_filial_retirada'] ?? 0),
            'canais' => ['email' => true, 'whatsapp' => true, 'sms' => true],
            'origem_label' => !empty($contrato['codigo']) ? 'Contrato #' . $contrato['codigo'] : 'Contrato',
        ]);
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
