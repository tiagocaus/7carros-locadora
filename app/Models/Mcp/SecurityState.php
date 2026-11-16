<?php

declare(strict_types=1);
namespace App\Models\Mcp;

use App\Models\Model;
use App\Services\Mcp\{Context, SecurityException, SecurityPolicy};

/** One bounded row per employee. All mutations serialize on the same tenant-scoped row. */
final class SecurityState extends Model
{
    private function queryFor(string $tenant, int $employee): \App\Classes\QueryBuilder
    {
        if (trim($tenant) === '' || $tenant === '0' || $employee < 1) throw new \InvalidArgumentException('Identidade MCP inválida.');
        return $this->qb->table('mcp_security_state')->withChave($tenant)->where('employee_id', '=', $employee);
    }

    public function status(string $tenant, int $employee, ?int $now = null): array
    {
        $now ??= time();
        $row = $this->queryFor($tenant, $employee)->first() ?? [];
        return $this->normalize($row, $now) + ['limits' => SecurityPolicy::limits()];
    }

    private function normalize(array $row, int $now): array
    {
        $hour = intdiv($now, 3600); $day = intdiv($now, 86400);
        $sameHour = (int) ($row['hour_bucket'] ?? 0) === $hour;
        $sameDay = (int) ($row['day_bucket'] ?? 0) === $day;
        $strikes = json_decode($row['infractions'] ?? '[]', true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($strikes) || !array_is_list($strikes) || array_filter($strikes, fn ($t) => !is_int($t) || $t < 0)) {
            throw new \RuntimeException('Estado MCP inválido.');
        }
        return ['infractions' => array_values(array_filter($strikes, fn ($t) => is_int($t) && $t > $now - 300)),
            'suspended_until' => (int) ($row['suspended_until'] ?? 0), 'hour_bucket' => $hour, 'day_bucket' => $day,
            'hour_records' => $sameHour ? (int) $row['hour_records'] : 0,
            'day_records' => $sameDay ? (int) $row['day_records'] : 0,
            'hour_sensitive' => $sameHour ? (int) $row['hour_sensitive'] : 0,
            'day_sensitive' => $sameDay ? (int) $row['day_sensitive'] : 0];
    }

    public function assertAllowed(string $tenant, int $employee): void
    {
        $now = time();
        $until = $this->status($tenant, $employee, $now)['suspended_until'];
        if ($until > $now) throw new SecurityException('mcp_suspended', $until - $now);
    }

    private function locked(string $tenant, int $employee, callable $work): mixed
    {
        $db = $this->getMysqli();
        $db->begin_transaction();
        try {
            // Serialize first-row creation as well; avoid duplicate-insert lock upgrades/deadlocks.
            $owner = $this->qb->table('funcionarios')->withChave($tenant)->select(['id'])
                ->where('id', '=', $employee)->lockForUpdate()->first();
            if (!$owner) throw new \RuntimeException('Funcionário MCP indisponível.');
            $row = $this->queryFor($tenant, $employee)->lockForUpdate()->first();
            if (!$row) {
                $this->qb->table('mcp_security_state')->withChave($tenant)->insert([
                    'chave' => $tenant, 'employee_id' => $employee, 'infractions' => '[]']);
                $row = $this->queryFor($tenant, $employee)->lockForUpdate()->first();
            }
            if (!$row) throw new \RuntimeException('Estado MCP indisponível.');
            $now = time();
            $state = $this->normalize($row, $now);
            $result = $work($state, $now);
            $state['infractions'] = json_encode($state['infractions'], JSON_THROW_ON_ERROR);
            $this->queryFor($tenant, $employee)->update($state);
            $db->commit();
            return $result;
        } catch (\Throwable $e) { $db->rollback(); throw $e; }
    }

    public function infraction(Context $c, string $reason, string $requestId): void
    {
        $this->locked($c->chave, $c->employeeId, function (array &$s, int $now) use ($c, $reason, $requestId) {
            if ($s['suspended_until'] > $now) return;
            $s['infractions'][] = $now;
            $store = new Store();
            $store->audit($c, 'security_infraction', $reason, $requestId);
            if (count($s['infractions']) >= SecurityPolicy::limits()['infractions']) {
                $s['suspended_until'] = $now + 1800;
                $s['infractions'] = [];
                $store->audit($c, 'security_suspended', 'mcp_suspended', $requestId);
            }
        });
    }

    /** Debit and success audit are committed together. No business query holds this lock. */
    public function deliver(Context $c, int $records, int $sensitive, string $requestId, callable $audit): void
    {
        $denial = $this->locked($c->chave, $c->employeeId,
            function (array &$s, int $now) use ($c, $records, $sensitive, $requestId, $audit) {
                if ($s['suspended_until'] > $now) return new SecurityException('mcp_suspended', $s['suspended_until'] - $now);
                $limits = SecurityPolicy::limits();
                $wait = 0;
                foreach (['hour' => 3600, 'day' => 86400] as $period => $seconds) {
                    if ($s[$period . '_records'] + $records > $limits[$period . '_records']
                        || $s[$period . '_sensitive'] + $sensitive > $limits[$period . '_sensitive']) {
                        $wait = max($wait, ($s[$period . '_bucket'] + 1) * $seconds - $now);
                    }
                }
                if ($wait > 0) {
                    (new Store())->audit($c, 'security_quota', 'quota_exceeded', $requestId);
                    return new SecurityException('mcp_quota_exceeded', $wait);
                }
                $audit();
                foreach (['hour', 'day'] as $period) {
                    $s[$period . '_records'] += $records;
                    $s[$period . '_sensitive'] += $sensitive;
                }
                return null;
            });
        if ($denial !== null) throw $denial;
    }

    public function release(Context $actor, int $employee, string $requestId): void
    {
        $this->locked($actor->chave, $employee, function (array &$s) use ($actor, $employee, $requestId) {
            $s['suspended_until'] = 0; $s['infractions'] = [];
            (new Store())->audit($actor, 'security_released', 'success', $requestId, 0, 0,
                json_encode(['employee_id' => $employee], JSON_THROW_ON_ERROR));
        });
    }

    public function events(string $tenant, int $employee, bool $admin): array
    {
        $q = $this->qb->table('mcp_audit')->withChave($tenant)
            ->select(['employee_id', 'event', 'result', 'created_at', 'request_id', 'request_payload'])
            ->whereIn('event', ['security_infraction', 'security_suspended', 'security_released', 'security_quota']);
        if (!$admin) $q->whereNested(function ($filter) use ($employee) {
            $filter->where('employee_id', '=', $employee)->orWhereRaw(
                "(event = 'security_released' AND JSON_EXTRACT(CASE WHEN JSON_VALID(request_payload) THEN request_payload ELSE '{}' END, '$.employee_id') = ?)", [$employee]);
        });
        $rows = $q->orderByDesc('id')->limit(50)->get();
        foreach ($rows as &$row) {
            $payload = json_decode($row['request_payload'] ?? '{}', true);
            $row['affected_employee_id'] = $row['event'] === 'security_released'
                ? (int) ($payload['employee_id'] ?? $row['employee_id']) : (int) $row['employee_id'];
            unset($row['request_payload']);
        }
        unset($row);
        return $rows;
    }
}
