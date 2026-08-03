<?php

namespace App\Models;

use App\Helpers\FileHelper;

/**
 * Model Grupo
 *
 * Gerencia grupos de veiculos para precificacao.
 * Cada grupo define valores base para km pago, km controlado, km livre, seguros e tolerancia.
 */
class Grupo extends Model
{
    /**
     * Lista todos os grupos do tenant
     *
     * @return array Lista de grupos
     */
    public function listar(): array
    {
        $grupos = $this->qb
            ->table('grupos')
            ->orderBy('nome', 'ASC')
            ->get();

        // Adicionar URL da imagem
        $chave = $_SESSION['chave'] ?? '';
        foreach ($grupos as &$grupo) {
            $grupo['imagem_url'] = FileHelper::url($grupo['imagem'], $chave);
        }
        unset($grupo);

        return $grupos;
    }

    /**
     * Lista grupos do tenant para select com busca server-side
     *
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarParaSelect(string $search = ''): array
    {
        $query = $this->qb
            ->table('grupos')
            ->select(['id', 'nome']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query->orderBy('nome', 'ASC')->limit(50)->get();
    }

    /**
     * Lista todos os grupos para importacao de veiculos.
     *
     * @return array<int,array{id:int,nome:string}>
     */
    public function listarParaImportacaoVeiculos(): array
    {
        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'nome' => (string) $row['nome'],
        ], $this->qb
            ->table('grupos')
            ->select(['id', 'nome'])
            ->orderBy('nome', 'ASC')
            ->get());
    }

    /**
     * Lista grupos do tenant com paginacao e busca
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de grupos
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('grupos');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('descricao', 'LIKE', $searchTerm);
            });
        }

        $grupos = $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();

        // Adicionar URL da imagem
        $chave = $_SESSION['chave'] ?? '';
        foreach ($grupos as &$grupo) {
            $grupo['imagem_url'] = FileHelper::url($grupo['imagem'], $chave);
        }
        unset($grupo);

        return $grupos;
    }

    /**
     * Conta o total de grupos do tenant
     *
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('grupos');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('descricao', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca um grupo por ID
     *
     * @param int $id ID do grupo
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $grupo = $this->qb
            ->table('grupos')
            ->where('id', '=', $id)
            ->first();

        if (!$grupo) {
            return null;
        }

        $chave = $_SESSION['chave'] ?? '';
        $grupo['imagem_url'] = FileHelper::url($grupo['imagem'], $chave);

        return $grupo;
    }

    /**
     * Cria um novo grupo
     *
     * @param array $dados Dados do grupo
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        // Processar imagem se enviada como base64
        $imagem = null;
        if (!empty($dados['imagem_base64'])) {
            $imagem = FileHelper::save($dados['imagem_base64'], 'grupo');
        }

        $id = $this->qb
            ->table('grupos')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'] ?? null,
                'imagem' => $imagem,
                'visivel_no_site' => isset($dados['visivel_no_site']) ? (int) $dados['visivel_no_site'] : 1,
                'comissao_investidor_tipo' => $dados['comissao_investidor_tipo'] ?? null,
                'comissao_investidor_valor' => isset($dados['comissao_investidor_valor']) ? currency_parse($dados['comissao_investidor_valor']) : null,
            ]);

        // Nova arquitetura: cria linhas zeradas em grupos_precos_filiais pra
        // todas as filiais do tenant. Operador edita cada uma pela Aba 2.
        (new GrupoPrecoFilial())->garantirEntriesParaGrupo($id);

        return $id;
    }

    /**
     * Atualiza um grupo existente
     *
     * @param int $id ID do grupo
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $grupo = $this->buscarPorId($id);
        if (!$grupo) {
            throw new \InvalidArgumentException('Grupo nao encontrado');
        }

        $dadosUpdate = [];

        // Campos de texto
        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }

        // Processar imagem se enviada como base64
        $chave = $_SESSION['chave'] ?? '';
        if (!empty($dados['imagem_base64'])) {
            // Deletar imagem antiga
            if (!empty($grupo['imagem'])) {
                FileHelper::delete($grupo['imagem'], $chave);
            }
            $dadosUpdate['imagem'] = FileHelper::save($dados['imagem_base64'], 'grupo');
        }

        // Flag para remover imagem
        if (isset($dados['remover_imagem']) && $dados['remover_imagem']) {
            if (!empty($grupo['imagem'])) {
                FileHelper::delete($grupo['imagem'], $chave);
            }
            $dadosUpdate['imagem'] = null;
        }

        if (isset($dados['visivel_no_site'])) {
            $dadosUpdate['visivel_no_site'] = (int) $dados['visivel_no_site'];
        }

        // Campos de comissao investidor
        if (array_key_exists('comissao_investidor_tipo', $dados)) {
            $dadosUpdate['comissao_investidor_tipo'] = $dados['comissao_investidor_tipo'] ?: null;
        }
        if (array_key_exists('comissao_investidor_valor', $dados)) {
            $dadosUpdate['comissao_investidor_valor'] = $dados['comissao_investidor_valor']
                ? currency_parse($dados['comissao_investidor_valor'])
                : null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('grupos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um grupo
     *
     * @param int $id ID do grupo
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $grupo = $this->buscarPorId($id);
        if (!$grupo) {
            throw new \InvalidArgumentException('Grupo nao encontrado');
        }

        // Deletar imagem se existir
        if (!empty($grupo['imagem'])) {
            $chave = $_SESSION['chave'] ?? '';
            FileHelper::delete($grupo['imagem'], $chave);
        }

        // Precos por dias sao deletados automaticamente via FK CASCADE
        return $this->qb
            ->table('grupos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Lista grupos com quantidade de veiculos disponiveis
     *
     * @param int|null $filialId ID da filial para filtrar veiculos (opcional)
     * @return array Lista de grupos com qtd_disponiveis
     */
    public function listarComDisponibilidade(?int $filialId = null): array
    {
        $query = $this->qb
            ->table('grupos', 'g')
            ->select(['g.id', 'g.nome'])
            ->selectSubquery(function ($q) use ($filialId) {
                $q->table('veiculos', 'v')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('v.id_grupo = g.id')
                  ->where('v.disponibilidade', '=', 'D');
                if (!empty($filialId)) {
                    $q->where('v.id_matriz_filial', '=', $filialId);
                }
            }, 'qtd_disponiveis')
            ->orderBy('g.nome', 'ASC');

        return $query->get();
    }

    /**
     * Lista grupos com quantidade livre no periodo informado.
     *
     * @param int $filialId ID da filial
     * @param string $dataSaida Data/hora inicial (Y-m-d H:i:s)
     * @param string $dataPrevista Data/hora final (Y-m-d H:i:s)
     * @return array Lista de grupos com qtd_disponiveis
     */
    public function listarComDisponibilidadePeriodo(int $filialId, string $dataSaida, string $dataPrevista): array
    {
        $grupos = $this->qb
            ->table('grupos')
            ->select(['id', 'nome'])
            ->orderBy('nome', 'ASC')
            ->get();

        $mapaDisponibilidade = (new Veiculo())->gruposDisponiveisPorFilial($filialId, $dataSaida, $dataPrevista);

        foreach ($grupos as &$grupo) {
            $grupo['qtd_disponiveis'] = $mapaDisponibilidade[(int) $grupo['id']] ?? 0;
        }
        unset($grupo);

        return $grupos;
    }

}
