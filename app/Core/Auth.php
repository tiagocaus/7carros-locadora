<?php

namespace App\Core;

use App\Core\Cache;
use App\Classes\QueryBuilder;
use App\Models\Role;
use mysqli;

/**
 * Sistema de Autenticação
 *
 * Gerencia login, logout e verificação de autenticação
 */
class Auth
{
    private static ?mysqli $mysqli = null;
    private static ?QueryBuilder $qb = null;

    /**
     * Obtém a conexão mysqli (singleton)
     */
    private static function getMysqli(): mysqli
    {
        if (self::$mysqli === null) {
            $host = Database::env('DB_HOST', 'localhost');
            $username = Database::env('DB_USERNAME');
            $password = Database::env('DB_PASSWORD');
            $database = Database::env('DB_DATABASE');
            $port = (int) Database::env('DB_PORT', '3306');

            self::$mysqli = new mysqli($host, $username, $password, $database, $port);

            if (self::$mysqli->connect_error) {
                throw new \RuntimeException('Erro ao conectar com o banco: ' . self::$mysqli->connect_error);
            }

            self::$mysqli->set_charset('utf8mb4');
        }

        return self::$mysqli;
    }

    /**
     * Obtém instância do QueryBuilder
     */
    private static function qb(): QueryBuilder
    {
        if (self::$qb === null) {
            self::$qb = new QueryBuilder(self::getMysqli());
        }

        return self::$qb;
    }

    /**
     * Tenta autenticar um usuário
     */
    public static function attempt(string $username, string $password, bool $remember = false): bool
    {
        // Busca o usuário pelo username ou email (sem filtro de chave - não há sessão ainda)
        $user = self::qb()
            ->withoutChave()
            ->table('funcionarios')
            ->whereRaw('usuario = ? OR email = ?', [$username, $username])
            ->first();

        if (!$user) {
            return false;
        }

        // Verifica a senha
        if (!password_verify($password, $user['senha'])) {
            return false;
        }

        // Verifica se o usuário está ativo
        if ($user['status'] !== 'A') {
            return false;
        }

        // Rehash transparente: migra hashes legados (bcrypt) para Argon2id
        if (password_needs_rehash($user['senha'], PASSWORD_ARGON2ID)) {
            $novoHash = password_hash($password, PASSWORD_ARGON2ID);
            self::qb()->withoutChave()->table('funcionarios')
                ->where('id', '=', $user['id'])
                ->update(['senha' => $novoHash]);
        }

        // Cria a sessão do usuário
        self::login($user, $remember);

        return true;
    }

    /**
     * Faz login do usuário
     */
    public static function login(array $user, bool $remember = false): void
    {
        // Regenera o ID da sessão para prevenir session fixation
        Session::regenerate();

        // Armazena dados do usuário na sessão
        Session::set('user_id', $user['id']);
        Session::set('chave', $user['chave']);
        Session::set('id_matriz_filial', $user['id_matriz_filial'] ?? null);
        Session::set('user_name', $user['nome']);
        Session::set('user_email', $user['email']);
        Session::set('user_plano', $user['plano']);
        Session::set('user_foto', $user['foto']);
        Session::set('user_usuario', $user['usuario']);
        Session::set('authenticated', true);

        // Carrega filiais permitidas
        $filiaisPermitidas = self::carregarFiliaisPermitidas($user['id']);
        Session::set('filiais_permitidas', $filiaisPermitidas);

        // Se "Remember Me" estiver marcado, cria um token
        if ($remember) {
            self::createRememberToken($user['id'], $user['chave']);
        }
    }

    /**
     * Carrega IDs das filiais que o usuário tem acesso
     */
    private static function carregarFiliaisPermitidas(int $userId): array
    {
        $result = self::qb()
            ->withoutChave()
            ->table('funcionarios_filiais')
            ->select(['id_matriz_filial'])
            ->where('id_funcionario', '=', $userId)
            ->get();

        return array_column($result, 'id_matriz_filial');
    }

    /**
     * Retorna filiais permitidas do usuário logado
     *
     * @return array IDs das filiais permitidas (vazio = acesso total)
     */
    public static function filiaisPermitidas(): array
    {
        if (!self::check()) {
            return [];
        }

        return Session::get('filiais_permitidas', []);
    }

    /**
     * Recarrega filiais permitidas na sessão (após alteração)
     */
    public static function refreshFiliais(): void
    {
        if (!self::check()) {
            return;
        }

        $userId = self::id();
        $filiais = self::carregarFiliaisPermitidas($userId);
        Session::set('filiais_permitidas', $filiais);
    }

    /**
     * Cria um token "Remember Me"
     */
    private static function createRememberToken(int $userId, string $chave): void
    {
        // Gera um token aleatório
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        // Define expiração para 30 dias
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Salva no banco de dados (withoutChave pois já passamos a chave explicitamente)
        self::qb()->withoutChave()->table('funcionarios_tokens')->insert([
            'chave' => $chave,
            'usuario_id' => $userId,
            'token' => $hashedToken,
            'expira_em' => $expiresAt
        ]);

        // Define o cookie com flags de seguranca completas
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        setcookie('remember_token', $token, [
            'expires' => time() + (30 * 24 * 60 * 60), // 30 dias
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Tenta fazer login via Remember Token
     */
    public static function attemptRememberToken(): bool
    {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }

        $token = $_COOKIE['remember_token'];
        $hashedToken = hash('sha256', $token);

        // Busca o token no banco (sem filtro de chave - não há sessão)
        $tokenData = self::qb()
            ->withoutChave()
            ->table('funcionarios_tokens')
            ->whereRaw('token = ? AND expira_em > NOW()', [$hashedToken])
            ->first();

        if (!$tokenData) {
            self::deleteRememberToken();
            return false;
        }

        // Busca o usuário (sem filtro de chave - não há sessão)
        $user = self::qb()
            ->withoutChave()
            ->table('funcionarios')
            ->whereRaw('id = ? AND chave = ? AND status = ?', [$tokenData['usuario_id'], $tokenData['chave'], 'A'])
            ->first();

        if (!$user) {
            self::deleteRememberToken();
            return false;
        }

        // Faz login
        self::login($user, true);

        return true;
    }

    /**
     * Remove o token "Remember Me"
     */
    private static function deleteRememberToken(): void
    {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);

            // Remove do banco (sem filtro de chave - pode não haver sessão)
            self::qb()->withoutChave()->table('funcionarios_tokens')->whereRaw('token = ?', [$hashedToken])->delete();

            // Remove o cookie (mesmas flags do set para garantir delete correto)
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Faz logout do usuário
     */
    public static function logout(): void
    {
        // Invalida cache do usuário antes de fazer logout
        self::invalidateUserCache();

        // Remove o token de remember me
        self::deleteRememberToken();

        // Destrói a sessão
        Session::destroy();
    }

    /**
     * Verifica se o usuário está autenticado
     */
    public static function check(): bool
    {
        Session::start();
        return Session::get('authenticated', false) === true;
    }

    /**
     * Verifica se o usuário é visitante (não autenticado)
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Obtém os dados do usuário autenticado
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => Session::get('user_id'),
            'chave' => Session::get('chave'),
            'id_matriz_filial' => Session::get('id_matriz_filial'),
            'filiais_permitidas' => Session::get('filiais_permitidas', []),
            'nome' => Session::get('user_name'),
            'usuario' => Session::get('user_usuario'),
            'email' => Session::get('user_email'),
            'plano' => Session::get('user_plano'),
            'foto' => Session::get('user_foto'),
        ];
    }

    /**
     * Obtém o ID do usuário autenticado
     */
    public static function id(): ?int
    {
        if (!self::check()) {
            return null;
        }

        return Session::get('user_id');
    }

    /**
     * Obtém a chave (tenant) do usuário autenticado
     */
    public static function chave(): ?string
    {
        if (!self::check()) {
            return null;
        }

        return Session::get('chave');
    }

    /**
     * Obtém o ID da matriz/filial vinculada ao usuário autenticado
     */
    public static function idMatrizFilial(): ?int
    {
        if (!self::check()) {
            return null;
        }

        return Session::get('id_matriz_filial');
    }

    /**
     * Verifica se a sessão atual pertence ao usuário técnico de suporte.
     */
    private static function isSupportAccessUser(): bool
    {
        $usuario = (string) Session::get('user_usuario', '');

        if (!str_starts_with($usuario, 'suporte')) {
            return false;
        }

        return Role::isSupportRole(self::getRole());
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        if (self::isSupportAccessUser()) {
            return true;
        }

        $userId = self::id();
        $chave = self::chave();

        // Tenta obter permissões do cache (duração da sessão)
        $cacheKey = "user_permissions:{$userId}";
        $permissions = Cache::remember(
            $cacheKey,
            3600, // 1 hora
            function () use ($userId, $chave) {
                // Busca as permissões do usuário através da role (sistema RBAC)
                $result = self::qb()
                    ->table('funcionarios', 'f')
                    ->select(['p.key'])
                    ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
                    ->innerJoin('funcionarios_role_permissions', 'rp', 'r.id', '=', 'rp.role_id')
                    ->innerJoin('permissions', 'p', 'rp.permission_id', '=', 'p.id')
                    ->whereRaw('f.id = ? AND f.chave = ?', [$userId, $chave])
                    ->get();

                if (!$result) {
                    return [];
                }

                // Extrair array de permissões
                return array_column($result, 'key');
            },
            $chave
        );

        return in_array($permission, $permissions, true);
    }

    /**
     * Obtém os dados da empresa/matriz-filial vinculada ao usuário autenticado
     */
    public static function empresa(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $idMatrizFilial = self::idMatrizFilial();
        $chave = self::chave();

        // Se tem id_matriz_filial, busca pelo ID
        if ($idMatrizFilial) {
            return Cache::remember(
                'empresa_data_by_id',
                900, // 15 minutos
                function () use ($idMatrizFilial) {
                    return self::qb()
                        ->withoutChave()
                        ->table('matrizes_filiais')
                        ->where('id', '=', $idMatrizFilial)
                        ->first();
                },
                'mf_' . $idMatrizFilial
            );
        }

        // Fallback: busca pela chave (comportamento anterior)
        return Cache::remember(
            'empresa_data',
            900, // 15 minutos
            function () use ($chave) {
                return self::qb()
                    ->withoutChave()
                    ->table('matrizes_filiais')
                    ->where('chave', '=', $chave)
                    ->first();
            },
            $chave
        );
    }

    /**
     * Recarrega os dados do usuário da sessão
     */
    public static function refresh(): void
    {
        if (!self::check()) {
            return;
        }

        $userId = self::id();
        $chave = self::chave();

        $user = self::qb()
            ->table('funcionarios')
            ->whereRaw('id = ?', [$userId])
            ->first();

        if ($user && $user['status'] === 'A') {
            // Invalida cache de permissões para recarregar
            self::invalidateUserPermissionsCache($userId, $chave);

            // Atualiza os dados na sessão
            Session::set('id_matriz_filial', $user['id_matriz_filial'] ?? null);
            Session::set('user_name', $user['nome']);
            Session::set('user_email', $user['email']);
            Session::set('user_plano', $user['plano']);
            Session::set('user_foto', $user['foto']);
            Session::set('user_usuario', $user['usuario']);

            // Recarrega filiais permitidas
            self::refreshFiliais();
        } else {
            // Usuário foi desativado ou removido, faz logout
            self::logout();
        }
    }

    /**
     * Invalida o cache de permissões de um usuário específico
     */
    public static function invalidateUserPermissionsCache(?int $userId = null, ?string $tenant = null): void
    {
        $userId = $userId ?? self::id();
        $tenant = $tenant ?? self::chave();

        if ($userId && $tenant) {
            Cache::forget("user_permissions:{$userId}", $tenant);
        }
    }

    /**
     * Invalida o cache de dados da empresa
     */
    public static function invalidateEmpresaCache(?string $tenant = null, ?int $idMatrizFilial = null): void
    {
        $tenant = $tenant ?? self::chave();
        $idMatrizFilial = $idMatrizFilial ?? self::idMatrizFilial();

        // Invalida cache por chave (fallback)
        if ($tenant) {
            Cache::forget('empresa_data', $tenant);
        }

        // Invalida cache por id_matriz_filial
        if ($idMatrizFilial) {
            Cache::forget('empresa_data_by_id', 'mf_' . $idMatrizFilial);
        }
    }

    /**
     * Invalida todo o cache do usuário atual (permissões + empresa)
     */
    public static function invalidateUserCache(): void
    {
        $userId = self::id();
        $chave = self::chave();

        if ($userId && $chave) {
            self::invalidateUserPermissionsCache($userId, $chave);
            self::invalidateEmpresaCache($chave);
        }
    }

    /**
     * Invalida todo o cache de um tenant específico
     */
    public static function invalidateTenantCache(?string $tenant = null): void
    {
        $tenant = $tenant ?? self::chave();

        if ($tenant) {
            Cache::flushTenant($tenant);
        }
    }

    /**
     * Obtém a role do usuário autenticado
     */
    public static function getRole(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $userId = self::id();
        $chave = self::chave();

        // Cache da role por 1 hora
        return Cache::remember(
            "user_role:{$userId}",
            3600,
            function () use ($userId, $chave) {
                return self::qb()
                    ->table('funcionarios', 'f')
                    ->select(['r.*'])
                    ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
                    ->whereRaw('f.id = ? AND f.chave = ?', [$userId, $chave])
                    ->first();
            },
            $chave
        );
    }

    /**
     * Verifica se o usuário tem uma role específica
     */
    public static function hasRole(string $roleName): bool
    {
        $role = self::getRole();

        if (!$role) {
            return false;
        }

        return strtolower($role['name']) === strtolower($roleName);
    }

    /**
     * Obtém todas as permissões do usuário (para debug)
     */
    public static function getPermissions(): array
    {
        if (!self::check()) {
            return [];
        }

        $userId = self::id();
        $chave = self::chave();

        if (self::isSupportAccessUser()) {
            return self::qb()
                ->table('permissions', 'p')
                ->select(['p.`key`', 'p.name', 'p.module'])
                ->withoutChave()
                ->orderBy('p.module')
                ->orderBy('p.key')
                ->get();
        }

        // Usa o mesmo cache que o método can()
        $cacheKey = "user_permissions:{$userId}";
        return Cache::remember(
            $cacheKey,
            3600,
            function () use ($userId, $chave) {
                return self::qb()
                    ->table('funcionarios', 'f')
                    ->select(['p.key', 'p.name', 'p.module'])
                    ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
                    ->innerJoin('funcionarios_role_permissions', 'rp', 'r.id', '=', 'rp.role_id')
                    ->innerJoin('permissions', 'p', 'rp.permission_id', '=', 'p.id')
                    ->whereRaw('f.id = ? AND f.chave = ?', [$userId, $chave])
                    ->orderBy('p.module')
                    ->orderBy('p.key')
                    ->get();
            },
            $chave
        );
    }
}
