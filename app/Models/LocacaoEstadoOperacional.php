<?php

namespace App\Models;

use App\Services\AuditLogService;

/** Estado operacional lido e auditado na transacao da locacao. */
class LocacaoEstadoOperacional extends Model
{
    public function bloquear(int $id): ?array
    {
        return $this->qb->table('locacoes')->where('id', '=', $id)->lockForUpdate()->first();
    }

    public static function validar(string $atual, mixed $original, string $novo, bool $confirmacao = false): void
    {
        if (!is_string($original) || $original !== $atual) {
            throw new \DomainException('status_conflict', 409);
        }
        $permitidos = $confirmacao ? ['P' => ['R']] : [
            'P' => ['P'], 'R' => ['R', 'A'], 'A' => ['A', 'F'], 'F' => ['F'],
        ];
        if (!in_array($novo, $permitidos[$atual] ?? [], true)) {
            throw new \DomainException('invalid_status_transition', 422);
        }
    }

    public function capturar(int $id, array $veiculosExtras = []): array
    {
        $locacao = $this->qb->table('locacoes')->select(['status', 'data_saida', 'data_prevista', 'data_chegada'])
            ->where('id', '=', $id)->first();
        if (!$locacao) {
            throw new \RuntimeException('Locacao nao encontrada para auditoria');
        }
        $snapshot = [];
        foreach (['status' => 'Status', 'data_saida' => 'Data Saída', 'data_prevista' => 'Data Prevista', 'data_chegada' => 'Data Chegada'] as $coluna => $label) {
            $snapshot[$label] = $locacao[$coluna];
        }
        $vinculos = $this->qb->table('locacoes_veiculos')->select([
            'id', 'id_veiculo', 'id_grupo', 'data_saida', 'data_entrada',
            'odometro_saida', 'odometro_entrada', 'combustivel_saida', 'combustivel_entrada',
        ])->where('id_locacao', '=', $id)->orderBy('id')->get();
        foreach ($vinculos as $vinculo) {
            foreach ($vinculo as $campo => $valor) {
                if ($campo !== 'id') $snapshot["Vínculo #{$vinculo['id']} / {$campo}"] = $valor;
            }
            $veiculosExtras[] = (int) $vinculo['id_veiculo'];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $veiculosExtras))));
        if ($ids) {
            foreach ($this->qb->table('veiculos')->select(['id', 'disponibilidade'])->whereIn('id', $ids)->orderBy('id')->get() as $v) {
                $snapshot["Veículo #{$v['id']} / disponibilidade"] = $v['disponibilidade'];
            }
        }
        return $snapshot;
    }

    public static function diferencas(array $antes, array $depois): array
    {
        $campos = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($depois))) as $label) {
            $de = $antes[$label] ?? null;
            $para = $depois[$label] ?? null;
            if (($de === null ? null : (string) $de) !== ($para === null ? null : (string) $para)) {
                $campos[] = AuditLogService::campo($label, $de, $para, 'Operação');
            }
        }
        return $campos;
    }

    /** Mantem campos comerciais do frontend; dados operacionais vêm somente do banco. */
    public static function camposComplementares(?string $json): array
    {
        $dados = json_decode($json ?? '', true);
        if (!is_array($dados)) $dados = json_decode(stripslashes($json ?? ''), true);
        if (!is_array($dados)) return [];
        $campos = [];
        // Os labels do FormAudit sao traduzidos. Remover equivalentes em todos os idiomas.
        $labels = ['status', 'grupo', 'veículo', 'veiculo', 'data saída', 'data prevista', 'data chegada',
            'odômetro (km)', 'odômetro devolução (km)', 'combustivel saída', 'combustível devolução'];
        foreach (glob(dirname(__DIR__) . '/Lang/*/modules/locacoes.php') as $arquivo) {
            $lang = require $arquivo;
            foreach (['status', 'group', 'vehicle', 'checkout_date', 'expected_date', 'arrival_date', 'return_date', 'checkout_time', 'expected_time', 'return_time'] as $key) {
                if (isset($lang['fields'][$key])) $labels[] = mb_strtolower($lang['fields'][$key]);
            }
            foreach ([
                ($lang['odometer_fuel']['odometer'] ?? '') . ' (km)',
                $lang['odometer_fuel']['fuel_out'] ?? '',
                $lang['form']['return_odometer_km'] ?? '',
                $lang['form']['return_fuel'] ?? '',
            ] as $label) {
                $labels[] = mb_strtolower(trim($label));
            }
        }
        $adicionar = static function ($campo, $aba = null) use (&$campos, $labels): void {
            if (!is_array($campo) || !isset($campo['label'])) return;
            if (($campo['de'] ?? null) === ($campo['para'] ?? null)) return;
            $label = mb_strtolower(trim(str_replace('*', '', (string) $campo['label'])));
            if (in_array($label, $labels, true)) return;
            $campos[] = AuditLogService::campo($campo['label'], $campo['de'] ?? null, $campo['para'] ?? null, $campo['aba'] ?? $aba);
        };
        foreach ($dados as $aba => $grupo) {
            if (is_array($grupo) && isset($grupo['label'])) $adicionar($grupo);
            elseif (is_array($grupo)) foreach ($grupo as $campo) $adicionar($campo, (string) $aba);
        }
        return $campos;
    }
}
