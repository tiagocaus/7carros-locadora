<?php

namespace App\Crons\Jobs;

use App\Models\Whatsapp;
use App\Services\WhatsAppSessionStatusService;

/**
 * Sincroniza o estado local das conexoes com o estado operacional na WuzAPI.
 */
class SyncWhatsappStatusJob extends BaseJob
{
    protected string $name = 'Sync WhatsApp Status';
    protected string $description = 'Sincroniza status das conexoes WhatsApp com o provedor';

    private WhatsAppSessionStatusService $statusService;
    private ?Whatsapp $whatsappModel;

    public function __construct(
        ?WhatsAppSessionStatusService $statusService = null,
        ?Whatsapp $whatsappModel = null
    ) {
        $this->statusService = $statusService ?? new WhatsAppSessionStatusService();
        $this->whatsappModel = $whatsappModel;
    }

    protected function handle(): array
    {
        $this->log('Iniciando sincronizacao de status WhatsApp...');

        if (!$this->statusService->isConfigured()) {
            $this->log('WHATSAPP_API_URL nao configurada', 'WARNING');
            return [
                'success' => true,
                'message' => 'WHATSAPP_API_URL nao configurada',
                'data' => $this->emptyCounters(),
            ];
        }

        $model = $this->whatsappModel ?? new Whatsapp();
        $counters = $this->emptyCounters();
        $sessionWasArray = isset($_SESSION) && is_array($_SESSION);
        $previousSession = $sessionWasArray ? $_SESSION : [];

        try {
            $conexoes = $model->listarParaSincronizacaoStatus();
            $this->log('Encontradas ' . count($conexoes) . ' conexoes para verificar');

            foreach ($conexoes as $conexao) {
                $counters['checked']++;
                $id = (int) $conexao['id'];
                $currentStatus = strtolower((string) $conexao['status']);

                try {
                    $result = $currentStatus === WhatsAppSessionStatusService::STATUS_DISCONNECTED
                        ? $this->statusService->consultar((string) $conexao['instanceName'])
                        : $this->statusService->consultarComConfirmacao((string) $conexao['instanceName']);

                    if (!$result['conclusive']) {
                        $counters['inconclusive']++;
                        $this->log(
                            "Conexao #{$id}: consulta inconclusiva (" . WhatsAppSessionStatusService::diagnostic($result) . ')',
                            'WARNING'
                        );
                        continue;
                    }

                    $counters['conclusive']++;
                    $newStatus = (string) $result['status'];
                    if ($newStatus === $currentStatus) {
                        continue;
                    }

                    if (!isset($_SESSION) || !is_array($_SESSION)) {
                        $_SESSION = [];
                    }
                    $_SESSION['chave'] = (string) $conexao['chave'];
                    $model->atualizarStatus($id, $newStatus, $result['owner'] ?? null);
                    $counters['updated']++;

                    if ($newStatus === WhatsAppSessionStatusService::STATUS_DISCONNECTED) {
                        $counters['disconnected_confirmed']++;
                    } elseif ($currentStatus === WhatsAppSessionStatusService::STATUS_DISCONNECTED) {
                        $counters['recovered']++;
                    }

                    $this->log("Conexao #{$id} atualizada: {$currentStatus} -> {$newStatus}");
                } catch (\Throwable $e) {
                    $counters['inconclusive']++;
                    $this->log("Conexao #{$id}: erro inesperado durante sincronizacao", 'WARNING');
                } finally {
                    if (isset($_SESSION) && is_array($_SESSION)) {
                        unset($_SESSION['chave']);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->restoreSession($sessionWasArray, $previousSession);
            $this->log('Erro ao sincronizar: ' . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'status' => self::STATUS_FAILED,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage(),
                'data' => $counters,
            ];
        }

        $this->restoreSession($sessionWasArray, $previousSession);

        $status = self::STATUS_SUCCESS;
        if ($counters['inconclusive'] > 0) {
            $status = $counters['conclusive'] > 0 ? self::STATUS_PARTIAL : self::STATUS_FAILED;
        }

        $message = sprintf(
            '%d verificadas, %d atualizadas, %d recuperadas, %d inconclusivas',
            $counters['checked'],
            $counters['updated'],
            $counters['recovered'],
            $counters['inconclusive']
        );
        $this->log('Sincronizacao concluida: ' . $message);

        return [
            'success' => $status === self::STATUS_SUCCESS,
            'status' => $status,
            'message' => $message,
            'data' => $counters,
        ];
    }

    private function emptyCounters(): array
    {
        return [
            'checked' => 0,
            'conclusive' => 0,
            'inconclusive' => 0,
            'disconnected_confirmed' => 0,
            'recovered' => 0,
            'updated' => 0,
        ];
    }

    private function restoreSession(bool $sessionWasArray, array $previousSession): void
    {
        if ($sessionWasArray) {
            $_SESSION = $previousSession;
            return;
        }

        unset($_SESSION);
    }
}
