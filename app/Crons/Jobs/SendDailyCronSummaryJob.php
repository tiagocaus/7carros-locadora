<?php

namespace App\Crons\Jobs;

use App\Core\Database;
use App\Crons\DailyCronSummaryStore;
use App\Services\EmailService;

/**
 * Envia o resumo diario dos CRONs executados 1x por dia.
 */
class SendDailyCronSummaryJob extends BaseJob
{
    protected string $name = 'SendDailyCronSummary';
    protected string $description = 'Envia resumo diario dos CRONs executados uma vez por dia';

    protected function handle(): array
    {
        $emailDestino = Database::env('APP_COMPANY_EMAIL');
        if (empty($emailDestino)) {
            $this->log('APP_COMPANY_EMAIL nao configurado, resumo diario nao enviado', 'WARNING');
            return [
                'success' => true,
                'message' => 'APP_COMPANY_EMAIL nao configurado',
                'data' => [],
            ];
        }

        $store = new DailyCronSummaryStore();
        $summary = $store->read();
        $stats = $this->calculateStats($summary);
        $subjectPrefix = $this->subjectPrefix($stats);
        $subject = $subjectPrefix . 'Resumo Diario dos CRONs - ' . format_date(today());

        $emailService = new EmailService();
        $result = $emailService->send([
            'to' => $emailDestino,
            'to_name' => '7Carros Admin',
            'subject' => $subject,
            'body' => $this->buildHtml($summary, $stats),
            'body_text' => $this->buildText($summary, $stats),
        ]);

        if (!$result['success']) {
            $this->log('Falha ao enviar resumo diario: ' . ($result['message'] ?? 'erro desconhecido'), 'ERROR');
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Falha ao enviar resumo diario',
                'data' => $stats,
            ];
        }

        $this->log("Resumo diario enviado para {$emailDestino}");

        return [
            'success' => true,
            'message' => "Resumo diario enviado para {$emailDestino}",
            'data' => $stats,
        ];
    }

    private function calculateStats(array $summary): array
    {
        $jobs = $summary['jobs'] ?? [];
        $expected = DailyCronSummaryStore::EXPECTED_JOBS;
        $executed = 0;
        $successful = 0;
        $partial = 0;
        $failed = 0;
        $missing = 0;
        $duration = 0.0;

        foreach ($expected as $jobId => $_meta) {
            if (!isset($jobs[$jobId])) {
                $missing++;
                continue;
            }

            $executed++;
            $duration += (float) ($jobs[$jobId]['duration'] ?? 0);

            $status = $this->resolveStoredStatus($jobs[$jobId]);

            if ($status === BaseJob::STATUS_SUCCESS) {
                $successful++;
            } elseif ($status === BaseJob::STATUS_PARTIAL) {
                $partial++;
            } else {
                $failed++;
            }
        }

        return [
            'expected' => count($expected),
            'executed' => $executed,
            'successful' => $successful,
            'partial' => $partial,
            'failed' => $failed,
            'missing' => $missing,
            'duration' => round($duration, 2),
        ];
    }

    private function buildHtml(array $summary, array $stats): string
    {
        $date = $this->formatDate($summary['date'] ?? today());
        $statusColor = ($stats['failed'] > 0 || $stats['missing'] > 0)
            ? '#dc2626'
            : ($stats['partial'] > 0 ? '#d97706' : '#059669');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#1f2937}
            .container{max-width:720px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden}
            .header{background:#111827;color:white;padding:20px}
            .header h1{margin:0;font-size:20px}
            .content{padding:20px}
            .summary{border-left:4px solid ' . $statusColor . ';background:#f9fafb;padding:14px;margin-bottom:16px}
            .job{border:1px solid #e5e7eb;border-radius:8px;margin-bottom:12px;padding:14px}
            .job h2{font-size:15px;margin:0 0 8px 0}
            .ok{color:#059669;font-weight:bold}.partial{color:#d97706;font-weight:bold}.fail{color:#dc2626;font-weight:bold}.missing{color:#d97706;font-weight:bold}
            .muted{color:#6b7280;font-size:12px}
            .row{width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px}
            .row td{border-bottom:1px dashed #e5e7eb;padding:4px 0;vertical-align:top;overflow-wrap:anywhere;word-break:break-word}
            .row-label{width:38%;padding-right:12px!important}.row-value{width:62%;text-align:right;font-weight:bold}
            .logs{background:#fef2f2;color:#991b1b;padding:8px;border-radius:6px;font-family:monospace;font-size:12px;margin-top:8px;overflow-wrap:anywhere;word-break:break-word;white-space:normal}
            .log-entry{margin-bottom:6px}.log-entry:last-child{margin-bottom:0}
            .footer{text-align:center;color:#9ca3af;font-size:11px;padding:16px}
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>Resumo Diario dos CRONs</h1><div class="muted">' . htmlspecialchars($date) . '</div></div>';
        $html .= '<div class="content">';
        $html .= '<div class="summary">';
        $html .= $this->buildRowHtml('Jobs esperados', (string) $stats['expected']);
        $html .= $this->buildRowHtml('Executados', (string) $stats['executed']);
        $html .= $this->buildRowHtml('Sucesso', (string) $stats['successful']);
        $html .= $this->buildRowHtml('Sucesso parcial', (string) $stats['partial']);
        $html .= $this->buildRowHtml('Falhas', (string) $stats['failed']);
        $html .= $this->buildRowHtml('Nao executados', (string) $stats['missing']);
        $html .= $this->buildRowHtml('Tempo total registrado', number_format($stats['duration'], 2, ',', '.') . 's');
        $html .= '</div>';

        foreach ($this->orderedJobs($summary) as $job) {
            $html .= $this->buildJobHtml($job);
        }

        $html .= '</div><div class="footer">7Carros Locadora - Sistema de Gestao</div></div></body></html>';

        return $html;
    }

    private function buildText(array $summary, array $stats): string
    {
        $lines = [
            'RESUMO DIARIO DOS CRONs',
            'Data: ' . $this->formatDate($summary['date'] ?? today()),
            '',
            'Jobs esperados: ' . $stats['expected'],
            'Executados: ' . $stats['executed'],
            'Sucesso: ' . $stats['successful'],
            'Sucesso parcial: ' . $stats['partial'],
            'Falhas: ' . $stats['failed'],
            'Nao executados: ' . $stats['missing'],
            'Tempo total registrado: ' . number_format($stats['duration'], 2, ',', '.') . 's',
            '',
        ];

        foreach ($this->orderedJobs($summary) as $job) {
            $lines[] = $job['expected_time'] . ' ' . $job['label'];
            $lines[] = 'Status: ' . $job['status'];
            $lines[] = 'Mensagem: ' . $job['message'];
            $lines[] = 'Duracao: ' . number_format((float) $job['duration'], 2, ',', '.') . 's';
            foreach ($this->formatLogEntries($job['logs']) as $logEntry) {
                $lines[] = $logEntry;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function orderedJobs(array $summary): array
    {
        $recorded = $summary['jobs'] ?? [];
        $items = [];

        foreach (DailyCronSummaryStore::EXPECTED_JOBS as $jobId => $meta) {
            $job = $recorded[$jobId] ?? null;
            $status = 'NAO EXECUTADO';

            if ($job) {
                $status = match ($this->resolveStoredStatus($job)) {
                    BaseJob::STATUS_SUCCESS => 'SUCESSO',
                    BaseJob::STATUS_PARTIAL => 'SUCESSO PARCIAL',
                    default => 'FALHA',
                };
            }

            $items[] = [
                'order' => $meta['order'],
                'label' => $meta['label'],
                'expected_time' => $meta['time'],
                'status' => $status,
                'message' => $job['message'] ?? 'Sem registro ate o envio do resumo',
                'duration' => (float) ($job['duration'] ?? 0),
                'data' => $job['data'] ?? [],
                'logs' => $job['logs'] ?? [],
                'executed_at' => $job['executed_at'] ?? null,
            ];
        }

        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $items;
    }

    private function buildJobHtml(array $job): string
    {
        $class = match ($job['status']) {
            'SUCESSO' => 'ok',
            'SUCESSO PARCIAL' => 'partial',
            'FALHA' => 'fail',
            default => 'missing',
        };
        $html = '<div class="job">';
        $html .= '<h2>' . htmlspecialchars($job['expected_time'] . '  ' . $job['label']) . '</h2>';
        $html .= '<div class="' . $class . '">' . htmlspecialchars($job['status']) . '</div>';
        $html .= $this->buildRowHtml('Mensagem', (string) $job['message']);
        $html .= $this->buildRowHtml('Duracao', number_format((float) $job['duration'], 2, ',', '.') . 's');

        foreach ($this->flattenData($job['data']) as $label => $value) {
            $html .= $this->buildRowHtml($label, $value);
        }

        if (!empty($job['logs'])) {
            $html .= '<div class="logs">' . $this->buildLogsHtml($job['logs']) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function buildRowHtml(string $label, string $value): string
    {
        return '<table role="presentation" class="row" width="100%" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px"><tr>'
            . '<td class="row-label" style="width:38%;padding:4px 12px 4px 0;vertical-align:top;border-bottom:1px dashed #e5e7eb;overflow-wrap:anywhere;word-break:break-word">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="row-value" style="width:62%;padding:4px 0;vertical-align:top;text-align:right;font-weight:bold;border-bottom:1px dashed #e5e7eb;overflow-wrap:anywhere;word-break:break-word">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr></table>';
    }

    private function buildLogsHtml(array $logs): string
    {
        return implode('', array_map(
            static fn (string $entry): string => '<div class="log-entry">' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '</div>',
            $this->formatLogEntries($logs)
        ));
    }

    private function formatLogEntries(array $logs): array
    {
        $entries = [];

        foreach ($logs as $log) {
            if (!is_array($log)) {
                $entries[] = (string) $log;
                continue;
            }

            $count = max(1, (int) ($log['count'] ?? 1));
            $prefix = '[' . ($log['level'] ?? 'WARNING') . '] [' . ($log['component'] ?? 'CRON') . '] ';
            $text = $prefix . ($count > 1 ? $count . 'x ' : '') . (string) ($log['message'] ?? '');
            $examples = array_values(array_filter((array) ($log['examples'] ?? []), 'is_scalar'));

            if ($examples !== []) {
                $text .= ' (ex.: ' . implode(', ', array_map('strval', $examples)) . ')';
            }

            $entries[] = $text;
        }

        return $entries;
    }

    private function subjectPrefix(array $stats): string
    {
        if (($stats['failed'] ?? 0) > 0 || ($stats['missing'] ?? 0) > 0) {
            return '[ERRO] ';
        }

        return ($stats['partial'] ?? 0) > 0 ? '[ATENCAO] ' : '';
    }

    private function resolveStoredStatus(array $job): string
    {
        if (isset($job['status'])) {
            return BaseJob::normalizeStatus((string) $job['status'], (bool) ($job['success'] ?? false));
        }

        $errors = $job['data']['erros'] ?? 0;
        $errorCount = is_array($errors) ? count($errors) : (int) $errors;
        $errorCount = max($errorCount, (int) ($job['data']['falhas'] ?? 0));

        if (!empty($job['success']) && $errorCount > 0) {
            return BaseJob::STATUS_PARTIAL;
        }

        return !empty($job['success']) ? BaseJob::STATUS_SUCCESS : BaseJob::STATUS_FAILED;
    }

    private function flattenData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($key === 'erros') {
                    $result[$this->formatKey($key)] = (string) count($value);
                }
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'sim' : 'nao';
            }

            $result[$this->formatKey((string) $key)] = (string) $value;
        }

        return $result;
    }

    private function formatKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? \App\Helpers\DateHelper::formatTimestamp($timestamp, 'd/m/Y') : $date;
    }
}
