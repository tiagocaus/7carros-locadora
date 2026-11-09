<?php

namespace App\Crons\Jobs;

use App\Models\FinanceiroTransacao;
use App\Services\CoraPaymentReconciliationService;

/** Recupera pagamentos Cora pendentes sem depender da entrega do webhook. */
class CoraPaymentReconcileJob extends BaseJob
{
    protected string $name = 'CoraPaymentReconcile';
    protected string $description = 'Concilia cobranças Cora pendentes com consulta autenticada';

    public function __construct(
        private ?string $statePath = null,
        private ?FinanceiroTransacao $model = null,
        private ?CoraPaymentReconciliationService $service = null
    ) {
        $this->statePath ??= dirname(__DIR__, 3) . '/storage/cron/cora-reconcile-state.json';
    }

    protected function handle(): array
    {
        $model = $this->model ?? new FinanceiroTransacao();
        $service = $this->service ?? new CoraPaymentReconciliationService();
        $cursor = $this->carregarCursor();
        $rows = $model->listarCoraPendentesParaConciliacao($cursor);
        if (!$rows && $cursor > 0) {
            $this->salvarCursor(0);
            $rows = $model->listarCoraPendentesParaConciliacao(0);
        }
        $checked = $paid = $errors = 0;
        $gateways = [];
        $started = microtime(true);
        foreach ($rows as $row) {
            if ($checked > 0 && microtime(true) - $started >= 60) break;
            $event = ['event' => 'invoice.cron', 'event_id' => '', 'resource_id' => $row['external_id']];
            try {
                $key = $row['chave'] . ':' . $row['id_gateway'];
                $gateway = $gateways[$key] ??= $service->criarGateway($row);
                $result = $service->confirmar($row, $gateway, $event);
                if ($result['status'] === 'paid') {
                    $paid++;
                    $this->log('Pagamento confirmado [Financeiro #' . (int) $row['id_financeiro'] . ']');
                }
            } catch (\Throwable $e) {
                $errors++;
                CoraPaymentReconciliationService::log('cron_falhou', $event, $row, $e);
                $this->log('Consulta não concluída [Transação #' . (int) $row['id'] . ']; disponível no próximo ciclo.', 'WARNING');
            }
            $checked++;
            // Avança inclusive na falha; após o fim da lista, volta às pendentes anteriores.
            $this->salvarCursor((int) $row['id']);
        }
        if ($checked === count($rows) && count($rows) < 50) $this->salvarCursor(0);
        return [
            'success' => $errors === 0,
            'status' => $errors === 0 ? self::STATUS_SUCCESS : ($checked > $errors ? self::STATUS_PARTIAL : self::STATUS_FAILED),
            'message' => "{$checked} cobrança(s) Cora consultada(s); {$paid} pagamento(s) confirmado(s); {$errors} falha(s)",
            'data' => ['verificadas' => $checked, 'confirmadas' => $paid, 'erros' => $errors],
        ];
    }

    private function carregarCursor(): int
    {
        if (!is_file($this->statePath)) return 0;
        $state = json_decode((string) file_get_contents($this->statePath), true);
        return max(0, (int) ($state['last_id'] ?? 0));
    }

    private function salvarCursor(int $id): void
    {
        $dir = dirname($this->statePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar o diretório do cursor Cora.');
        }
        $temp = tempnam($dir, 'cora-cursor-');
        if ($temp === false) throw new \RuntimeException('Não foi possível criar o cursor Cora.');
        try {
            if (file_put_contents($temp, json_encode(['last_id' => $id], JSON_THROW_ON_ERROR), LOCK_EX) === false
                || !rename($temp, $this->statePath)) {
                throw new \RuntimeException('Não foi possível salvar o cursor Cora.');
            }
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }
}
