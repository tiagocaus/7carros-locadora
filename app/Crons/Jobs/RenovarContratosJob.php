<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Financeiro;
use App\Models\FormaPagamento;
use App\Models\PagamentoLink;
use App\Services\AuditLogService;
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

        // Atualizar datas do contrato
        $contratoModel->atualizar($contrato['id'], [
            'data_ini' => $regularizacao['nova_data_ini'],
            'data_fim' => $regularizacao['nova_data_fim'],
            'data_renovacao' => $regularizacao['nova_data_renovacao'],
        ]);

        // Gerar novas parcelas financeiras
        $idFormaPagamento = (int) ($contrato['id_forma_pagamento'] ?? 0);
        if ($idFormaPagamento > 0) {
            $primeiroVencimento = substr($regularizacao['data_fim_atual'], 0, 10);

            $config = [
                'id_forma_pagamento' => $idFormaPagamento,
                'id_comando_parcela' => $contrato['id_comando_parcela'] ?? 0,
                'id_conta' => $contrato['id_conta'] ?? 0,
                'primeiro_vencimento' => $primeiroVencimento,
                'data_fim' => substr($regularizacao['nova_data_fim'], 0, 10),
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
                AuditLogService::campo('Data Início', $contrato['data_ini'], $regularizacao['nova_data_ini']),
                AuditLogService::campo('Data Fim', $contrato['data_fim'], $regularizacao['nova_data_fim']),
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
        $resultado = [];
        $cliente = !empty($contrato['id_cliente'])
            ? (new Cliente())->buscarPorIdComContatos((int) $contrato['id_cliente'])
            : null;

        if (!$cliente) {
            return [[
                'parcela_id' => null,
                'canal' => 'all',
                'success' => false,
                'message' => 'Cliente do contrato nao encontrado para envio de cobranca',
            ]];
        }

        $email = $cliente['email'] ?? '';
        $telefone = $cliente['telefone'] ?? $cliente['celular'] ?? '';
        $financeiroModel = new Financeiro();
        $linkModel = new PagamentoLink();

        foreach ($idsParcelas as $idParcela) {
            $financeiro = $financeiroModel->buscarPorId((int) $idParcela);
            if (!$financeiro) {
                continue;
            }

            $link = $linkModel->buscarPorFinanceiro((int) $idParcela);
            if (!$link) {
                $linkId = $linkModel->criar([
                    'chave' => $chave,
                    'id_financeiro' => (int) $idParcela,
                    'id_cliente' => $financeiro['id_cliente'] ?? null,
                    'valor' => $financeiro['valor_total'],
                    'descricao' => $financeiro['descricao'] ?? 'Cobrança',
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
                $link = $linkModel->buscarPorId($linkId);
            }

            $context = [
                'cliente' => [
                    'nome' => $cliente['nome_rsocial'] ?? '',
                    'primeiro_nome' => explode(' ', trim((string) ($cliente['nome_rsocial'] ?? '')))[0] ?? '',
                    'email' => $email,
                    'cpf_cnpj' => $cliente['cpf_cnpj'] ?? '',
                    'telefone' => $telefone,
                    'celular' => $telefone,
                    'preferred_locale' => $cliente['preferred_locale'] ?? null,
                ],
                'empresa' => [
                    'id' => $contrato['id_matriz_filial_retirada'] ?? null,
                ],
                'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
                'fatura' => [
                    'numero' => $financeiro['codigo'] ?? $financeiro['sequencia'] ?? $idParcela,
                    'valor' => $financeiro['valor_total'],
                    'data_vencimento' => $financeiro['data_venci'],
                    'descricao' => $financeiro['descricao'] ?? '',
                    'status' => 'Pendente',
                    'link_boleto' => $link ? $linkModel->getUrl($link['codigo']) : '',
                ],
            ];

            foreach (['email', 'whatsapp', 'sms'] as $canal) {
                if ($canal === 'email' && $email === '') {
                    $resultado[] = ['parcela_id' => $idParcela, 'canal' => $canal, 'success' => false, 'message' => 'Cliente sem email'];
                    continue;
                }
                if (in_array($canal, ['whatsapp', 'sms'], true) && $telefone === '') {
                    $resultado[] = ['parcela_id' => $idParcela, 'canal' => $canal, 'success' => false, 'message' => 'Cliente sem telefone'];
                    continue;
                }

                try {
                    $messageId = queue_template_message('payment_reminder', $canal, $context, $chave);
                    $resultado[] = ['parcela_id' => $idParcela, 'canal' => $canal, 'success' => true, 'message' => "message_id={$messageId}"];
                } catch (\Throwable $e) {
                    $resultado[] = ['parcela_id' => $idParcela, 'canal' => $canal, 'success' => false, 'message' => $e->getMessage()];
                }
            }
        }

        return $resultado;
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
