<?php
/** Operator-only approval, diagnostics and attested homologation. Never executes migrations. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Mcp\Store;
use App\Services\Mcp\{ClientRegistry, Config, Readiness};

$options = getopt('', ['env:', 'client-id:', 'name:', 'redirect-uri:', 'approve', 'homologated', 'cimd',
    'check', 'username:', 'tenant:', 'mark-homologated', 'evidence:']);
$_ENV['APP_ENV'] = $options['env'] ?? 'development';
if (!is_string($_ENV['APP_ENV']) || !in_array($_ENV['APP_ENV'], ['development', 'production'], true)) {
    fwrite(STDERR, "Ambiente inválido.\n"); exit(1);
}
putenv('APP_ENV=' . $_ENV['APP_ENV']);
date_default_timezone_set((string) Config::value('APP_TIMEZONE', 'America/Sao_Paulo'));

try {
    if (isset($options['homologated'])) throw new InvalidArgumentException('Use --mark-homologated --evidence=REFERENCIA somente após concluir os testes.');
    $operations = array_intersect(['check', 'approve', 'mark-homologated'], array_keys($options));
    if (count($operations) !== 1 || !is_string($options['client-id'] ?? null)) {
        throw new InvalidArgumentException('Informe --client-id=ID e uma operação: --check, --approve ou --mark-homologated --evidence=REFERENCIA.');
    }
    $id = $options['client-id'];
    if ($id === '' || strlen($id) > 255 || preg_match('/[\x00-\x20\x7f]/', $id)) throw new InvalidArgumentException('client-id inválido.');
    $operation = array_values($operations)[0];
    $registry = new ClientRegistry();
    $metadata = null;
    if (isset($options['cimd']) || ($operation !== 'approve' && str_starts_with($id, 'https://'))) {
        $metadata = $registry->fetch($id);
    } elseif (str_contains($id, '://')) throw new InvalidArgumentException('client-id URL requer --cimd para aprovação.');
    if ($operation === 'check') {
        $username = $options['username'] ?? 'tiagocaus';
        if (!is_string($username) || $username === '' || strlen($username) > 45) throw new InvalidArgumentException('Usuário de diagnóstico inválido.');
        $tenant = $options['tenant'] ?? null;
        if ($tenant !== null && !is_string($tenant)) throw new InvalidArgumentException('Informe uma única locadora em --tenant=CHAVE.');
        $report = Readiness::report(new Store(), $id, $metadata, $username, $tenant);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        exit($report['ready_to_enable'] ? 0 : 2);
    }
    if ($operation === 'mark-homologated') {
        if (!is_string($options['evidence'] ?? null)) throw new InvalidArgumentException('Informe --evidence com a referência dos testes concluídos.');
        (new Store())->markHomologated($id, $options['evidence'], $metadata);
        echo "Homologação registrada com referência de evidências. As restrições do piloto permanecem em vigor.\n";
        exit(0);
    }
    $name = $metadata['client_name'] ?? $options['name'] ?? '';
    $uris = $metadata['redirect_uris'] ?? (array) ($options['redirect-uri'] ?? []);
    if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 150 || !$uris || count($uris) > 20) {
        throw new InvalidArgumentException('Informe nome e retornos válidos.');
    }
    foreach ($uris as $uri) ClientRegistry::validateRedirect($uri, $id);
    $secret = getenv('MCP_CLIENT_SECRET');
    if ($metadata !== null && $secret !== false && $secret !== '') throw new InvalidArgumentException('CIMD negocia autenticação none; remova MCP_CLIENT_SECRET.');
    if ($secret !== false && $secret !== '' && strlen($secret) < 32) throw new InvalidArgumentException('Segredo deve ter pelo menos 32 caracteres.');
    (new Store())->registerClient($id, $name, array_values(array_unique($uris)),
        $secret === false || $secret === '' ? null : password_hash($secret, PASSWORD_DEFAULT), $metadata, false);
    echo "Cadastro aprovado e preservado, sem homologação antecipada. Login e consentimento do funcionário continuam obrigatórios.\n";
    if ($metadata !== null) echo json_encode(['negotiated' => ClientRegistry::capabilities($metadata)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (mysqli_sql_exception) {
    fwrite(STDERR, "Falha no diagnóstico/acesso ao banco. Confira ambiente, conexão e migrations no servidor.\n"); exit(1);
} catch (Throwable $e) {
    // Only controlled validation messages are shown; DB/crypto internals never become terminal output.
    $message = $e instanceof InvalidArgumentException || ($e instanceof RuntimeException && !str_contains($e->getMessage(), 'conectar com o banco'))
        ? $e->getMessage() : 'Operação MCP não concluída. Confira configuração e pré-requisitos.';
    fwrite(STDERR, $message . "\n"); exit(1);
}
