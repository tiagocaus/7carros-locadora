<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\QueryBuilder;
use App\Services\Mcp\Catalog;

/** Read-only web projection of MCP tool calls; excludes transport and credential events. */
final class McpAuditLog extends Model
{
    public static function tools(): array
    {
        $names = [];
        foreach (Catalog::CATEGORIES as $category => $definition) {
            $names[] = 'listar_' . $category;
            $names[] = 'obter_' . Catalog::SINGULAR[$category];
        }
        return [...$names, 'resumir_financeiro'];
    }

    private function auditQuery(string $search, array $matchingTools): QueryBuilder
    {
        $tenant = $_SESSION['chave'] ?? null;
        if (!is_string($tenant) || trim($tenant) === '' || $tenant === '0') {
            throw new \RuntimeException('Contexto de auditoria ausente.');
        }
        $query = $this->qb->table('mcp_audit', 'a')->withChave($tenant)
            ->leftJoinRaw('funcionarios', 'f', 'f.id = a.employee_id AND f.chave = a.chave')
            ->leftJoin('mcp_clients', 'c', 'c.client_id', '=', 'a.client_id')
            ->whereIn('a.event', self::tools());
        if ($search !== '') {
            $matchingTools = array_values(array_intersect(self::tools(), $matchingTools));
            $query->whereNested(function ($q) use ($search, $matchingTools) {
                $term = '%' . $search . '%';
                $q->where('f.nome', 'LIKE', $term)->orWhere('c.name', 'LIKE', $term)->orWhere('a.event', 'LIKE', $term);
                if ($matchingTools) {
                    $q->orWhereRaw('a.event IN (' . implode(',', array_fill(0, count($matchingTools), '?')) . ')', $matchingTools);
                }
            });
        }
        return $query;
    }

    /** Legacy payloads may be absent or malformed; only a JSON string is a question. */
    private function questionExpression(): string
    {
        $value = "JSON_EXTRACT(CASE WHEN JSON_VALID(a.request_payload) THEN a.request_payload ELSE '{}' END, '$.argumentos.pergunta_usuario')";
        return "CASE WHEN JSON_TYPE($value) = 'STRING' THEN $value ELSE NULL END";
    }

    /**
     * Decode in PHP: older MariaDB JSON_UNQUOTE replaces supplementary characters.
     * A preview needs at most 256 code points; each can occupy 12 JSON characters
     * (an escaped surrogate pair), plus two quotes. Repair only the cut JSON suffix.
     */
    private function decodeQuestion(?string $json, bool $preview = false): ?string
    {
        if ($json === null) return null;
        $text = json_decode($json, true);
        if (is_string($text)) return $preview ? mb_substr($text, 0, 256) : $text;
        if (!$preview) return null;
        for ($cut = 0; $cut <= 12; $cut++) {
            $prefix = $cut === 0 ? $json : mb_substr($json, 0, -$cut);
            $text = json_decode($prefix . '"', true);
            if (is_string($text)) return mb_substr($text, 0, 256);
        }
        return null;
    }

    public function listarPaginado(int $page, int $perPage, string $search = '', array $matchingTools = []): array
    {
        $rows = $this->auditQuery($search, $matchingTools)->select([
            'a.id', 'a.created_at AS data', 'f.nome AS usuario_nome', 'c.name AS aplicativo',
            'a.event AS evento', 'a.result AS resultado', 'a.records AS registros',
            'a.duration_ms AS duracao_ms', 'a.request_id',
            'LEFT(' . $this->questionExpression() . ', 3074) AS pergunta_json_previa',
            'LEFT(a.request_payload, 256) AS solicitacao_previa', 'LEFT(a.response_payload, 256) AS retorno_previa',
            '(a.request_payload IS NOT NULL OR a.response_payload IS NOT NULL) AS conteudo_disponivel',
        ])->orderByDesc('a.id')->paginate(max(1, $page), max(1, min(100, $perPage)))->get();
        foreach ($rows as &$row) {
            $row['pergunta_previa'] = $this->decodeQuestion($row['pergunta_json_previa'], true);
            unset($row['pergunta_json_previa']);
        }
        unset($row);
        return $rows;
    }

    public function detalhar(int $id): ?array
    {
        $row = $this->auditQuery('', [])->select([
            'a.id', 'a.created_at AS data', 'f.nome AS usuario_nome', 'c.name AS aplicativo',
            'a.event AS evento', 'a.result AS resultado', 'a.records AS registros',
            'a.duration_ms AS duracao_ms', 'a.request_id',
            'a.request_payload AS solicitacao', 'a.response_payload AS retorno',
            $this->questionExpression() . ' AS pergunta_json',
        ])->where('a.id', '=', $id)->first();
        if ($row !== null) {
            $row['pergunta_usuario'] = $this->decodeQuestion($row['pergunta_json']);
            unset($row['pergunta_json']);
        }
        return $row;
    }

    public function contar(string $search = '', array $matchingTools = []): int
    {
        return $this->auditQuery($search, $matchingTools)->count('a.id');
    }
}
