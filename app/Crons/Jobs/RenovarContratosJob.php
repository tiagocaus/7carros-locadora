<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\ComandoParcela;
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

        // Buscar chaves distintas que possuem contratos com autorrenovacao ativa.
        // A data de corte e aplicada depois, ja com o timezone do tenant carregado.
        $chaves = $this->carregarChavesComContratosVencidos($qb);

        $renovados = 0;
        $tenantsProcessados = 0;
        $erros = [];

        foreach ($chaves as $chave) {
            $this->setContextoTenant($chave);
            $tenantsProcessados++;

            $this->log("Processando tenant {$chave}");

            $hoje = today();
            $contratos = $this->carregarContratosParaRenovar($qb, $chave, $hoje);

            foreach ($contratos as $contrato) {
                try {
                    $envios = $this->renovarContrato($contrato, $chave, $hoje);
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
            ->orderBy('chave')
            ->get();
        return array_values(array_filter(array_unique(array_column($rows, 'chave'))));
    }

    /**
     * Carrega contratos de um tenant prontos para renovação
     */
    private function carregarContratosParaRenovar(QueryBuilder $qb, string $chave, string $hoje): array
    {
        return $qb->withoutChave()
            ->table('contratos')
            ->select(['*'])
            ->where('chave', '=', $chave)
            ->whereRaw("auto_renovacao = 'auto'")
            ->whereRaw("status = 'A'")
            ->whereNotNull('data_renovacao')
            ->whereRaw("data_renovacao <> '0000-00-00'")
            ->whereRaw('data_renovacao <= ?', [$hoje])
            ->orderBy('data_renovacao', 'ASC')
            ->get();
    }

    /**
     * Executa a renovação de um contrato individual
     */
    private function renovarContrato(array $contrato, string $chave, string $hoje): array
    {
        $contratoModel = new Contrato();
        $regularizacao = $contratoModel->calcularRegularizacaoAutorenovacao($contrato, $hoje);
        $idsParcelas = [];
        $envios = [];

        $idFormaPagamento = (int) ($contrato['id_forma_pagamento'] ?? 0);
        if ($idFormaPagamento <= 0) {
            throw new \RuntimeException('Autorenovacao bloqueada: contrato sem forma de pagamento definida');
        }

        $idComandoParcela = (int) ($contrato['id_comando_parcela'] ?? 0);
        if ($idComandoParcela <= 0) {
            throw new \RuntimeException('Autorenovacao bloqueada: contrato sem comando de parcelas definido');
        }

        $comandoRegistro = (new ComandoParcela())->buscarPorId($idComandoParcela);
        $comandoStr = trim((string) ($comandoRegistro['comando'] ?? ''));
        if ($comandoStr === '' || ComandoParcela::parseComando($comandoStr)['tipo'] === 'desconhecido') {
            throw new \RuntimeException('Autorenovacao bloqueada: comando de parcelas invalido');
        }

        if ((float) ($contrato['total_pagar'] ?? 0) <= 0) {
            throw new \RuntimeException('Autorenovacao bloqueada: valor do contrato zerado');
        }

        $primeiroVencimento = $regularizacao['periodo_cobranca_ini'];

        $config = [
            'id_forma_pagamento' => $idFormaPagamento,
            'id_comando_parcela' => $idComandoParcela,
            'id_conta' => $contrato['id_conta'] ?? 0,
            'primeiro_vencimento' => $primeiroVencimento,
            'data_fim' => $regularizacao['periodo_cobranca_fim'],
            'valor_desconto' => 0,
        ];

        $preview = $contratoModel->gerarPreviewParcelas($contrato['id'], $config);
        if (empty($preview['parcelas'])) {
            throw new \RuntimeException('Autorenovacao bloqueada: preview sem parcelas');
        }

        $resultadoParcelas = $contratoModel->salvarParcelasContratoComResultado(
            $contrato['id'],
            $preview['parcelas'],
            $chave,
            false
        );

        if ($resultadoParcelas['total_confirmado'] < $resultadoParcelas['total_esperado']) {
            throw new \RuntimeException(
                "Autorenovacao bloqueada: {$resultadoParcelas['total_confirmado']} de {$resultadoParcelas['total_esperado']} parcela(s) confirmada(s)"
            );
        }

        $idsParcelas = $resultadoParcelas['ids_criados'];
        $this->log(
            "  -> {$resultadoParcelas['total_esperado']} parcela(s) esperada(s), "
            . count($resultadoParcelas['ids_criados']) . " criada(s), "
            . count($resultadoParcelas['ids_existentes']) . " ja existente(s)"
        );

        // Atualizar somente a proxima data de renovacao apos confirmar as parcelas.
        // data_ini/data_fim sao o periodo original do contrato.
        $contratoModel->atualizar($contrato['id'], [
            'data_renovacao' => $regularizacao['nova_data_renovacao'],
        ]);

        if (!empty($idsParcelas)) {
            $envios = $this->enfileirarCobrancasParcelas($idsParcelas, $contrato, $chave);
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
