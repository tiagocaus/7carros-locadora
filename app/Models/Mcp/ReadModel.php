<?php

declare(strict_types=1);

namespace App\Models\Mcp;

use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Services\Mcp\{Catalog, Context};

final class ReadModel extends Model
{
    public function __construct(private readonly Context $context)
    {
        parent::__construct();
        // Database execution is bounded as well as PHP processing and response delivery.
        $db = $this->getMysqli();
        $limit = stripos($db->server_info, 'MariaDB') !== false
            ? 'SET SESSION max_statement_time = 15' // MariaDB: seconds, including non-SELECT statements.
            : 'SET SESSION max_execution_time = 15000'; // MySQL: milliseconds.
        if (!$db->query($limit)) throw new \RuntimeException('Limite de execução indisponível.', $db->errno);
    }

    private function queryFor(string $category, array $filters): QueryBuilder
    {
        $def = Catalog::CATEGORIES[$category];
        $q = $this->qb->table($def['table'], 'r')->withChave($this->context->chave);
        $branchColumns = ['r.' . $def['branch']];
        if ($category === 'locacoes') $branchColumns[] = 'r.id_matriz_filial_devolucao';
        if (!$this->context->allBranches) {
            $ids = $this->context->branchIds;
            if (!$ids) $q->whereRaw('1 = 0');
            else {
                $slots = implode(',', array_fill(0, count($ids), '?'));
                $parts = array_map(fn ($c) => "$c IN ($slots)", $branchColumns);
                $q->whereRaw('(' . implode(' OR ', $parts) . ')', count($parts) === 2 ? [...$ids, ...$ids] : $ids);
            }
        }
        if (isset($filters['filial_id'])) {
            $q->whereRaw('(' . implode(' OR ', array_map(fn ($c) => "$c = ?", $branchColumns)) . ')',
                array_fill(0, count($branchColumns), $filters['filial_id']));
        }
        if (($filters['busca'] ?? '') !== '') {
            $q->whereRaw('(' . implode(' OR ', array_map(fn ($c) => "r.$c LIKE ?", $def['search'])) . ')',
                array_fill(0, count($def['search']), '%' . $filters['busca'] . '%'));
        }
        if (isset($filters['status'])) $q->where('r.' . $def['status'], '=', $filters['status']);
        if (isset($filters['tipo'])) $q->where('r.tipo', '=', $filters['tipo']);
        if ($def['date']) {
            if (isset($filters['data_de'])) $q->where('r.' . $def['date'], '>=', $filters['data_de']);
            if (isset($filters['data_ate'])) $q->where('r.' . $def['date'], '<', date('Y-m-d', strtotime($filters['data_ate'] . ' +1 day')));
        }
        return $q;
    }

    private function selectFields(QueryBuilder $q, string $category): QueryBuilder
    {
        $fields = array_map(fn ($f) => 'r.' . $f, Catalog::CATEGORIES[$category]['fields']);
        if (in_array($category, ['contratos', 'locacoes', 'lancamentos'], true)) {
            $q->leftJoinRaw('matrizes_filiais', 'm', 'm.id = r.' . Catalog::CATEGORIES[$category]['branch'] . ' AND m.chave = r.chave');
            $fields[] = 'm.currency_code AS moeda';
        }
        return $q->select($fields);
    }

    public function listing(string $category, array $filters): array
    {
        $total = $this->queryFor($category, $filters)->count('r.id');
        $page = $filters['pagina'] ?? 1; $size = $filters['por_pagina'] ?? 25;
        $rows = $this->selectFields($this->queryFor($category, $filters), $category)
            ->orderBy('r.id', 'ASC')->limit($size)->offset(($page - 1) * $size)->get();
        return ['dados' => $rows, 'paginacao' => ['pagina' => $page, 'por_pagina' => $size, 'total' => $total]];
    }

    public function detail(string $category, int $id, bool $documents, bool $contacts): ?array
    {
        $q = $this->selectFields($this->queryFor($category, []), $category)->where('r.id', '=', $id);
        $row = $q->first();
        if (!$row || $category !== 'clientes') return $row;
        if ($documents) {
            $private = $this->queryFor('clientes', [])
                ->select(['cpf_cnpj', 'rg_ie', 'cnh_numero', 'cnh_categoria', 'cnh_validade'])->where('r.id', '=', $id)->first();
            $row += $private ?? [];
        }
        if ($contacts) {
            $row['emails'] = $this->qb->table('contatos_emails')->withChave($this->context->chave)
                ->select(['email'])->where('entidade_tipo', '=', 'cliente')->where('entidade_id', '=', $id)->get();
            $row['telefones'] = $this->qb->table('contatos_telefones')->withChave($this->context->chave)
                ->select(['telefone'])->where('entidade_tipo', '=', 'cliente')->where('entidade_id', '=', $id)->get();
        }
        return $row;
    }

    public function summary(array $filters): array
    {
        // Each financeiro row is one installment (including the first/origin).
        // Do not join financeiro_itens or add operational total_fatura to these amounts.
        return $this->queryFor('lancamentos', $filters)
            ->leftJoinRaw('matrizes_filiais', 'm', 'm.id = r.id_matriz_filial AND m.chave = r.chave')
            ->select(['m.currency_code AS moeda', 'r.tipo', 'r.pago', 'SUM(r.valor_total) AS total', 'COUNT(*) AS quantidade'])
            ->groupBy(['m.currency_code', 'r.tipo', 'r.pago'])->get();
    }
}
