<?php

namespace App\Crons;

use App\Crons\Jobs\BaseJob;

/**
 * Orquestrador de Jobs CRON
 *
 * Gerencia execução de múltiplos jobs e coleta resultados
 */
class CronRunner
{
    private array $jobs = [];
    private array $results = [];

    /**
     * Registra um job para execução
     */
    public function registerJob(BaseJob $job): void
    {
        $this->jobs[] = $job;
    }

    /**
     * Executa todos os jobs registrados
     *
     * @return array Sumário da execução
     */
    public function run(): array
    {
        $this->results = [];
        $startTime = microtime(true);

        foreach ($this->jobs as $job) {
            echo "\n--- Executando: {$job->getName()} ---\n";
            
            try {
                $result = $job->run();
                $this->results[] = [
                    'job' => $job->getName(),
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? '',
                    'duration' => $result['duration'] ?? 0,
                    'data' => $result['data'] ?? [],
                    'logs' => $result['logs'] ?? [],
                ];

                $status = $result['success'] ? '✓ SUCESSO' : '✗ FALHOU';
                echo "Status: {$status}\n";
                echo "Duração: {$result['duration']}s\n";
                echo "Mensagem: {$result['message']}\n";

            } catch (\Exception $e) {
                $this->results[] = [
                    'job' => $job->getName(),
                    'success' => false,
                    'message' => 'Erro fatal: ' . $e->getMessage(),
                    'duration' => 0,
                    'data' => [],
                    'logs' => [],
                ];

                echo "✗ ERRO FATAL: {$e->getMessage()}\n";
            }

            echo "---\n";
        }

        $totalDuration = round(microtime(true) - $startTime, 2);

        return $this->getSummary($totalDuration);
    }

    /**
     * Gera sumário da execução
     */
    private function getSummary(float $totalDuration): array
    {
        $successful = 0;
        $failed = 0;

        foreach ($this->results as $result) {
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
        }

        return [
            'total_jobs' => count($this->results),
            'successful' => $successful,
            'failed' => $failed,
            'duration' => $totalDuration,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $this->results,
        ];
    }

    /**
     * Verifica se todos os jobs foram executados com sucesso
     */
    public function isSuccessful(): bool
    {
        foreach ($this->results as $result) {
            if (!$result['success']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtém os resultados de todos os jobs
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
