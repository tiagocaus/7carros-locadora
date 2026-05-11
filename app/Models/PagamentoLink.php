<?php

namespace App\Models;

use App\Core\Auth;

/**
 * Model para gerenciamento de links de pagamento públicos
 *
 * Permite criar links únicos para clientes pagarem via URL pública.
 * URL: /pagar/{codigo}
 */
class PagamentoLink extends Model
{
    /**
     * Gera código único para o link (32 hex chars)
     *
     * @return string
     */
    private function gerarCodigo(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Cria link de pagamento
     *
     * @param array<string, mixed> $dados
     * @return int ID do link criado
     */
    public function criar(array $dados): int
    {
        $codigo = $this->gerarCodigo();

        // Garantir unicidade do código
        while ($this->codigoExiste($codigo)) {
            $codigo = $this->gerarCodigo();
        }

        return $this->qb
            ->table('pagamentos_links')
            ->insert([
                'chave' => $dados['chave'] ?? Auth::chave(),
                'codigo' => $codigo,
                'id_financeiro' => $dados['id_financeiro'] ?? null,
                'id_locacao' => $dados['id_locacao'] ?? null,
                'id_cliente' => $dados['id_cliente'] ?? null,
                'valor' => $dados['valor'],
                'descricao' => $dados['descricao'] ?? null,
                'expires_at' => $dados['expires_at'] ?? null,
                'status' => 'pending',
            ]);
    }

    /**
     * Busca link de pagamento pendente por locacao
     */
    public function buscarPorLocacao(int $idLocacao): ?array
    {
        return $this->qb
            ->table('pagamentos_links')
            ->where('id_locacao', '=', $idLocacao)
            ->where('status', '=', 'pending')
            ->first();
    }

    /**
     * Verifica se código já existe
     *
     * @param string $codigo
     * @return bool
     */
    private function codigoExiste(string $codigo): bool
    {
        return $this->qb
            ->table('pagamentos_links')
            ->withoutChave()
            ->where('codigo', '=', $codigo)
            ->exists();
    }

    /**
     * Busca link por código (para página pública)
     *
     * @param string $codigo
     * @return array<string, mixed>|null
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->qb
            ->table('pagamentos_links', 'pl')
            ->select([
                'pl.*',
                'f.descricao AS financeiro_descricao',
                'f.documento AS financeiro_documento',
                'f.data_venci AS financeiro_vencimento',
                'f.id_forma_pagamento AS id_forma_pagamento',
                'c.nome_rsocial AS cliente_nome',
                'c.cpf_cnpj AS cliente_documento',
                "(SELECT email FROM contatos_emails WHERE entidade_tipo = 'cliente' AND entidade_id = c.id AND principal = 'S' LIMIT 1) AS cliente_email",
                "(SELECT telefone FROM contatos_telefones WHERE entidade_tipo = 'cliente' AND entidade_id = c.id AND principal = 'S' LIMIT 1) AS cliente_telefone",
                'mf.nome_fantasia AS empresa_nome',
                'mf.razao_social AS empresa_razao_social',
                'mf.cpf_cnpj AS empresa_cnpj',
                'mf.rua AS empresa_endereco',
                'mf.cidade AS empresa_cidade',
                'mf.estado AS empresa_uf',
                'mf.logo AS empresa_logo',
                'mf.chave AS empresa_chave',
                'mf.currency_code AS tenant_currency',
                "(SELECT telefone FROM contatos_telefones WHERE entidade_tipo = 'matriz_filial' AND entidade_id = mf.id AND principal = 'S' LIMIT 1) AS empresa_telefone",
                "(SELECT email FROM contatos_emails WHERE entidade_tipo = 'matriz_filial' AND entidade_id = mf.id AND principal = 'S' LIMIT 1) AS empresa_email",
            ])
            ->leftJoin('financeiro', 'f', 'pl.id_financeiro', '=', 'f.id')
            ->leftJoin('clientes', 'c', 'pl.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'mf', 'f.id_matriz_filial', '=', 'mf.id')
            ->withoutChave()
            ->where('pl.codigo', '=', $codigo)
            ->first();
    }

    /**
     * Busca link por ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('pagamentos_links', 'pl')
            ->select([
                'pl.*',
                'f.descricao AS financeiro_descricao',
                'c.nome_rsocial AS cliente_nome',
            ])
            ->leftJoin('financeiro', 'f', 'pl.id_financeiro', '=', 'f.id')
            ->leftJoin('clientes', 'c', 'pl.id_cliente', '=', 'c.id')
            ->where('pl.id', '=', $id)
            ->first();
    }

    /**
     * Lista links do tenant
     *
     * @param int $page
     * @param int $perPage
     * @param string $search
     * @param string $status
     * @return array<int, array<string, mixed>>
     */
    public function listarPaginado(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $query = $this->qb
            ->table('pagamentos_links', 'pl')
            ->select([
                'pl.*',
                'f.descricao AS financeiro_descricao',
                'c.nome_rsocial AS cliente_nome',
            ])
            ->leftJoin('financeiro', 'f', 'pl.id_financeiro', '=', 'f.id')
            ->leftJoin('clientes', 'c', 'pl.id_cliente', '=', 'c.id')
            ->orderBy('pl.created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('pl.codigo', 'LIKE', "%{$search}%")
                  ->orWhere('c.nome_rsocial', 'LIKE', "%{$search}%")
                  ->orWhere('f.descricao', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('pl.status', '=', $status);
        }

        return $query->get();
    }

    /**
     * Conta registros
     *
     * @param string $search
     * @param string $status
     * @return int
     */
    public function contar(string $search = '', string $status = ''): int
    {
        $query = $this->qb
            ->table('pagamentos_links', 'pl')
            ->selectRaw('COUNT(*) as total')
            ->leftJoin('financeiro', 'f', 'pl.id_financeiro', '=', 'f.id')
            ->leftJoin('clientes', 'c', 'pl.id_cliente', '=', 'c.id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('pl.codigo', 'LIKE', "%{$search}%")
                  ->orWhere('c.nome_rsocial', 'LIKE', "%{$search}%")
                  ->orWhere('f.descricao', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('pl.status', '=', $status);
        }

        $result = $query->first();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Marca link como pago
     *
     * @param int $id
     * @param int $idTransacao ID da transação que pagou
     * @param string|null $ip IP do cliente
     * @param string|null $userAgent User-Agent do cliente
     * @return int
     */
    public function marcarComoPago(int $id, int $idTransacao, ?string $ip = null, ?string $userAgent = null): int
    {
        return $this->qb
            ->table('pagamentos_links')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'status' => 'paid',
                'id_transacao_paga' => $idTransacao,
                'ip_pagamento' => $ip,
                'user_agent_pagamento' => $userAgent,
            ]);
    }

    /**
     * Marca link como expirado
     *
     * @param int $id
     * @return int
     */
    public function marcarComoExpirado(int $id): int
    {
        return $this->qb
            ->table('pagamentos_links')
            ->withoutChave()
            ->where('id', '=', $id)
            ->where('status', '=', 'pending')
            ->update(['status' => 'expired']);
    }

    /**
     * Marca link como cancelado
     *
     * @param int $id
     * @return int
     */
    public function marcarComoCancelado(int $id): int
    {
        return $this->qb
            ->table('pagamentos_links')
            ->where('id', '=', $id)
            ->where('status', '=', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Lista links expirados pendentes para atualização
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarExpiradosPendentes(): array
    {
        return $this->qb
            ->table('pagamentos_links')
            ->withoutChave()
            ->where('status', '=', 'pending')
            ->whereRaw('expires_at IS NOT NULL')
            ->whereRaw('expires_at < NOW()')
            ->get();
    }

    /**
     * Atualiza status de links expirados
     *
     * @return int Número de registros atualizados
     */
    public function atualizarExpirados(): int
    {
        return $this->qb
            ->table('pagamentos_links')
            ->withoutChave()
            ->where('status', '=', 'pending')
            ->whereRaw('expires_at IS NOT NULL')
            ->whereRaw('expires_at < NOW()')
            ->update(['status' => 'expired']);
    }

    /**
     * Busca link por ID do financeiro
     *
     * @param int $idFinanceiro
     * @return array<string, mixed>|null
     */
    public function buscarPorFinanceiro(int $idFinanceiro): ?array
    {
        return $this->qb
            ->table('pagamentos_links')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->where('status', '=', 'pending')
            ->first();
    }

    /**
     * Verifica se já existe link pendente para o financeiro
     *
     * @param int $idFinanceiro
     * @return bool
     */
    public function existeLinkPendente(int $idFinanceiro): bool
    {
        return $this->qb
            ->table('pagamentos_links')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->where('status', '=', 'pending')
            ->exists();
    }

    /**
     * Retorna URL completa do link
     *
     * @param string $codigo
     * @return string
     */
    public function getUrl(string $codigo): string
    {
        $baseUrl = \App\Core\Database::env('APP_URL', 'https://locadora.7carros.com');
        return "{$baseUrl}/pagar/{$codigo}";
    }
}
