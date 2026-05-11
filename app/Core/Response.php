<?php

namespace App\Core;

/**
 * Gerenciamento de Respostas HTTP
 *
 * Fornece métodos para retornar diferentes tipos de respostas
 */
class Response
{
    /**
     * Envia uma resposta HTML
     */
    public static function html(string $content, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    /**
     * Envia uma resposta JSON
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redireciona para uma URL
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    /**
     * Redireciona de volta para a página anterior
     */
    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    /**
     * Redireciona de volta com mensagem de sucesso
     */
    public static function backWithSuccess(string $message): void
    {
        Session::flash('success', $message);
        self::back();
    }

    /**
     * Redireciona de volta com mensagem de erro
     */
    public static function backWithError(string $message): void
    {
        Session::flash('error', $message);
        self::back();
    }

    /**
     * Redireciona de volta com erros de validação
     */
    public static function backWithErrors(array $errors, array $old = []): void
    {
        Session::flash('errors', $errors);
        if (!empty($old)) {
            Session::flashOld($old);
        }
        self::back();
    }

    /**
     * Redireciona para uma rota com mensagem de sucesso
     */
    public static function redirectWithSuccess(string $url, string $message): void
    {
        Session::flash('success', $message);
        self::redirect($url);
    }

    /**
     * Redireciona para uma rota com mensagem de erro
     */
    public static function redirectWithError(string $url, string $message): void
    {
        Session::flash('error', $message);
        self::redirect($url);
    }

    /**
     * Retorna resposta de sucesso em JSON
     */
    public static function success(mixed $data = null, string $message = null, int $statusCode = 200): void
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    /**
     * Retorna resposta de erro em JSON
     */
    public static function error(string $message, mixed $errors = null, int $statusCode = 400): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Retorna erro 404
     */
    public static function notFound(string $message = 'Página não encontrada'): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo "<h1>404 - $message</h1>";
        exit;
    }

    /**
     * Retorna erro 403 (Forbidden)
     *
     * Detecta automaticamente o contexto:
     * - AJAX: retorna JSON
     * - Iframe: renderiza view simples
     * - Página: renderiza view completa
     */
    public static function forbidden(string $message = 'Você não tem permissão para acessar este recurso.'): void
    {
        http_response_code(403);

        // Se for requisição AJAX, retorna JSON
        if (self::isAjaxRequest()) {
            self::json([
                'success' => false,
                'message' => $message
            ], 403);
            return;
        }

        // Renderiza a view de erro 403
        try {
            // Detecta se está em um iframe (páginas /pages/*)
            $isIframe = strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/') === 0;

            $view = $isIframe ? 'errors.403-iframe' : 'errors.403';
            $html = \App\Views\Template::render($view, ['message' => $message]);

            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
        } catch (\Exception $e) {
            // Fallback se a view não existir
            header('Content-Type: text/html; charset=UTF-8');
            echo "<h1>403 - $message</h1>";
        }

        exit;
    }

    /**
     * Retorna erro 403 em formato JSON (para APIs)
     */
    public static function forbiddenJson(string $message = 'Você não tem permissão para realizar esta ação.'): void
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Verifica se é uma requisição AJAX
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Retorna erro 500
     */
    public static function serverError(string $message = 'Erro interno do servidor'): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo "<h1>500 - $message</h1>";
        exit;
    }

    /**
     * Envia um arquivo para download
     */
    public static function download(string $filePath, string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            self::notFound('Arquivo não encontrado');
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    }

    /**
     * Define um header HTTP
     */
    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    /**
     * Define o status code da resposta
     */
    public static function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Envia headers para impedir cache
     */
    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }
}
