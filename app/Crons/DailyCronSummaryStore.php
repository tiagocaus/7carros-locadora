<?php

namespace App\Crons;

use App\Crons\Jobs\BaseJob;

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
        \App\Crons\Jobs\CalculateOverdueFeesJob::class => [
            'label' => 'Juros e Multa Financeiro',
            'time' => '00:15',
            'order' => 15,
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

        $date = today();
        $summary = $this->read($date);
        $meta = self::EXPECTED_JOBS[$jobId];

        $summary['jobs'][$jobId] = [
            'job_id' => $jobId,
            'job' => $result['job'] ?? $meta['label'],
            'label' => $meta['label'],
            'expected_time' => $meta['time'],
            'executed_at' => now(),
            'success' => (bool) ($result['success'] ?? false),
            'status' => BaseJob::normalizeStatus(
                isset($result['status']) ? (string) $result['status'] : null,
                (bool) ($result['success'] ?? false)
            ),
            'message' => (string) ($result['message'] ?? ''),
            'duration' => (float) ($result['duration'] ?? 0),
            'data' => $result['data'] ?? [],
            'logs' => $this->filterLogs($result['logs'] ?? []),
            'schedule' => (string) ($result['schedule'] ?? ''),
        ];

        $summary['updated_at'] = now();
        $this->write($date, $summary);
    }

    public function read(?string $date = null): array
    {
        $date = $date ?: today();
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
        $groups = [];

        foreach ($logs as $index => $log) {
            $log = (string) $log;
            $parsed = $this->parseLog($log);
            if ($parsed === null) {
                continue;
            }

            $normalized = $this->normalizeLogMessage($parsed['message']);
            $signature = $parsed['level'] . '|' . $parsed['component'] . '|' . $normalized['signature'];

            if (!isset($groups[$signature])) {
                $groups[$signature] = [
                    'level' => $parsed['level'],
                    'component' => $parsed['component'],
                    'message' => $normalized['message'],
                    'count' => 0,
                    'examples' => [],
                    'last_occurred_at' => $parsed['timestamp'],
                    '_last_index' => $index,
                ];
            }

            $groups[$signature]['count']++;
            $groups[$signature]['last_occurred_at'] = $parsed['timestamp'];
            $groups[$signature]['_last_index'] = $index;

            if ($normalized['example'] !== null
                && !in_array($normalized['example'], $groups[$signature]['examples'], true)
                && count($groups[$signature]['examples']) < 3
            ) {
                $groups[$signature]['examples'][] = $normalized['example'];
            }
        }

        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => $a['_last_index'] <=> $b['_last_index']);
        $groups = array_slice($groups, -10);

        foreach ($groups as &$group) {
            unset($group['_last_index']);
        }
        unset($group);

        return $groups;
    }

    private function parseLog(string $log): ?array
    {
        if (!preg_match('/^\[([^]]+)] \[(ERROR|WARNING)] \[([^]]+)] (.*)$/u', $log, $matches)) {
            return null;
        }

        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'component' => $matches[3],
            'message' => $matches[4],
        ];
    }

    private function normalizeLogMessage(string $message): array
    {
        if (preg_match('/^Notificacao\s+(\S+)\s+nao enfileirada:\s+(.+)$/u', $message, $matches)) {
            $channel = $matches[1];
            $reason = $matches[2];
            $example = null;

            if (preg_match('/^(.*) \(([^()]*)\)$/u', $reason, $reasonParts)) {
                $reason = $reasonParts[1];
                $example = $reasonParts[2];
            }

            return [
                'signature' => 'notification|' . $channel . '|' . $reason,
                'message' => "Notificacao {$channel} nao enfileirada: {$reason}",
                'example' => $example,
            ];
        }

        if (preg_match('/^Erro ao renovar contrato #([^:]+):\s*(.+)$/u', $message, $matches)) {
            return [
                'signature' => 'contract-renewal|' . $matches[2],
                'message' => 'Erro ao renovar contrato: ' . $matches[2],
                'example' => '#' . $matches[1],
            ];
        }

        $patterns = [
            '/^(Erro ao (?:rotacionar hold|gerar lancamento para encargo|renovar encargo) )#([^:]+)(: .+)$/u',
            '/^(Erro no tenant|Tenant) ([^:]+)(: .+)$/u',
            '/^(Placa) ([^:]+)(: .+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $normalized = $matches[1] . $matches[3];
                return [
                    'signature' => 'entity|' . $normalized,
                    'message' => $normalized,
                    'example' => $matches[2],
                ];
            }
        }

        return [
            'signature' => 'exact|' . $message,
            'message' => $message,
            'example' => null,
        ];
    }
}
