<?php

declare(strict_types=1);
namespace App\Services\Mcp;

use App\Models\Mcp\Store;
use Mcp\Schema\JsonRpc\{Request, Response, Error};
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\{RequestHandlerInterface, CallToolHandler};
use Mcp\Server\Session\SessionInterface;

/** SDK extension: typed requests only; normal dispatch and schema validation stay in the SDK. */
final class SecurityToolHandler implements RequestHandlerInterface
{
    public function __construct(private readonly Context $context, private readonly Store $store,
        private readonly string $requestId, private readonly CallToolHandler $delegate) {}

    public function supports(Request $request): bool { return $request instanceof CallToolRequest; }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        assert($request instanceof CallToolRequest);
        try {
            $current = $this->store->context($this->context->connectionId, $this->context->clientId, $this->context->scopes);
            if ((new SecurityPolicy())->inspect($current, $request->name, $request->arguments, $this->requestId) !== null) {
                return new Response($request->getId(), CallToolResult::error([new TextContent(json_encode([
                    'codigo' => 'mcp_operation_denied', 'mensagem' => 'Operação ou argumentos não permitidos.'
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))]));
            }
            return $this->delegate->handle($request, $session);
        } catch (SecurityException $e) {
            return new Response($request->getId(), CallToolResult::error([
                new TextContent(json_encode($e->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))]));
        } catch (\Throwable $e) {
            error_log('[MCP-SECURITY] ' . json_encode(['request_id' => $this->requestId, 'exception' => get_class($e)], JSON_UNESCAPED_SLASHES));
            return Error::forInternalError('Controle de segurança MCP indisponível.', $request->getId());
        }
    }
}
