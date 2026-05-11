<?php

namespace App\Crons;

use App\Crons\Jobs\BaseJob;

/**
 * Scheduler de Jobs CRON
 *
 * Gerencia execução de jobs com diferentes frequências usando expressões cron
 *
 * Exemplo de uso:
 *   $scheduler = new Scheduler();
 *   $scheduler->job(new ProcessMessageQueueJob())->everyFifteenMinutes();
 *   $scheduler->job(new CheckPreventiveMaintenanceJob())->dailyAt('00:01');
 *   $summary = $scheduler->run();
 */
class Scheduler
{
    /** @var ScheduledJob[] */
    private array $scheduledJobs = [];

    private array $results = [];
    private string $stateFile;
    private string $stateDir;

    public function __construct(?string $stateDir = null)
    {
        $this->stateDir = $stateDir ?? dirname(__DIR__, 2) . '/storage/cron';
        $this->stateFile = $this->stateDir . '/schedule-state.json';
        $this->ensureStateDirectory();
    }

    /**
     * Registra um job para execução com agendamento
     *
     * @param BaseJob $job Job a ser agendado
     * @return ScheduledJob Wrapper fluente para configurar frequência
     */
    public function job(BaseJob $job): ScheduledJob
    {
        $scheduledJob = new ScheduledJob($job, $this);
        $this->scheduledJobs[] = $scheduledJob;
        return $scheduledJob;
    }

    /**
     * Executa todos os jobs que estão agendados para o momento atual
     *
     * @return array Sumário da execução
     */
    public function run(): array
    {
        $this->results = [];
        $startTime = microtime(true);
        $state = $this->loadState();
        $executed = 0;
        $skipped = 0;

        foreach ($this->scheduledJobs as $scheduledJob) {
            $job = $scheduledJob->getJob();
            $jobId = $scheduledJob->getJobId();

            // Verifica se é hora de executar
            if (!$scheduledJob->isDue()) {
                $nextRun = $scheduledJob->getNextRunDate()->format('Y-m-d H:i:s');
                echo "[SKIP] {$job->getName()} - Próxima execução: {$nextRun}\n";
                $skipped++;
                continue;
            }

            // Verifica se já foi executado recentemente (evita duplicatas)
            if ($this->wasRecentlyRun($jobId, $state)) {
                echo "[SKIP] {$job->getName()} - Já executado neste minuto\n";
                $skipped++;
                continue;
            }

            echo "\n--- Executando: {$job->getName()} ---\n";
            echo "Schedule: {$scheduledJob->getFrequencyDescription()}\n";

            try {
                $result = $job->run();
                $this->results[] = [
                    'job' => $job->getName(),
                    'job_id' => $jobId,
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? '',
                    'duration' => $result['duration'] ?? 0,
                    'data' => $result['data'] ?? [],
                    'logs' => $result['logs'] ?? [],
                    'schedule' => $scheduledJob->getExpression(),
                ];

                $status = $result['success'] ? '✓ SUCESSO' : '✗ FALHOU';
                echo "Status: {$status}\n";
                echo "Duração: {$result['duration']}s\n";
                echo "Mensagem: {$result['message']}\n";

                // Marca como executado
                $this->markAsRun($jobId, $state);
                $executed++;

            } catch (\Exception $e) {
                $this->results[] = [
                    'job' => $job->getName(),
                    'job_id' => $jobId,
                    'success' => false,
                    'message' => 'Erro fatal: ' . $e->getMessage(),
                    'duration' => 0,
                    'data' => [],
                    'logs' => [],
                    'schedule' => $scheduledJob->getExpression(),
                ];

                echo "✗ ERRO FATAL: {$e->getMessage()}\n";
                $executed++;
            }

            echo "---\n";
        }

        // Salva estado atualizado
        $this->saveState($state);

        $totalDuration = round(microtime(true) - $startTime, 2);

        return $this->getSummary($totalDuration, $executed, $skipped);
    }

    /**
     * Lista todos os jobs agendados com suas frequências
     *
     * @return array Lista de jobs com informações de agendamento
     */
    public function list(): array
    {
        $list = [];

        foreach ($this->scheduledJobs as $scheduledJob) {
            $job = $scheduledJob->getJob();
            $list[] = [
                'name' => $job->getName(),
                'description' => $job->getDescription(),
                'job_id' => $scheduledJob->getJobId(),
                'expression' => $scheduledJob->getExpression(),
                'frequency' => $scheduledJob->getFrequencyDescription(),
                'next_run' => $scheduledJob->getNextRunDate()->format('Y-m-d H:i:s'),
                'is_due' => $scheduledJob->isDue(),
            ];
        }

        return $list;
    }

    /**
     * Exibe lista de jobs no console
     */
    public function printList(): void
    {
        echo "\n======================================\n";
        echo "  Jobs Agendados\n";
        echo "======================================\n\n";

        $list = $this->list();

        if (empty($list)) {
            echo "Nenhum job agendado.\n";
            return;
        }

        foreach ($list as $item) {
            $dueStatus = $item['is_due'] ? '[DUE NOW]' : '';
            echo "Job: {$item['name']} {$dueStatus}\n";
            echo "  Frequência: {$item['frequency']}\n";
            echo "  Expression: {$item['expression']}\n";
            echo "  Próxima execução: {$item['next_run']}\n";
            echo "\n";
        }
    }

    // ========================================
    // Controle de Estado
    // ========================================

    /**
     * Garante que o diretório de estado existe
     */
    private function ensureStateDirectory(): void
    {
        if (!is_dir($this->stateDir)) {
            mkdir($this->stateDir, 0755, true);
        }
    }

    /**
     * Carrega o estado de execução dos jobs
     */
    private function loadState(): array
    {
        if (!file_exists($this->stateFile)) {
            return [];
        }

        $content = file_get_contents($this->stateFile);
        $state = json_decode($content, true);

        return is_array($state) ? $state : [];
    }

    /**
     * Salva o estado de execução dos jobs
     */
    private function saveState(array $state): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->stateFile, $json);
    }

    /**
     * Verifica se o job foi executado recentemente (no mesmo minuto)
     */
    private function wasRecentlyRun(string $jobId, array $state): bool
    {
        if (!isset($state[$jobId]['last_run'])) {
            return false;
        }

        $lastRun = $state[$jobId]['last_run'];
        $currentMinute = date('Y-m-d H:i');
        $lastRunMinute = substr($lastRun, 0, 16); // "YYYY-MM-DD HH:MM"

        return $currentMinute === $lastRunMinute;
    }

    /**
     * Marca um job como executado
     */
    private function markAsRun(string $jobId, array &$state): void
    {
        $now = date('Y-m-d H:i:s');

        // Encontra o ScheduledJob para calcular próxima execução
        $nextRun = null;
        foreach ($this->scheduledJobs as $scheduledJob) {
            if ($scheduledJob->getJobId() === $jobId) {
                $nextRun = $scheduledJob->getNextRunDate()->format('Y-m-d H:i:s');
                break;
            }
        }

        $state[$jobId] = [
            'last_run' => $now,
            'next_run' => $nextRun,
        ];
    }

    /**
     * Limpa o histórico de execução de um job específico
     */
    public function clearJobState(string $jobId): void
    {
        $state = $this->loadState();

        if (isset($state[$jobId])) {
            unset($state[$jobId]);
            $this->saveState($state);
        }
    }

    /**
     * Limpa todo o histórico de execução
     */
    public function clearAllState(): void
    {
        if (file_exists($this->stateFile)) {
            unlink($this->stateFile);
        }
    }

    // ========================================
    // Sumário e Resultados
    // ========================================

    /**
     * Gera sumário da execução
     */
    private function getSummary(float $totalDuration, int $executed, int $skipped): array
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
            'total_scheduled' => count($this->scheduledJobs),
            'executed' => $executed,
            'skipped' => $skipped,
            'successful' => $successful,
            'failed' => $failed,
            'duration' => $totalDuration,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $this->results,
        ];
    }

    /**
     * Verifica se todos os jobs executados foram bem-sucedidos
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
     * Obtém os resultados de todos os jobs executados
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Obtém os jobs agendados
     *
     * @return ScheduledJob[]
     */
    public function getScheduledJobs(): array
    {
        return $this->scheduledJobs;
    }
}
