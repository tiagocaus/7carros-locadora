<?php

declare(strict_types=1);

namespace App\Models\Mcp;

use App\Models\Model;
use App\Services\Mcp\{Catalog, ClientPolicy, ClientRegistry, Config, Context};

/** OAuth credential resolution is the only pre-context tenant lookup. */
final class Store extends Model
{
    public function client(string $id): ?array
    {
        $row = $this->registeredClient($id);
        return $row && (int) $row['enabled'] === 1 ? $row : null;
    }

    public function registeredClient(string $id): ?array
    {
        $row = $this->qb->table('mcp_clients')->withoutChave()->where('client_id', '=', $id)
            ->first();
        return $row && hash_equals($row['client_id'], $id) ? $row : null;
    }

    public function registerClient(string $id, string $name, array $redirects, ?string $secretHash, ?array $metadata, bool $homologated): void
    {
        foreach ($redirects as $redirect) ClientRegistry::validateRedirect($redirect, $id);
        if ($id === ClientRegistry::CLAUDE_CODE_CLIENT_ID && ($secretHash !== null || $metadata !== null)) {
            throw new \InvalidArgumentException('Claude Code usa cliente público estático, sem segredo ou documento CIMD.');
        }
        if ($homologated) throw new \InvalidArgumentException('Registre evidências antes de marcar o aplicativo como homologado.');
        if ($metadata !== null) {
            ClientRegistry::validateDocument($id, $metadata);
            if ($secretHash !== null || $metadata['client_name'] !== $name || $metadata['redirect_uris'] !== $redirects) {
                throw new \InvalidArgumentException('Cadastro divergente do documento CIMD.');
            }
        }
        $envelope = $metadata === null ? null : ClientRegistry::envelope($metadata);
        $encoded = $envelope === null ? null : json_encode($envelope, JSON_THROW_ON_ERROR);
        if ($encoded !== null && strlen($encoded) > 65535) throw new \InvalidArgumentException('Metadados excedem o tamanho permitido.');
        $data = ['name' => $name, 'redirect_uris' => json_encode($redirects, JSON_THROW_ON_ERROR),
            'secret_hash' => $secretHash, 'enabled' => 1, 'homologated' => 0, 'metadata' => $encoded];
        $existing = $this->registeredClient($id);
        if ($existing) {
            $old = $existing['metadata'] === null ? null : json_decode($existing['metadata'], true, 32, JSON_THROW_ON_ERROR);
            $sameDocument = $metadata === null ? $old === null
                : isset($old['document']) && ClientRegistry::fingerprint($old['document']) === ClientRegistry::fingerprint($metadata);
            if ((int) $existing['enabled'] === 1 && $existing['name'] === $name
                && json_decode($existing['redirect_uris'], true, 32, JSON_THROW_ON_ERROR) === $redirects
                && $existing['secret_hash'] === $secretHash && $sameDocument) return;
            throw new \RuntimeException('Cadastro existente diverge ou está desabilitado; exige revisão operacional, sem substituição automática.');
        }
        $this->qb->table('mcp_clients')->withoutChave()->insert(['client_id' => $id] + $data);
    }

    public function markHomologated(string $id, string $evidence, ?array $currentDocument): void
    {
        if (trim($evidence) === '' || strlen($evidence) > 512 || preg_match('/[\x00-\x1f\x7f]/', $evidence)) {
            throw new \InvalidArgumentException('Informe a referência de evidências da homologação (até 512 caracteres).');
        }
        $this->transaction(function () use ($id, $evidence, $currentDocument): void {
            $row = $this->qb->table('mcp_clients')->withoutChave()->where('client_id', '=', $id)->lockForUpdate()->first();
            if (!$row || !hash_equals($row['client_id'], $id) || (int) $row['enabled'] !== 1) {
                throw new \RuntimeException('Aplicativo aprovado e habilitado não localizado.');
            }
            $metadata = $row['metadata'] === null ? [] : json_decode($row['metadata'], true, 32, JSON_THROW_ON_ERROR);
            if (isset($metadata['document'])) {
                if ($currentDocument === null || ClientRegistry::fingerprint($metadata['document']) !== ClientRegistry::fingerprint($currentDocument)) {
                    throw new \RuntimeException('Documento CIMD mudou; revise o cadastro e repita a homologação.');
                }
            } elseif ($currentDocument !== null) throw new \RuntimeException('Cadastro não corresponde ao documento CIMD.');
            if ((int) $row['homologated'] === 1 && ($metadata['homologation']['evidence'] ?? '') === $evidence) return;
            $metadata['homologation'] = ['evidence' => $evidence, 'verified_at' => date(DATE_ATOM), 'protocol' => '2026-07-28'];
            $encoded = json_encode($metadata, JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 65535) throw new \RuntimeException('Metadados excedem o tamanho permitido.');
            $this->qb->table('mcp_clients')->withoutChave()->where('id', '=', $row['id'])
                ->update(['homologated' => 1, 'metadata' => $encoded]);
        });
    }

    public function employeeByUsername(string $chave, string $username): ?array
    {
        return $this->qb->table('funcionarios')->withChave($chave)->select(['id', 'status'])
            ->where('usuario', '=', $username)->first();
    }

    public function mcpMigrationApplied(): bool
    {
        return $this->qb->table('migrations')->withoutChave()
            ->where('migration', '=', '00441_create_mcp_integration.php')->exists();
    }

    public function connection(string $id, bool $lock = false): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) {
            return null;
        }
        // Authentication lookup by unguessable credential subject, never business data.
        $q = $this->qb->table('mcp_connections')->withoutChave()->where('connection_id', '=', $id);
        if ($lock) $q->lockForUpdate();
        return $q->first();
    }

    public function scopedConnection(string $chave, string $id): ?array
    {
        return $this->qb->table('mcp_connections')->withChave($chave)->where('connection_id', '=', $id)->first();
    }

    public function consentBranches(string $chave, int $employeeId): array
    {
        $access = $this->branches($chave, $employeeId);
        if (!$access['all'] && $access['ids'] === []) return ['all' => false, 'units' => []];
        $query = $this->qb->table('matrizes_filiais')->withChave($chave)
            ->select(['id', 'nome_fantasia', 'razao_social']);
        if (!$access['all']) $query->whereIn('id', $access['ids']);
        $rows = $query->orderBy('nome_fantasia', 'ASC')->orderBy('id', 'ASC')->get();
        $units = array_map(static function (array $row): array {
            $name = trim((string) ($row['nome_fantasia'] ?? ''));
            if ($name === '') $name = trim((string) ($row['razao_social'] ?? ''));
            if ($name === '') $name = 'Empresa/filial';
            return ['id' => (int) $row['id'], 'name' => $name];
        }, $rows);
        return ['all' => $access['all'], 'units' => $units];
    }

    public function auditConnection(string $id, string $event, string $result): void
    {
        $row = $this->connection($id);
        if (!$row) return;
        $context = new Context($row['chave'], (int) $row['employee_id'], $row['connection_id'], $row['client_id'], [], [], false, []);
        $this->audit($context, $event, $result, bin2hex(random_bytes(16)));
    }

    public function employee(string $chave, int $id): ?array
    {
        return $this->qb->table('funcionarios')->withChave($chave)->select(['id', 'status', 'id_role', 'nome'])
            ->where('id', '=', $id)->first();
    }

    public function permissions(string $chave, int $id): array
    {
        return array_column($this->qb->table('funcionarios', 'f')->withChave($chave)
            ->select(['p.`key`'])
            ->joinRaw('funcionarios_roles', 'r', "r.id = f.id_role AND (r.chave = f.chave OR r.chave = '0')")
            ->join('funcionarios_role_permissions', 'rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions', 'p', 'p.id', '=', 'rp.permission_id')
            ->where('f.id', '=', $id)->get(), 'key');
    }

    public function branches(string $chave, int $id): array
    {
        // A completed, empty assignment query means unrestricted in the existing system.
        // Errors propagate; an invalid branch assignment must not become unrestricted.
        $assigned = $this->qb->table('funcionarios_filiais')->withChave($chave)
            ->select(['id_matriz_filial'])->where('id_funcionario', '=', $id)->get();
        if (!$assigned) return ['all' => true, 'ids' => []];
        $ids = array_map('intval', array_column($assigned, 'id_matriz_filial'));
        $valid = $this->qb->table('matrizes_filiais')->withChave($chave)->select(['id'])
            ->whereIn('id', $ids)->get();
        return ['all' => false, 'ids' => array_map('intval', array_column($valid, 'id'))];
    }

    public function context(string $connectionId, ?string $clientId = null, ?array $tokenScopes = null): Context
    {
        $c = $this->connection($connectionId);
        if (!$c || $c['revoked_at'] !== null || strtotime($c['expires_at']) <= time()
            || ($clientId !== null && $c['client_id'] !== $clientId) || !Config::enabled($c['chave'])) {
            throw new \RuntimeException('Autorização MCP indisponível.');
        }
        $client = $this->client($c['client_id']);
        if (!$client || !ClientPolicy::permitsIdentity($client, $c['chave'], (int) $c['employee_id'])) {
            throw new \RuntimeException('Aplicativo MCP indisponível.');
        }
        $user = $this->employee($c['chave'], (int) $c['employee_id']);
        if (!$user || $user['status'] !== 'A') {
            throw new \RuntimeException('Funcionário sem acesso MCP.');
        }
        $permissions = $this->permissions($c['chave'], (int) $c['employee_id']);
        if (!in_array('mcp.conectar', $permissions, true)) {
            throw new \RuntimeException('Conexão MCP não autorizada.');
        }
        $scopes = json_decode($c['scopes'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($scopes) || !array_is_list($scopes)) throw new \RuntimeException('Consentimento inválido.');
        if ($tokenScopes !== null) $scopes = array_values(array_intersect($scopes, $tokenScopes));
        $branches = $this->branches($c['chave'], (int) $c['employee_id']);
        return new Context($c['chave'], (int) $c['employee_id'], $connectionId, $c['client_id'], $scopes,
            $permissions, $branches['all'], $branches['ids']);
    }

    public function createConnection(string $chave, int $employeeId, string $clientId, array $scopes): string
    {
        $id = bin2hex(random_bytes(32));
        $this->qb->table('mcp_connections')->withChave($chave)->insert([
            'chave' => $chave, 'connection_id' => $id, 'employee_id' => $employeeId,
            'client_id' => $clientId, 'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'expires_at' => date('Y-m-d H:i:s', time() + 90 * 86400),
        ]);
        return $id;
    }

    public function credentials(string $kind, string $identifier, bool $lock = false): ?array
    {
        // Auth-only hash lookup before tenant context exists.
        $q = $this->qb->table('mcp_credentials')->withoutChave()->where('kind', '=', $kind)
            ->where('token_hash', '=', hash('sha256', $identifier));
        if ($lock) $q->lockForUpdate();
        return $q->first();
    }

    public function persistCredential(Context $c, string $kind, string $identifier, \DateTimeImmutable $expires): void
    {
        $this->qb->table('mcp_credentials')->withChave($c->chave)->insert([
            'chave' => $c->chave, 'connection_id' => $c->connectionId, 'kind' => $kind,
            'token_hash' => hash('sha256', $identifier), 'expires_at' => $expires->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeCredential(string $kind, string $identifier): void
    {
        $credential = $this->credentials($kind, $identifier);
        if ($credential) $this->qb->table('mcp_credentials')->withChave($credential['chave'])
            ->where('id', '=', $credential['id'])->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeConnection(string $chave, string $id): void
    {
        $this->qb->table('mcp_connections')->withChave($chave)->where('connection_id', '=', $id)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function touch(Context $c): void
    {
        $this->qb->table('mcp_connections')->withChave($c->chave)->where('connection_id', '=', $c->connectionId)
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function audit(Context $c, string $event, string $result, string $requestId, int $records = 0, int $ms = 0,
        ?string $requestPayload = null, ?string $responsePayload = null): void
    {
        $this->qb->table('mcp_audit')->withChave($c->chave)->insert([
            'chave' => $c->chave, 'employee_id' => $c->employeeId, 'connection_id' => $c->connectionId,
            'client_id' => $c->clientId, 'request_id' => $requestId, 'event' => substr($event, 0, 80),
            'result' => $result, 'records' => max(0, $records), 'duration_ms' => max(0, $ms),
            ...($requestPayload !== null || $responsePayload !== null ? [
                'request_payload' => $requestPayload, 'response_payload' => $responsePayload,
            ] : []),
        ]);
    }

    public function listConnections(string $chave, int $employeeId, bool $admin, int $page = 1): array
    {
        $q = $this->qb->table('mcp_connections', 'c')->withChave($chave)
            ->select(['c.connection_id', 'c.client_id', 'c.employee_id', 'c.scopes', 'c.expires_at',
                'c.revoked_at', 'c.last_used_at', 'a.name AS application', 'f.nome AS employee'])
            ->leftJoin('mcp_clients', 'a', 'a.client_id', '=', 'c.client_id')
            ->leftJoinRaw('funcionarios', 'f', 'f.id = c.employee_id AND f.chave = c.chave');
        if (!$admin) $q->where('c.employee_id', '=', $employeeId);
        return $q->orderBy('c.id', 'DESC')->limit(25)->offset((max(1, $page) - 1) * 25)->get();
    }

    public function transaction(callable $callback): mixed
    {
        $db = $this->getMysqli();
        $db->begin_transaction();
        try { $result = $callback(); $db->commit(); return $result; }
        catch (\Throwable $e) { $db->rollback(); throw $e; }
    }
}
