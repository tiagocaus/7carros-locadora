<?php

namespace App\Crons\Jobs;

use App\Classes\QueryBuilder;
use App\Core\Database;
use mysqli;

/**
 * Job para sincronizar status das conexoes WhatsApp
 *
 * Verifica periodicamente o estado real das instancias no provedor de WhatsApp
 * e atualiza o banco de dados se houver divergencia.
 *
 * Cenarios tratados:
 * - Instancia expirada / removida no provedor
 * - Usuario desconectou diretamente pelo app WhatsApp
 */
class SyncWhatsappStatusJob extends BaseJob
{
    protected string $name = 'Sync WhatsApp Status';
    protected string $description = 'Sincroniza status das conexoes WhatsApp com o provedor';

    private QueryBuilder $qb;
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = Database::env('WHATSAPP_API_URL', '');
    }

    /**
     * Implementa a logica do job
     */
    protected function handle(): array
    {
        $this->log("Iniciando sincronizacao de status WhatsApp...");

        if (empty($this->baseUrl)) {
            $this->log("WHATSAPP_API_URL nao configurada", 'WARNING');
            return [
                'success' => true,
                'message' => 'WHATSAPP_API_URL nao configurada',
                'data' => ['checked' => 0, 'updated' => 0],
            ];
        }

        // Cria QueryBuilder
        $mysqli = new mysqli(
            Database::env('DB_HOST'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );
        $mysqli->set_charset('utf8mb4');
        $this->qb = new QueryBuilder($mysqli);
        $this->qb->withoutChave(); // Verificar todas as conexoes de todos os tenants

        $checked = 0;
        $updated = 0;

        try {
            // Buscar conexoes com status 'connected' ou 'connecting'
            $conexoes = $this->qb->table('whatsapp')
                ->select(['id', 'instanceName', 'status', 'chave'])
                ->whereIn('status', ['connected', 'connecting'])
                ->get();

            $this->log("Encontradas " . count($conexoes) . " conexoes para verificar");

            foreach ($conexoes as $conexao) {
                $checked++;
                $instanceToken = $conexao['instanceName']; // instanceName e usado como token
                $currentStatus = $conexao['status'];
                $chave = $conexao['chave'];

                // Verificar estado na API
                $apiResponse = $this->getSessionStatus($instanceToken);
                $newStatus = $this->mapApiStateToStatus($apiResponse);

                // Se status mudou para desconectado, atualizar
                if ($newStatus === 'disconnected' && $currentStatus !== 'disconnected') {
                    $this->qb->table('whatsapp')
                        ->where('id', '=', $conexao['id'])
                        ->update([
                            'status' => 'disconnected',
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);

                    $updated++;
                    $this->log("Conexao [{$instanceToken}] (tenant: {$chave}) atualizada: {$currentStatus} -> disconnected");
                }
            }

            $mysqli->close();

        } catch (\Exception $e) {
            $this->log("Erro ao sincronizar: " . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'message' => 'Erro ao sincronizar: ' . $e->getMessage(),
                'data' => [
                    'checked' => $checked,
                    'updated' => $updated,
                ],
            ];
        }

        $this->log("Sincronizacao concluida: {$checked} verificadas, {$updated} atualizadas");

        return [
            'success' => true,
            'message' => "Verificadas {$checked} conexoes, {$updated} atualizadas",
            'data' => [
                'checked' => $checked,
                'updated' => $updated,
            ],
        ];
    }

    /**
     * Mapeia resposta de status para nosso status interno.
     *
     * Provedor responde { Connected: bool, LoggedIn: bool }.
     */
    private function mapApiStateToStatus(array $apiResponse): string
    {
        if (!$apiResponse['success']) {
            return 'disconnected';
        }

        $data = $apiResponse['data']['data'] ?? $apiResponse['data'] ?? [];
        // Provedor pode retornar campos em camelCase ou PascalCase, aceitamos ambos
        $loggedIn = !empty($data['LoggedIn']) || !empty($data['loggedIn']);
        $connected = !empty($data['Connected']) || !empty($data['connected']);

        if ($loggedIn) {
            return 'connected';
        }
        if ($connected) {
            return 'connecting';
        }
        return 'disconnected';
    }

    /**
     * GET /session/status com header token = instanceToken.
     */
    private function getSessionStatus(string $instanceToken): array
    {
        $url = rtrim($this->baseUrl, '/') . '/session/status';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instanceToken,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "Erro cURL: {$error}",
                'data' => null,
            ];
        }

        $response = json_decode($body, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $response,
            ];
        }

        return [
            'success' => false,
            'message' => $response['message'] ?? $response['error'] ?? "HTTP {$httpCode}",
            'data' => $response,
        ];
    }
}
