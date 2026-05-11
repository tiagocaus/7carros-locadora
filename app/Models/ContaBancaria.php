<?php

namespace App\Models;

/**
 * Model ContaBancaria
 *
 * Gerencia contas bancarias e caixas do sistema financeiro.
 */
class ContaBancaria extends Model
{
    /**
     * Lista todas as contas bancarias do tenant
     *
     * @return array Lista de contas
     */
    public function listar(): array
    {
        return $this->qb
            ->table('contas_bancarias')
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista contas bancarias do tenant para select
     *
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarParaSelect(string $search = ''): array
    {
        $query = $this->qb
            ->table('contas_bancarias')
            ->select(['id', 'nome'])
            ->where('status', '=', 'A');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query->orderBy('nome', 'ASC')->limit(50)->get();
    }

    /**
     * Lista contas bancarias do tenant com paginacao e busca
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de contas
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('contas_bancarias');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('banco', 'LIKE', $searchTerm)
                  ->orWhere('agencia', 'LIKE', $searchTerm)
                  ->orWhere('conta', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de contas bancarias do tenant
     *
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('contas_bancarias');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('banco', 'LIKE', $searchTerm)
                  ->orWhere('agencia', 'LIKE', $searchTerm)
                  ->orWhere('conta', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca uma conta bancaria por ID
     *
     * @param int $id ID da conta
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('contas_bancarias')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria uma nova conta bancaria
     *
     * @param array $dados Dados da conta
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('contas_bancarias')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'e_conta_bancaria' => $dados['e_conta_bancaria'] ?? 'S',
                'status' => $dados['status'] ?? 'A',
                'banco' => $dados['banco'] ?? null,
                'agencia' => $dados['agencia'] ?? null,
                'conta' => $dados['conta'] ?? null,
                'obs' => $dados['obs'] ?? null,
            ]);
    }

    /**
     * Atualiza uma conta bancaria existente
     *
     * @param int $id ID da conta
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new \InvalidArgumentException('Conta bancaria nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['e_conta_bancaria'])) {
            $dadosUpdate['e_conta_bancaria'] = $dados['e_conta_bancaria'];
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('banco', $dados)) {
            $dadosUpdate['banco'] = $dados['banco'] ?: null;
        }
        if (array_key_exists('agencia', $dados)) {
            $dadosUpdate['agencia'] = $dados['agencia'] ?: null;
        }
        if (array_key_exists('conta', $dados)) {
            $dadosUpdate['conta'] = $dados['conta'] ?: null;
        }
        if (array_key_exists('obs', $dados)) {
            $dadosUpdate['obs'] = $dados['obs'] ?: null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('contas_bancarias')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma conta bancaria
     *
     * @param int $id ID da conta
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new \InvalidArgumentException('Conta bancaria nao encontrada');
        }

        return $this->qb
            ->table('contas_bancarias')
            ->where('id', '=', $id)
            ->delete();
    }
}
