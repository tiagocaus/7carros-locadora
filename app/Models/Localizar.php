<?php

namespace App\Models;

/**
 * Model para busca global (Spotlight)
 *
 * Fornece acesso ao QueryBuilder via Singleton de conexão do Model base.
 */
class Localizar extends Model
{
    public function buscarClientes(string $searchTerm, ?string $filialWhere, array $filialParams): array
    {
        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj'])
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            })
            ->limit(5);

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->orderBy('nome_rsocial', 'ASC')->get();
    }

    public function buscarVeiculos(string $searchTerm, ?string $filialWhere, array $filialParams): array
    {
        $chave = $_SESSION['chave'] ?? null;
        if (!$chave) return [];

        $query = $this->qb
            ->table('veiculos', 'v')
            ->withoutChave()
            ->select(['v.id', 'v.placa', 'v.marca', 'v.modelo', 'v.ano'])
            ->where('v.chave', '=', $chave)
            ->whereRaw(
                '(v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR v.renavam LIKE ?)',
                [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
            )
            ->limit(5);

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $filialWhereV = str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWhereV, $filialParams);
        }

        return $query->orderBy('v.modelo', 'ASC')->get();
    }

    public function buscarLocacoes(string $searchTerm, ?string $filialWhere, array $filialParams): array
    {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.id', 'l.codigo', 'l.cliente_nome', 'l.status'])
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('l.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('l.cliente_nome', 'LIKE', $searchTerm);
            })
            ->limit(5);

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->orderByDesc('l.id')->get();
    }

    public function buscarContratos(string $searchTerm, ?string $filialWhere, array $filialParams): array
    {
        $query = $this->qb
            ->table('contratos', 'c')
            ->select(['c.id', 'c.codigo', 'c.status'])
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('c.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cl.cpf_cnpj', 'LIKE', $searchTerm);
            })
            ->limit(5);

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->orderByDesc('c.id')->get();
    }

    public function buscarFuncionarios(string $searchTerm): array
    {
        $chave = $_SESSION['chave'] ?? null;
        if (!$chave) return [];

        return $this->qb
            ->table('funcionarios', 'f')
            ->withoutChave()
            ->select(['f.id', 'f.nome', 'f.email', 'r.name AS role_name', 'r.name AS funcao'])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->where('f.chave', '=', $chave)
            ->where('f.usuario', 'NOT LIKE', 'suporte%')
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('f.nome', 'LIKE', $searchTerm)
                  ->orWhere('f.email', 'LIKE', $searchTerm);
            })
            ->orderBy('f.nome', 'ASC')
            ->limit(5)
            ->get();
    }
}
