<?php

namespace App\Crons;

/**
 * Armazena e le o resumo dos CRONs diarios.
 */
class DailyCronSummaryStore
{
    public const EXPECTED_JOBS = [
        \App\Crons\Jobs\CheckPreventiveMaintenanceJob::class => [
            'label' => 'Manutencao Preventiva',
            'time' => '00:05',
            'order' => 10,
        ],
        \App\Crons\Jobs\CleanupOldRecordingsJob::class => [
            'label' => 'Limpeza de Gravacoes',
            'time' => '01:00',
            'order' => 20,
        ],
        \App\Crons\Jobs\RenovarContratosJob::class => [
            'label' => 'Renovacao de Contratos',
            'time' => '01:10',
            'order' => 30,
        ],
        \App\Crons\Jobs\GerarEncargosFinanceiroJob::class => [
            'label' => 'Encargos Financeiros',
            'time' => '02:00',
            'order' => 40,
        ],
        \App\Crons\Jobs\RotateAuthorizationHoldsJob::class => [
            'label' => 'Rotacao de Bloqueios/Caucao',
            'time' => '03:00',
            'order' => 50,
        ],
        \App\Crons\Jobs\SerproAutoConsultaJob::class => [
            'label' => 'Auto-consulta SERPRO',
            'time' => '03:30',
            'order' => 60,
        ],
    ];

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/cron/daily-summary';
    }

    public function shouldRecord(string $jobId): bool
    {
        return isset(self::EXPECTED_JOBS[$jobId]);
    }

    public function record(array $result): void
    {
        $jobId = $result['job_id'] ?? '';
        if (!$this->shouldRecord($jobId)) {
            return;
        }

        $date = date('Y-m-d');
        $summary = $this->read($date);
        $meta = self::EXPECTED_JOBS[$jobId];

        $summary['jobs'][$jobId] = [
            'job_id' => $jobId,
            'job' => $result['job'] ?? $meta['label'],
            'label' => $meta['label'],
            'expected_time' => $meta['time'],
            'executed_at' => date('Y-m-d H:i:s'),
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'duration' => (float) ($result['duration'] ?? 0),
            'data' => $result['data'] ?? [],
            'logs' => $this->filterLogs($result['logs'] ?? []),
            'schedule' => (string) ($result['schedule'] ?? ''),
        ];

        $summary['updated_at'] = date('Y-m-d H:i:s');
        $this->write($date, $summary);
    }

    public function read(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $path = $this->path($date);

        if (!file_exists($path)) {
            return [
                'date' => $date,
                'updated_at' => null,
                'jobs' => [],
            ];
        }

        $content = file_get_contents($path);
        $summary = json_decode((string) $content, true);

        if (!is_array($summary)) {
            return [
                'date' => $date,
                'updated_at' => null,
                'jobs' => [],
            ];
        }

        $summary['date'] = $summary['date'] ?? $date;
        $summary['jobs'] = is_array($summary['jobs'] ?? null) ? $summary['jobs'] : [];

        return $summary;
    }

    private function write(string $date, array $summary): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->path($date), $json);
    }

    private function path(string $date): string
    {
        return $this->directory . '/' . $date . '.json';
    }

    private function filterLogs(array $logs): array
    {
        $filtered = [];

        foreach ($logs as $log) {
            $log = (string) $log;
            if (str_contains($log, '[ERROR]') || str_contains($log, '[WARNING]')) {
                $filtered[] = $log;
            }
        }

        return array_slice($filtered, -10);
    }
}
