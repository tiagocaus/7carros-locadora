<?php

declare(strict_types=1);
namespace App\Controllers\Mcp;

use App\Core\{Auth, Request, Response};
use App\Models\Mcp\{Store, SecurityState};
use App\Services\Mcp\{AuthorizationService, Catalog, Config};
use App\Views\Template;

final class WebController
{
    private const PACKAGES = [
        'chatgpt' => ['title' => 'ChatGPT', 'version' => '1.0.2', 'description' => 'Plugin para o ChatGPT',
            'file' => 'integrations/chatgpt/7carros-chatgpt-1.0.2.zip'],
        'claude-code' => ['title' => 'Claude Code', 'version' => '1.0.0', 'description' => 'Para uso no terminal',
            'file' => 'integrations/claude-code/7carros-claude-code-1.0.0.zip'],
    ];

    public function authorize(Request $request): void
    {
        $requestId = bin2hex(random_bytes(16));
        $started = microtime(true); $stage = 'prepare';
        header('X-Request-Id: ' . $requestId);
        header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        try {
            $store = new Store(); $service = new AuthorizationService($store);
            $chave = (string) Auth::chave(); $employeeId = (int) Auth::id();
            if ($request->method() === 'POST') {
                $stage = 'validate_request';
                $nonce = $request->input('nonce', '');
                $decision = $request->input('decision'); $scopes = $request->input('scopes', []);
                if (!is_string($nonce) || !preg_match('/^[a-f0-9]{48}$/D', $nonce)
                    || !is_string($decision) || !in_array($decision, ['allow', 'deny'], true)
                    || !is_array($scopes) || !array_is_list($scopes)
                    || array_filter($scopes, fn ($scope) => !is_string($scope))) {
                    throw new \InvalidArgumentException('Formulário de autorização inválido.');
                }
                self::pruneConsumed();
                if (isset($_SESSION['mcp_consumed'][$nonce])) {
                    throw new \RuntimeException('Esta tentativa já foi enviada. Reinicie a conexão no aplicativo.', 409);
                }
                $pending = $_SESSION['mcp_pending'][$nonce] ?? [];
                $stage = 'validate_consent';
                $response = $service->complete($pending, $chave, $employeeId, $scopes, $decision === 'allow',
                    function () use ($nonce, &$stage): void {
                        // The PHP session lock makes the claim exclusive across simultaneous POSTs.
                        unset($_SESSION['mcp_pending'][$nonce]);
                        $_SESSION['mcp_consumed'][$nonce] = time() + 300;
                        self::pruneConsumed();
                        // Persist before issuance and release the lock; a second POST returns 409.
                        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
                            throw new \RuntimeException('Falha ao persistir a tentativa.');
                        }
                        $stage = 'issue';
                    }, $requestId);
                self::consentLog($requestId, 'response_ready', $response->getStatusCode(), $started);
                Gateway::emit($response->withHeader('X-Request-Id', $requestId));
                return;
            }
            $pending = $service->prepare($chave, $employeeId, $_GET);
            $nonce = bin2hex(random_bytes(24));
            $_SESSION['mcp_pending'] = array_filter($_SESSION['mcp_pending'] ?? [], fn ($p) => ($p['expires'] ?? 0) > time());
            if (count($_SESSION['mcp_pending']) >= 10) array_shift($_SESSION['mcp_pending']);
            $_SESSION['mcp_pending'][$nonce] = $pending;
            self::pruneConsumed();
            // Only the HTTPS origin of this library-validated, exactly registered callback.
            header("Content-Security-Policy: frame-ancestors 'none'; form-action 'self' " . $pending['redirect_origin'] . "; base-uri 'none'");
            $labels = self::labels();
            ob_start(); require dirname(__DIR__, 2) . '/Views/pages/mcp/consent.php';
            $html = ob_get_clean();
            self::consentLog($requestId, 'displayed', 200, $started);
            Response::html($html);
        } catch (\Throwable $error) {
            $status = 503;
            if ($error instanceof \InvalidArgumentException) $status = 400;
            elseif ($error instanceof \RuntimeException && in_array($error->getCode(), [403, 409, 410], true)) $status = $error->getCode();
            elseif ($error instanceof \League\OAuth2\Server\Exception\OAuthServerException) {
                $status = $error->getHttpStatusCode() === 400 ? 400 : 403;
            }
            self::consentLog($requestId, $stage, $status, $started, $error);
            $message = match ($status) {
                400 => 'Solicitação de autorização inválida. Reinicie a conexão no aplicativo.',
                403 => 'Seu acesso mudou ou não permite esta autorização. Confira suas permissões e reinicie a conexão no aplicativo.',
                409 => 'Esta tentativa já foi enviada. Volte ao aplicativo e reinicie a conexão se ela não foi concluída.',
                410 => 'Esta tentativa expirou ou não está mais disponível. Reinicie a conexão no aplicativo.',
                default => 'Não foi possível concluir a autorização. Reinicie a conexão no aplicativo e informe a referência ao suporte se o problema continuar.',
            };
            Response::html('<main><h1>Autorização não concluída</h1><p>' . $message
                . '</p><p>Referência: ' . $requestId . '</p></main>', $status);
        }
    }

    private static function pruneConsumed(): void
    {
        $_SESSION['mcp_consumed'] = array_filter($_SESSION['mcp_consumed'] ?? [], fn ($expires) => $expires > time());
        $_SESSION['mcp_consumed'] = array_slice($_SESSION['mcp_consumed'], -20, null, true);
    }

    private static function consentLog(string $id, string $stage, int $status, float $started, ?\Throwable $error = null): void
    {
        $entry = ['request' => $id, 'stage' => $stage, 'status' => $status,
            'duration_ms' => max(0, (int) ((microtime(true) - $started) * 1000))];
        $ray = $_SERVER['HTTP_CF_RAY'] ?? '';
        if (is_string($ray) && preg_match('/^[a-f0-9]{16,32}-[A-Z0-9]{3}$/D', $ray)) $entry['cf_ray'] = $ray;
        if ($error !== null) $entry['exception'] = get_class($error);
        error_log('[MCP-CONSENT] ' . json_encode($entry, JSON_UNESCAPED_SLASHES));
    }

    public function index(Request $request): void
    {
        $store = new Store(); $chave = (string) Auth::chave(); $id = (int) Auth::id();
        if (!Config::enabled($chave)) { Response::html('Integração IA/MCP indisponível.', 404); return; }
        $user = $store->employee($chave, $id); $permissions = $store->permissions($chave, $id);
        if (!$user || $user['status'] !== 'A') {
            Response::html('Acesso não autorizado.', 403); return;
        }
        $page = max(1, min(1000000, (int) $request->query('pagina', 1)));
        $admin = in_array('mcp.administrar', $permissions, true);
        $rows = $store->listConnections($chave, $id, $admin, $page);
        $security = new SecurityState();
        foreach ($rows as &$row) $row['security'] = $security->status($chave, (int) $row['employee_id']);
        unset($row);
        $securityEvents = $security->events($chave, $id, $admin);
        Response::html(Template::render('pages.mcp.index', ['connections' => $rows, 'page' => $page, 'endpoint' => Config::resource(), 'scopeLabels' => self::labels(), 'packages' => self::PACKAGES, 'securityEvents' => $securityEvents, 'securityAdmin' => $admin]));
    }

    public function download(Request $request, string $aplicativo): void
    {
        $package = self::PACKAGES[$aplicativo] ?? null;
        $chave = (string) Auth::chave();
        if (!$package || !Config::enabled($chave)) {
            Response::html('Pacote não encontrado.', 404); return;
        }
        $user = (new Store())->employee($chave, (int) Auth::id());
        if (!$user || $user['status'] !== 'A') {
            Response::html('Acesso não autorizado.', 403); return;
        }
        // File paths come exclusively from this fixed catalog, never from request input.
        $path = dirname(__DIR__, 3) . '/' . $package['file'];
        if (!is_file($path) || !is_readable($path)) {
            Response::html('Pacote indisponível.', 404); return;
        }
        header('X-Content-Type-Options: nosniff');
        Response::download($path, basename($package['file']));
    }

    public function revoke(Request $request): void
    {
        $store = new Store(); $chave = (string) Auth::chave(); $id = (int) Auth::id();
        $user = $store->employee($chave, $id); $permissions = $store->permissions($chave, $id);
        if (!$user || $user['status'] !== 'A' || !Config::enabled($chave)) { Response::html('Acesso não autorizado.', 403); return; }
        $connectionId = (string) $request->input('connection_id', '');
        $admin = in_array('mcp.administrar', $permissions, true);
        $row = $store->scopedConnection($chave, $connectionId);
        if (!$row || (!$admin && (int) $row['employee_id'] !== $id)) { Response::html('Conexão não encontrada.', 404); return; }
        $store->revokeConnection($chave, $connectionId);
        // The subject identity is the connection owner, not a browser privilege bypass.
        $context = new \App\Services\Mcp\Context($chave, $id, $connectionId, $row['client_id'], [], [], false, []);
        $store->audit($context, 'revoke', 'success', bin2hex(random_bytes(16)));
        Response::redirect('/pages/integracoes/mcp');
    }

    public function release(Request $request): void
    {
        $store = new Store(); $tenant = (string) Auth::chave(); $actorId = (int) Auth::id();
        $user = $store->employee($tenant, $actorId);
        if (!$user || $user['status'] !== 'A' || !Config::enabled($tenant)
            || !in_array('mcp.administrar', $store->permissions($tenant, $actorId), true)) {
            Response::html('Acesso não autorizado.', 403); return;
        }
        $connection = $store->scopedConnection($tenant, (string) $request->input('connection_id', ''));
        if (!$connection) { Response::html('Conexão não encontrada.', 404); return; }
        $actor = new \App\Services\Mcp\Context($tenant, $actorId, $connection['connection_id'],
            $connection['client_id'], [], [], false, []);
        (new SecurityState())->release($actor, (int) $connection['employee_id'], bin2hex(random_bytes(16)));
        Response::redirect('/pages/integracoes/mcp');
    }

    private static function labels(): array
    {
        return ['frota:read' => 'Frota', 'clientes:read' => 'Clientes', 'contratos:read' => 'Contratos',
            'locacoes:read' => 'Locações', 'financeiro:read' => 'Financeiro',
            'clientes:documentos' => 'Documentos de clientes', 'clientes:contatos' => 'Contatos de clientes'];
    }
}
