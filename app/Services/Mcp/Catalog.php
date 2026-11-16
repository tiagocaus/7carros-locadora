<?php

declare(strict_types=1);

namespace App\Services\Mcp;

final class Catalog
{
    public const SCOPES = [
        'frota:read' => 'veiculos.visualizar', 'clientes:read' => 'clientes.visualizar',
        'contratos:read' => 'contratos.visualizar', 'locacoes:read' => 'locacoes.visualizar',
        'financeiro:read' => 'financeiro.visualizar', 'clientes:documentos' => 'mcp.documentos',
        'clientes:contatos' => 'mcp.contatos',
    ];
    public const CATEGORIES = [
        'veiculos' => ['scope' => 'frota:read', 'table' => 'veiculos', 'branch' => 'id_matriz_filial',
            'fields' => ['id', 'id_matriz_filial', 'placa', 'marca', 'modelo', 'ano', 'cor', 'disponibilidade', 'odometro'],
            'search' => ['placa', 'marca', 'modelo'], 'status' => 'disponibilidade', 'date' => null],
        'clientes' => ['scope' => 'clientes:read', 'table' => 'clientes', 'branch' => 'id_matriz_filial',
            'fields' => ['id', 'id_matriz_filial', 'nome_rsocial', 'nome_fantasia', 'tipo', 'situacao'],
            'search' => ['nome_rsocial', 'nome_fantasia'], 'status' => 'situacao', 'date' => 'data_cadastro'],
        'contratos' => ['scope' => 'contratos:read', 'table' => 'contratos', 'branch' => 'id_matriz_filial_retirada',
            'fields' => ['id', 'codigo', 'id_cliente', 'id_matriz_filial_retirada', 'status', 'data_ini', 'data_fim', 'total_fatura', 'total_pagar'],
            'search' => ['codigo'], 'status' => 'status', 'date' => 'data_fim'],
        'locacoes' => ['scope' => 'locacoes:read', 'table' => 'locacoes', 'branch' => 'id_matriz_filial_retirada',
            'fields' => ['id', 'codigo', 'id_cliente', 'id_matriz_filial_retirada', 'id_matriz_filial_devolucao', 'status',
                'data_saida', 'data_prevista', 'data_chegada', 'total_fatura', 'total_pagar'],
            'search' => ['codigo'], 'status' => 'status', 'date' => 'data_prevista'],
        'lancamentos' => ['scope' => 'financeiro:read', 'table' => 'financeiro', 'branch' => 'id_matriz_filial',
            'fields' => ['id', 'codigo', 'descricao', 'id_matriz_filial', 'tipo', 'pago', 'data_venci', 'data_pago',
                'valor_total', 'valor_taxa', 'valor_liquido', 'parcela', 'total_parcelas', 'id_financeiro_origem'],
            'search' => ['codigo'], 'status' => 'pago', 'date' => 'data_venci'],
    ];
    public const SINGULAR = ['veiculos' => 'veiculo', 'clientes' => 'cliente', 'contratos' => 'contrato',
        'locacoes' => 'locacao', 'lancamentos' => 'lancamento'];

    public static function filters(string $category, bool $pagination = true): array
    {
        $properties = [];
        if ($pagination) $properties += ['pagina' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000, 'default' => 1],
            'por_pagina' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25]];
        $properties += ['busca' => ['type' => 'string', 'maxLength' => 100],
            'filial_id' => ['type' => 'integer', 'minimum' => 1],
            'status' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2]];
        if (self::CATEGORIES[$category]['date']) {
            $properties['data_de'] = ['type' => 'string', 'format' => 'date'];
            $properties['data_ate'] = ['type' => 'string', 'format' => 'date'];
        }
        if ($category === 'lancamentos') $properties['tipo'] = ['type' => 'string', 'enum' => ['R', 'D']];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => (object) $properties];
    }

    public static function inputSchema(string $category, bool $detail = false, bool $summary = false): array
    {
        $p = $detail ? ['id' => ['type' => 'integer', 'minimum' => 1]]
            : ['filtros' => self::filters($category, !$summary)];
        if ($detail && $category === 'clientes') $p += [
            'incluir_documentos' => ['type' => 'boolean', 'default' => false],
            'incluir_contatos' => ['type' => 'boolean', 'default' => false],
        ];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $p,
            'required' => $detail ? ['id'] : []];
    }

    public static function rowSchema(string $category): array
    {
        $p = [];
        foreach (self::CATEGORIES[$category]['fields'] as $field) {
            $p[$field] = ['type' => ['string', 'integer', 'null']];
        }
        if (in_array($category, ['contratos', 'locacoes', 'lancamentos'], true)) $p['moeda'] = ['type' => ['string', 'null']];
        if ($category === 'clientes') {
            foreach (['cpf_cnpj', 'rg_ie', 'cnh_numero', 'cnh_categoria', 'cnh_validade'] as $field) $p[$field] = ['type' => ['string', 'null']];
            $p['emails'] = ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => ['email' => ['type' => 'string']], 'required' => ['email']]];
            $p['telefones'] = ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => ['telefone' => ['type' => 'string']], 'required' => ['telefone']]];
        }
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $p,
            'required' => self::CATEGORIES[$category]['fields']];
    }

    public static function outputSchema(string $category, bool $detail = false, bool $summary = false): array
    {
        $data = $summary ? ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
            'properties' => ['moeda' => ['type' => ['string', 'null']], 'tipo' => ['type' => 'string'],
                'pago' => ['type' => 'string'], 'total' => ['type' => ['string', 'null']], 'quantidade' => ['type' => ['string', 'integer']]],
            'required' => ['moeda', 'tipo', 'pago', 'total', 'quantidade']]]
            : ($detail ? ['anyOf' => [self::rowSchema($category), ['type' => 'null']]]
                : ['type' => 'array', 'items' => self::rowSchema($category)]);
        $p = ['dados' => $data, 'consultado_em' => ['type' => 'string'],
            'filtros' => $detail ? ['type' => 'object', 'additionalProperties' => false,
                'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]
                : self::filters($category, !$summary)];
        if (!$detail && !$summary) $p['paginacao'] = ['type' => 'object', 'additionalProperties' => false,
            'properties' => ['pagina' => ['type' => 'integer'], 'por_pagina' => ['type' => 'integer'], 'total' => ['type' => 'integer']],
            'required' => ['pagina', 'por_pagina', 'total']];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $p, 'required' => array_keys($p)];
    }
}
