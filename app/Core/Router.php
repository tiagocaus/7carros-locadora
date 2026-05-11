<?php

namespace App\Core;

/**
 * Sistema de Roteamento
 *
 * Gerencia rotas HTTP com suporte a middlewares e parâmetros dinâmicos
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $groupMiddlewares = [];
    private string $groupPrefix = '';

    /**
     * Registra uma rota GET
     */
    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota POST
     */
    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota PUT
     */
    public function put(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota DELETE
     */
    public function delete(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota PATCH
     */
    public function patch(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota para múltiplos métodos
     */
    public function match(array $methods, string $path, callable|array $handler, array $middlewares = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middlewares);
        }
        return $this;
    }

    /**
     * Registra uma rota para todos os métodos
     */
    public function any(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $handler, $middlewares);
    }

    /**
     * Adiciona uma rota ao registro
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): self
    {
        $path = $this->groupPrefix . $path;
        $middlewares = array_merge($this->groupMiddlewares, $middlewares);

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares,
            'pattern' => $this->convertToPattern($path)
        ];

        return $this;
    }

    /**
     * Converte um path com parâmetros em regex
     */
    private function convertToPattern(string $path): string
    {
        // Escapa barras
        $pattern = str_replace('/', '\/', $path);

        // Converte {param} para regex nomeado
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Define um grupo de rotas com prefixo e middlewares
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        // Adiciona prefixo do grupo
        if (isset($attributes['prefix'])) {
            $this->groupPrefix .= $attributes['prefix'];
        }

        // Adiciona middlewares do grupo
        if (isset($attributes['middleware'])) {
            $middlewares = is_array($attributes['middleware'])
                ? $attributes['middleware']
                : [$attributes['middleware']];
            $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);
        }

        // Executa o callback com as rotas do grupo
        $callback($this);

        // Restaura estado anterior
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Registra um middleware global
     */
    public function middleware(string $name, string $class): void
    {
        $this->middlewares[$name] = $class;
    }

    /**
     * Despacha a requisição para a rota correspondente
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        // Remove trailing slash (exceto para raiz)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Extrai parâmetros da URL
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Executa middlewares
                if (!$this->runMiddlewares($route['middlewares'], $request)) {
                    return;
                }

                // Executa o handler
                $this->executeHandler($route['handler'], $request, $params);
                return;
            }
        }

        // Nenhuma rota encontrada
        Response::notFound();
    }

    /**
     * Executa os middlewares da rota
     *
     * Suporta middlewares com parâmetros no formato: 'middleware:param1,param2'
     * Exemplo: 'permission:clientes.visualizar'
     */
    private function runMiddlewares(array $middlewareNames, Request $request): bool
    {
        foreach ($middlewareNames as $middlewareEntry) {
            // Separa nome do middleware e parâmetros
            $parts = explode(':', $middlewareEntry, 2);
            $name = $parts[0];
            $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

            if (!isset($this->middlewares[$name])) {
                throw new \RuntimeException("Middleware '$name' não registrado");
            }

            $middlewareClass = $this->middlewares[$name];
            $middleware = new $middlewareClass();

            if (!method_exists($middleware, 'handle')) {
                throw new \RuntimeException("Middleware '$name' deve ter método handle()");
            }

            // Se o middleware retornar false, interrompe a execução
            // Passa request e parâmetros adicionais
            if ($middleware->handle($request, ...$params) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Executa o handler da rota
     */
    private function executeHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_array($handler)) {
            // Handler é [Controller::class, 'method']
            [$controllerClass, $method] = $handler;

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller '$controllerClass' não encontrado");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Método '$method' não existe no controller '$controllerClass'");
            }

            // Injeta Request e params no método do controller
            $controller->$method($request, ...array_values($params));
        } else {
            // Handler é uma closure
            $handler($request, ...array_values($params));
        }
    }

    /**
     * Gera uma URL para uma rota nomeada (futuro)
     */
    public function url(string $name, array $params = []): string
    {
        // TODO: Implementar sistema de rotas nomeadas
        return '/';
    }

    /**
     * Obtém todas as rotas registradas
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
