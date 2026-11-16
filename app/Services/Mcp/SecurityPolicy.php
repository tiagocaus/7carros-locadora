<?php

declare(strict_types=1);
namespace App\Services\Mcp;

use App\Models\Mcp\SecurityState;

final class SecurityPolicy
{
    public static function limits(): array
    {
        $limits = [];
        foreach (['infractions' => 3, 'hour_records' => 3000, 'day_records' => 10000,
            'hour_sensitive' => 100, 'day_sensitive' => 300] as $key => $default) {
            $value = Config::value('MCP_SECURITY_' . strtoupper($key), (string) $default);
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 100000000) {
                throw new \RuntimeException('Configuração de segurança MCP inválida.');
            }
            $limits[$key] = (int) $value;
        }
        return $limits;
    }

    /** Only structural violations count. Never classify the user's question or record contents. */
    public function inspect(Context $c, string $name, array $arguments, string $requestId): ?string
    {
        (new SecurityState())->assertAllowed($c->chave, $c->employeeId);
        $definition = null;
        foreach (Catalog::CATEGORIES as $category => $def) {
            $detail = $name === 'obter_' . Catalog::SINGULAR[$category];
            $summary = $category === 'lancamentos' && $name === 'resumir_financeiro';
            if ($detail || $summary || $name === 'listar_' . $category) {
                $definition = [$category, $def, $detail, $summary]; break;
            }
        }
        $reason = null;
        if ($definition === null) $reason = 'unknown_tool';
        else {
            [$category, $def, $detail, $summary] = $definition;
            if (!$c->permits($def['scope'])
                || (($arguments['incluir_documentos'] ?? false) === true && !$c->permits('clientes:documentos'))
                || (($arguments['incluir_contatos'] ?? false) === true && !$c->permits('clientes:contatos'))) $reason = 'forbidden_tool';
            $schema = Catalog::inputSchema($category, $detail, $summary);
            if ($reason === null && array_diff_key($arguments, $schema['properties'])) $reason = 'forbidden_argument';
            $filters = $arguments['filtros'] ?? [];
            if ($reason === null && ($filters instanceof \stdClass || (is_array($filters) && !array_is_list($filters)))
                && array_diff_key((array) $filters, (array) Catalog::filters($category, !$summary)['properties'])) $reason = 'forbidden_argument';
        }
        if ($reason !== null) (new SecurityState())->infraction($c, $reason, $requestId);
        return $reason;
    }
}
