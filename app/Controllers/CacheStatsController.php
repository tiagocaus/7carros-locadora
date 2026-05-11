<?php

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Response;

/**
 * Controller para visualização de estatísticas de cache
 *
 * ATENÇÃO: Este controller deve ser protegido e acessível apenas em desenvolvimento
 * ou para administradores do sistema
 */
class CacheStatsController
{
    /**
     * Exibe estatísticas do cache
     */
    public function index(): void
    {
        // Obtém estatísticas
        $stats = Cache::stats();
        $info = Cache::info();

        // Calcula métricas adicionais
        $metrics = $this->calculateMetrics($stats);

        // Em JSON se requisição for AJAX
        if ($this->isAjaxRequest()) {
            Response::json([
                'stats' => $stats,
                'info' => $info,
                'metrics' => $metrics
            ]);
            return;
        }

        // Renderiza view HTML
        $this->renderHtml($stats, $info, $metrics);
    }

    /**
     * Limpa o cache
     */
    public function clear(): void
    {
        $result = Cache::flush();

        if ($this->isAjaxRequest()) {
            Response::json([
                'success' => $result,
                'message' => $result ? 'Cache limpo com sucesso!' : 'Erro ao limpar cache'
            ]);
            return;
        }

        header('Location: /cache/stats');
        exit;
    }

    /**
     * Calcula métricas adicionais
     */
    private function calculateMetrics(array $stats): array
    {
        $total = $stats['total_requests'];
        $hits = $stats['hits'];
        $misses = $stats['misses'];

        return [
            'efficiency' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            'miss_rate' => $total > 0 ? round(($misses / $total) * 100, 2) : 0,
            'average_hits_per_request' => $total > 0 ? round($hits / $total, 2) : 0
        ];
    }

    /**
     * Verifica se é requisição AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Renderiza HTML com estatísticas
     */
    private function renderHtml(array $stats, array $info, array $metrics): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cache Statistics - 7Carros Locadora</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    padding: 20px;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .header {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                }
                h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 10px;
                }
                .status {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status.enabled {
                    background: #10b981;
                    color: white;
                }
                .status.disabled {
                    background: #ef4444;
                    color: white;
                }
                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-bottom: 20px;
                }
                .card {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .card h2 {
                    font-size: 14px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 10px;
                }
                .card .value {
                    font-size: 32px;
                    font-weight: 700;
                    color: #333;
                }
                .card .subtitle {
                    font-size: 12px;
                    color: #999;
                    margin-top: 5px;
                }
                .info-grid {
                    display: grid;
                    gap: 10px;
                }
                .info-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #eee;
                }
                .info-item:last-child {
                    border-bottom: none;
                }
                .info-label {
                    color: #666;
                    font-weight: 500;
                }
                .info-value {
                    color: #333;
                    font-family: monospace;
                }
                .progress-bar {
                    height: 8px;
                    background: #e5e7eb;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-top: 10px;
                }
                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #3b82f6, #10b981);
                    transition: width 0.3s ease;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #ef4444;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    border: none;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 600;
                    transition: background 0.2s;
                }
                .btn:hover {
                    background: #dc2626;
                }
                .actions {
                    display: flex;
                    gap: 10px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📊 Cache Statistics</h1>
                    <span class="status <?= $stats['enabled'] ? 'enabled' : 'disabled' ?>">
                        <?= $stats['enabled'] ? '✓ Enabled' : '✗ Disabled' ?>
                    </span>
                    <div class="actions">
                        <button onclick="location.reload()" class="btn" style="background: #3b82f6">
                            🔄 Atualizar
                        </button>
                        <button onclick="confirmClear()" class="btn">
                            🗑️ Limpar Cache
                        </button>
                    </div>
                </div>

                <div class="grid">
                    <div class="card">
                        <h2>Total Requests</h2>
                        <div class="value"><?= number_format($stats['total_requests']) ?></div>
                        <div class="subtitle">Hits + Misses</div>
                    </div>

                    <div class="card">
                        <h2>Cache Hits</h2>
                        <div class="value" style="color: #10b981"><?= number_format($stats['hits']) ?></div>
                        <div class="subtitle"><?= $stats['hit_rate'] ?> taxa de acerto</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $metrics['efficiency'] ?>%"></div>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Cache Misses</h2>
                        <div class="value" style="color: #ef4444"><?= number_format($stats['misses']) ?></div>
                        <div class="subtitle"><?= $metrics['miss_rate'] ?>% taxa de falha</div>
                    </div>

                    <div class="card">
                        <h2>Sets</h2>
                        <div class="value" style="color: #3b82f6"><?= number_format($stats['sets']) ?></div>
                        <div class="subtitle">Itens armazenados</div>
                    </div>

                    <div class="card">
                        <h2>Deletes</h2>
                        <div class="value" style="color: #f59e0b"><?= number_format($stats['deletes']) ?></div>
                        <div class="subtitle">Itens removidos</div>
                    </div>

                    <div class="card">
                        <h2>Efficiency</h2>
                        <div class="value" style="color: #10b981"><?= $metrics['efficiency'] ?>%</div>
                        <div class="subtitle">Hit rate</div>
                    </div>
                </div>

                <div class="card">
                    <h2>Redis Server Info</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value"><?= $info['status'] ?? 'unknown' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Version</span>
                            <span class="info-value"><?= $info['version'] ?? 'N/A' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Memory Used</span>
                            <span class="info-value"><?= $info['used_memory'] ?? 'N/A' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Connected Clients</span>
                            <span class="info-value"><?= $info['connected_clients'] ?? 'N/A' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Keys</span>
                            <span class="info-value"><?= number_format($info['total_keys'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function confirmClear() {
                    if (confirm('Tem certeza que deseja limpar TODO o cache?\n\nEsta ação não pode ser desfeita.')) {
                        window.location.href = '/cache/clear';
                    }
                }
            </script>
        </body>
        </html>
        <?php
    }
}
