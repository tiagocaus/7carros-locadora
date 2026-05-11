<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use mysqli;

/**
 * CRON Job: Gerar lancamentos financeiros para encargos de veiculos
 *
 * Executa diariamente e:
 * 1. Busca encargos com vencimento proximo (dentro da janela de dias_antecedencia)
 *    que ainda nao tem lancamento financeiro vinculado
 * 2. Cria lancamento tipo D (despesa) no financeiro
 * 3. Renova automaticamente encargos vencidos com recorrencia ativa
 */
class GerarEncargosFinanceiroJob extends BaseJob
{
    protected string $name = 'GerarEncargosFinanceiro';
    protected string $description = 'Gera lancamentos financeiros para encargos de veiculos e renova encargos recorrentes';

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

        // Buscar chaves distintas com encargos ativos
        $chaves = $this->carregarChavesComEncargos($qb);

        $lancamentosGerados = 0;
        $encargosRenovados = 0;
        $tenantsProcessados = 0;
        $erros = [];

        foreach ($chaves as $chave) {
            $this->setContextoTenant($chave);
            $tenantsProcessados++;

            try {
                // Fase 1: Gerar lancamentos financeiros
                $gerados = $this->gerarLancamentos($qb, $chave);
                $lancamentosGerados += $gerados;

                // Fase 2: Renovar encargos recorrentes vencidos
                $renovados = $this->renovarEncargos($qb, $chave);
                $encargosRenovados += $renovados;
            } catch (\Exception $e) {
                $erros[] = [
                    'tenant' => $chave,
                    'erro' => $e->getMessage(),
                ];
                $this->log("Erro no tenant {$chave}: {$e->getMessage()}", 'ERROR');
            }
        }

        $this->limparContextoTenant();

        $this->log("Finalizado: {$tenantsProcessados} tenants, {$lancamentosGerados} lancamentos, {$encargosRenovados} renovacoes");

        return [
            'success' => empty($erros),
            'message' => "{$lancamentosGerados} lancamento(s) gerado(s), {$encargosRenovados} encargo(s) renovado(s)",
            'data' => [
                'tenants_processados' => $tenantsProcessados,
                'lancamentos_gerados' => $lancamentosGerados,
                'encargos_renovados' => $encargosRenovados,
                'erros' => $erros,
            ]
        ];
    }

    /**
     * Busca chaves distintas com encargos ativos
     */
    private function carregarChavesComEncargos(QueryBuilder $qb): array
    {
        $rows = $qb->withoutChave()
            ->table('veiculos_encargos')
            ->select(['chave'])
            ->distinct()
            ->where('ativo', '=', 1)
            ->get();

        return array_column($rows, 'chave');
    }

    /**
     * Gera lancamentos financeiros para encargos pendentes de um tenant
     */
    private function gerarLancamentos(QueryBuilder $qb, string $chave): int
    {
        // Buscar encargos pendentes (dentro da janela de antecedencia, sem financeiro vinculado)
        $encargos = $qb->withoutChave()
            ->table('veiculos_encargos', 've')
            ->select([
                've.*',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.id_matriz_filial',
            ])
            ->innerJoin('veiculos', 'v', 've.id_veiculo', '=', 'v.id')
            ->where('ve.chave', '=', $chave)
            ->where('ve.ativo', '=', 1)
            ->whereNull('ve.id_financeiro')
            ->whereNotNull('ve.valor')
            ->whereRaw('ve.valor > 0')
            ->whereNotNull('ve.vencimento')
            ->whereRaw('ve.vencimento >= CURDATE()')
            ->whereRaw('DATEDIFF(ve.vencimento, CURDATE()) <= ve.dias_antecedencia')
            ->get();

        $gerados = 0;

        foreach ($encargos as $encargo) {
            try {
                // Gerar sequencia
                $sequencia = \App\Helpers\SequenciaHelper::proximaSequencia(
                    $chave,
                    $encargo['id_matriz_filial'],
                    'financeiro'
                );

                $descricaoFinanceiro = $encargo['nome'];
                if (!empty($encargo['placa'])) {
                    $descricaoFinanceiro .= ' - ' . $encargo['placa'];
                }
                if (!empty($encargo['descricao'])) {
                    $descricaoFinanceiro .= ' (' . $encargo['descricao'] . ')';
                }

                // Criar lancamento no financeiro
                $idFinanceiro = $qb->withoutChave()
                    ->table('financeiro')
                    ->insert([
                        'chave' => $chave,
                        'sequencia' => $sequencia,
                        'id_matriz_filial' => $encargo['id_matriz_filial'],
                        'tipo' => 'D',
                        'pago' => 'N',
                        'descricao' => $descricaoFinanceiro,
                        'data_criada' => date('Y-m-d'),
                        'data_venci' => $encargo['vencimento'],
                        'valor_subtotal' => $encargo['valor'],
                        'juros' => 0,
                        'multa' => 0,
                        'desconto' => 0,
                        'valor_total' => $encargo['valor'],
                        'valor_taxa' => 0,
                        'parcela' => 0,
                        'total_parcelas' => 0,
                    ]);

                // Vincular financeiro ao encargo
                $qb->withoutChave()
                    ->table('veiculos_encargos')
                    ->where('id', '=', $encargo['id'])
                    ->update(['id_financeiro' => $idFinanceiro]);

                $gerados++;
                $this->log("Lancamento #{$idFinanceiro} gerado para encargo '{$encargo['nome']}' - {$encargo['placa']}");
            } catch (\Exception $e) {
                $this->log("Erro ao gerar lancamento para encargo #{$encargo['id']}: {$e->getMessage()}", 'ERROR');
            }
        }

        return $gerados;
    }

    /**
     * Renova encargos recorrentes vencidos de um tenant
     */
    private function renovarEncargos(QueryBuilder $qb, string $chave): int
    {
        $encargos = $qb->withoutChave()
            ->table('veiculos_encargos')
            ->select(['*'])
            ->where('chave', '=', $chave)
            ->where('ativo', '=', 1)
            ->whereRaw("recorrencia != 'nenhuma'")
            ->whereRaw('vencimento < CURDATE()')
            ->get();

        $renovados = 0;

        foreach ($encargos as $encargo) {
            try {
                // Calcular proximo vencimento
                $novoVencimento = $this->calcularProximoVencimento(
                    $encargo['vencimento'],
                    $encargo['recorrencia']
                );

                if (!$novoVencimento) {
                    continue;
                }

                // Criar novo encargo com proximo vencimento
                $qb->withoutChave()
                    ->table('veiculos_encargos')
                    ->insert([
                        'chave' => $encargo['chave'],
                        'id_veiculo' => $encargo['id_veiculo'],
                        'nome' => $encargo['nome'],
                        'descricao' => $encargo['descricao'],
                        'valor' => $encargo['valor'],
                        'vencimento' => $novoVencimento,
                        'recorrencia' => $encargo['recorrencia'],
                        'dias_antecedencia' => $encargo['dias_antecedencia'],
                        'id_financeiro' => null,
                        'ativo' => 1,
                    ]);

                // Desativar encargo antigo
                $qb->withoutChave()
                    ->table('veiculos_encargos')
                    ->where('id', '=', $encargo['id'])
                    ->update(['ativo' => 0]);

                $renovados++;
                $this->log("Encargo '{$encargo['nome']}' renovado ate {$novoVencimento}");
            } catch (\Exception $e) {
                $this->log("Erro ao renovar encargo #{$encargo['id']}: {$e->getMessage()}", 'ERROR');
            }
        }

        return $renovados;
    }

    /**
     * Calcula a proxima data de vencimento baseado na recorrencia
     */
    private function calcularProximoVencimento(string $dataAtual, string $recorrencia): ?string
    {
        $mesesMap = [
            'mensal' => 1,
            'trimestral' => 3,
            'semestral' => 6,
            'anual' => 12,
        ];

        $meses = $mesesMap[$recorrencia] ?? null;
        if (!$meses) {
            return null;
        }

        $date = new \DateTime($dataAtual);
        $date->modify("+{$meses} months");

        return $date->format('Y-m-d');
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
