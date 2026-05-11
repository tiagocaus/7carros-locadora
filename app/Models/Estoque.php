<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model Estoque
 *
 * Gerencia produtos do estoque da locadora
 */
class Estoque extends Model
{
    use Auditable;

    /**
     * Retorna o nome da entidade para auditoria
     */
    public function getEntidadeAuditoria(): string
    {
        return 'Estoque';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    public function getCampoIdentificador(): string
    {
        return 'produto_nome';
    }

    /**
     * Lista produtos do estoque com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param int|null $idFilial Filtro por filial (opcional)
     * @return array Lista de produtos
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = '', ?int $idFilial = null, ?string $status = null): array
    {
        $query = $this->qb
            ->table('estoque', 'e')
            ->select([
                'e.*',
                'mf.nome_fantasia AS filial_nome',
                'f.nome_rsocial AS fornecedor_nome'
            ])
            ->leftJoin('matrizes_filiais', 'mf', 'e.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('fornecedores', 'f', 'e.id_fornecedor', '=', 'f.id');

        if ($idFilial) {
            $query->where('e.id_matriz_filial', '=', $idFilial);
        }

        if ($status !== null && $status !== '') {
            $query->where('e.status', '=', $status);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('e.produto_codigo', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_nome', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_marca', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_modelo', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('e.produto_nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de produtos do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @param int|null $idFilial Filtro por filial (opcional)
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = '', ?int $idFilial = null, ?string $status = null): int
    {
        $query = $this->qb->table('estoque');

        if ($idFilial) {
            $query->where('id_matriz_filial', '=', $idFilial);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', '=', $status);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('produto_codigo', 'LIKE', $searchTerm)
                  ->orWhere('produto_nome', 'LIKE', $searchTerm)
                  ->orWhere('produto_marca', 'LIKE', $searchTerm)
                  ->orWhere('produto_modelo', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Lista produtos ativos para selects server-side.
     *
     * @param string $search Termo de busca (opcional)
     * @return array Lista de produtos
     */
    public function listarParaSelect(string $search = ''): array
    {
        $query = $this->qb
            ->table('estoque', 'e')
            ->select([
                'e.id',
                'e.produto_codigo',
                'e.produto_nome',
                'e.produto_unidade',
                'e.valor_venda',
                'e.produto_estoque_atual',
                'e.baixa_automatica',
                'e.permitir_estoque_negativo',
            ])
            ->where('e.status', '=', 'A');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('e.produto_codigo', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_nome', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_marca', 'LIKE', $searchTerm)
                  ->orWhere('e.produto_modelo', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('e.produto_nome', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Busca um produto por ID
     *
     * @param int $id ID do produto
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('estoque', 'e')
            ->select([
                'e.*',
                'mf.nome_fantasia AS filial_nome',
                'f.nome_rsocial AS fornecedor_nome'
            ])
            ->leftJoin('matrizes_filiais', 'mf', 'e.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('fornecedores', 'f', 'e.id_fornecedor', '=', 'f.id')
            ->where('e.id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo produto no estoque
     *
     * @param array $dados Dados do produto
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('estoque')
            ->insert([
                'chave' => $dados['chave'],
                'id_matriz_filial' => $dados['id_matriz_filial'] ?? null,
                'id_fornecedor' => $dados['id_fornecedor'] ?? null,
                'produto_codigo' => $dados['produto_codigo'],
                'produto_nome' => $dados['produto_nome'],
                'produto_local' => $dados['produto_local'] ?? null,
                'produto_marca' => $dados['produto_marca'],
                'produto_modelo' => $dados['produto_modelo'],
                'produto_unidade' => $dados['produto_unidade'],
                'produto_estoque_atual' => (int) ($dados['produto_estoque_atual'] ?? 0),
                'produto_estoque_minimo' => (int) ($dados['produto_estoque_minimo'] ?? 0),
                'valor_compra' => currency_parse($dados['valor_compra'] ?? '0'),
                'valor_venda' => currency_parse($dados['valor_venda'] ?? '0'),
                'status' => 'A',
                'baixa_automatica' => $dados['baixa_automatica'] ?? 'N',
                'permitir_estoque_negativo' => $dados['permitir_estoque_negativo'] ?? 'N',
            ]);
    }

    /**
     * Atualiza um produto existente
     *
     * @param int $id ID do produto
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $produto = $this->buscarPorId($id);
        if (!$produto) {
            throw new \InvalidArgumentException('Produto nao encontrado');
        }

        $dadosUpdate = [];

        // Campos texto
        $camposTexto = [
            'produto_codigo', 'produto_nome', 'produto_local',
            'produto_marca', 'produto_modelo', 'produto_unidade', 'status',
            'baixa_automatica', 'permitir_estoque_negativo'
        ];

        foreach ($camposTexto as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        // Campos inteiros
        if (array_key_exists('id_matriz_filial', $dados)) {
            $dadosUpdate['id_matriz_filial'] = $dados['id_matriz_filial'] ?: null;
        }
        if (array_key_exists('id_fornecedor', $dados)) {
            $dadosUpdate['id_fornecedor'] = $dados['id_fornecedor'] ?: null;
        }
        if (array_key_exists('produto_estoque_atual', $dados)) {
            $dadosUpdate['produto_estoque_atual'] = (int) $dados['produto_estoque_atual'];
        }
        if (array_key_exists('produto_estoque_minimo', $dados)) {
            $dadosUpdate['produto_estoque_minimo'] = (int) $dados['produto_estoque_minimo'];
        }

        // Campos decimais
        if (array_key_exists('valor_compra', $dados)) {
            $dadosUpdate['valor_compra'] = currency_parse($dados['valor_compra']);
        }
        if (array_key_exists('valor_venda', $dados)) {
            $dadosUpdate['valor_venda'] = currency_parse($dados['valor_venda']);
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('estoque')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um produto do estoque
     *
     * @param int $id ID do produto
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $produto = $this->buscarPorId($id);
        if (!$produto) {
            throw new \InvalidArgumentException('Produto nao encontrado');
        }

        return $this->qb
            ->table('estoque')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se produto tem vinculos que impedem exclusao
     *
     * @param int $id ID do produto
     * @return array Lista de vinculos encontrados
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        $count = $this->qb
            ->table('manutencoes_itens')
            ->where('id_estoque', '=', $id)
            ->count();

        if ($count > 0) {
            $vinculos[] = 'Produto vinculado a ' . $count . ' item(ns) de manutencao';
        }

        return $vinculos;
    }

    /**
     * Inativa um produto (status = I)
     *
     * @param int $id ID do produto
     * @return int Linhas afetadas
     */
    public function inativar(int $id): int
    {
        return $this->qb
            ->table('estoque')
            ->where('id', '=', $id)
            ->update([
                'status' => 'I',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Reativa um produto (status = A)
     *
     * @param int $id ID do produto
     * @return int Linhas afetadas
     */
    public function reativar(int $id): int
    {
        return $this->qb
            ->table('estoque')
            ->where('id', '=', $id)
            ->update([
                'status' => 'A',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

}
