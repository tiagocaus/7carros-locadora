<?php

namespace App\Models;

/**
 * Model VeiculoAcessorio
 *
 * Gerencia acessorios de veiculos.
 */
class VeiculoAcessorio extends Model
{
    /**
     * Lista todos os acessorios do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Lista de acessorios
     */
    public function listar(string $chave): array
    {
        return $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista acessorios do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de acessorios
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('chave', '=', $chave);

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de acessorios do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = ''): int
    {
        $query = $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('chave', '=', $chave);

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        return $query->count();
    }

    /**
     * Busca um acessorio por ID
     *
     * @param int $id ID do acessorio
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo acessorio
     *
     * @param array $dados Dados do acessorio
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('veiculos_acessorios')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
            ]);
    }

    /**
     * Atualiza um acessorio existente
     *
     * @param int $id ID do acessorio
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um acessorio
     *
     * @param int $id ID do acessorio
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('veiculos_acessorios')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete();
    }
}
