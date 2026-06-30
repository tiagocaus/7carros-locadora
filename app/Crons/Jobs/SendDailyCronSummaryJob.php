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
        $subjectPrefix = ($stats['failed'] > 0 || $stats['missing'] > 0) ? '[ERRO] ' : '';
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

            if (!empty($jobs[$jobId]['success'])) {
                $successful++;
            } else {
                $failed++;
            }
        }

        return [
            'expected' => count($expected),
            'executed' => $executed,
            'successful' => $successful,
            'failed' => $failed,
            'missing' => $missing,
            'duration' => round($duration, 2),
        ];
    }

    private function buildHtml(array $summary, array $stats): string
    {
        $date = $this->formatDate($summary['date'] ?? today());
        $statusColor = ($stats['failed'] > 0 || $stats['missing'] > 0) ? '#dc2626' : '#059669';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#1f2937}
            .container{max-width:720px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden}
            .header{background:#111827;color:white;padding:20px}
            .header h1{margin:0;font-size:20px}
            .content{padding:20px}
            .summary{border-left:4px solid ' . $statusColor . ';background:#f9fafb;padding:14px;margin-bottom:16px}
            .job{border:1px solid #e5e7eb;border-radius:8px;margin-bottom:12px;padding:14px}
            .job h2{font-size:15px;margin:0 0 8px 0}
            .ok{color:#059669;font-weight:bold}.fail{color:#dc2626;font-weight:bold}.missing{color:#d97706;font-weight:bold}
            .muted{color:#6b7280;font-size:12px}
            .row{display:flex;justify-content:space-between;border-bottom:1px dashed #e5e7eb;padding:4px 0;font-size:13px}
            .logs{background:#fef2f2;color:#991b1b;padding:8px;border-radius:6px;font-family:monospace;font-size:12px;margin-top:8px}
            .footer{text-align:center;color:#9ca3af;font-size:11px;padding:16px}
        </style></head><body><div class="container">';

        $html .= '<div class="header"><h1>Resumo Diario dos CRONs</h1><div class="muted">' . htmlspecialchars($date) . '</div></div>';
        $html .= '<div class="content">';
        $html .= '<div class="summary">';
        $html .= '<div class="row"><span>Jobs esperados</span><strong>' . $stats['expected'] . '</strong></div>';
        $html .= '<div class="row"><span>Executados</span><strong>' . $stats['executed'] . '</strong></div>';
        $html .= '<div class="row"><span>Sucesso</span><strong>' . $stats['successful'] . '</strong></div>';
        $html .= '<div class="row"><span>Falhas</span><strong>' . $stats['failed'] . '</strong></div>';
        $html .= '<div class="row"><span>Nao executados</span><strong>' . $stats['missing'] . '</strong></div>';
        $html .= '<div class="row"><span>Tempo total registrado</span><strong>' . number_format($stats['duration'], 2, ',', '.') . 's</strong></div>';
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
                $status = !empty($job['success']) ? 'SUCESSO' : 'FALHA';
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
        $class = $job['status'] === 'SUCESSO' ? 'ok' : ($job['status'] === 'FALHA' ? 'fail' : 'missing');
        $html = '<div class="job">';
        $html .= '<h2>' . htmlspecialchars($job['expected_time'] . '  ' . $job['label']) . '</h2>';
        $html .= '<div class="' . $class . '">' . htmlspecialchars($job['status']) . '</div>';
        $html .= '<div class="row"><span>Mensagem</span><strong>' . htmlspecialchars((string) $job['message']) . '</strong></div>';
        $html .= '<div class="row"><span>Duracao</span><strong>' . number_format((float) $job['duration'], 2, ',', '.') . 's</strong></div>';

        foreach ($this->flattenData($job['data']) as $label => $value) {
            $html .= '<div class="row"><span>' . htmlspecialchars($label) . '</span><strong>' . htmlspecialchars($value) . '</strong></div>';
        }

        if (!empty($job['logs'])) {
            $html .= '<div class="logs">' . nl2br(htmlspecialchars(implode("\n", $job['logs']))) . '</div>';
        }

        $html .= '</div>';

        return $html;
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
