<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model Oficina
 *
 * Gerencia oficinas mecanicas para manutencoes de veiculos.
 */
class Oficina extends Model
{
    use Auditable;

    /**
     * Retorna o nome da entidade para auditoria
     */
    public function getEntidadeAuditoria(): string
    {
        return 'Oficina';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    public function getCampoIdentificador(): string
    {
        return 'empresa';
    }

    /**
     * Lista todas as oficinas do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Lista de oficinas
     */
    public function listar(string $chave): array
    {
        return $this->qb
            ->table('oficinas')
            ->orderBy('empresa', 'ASC')
            ->get();
    }

    /**
     * Lista oficinas do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de oficinas
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb->table('oficinas');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('empresa', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm)
                  ->orWhere('telefone', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('empresa', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de oficinas do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = ''): int
    {
        $query = $this->qb->table('oficinas');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('empresa', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm)
                  ->orWhere('telefone', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca uma oficina por ID
     *
     * @param int $id ID da oficina
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('oficinas')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Lista oficinas para select (busca server-side)
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarParaSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('oficinas')
            ->select(['id', 'empresa']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('empresa', 'LIKE', $searchTerm);
        }

        return $query
            ->orderBy('empresa', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Cria uma nova oficina
     *
     * @param array $dados Dados da oficina
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('oficinas')
            ->insert([
                'chave' => $dados['chave'],
                'empresa' => $dados['empresa'] ?? null,
                'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'contrato' => $dados['contrato'] ?? null,
                'obs' => $dados['obs'] ?? null,
            ]);
    }

    /**
     * Atualiza uma oficina existente
     *
     * @param int $id ID da oficina
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $oficina = $this->buscarPorId($id);
        if (!$oficina) {
            throw new \InvalidArgumentException('Oficina nao encontrada');
        }

        $dadosUpdate = [];

        $campos = ['empresa', 'email', 'telefone', 'contrato', 'obs'];

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo] ?: null;
            }
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = now();

        return $this->qb
            ->table('oficinas')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma oficina
     *
     * @param int $id ID da oficina
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $oficina = $this->buscarPorId($id);
        if (!$oficina) {
            throw new \InvalidArgumentException('Oficina nao encontrada');
        }

        return $this->qb
            ->table('oficinas')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se oficina tem vinculos que impedem exclusao
     *
     * @param int $id ID da oficina
     * @return array Lista de vinculos encontrados
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        // Verificar manutencoes
        $manutencoes = $this->qb
            ->table('manutencoes')
            ->where('id_oficina', '=', $id)
            ->count();

        if ($manutencoes > 0) {
            $vinculos[] = "{$manutencoes} manutencao(oes) vinculada(s)";
        }

        return $vinculos;
    }
}
