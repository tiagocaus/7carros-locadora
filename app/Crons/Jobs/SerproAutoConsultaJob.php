<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\SerproConfiguracao;
use App\Models\Veiculo;
use App\Models\Multa;
use App\Services\SerproService;
use App\Services\SerproSaldoService;
use mysqli;

/**
 * Job de auto-consulta online de infracoes
 *
 * Executa consultas automaticas de infracoes para todos os tenants
 * com auto_consulta_ativo = 1 e cujo intervalo de dias tenha expirado.
 *
 * Requisitos:
 * - Tenant deve ter config SERPRO com auto_consulta_ativo = 1
 * - Intervalo desde ultima consulta >= intervalo_dias_consulta
 * - Tenant deve ter saldo suficiente para N consultas (N = veiculos)
 */
class SerproAutoConsultaJob extends BaseJob
{
    protected string $name = 'SerproAutoConsulta';
    protected string $description = 'Consulta automatica de infracoes SERPRO para tenants configurados';

    protected function handle(): array
    {
        $this->log('Iniciando auto-consulta SERPRO...');

        $configModel = new SerproConfiguracao();
        $tenants = $configModel->listarAutoConsultaAtivos();

        if (empty($tenants)) {
            $this->log('Nenhum tenant com auto-consulta ativa.');
            return [
                'success' => true,
                'message' => 'Nenhum tenant com auto-consulta ativa',
                'data' => ['tenants_processados' => 0],
            ];
        }

        $this->log(count($tenants) . ' tenant(s) com auto-consulta ativa.');

        $totalProcessados = 0;
        $totalInfracoes = 0;
        $totalErros = 0;
        $detalhes = [];

        foreach ($tenants as $tenant) {
            $chave = $tenant['chave'];
            $intervalo = (int) ($tenant['intervalo_dias_consulta'] ?? 7);
            $ultimaConsulta = $tenant['ultima_consulta_em'] ?? null;

            // Verificar se ja passou o intervalo
            if ($ultimaConsulta) {
                $diasDesdeUltima = (int) ((\App\Helpers\DateHelper::timestamp() - strtotime($ultimaConsulta)) / 86400);
                if ($diasDesdeUltima < $intervalo) {
                    $this->log("Tenant {$chave}: pulando (ultima consulta ha {$diasDesdeUltima} dias, intervalo: {$intervalo})");
                    continue;
                }
            }

            $this->log("Tenant {$chave}: processando...");

            try {
                $resultado = $this->processarTenant($chave);
                $totalProcessados++;
                $totalInfracoes += $resultado['novas_infracoes'];
                $totalErros += $resultado['erros'];

                $detalhes[] = [
                    'chave' => $chave,
                    'veiculos' => $resultado['veiculos'],
                    'novas_infracoes' => $resultado['novas_infracoes'],
                    'erros' => $resultado['erros'],
                ];

                $this->log("Tenant {$chave}: {$resultado['veiculos']} veiculos, {$resultado['novas_infracoes']} novas infracoes");
            } catch (\Exception $e) {
                $totalErros++;
                $this->log("Tenant {$chave}: ERRO - " . $e->getMessage(), 'ERROR');

                $detalhes[] = [
                    'chave' => $chave,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        $mensagem = "Auto-consulta finalizada. {$totalProcessados} tenants, {$totalInfracoes} novas infracoes.";
        $this->log($mensagem);

        return [
            'success' => $totalErros === 0,
            'message' => $mensagem,
            'data' => [
                'tenants_processados' => $totalProcessados,
                'total_infracoes' => $totalInfracoes,
                'erros' => $totalErros,
                'detalhes' => $detalhes,
            ],
        ];
    }

    /**
     * Processa auto-consulta para um tenant especifico
     */
    private function processarTenant(string $chave): array
    {
        // Definir sessao do tenant (necessario para Models com filtro por chave)
        $_SESSION['chave'] = $chave;

        try {
            $veiculoModel = new Veiculo();
            $veiculos = $veiculoModel->listarPlacasBrasileirasPorChave($chave);

            if (empty($veiculos)) {
                $this->log("Tenant {$chave}: nenhum veiculo brasileiro encontrado");
                return ['veiculos' => 0, 'novas_infracoes' => 0, 'erros' => 0];
            }

            $totalVeiculos = count($veiculos);

            // Verificar saldo
            $saldoService = new SerproSaldoService();
            $saldoService->inicializarSaldo();

            if (!$saldoService->temSaldoParaConsultas($totalVeiculos)) {
                $this->log("Tenant {$chave}: saldo insuficiente para {$totalVeiculos} consultas", 'WARNING');
                return ['veiculos' => $totalVeiculos, 'novas_infracoes' => 0, 'erros' => 1];
            }

            $serpro = new SerproService();
            $multaModel = new Multa();
            $novasInfracoes = 0;
            $erros = 0;

            foreach ($veiculos as $veiculo) {
                $placa = $veiculo['placa'];

                try {
                    // Debitar consulta
                    $debito = $saldoService->debitarConsulta("Auto-consulta placa {$placa}", $placa);

                    // Consultar SERPRO
                    $resultado = $serpro->consultarInfracoes($placa);

                    if ($resultado['success'] && !empty($resultado['data'])) {
                        $novas = $this->sincronizarInfracoes($chave, $placa, $veiculo['id'], $resultado['data'], $multaModel);
                        $novasInfracoes += $novas;
                    } elseif (!$resultado['success']) {
                        $this->estornarDebitoConsulta($saldoService, $debito, $placa, $resultado);
                        $erros++;
                    }

                    // Rate limit: 100ms entre consultas
                    usleep(100000);
                } catch (\Exception $e) {
                    $erros++;
                    $this->log("Placa {$placa}: " . $e->getMessage(), 'WARNING');
                }
            }

            // Atualizar data da ultima consulta
            $configModel = new SerproConfiguracao();
            $configModel->atualizarUltimaConsulta();

            return [
                'veiculos' => $totalVeiculos,
                'novas_infracoes' => $novasInfracoes,
                'erros' => $erros,
            ];
        } finally {
            unset($_SESSION['chave']);
        }
    }

    /**
     * Sincroniza infracoes com a tabela de multas
     */
    private function sincronizarInfracoes(string $chave, string $placa, int $idVeiculo, array $infracoes, Multa $multaModel): int
    {
        $novas = 0;

        foreach ($infracoes as $infracao) {
            $dadosMulta = $multaModel->normalizarInfracaoSerpro(array_merge($infracao, [
                'id_veiculo' => $idVeiculo,
                'placa' => $placa,
                'origem' => 'serpro_consulta',
                'status_processamento' => 'novo',
                'serpro_sync_at' => now(),
            ]));

            $codigoOrgao = $dadosMulta['codigo_orgao'] ?? '';
            $numeroAit = $dadosMulta['numero_ait'] ?? '';
            $codigoInfracao = $dadosMulta['codigo_infracao'] ?? '';

            if (empty($codigoOrgao) || empty($numeroAit)) {
                continue;
            }

            // Verificar duplicata
            $existente = $multaModel->buscarPorChavesSerpro($codigoOrgao, $numeroAit, $codigoInfracao);

            if ($existente) {
                $multaModel->atualizarDadosSerpro($existente['id'], $dadosMulta);
                continue;
            }

            // Criar multa
            $multaModel->criarDeSerpro($dadosMulta);

            $novas++;
        }

        return $novas;
    }

    private function estornarDebitoConsulta(SerproSaldoService $saldoService, array $debito, string $placa, array $resultado): void
    {
        $transacaoId = (int) ($debito['transacao_id'] ?? 0);
        if ($transacaoId <= 0) {
            return;
        }

        try {
            $saldoService->estornarDebito($transacaoId);
            $this->log("Placa {$placa}: debito estornado apos falha da Consulta Online ({$resultado['status']})", 'WARNING');
        } catch (\Throwable $e) {
            $this->log("Placa {$placa}: falha ao estornar debito {$transacaoId}: " . $e->getMessage(), 'ERROR');
        }
    }
}
