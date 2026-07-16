<?php

namespace App\Models;

use App\Core\Auth;

/**
 * Model Promocao
 *
 * Gerencia promocoes disponiveis para locacoes.
 * Suporta relacionamento N:N com filiais atraves da tabela promocoes_filiais.
 */
class Promocao extends Model
{
    /**
     * Tipos de promocao
     */
    public const TIPOS = [
        'DFIX' => 'Fixo (R$)',
        'DPOR' => 'Porcentagem (%)'
    ];

    /**
     * Status de promocao
     */
    public const STATUS = [
        'A' => 'Ativo',
        'D' => 'Desativado'
    ];

    /**
     * Onde exibir
     */
    public const ONDE_EXIBIR = [
        'SIS' => 'Sistema',
        'SITE' => 'Site',
        'APP' => 'App'
    ];

    /**
     * Lista promocoes com paginacao, busca e filtro de filiais
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array Lista de promocoes
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('promocoes', 'p')
            ->selectRaw('p.*, GROUP_CONCAT(DISTINCT mf.nome_fantasia ORDER BY mf.nome_fantasia SEPARATOR ", ") as filiais_nomes')
            ->leftJoin('promocoes_filiais', 'pf', 'p.id', '=', 'pf.id_promocao')
            ->leftJoin('matrizes_filiais', 'mf', 'pf.id_matriz_filial', '=', 'mf.id')
            ->groupBy('p.id');

        // Filtro de filiais (N:N)
        if (!empty($filialWhere)) {
            $filialWhereAjustado = str_replace('id_matriz_filial', 'pf.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWhereAjustado, $filialParams);
        }

        // Busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereRaw('(p.nome LIKE ? OR p.codigo LIKE ?)', [$searchTerm, $searchTerm]);
        }

        return $query
            ->orderBy('p.nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de promocoes com filtros
     *
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return int Total de registros
     */
    public function contar(
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): int {
        $query = $this->qb
            ->table('promocoes', 'p');

        // Filtro de filiais (N:N)
        if (!empty($filialWhere)) {
            $query->join('promocoes_filiais', 'pf', 'p.id', '=', 'pf.id_promocao');
            $filialWhereAjustado = str_replace('id_matriz_filial', 'pf.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWhereAjustado, $filialParams);
        }

        // Busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereRaw('(p.nome LIKE ? OR p.codigo LIKE ?)', [$searchTerm, $searchTerm]);
        }

        // Usar COUNT DISTINCT para evitar contagem duplicada com JOIN
        return $query->selectRaw('COUNT(DISTINCT p.id) as total')->first()['total'] ?? 0;
    }

    /**
     * Busca uma promocao por ID com suas filiais vinculadas
     *
     * @param int $id ID da promocao
     * @return array|null Dados da promocao ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $promocao = $this->qb
            ->table('promocoes')
            ->where('id', '=', $id)
            ->first();

        if ($promocao) {
            $promocao['filiais'] = $this->listarFiliaisDaPromocao($id);
            $promocao['grupos'] = $this->listarGruposDaPromocao($id);
        }

        return $promocao;
    }

    /**
     * Busca um codigo no tenant atual e traz o vinculo/valor da filial informada.
     */
    public function buscarPorCodigoComFilial(string $codigo, int $filialId, int $grupoId = 0): ?array
    {
        return $this->qb
            ->table('promocoes', 'p')
            ->select([
                'p.id', 'p.codigo', 'p.nome', 'p.validade', 'p.dias', 'p.valor',
                'p.tipo', 'p.onde_exibir', 'p.status', 'p.todos_grupos',
                'CASE WHEN pf.id IS NULL THEN 0 ELSE 1 END AS filial_vinculada',
                'pvf.valor AS valor_filial',
                'CASE WHEN gp.id IS NULL THEN 0 ELSE 1 END AS grupo_vinculado',
            ])
            ->leftJoinRaw(
                'promocoes_filiais',
                'pf',
                'pf.id_promocao = p.id AND pf.id_matriz_filial = ' . $filialId . ' AND pf.chave = p.chave'
            )
            ->leftJoinRaw(
                'promocoes_valores_filiais',
                'pvf',
                'pvf.id_promocao = p.id AND pvf.id_matriz_filial = ' . $filialId . ' AND pvf.chave = p.chave'
            )
            ->leftJoinRaw(
                'promocoes_grupos',
                'pg',
                'pg.id_promocao = p.id AND pg.id_grupo = ' . max(0, $grupoId) . ' AND pg.chave = p.chave'
            )
            ->leftJoinRaw(
                'grupos',
                'gp',
                'gp.id = pg.id_grupo AND gp.chave = p.chave'
            )
            ->where('p.codigo', '=', $codigo)
            ->first();
    }

    /**
     * Lista filiais vinculadas a uma promocao
     *
     * @param int $promocaoId ID da promocao
     * @return array Lista de IDs das filiais
     */
    public function listarFiliaisDaPromocao(int $promocaoId): array
    {
        $resultados = $this->qb
            ->table('promocoes_filiais', 'pf')
            ->select(['pf.id_matriz_filial', 'mf.nome_fantasia'])
            ->leftJoin('matrizes_filiais', 'mf', 'pf.id_matriz_filial', '=', 'mf.id')
            ->where('pf.id_promocao', '=', $promocaoId)
            ->orderBy('mf.nome_fantasia', 'ASC')
            ->get();

        return array_map(fn($r) => [
            'id' => (int) $r['id_matriz_filial'],
            'nome' => $r['nome_fantasia']
        ], $resultados);
    }

    public function listarGruposDaPromocao(int $promocaoId): array
    {
        $resultados = $this->qb
            ->table('promocoes_grupos', 'pg')
            ->select(['pg.id_grupo', 'g.nome'])
            ->leftJoin('grupos', 'g', 'pg.id_grupo', '=', 'g.id')
            ->where('pg.id_promocao', '=', $promocaoId)
            ->orderBy('g.nome', 'ASC')
            ->get();

        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id_grupo'],
            'nome' => (string) ($row['nome'] ?? ''),
        ], $resultados);
    }

    /**
     * Cria uma nova promocao
     *
     * @param array $dados Dados da promocao
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('promocoes')
            ->insert([
                'chave' => $dados['chave'],
                'codigo' => \App\Services\PromocaoAplicacaoService::normalizarCodigo($dados['codigo']),
                'nome' => trim((string) $dados['nome']),
                'validade' => !empty($dados['validade']) ? $dados['validade'] : null,
                'dias' => (int) ($dados['dias'] ?? 0),
                'valor' => currency_parse($dados['valor'] ?? 0),
                'tipo' => $dados['tipo'] ?? 'DFIX',
                'onde_exibir' => $dados['onde_exibir'] ?? 'SIS',
                'status' => $dados['status'] ?? 'A',
                'todos_grupos' => !empty($dados['todos_grupos']) ? 1 : 0,
            ]);
    }

    /**
     * Atualiza uma promocao existente
     *
     * @param int $id ID da promocao
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $promocao = $this->buscarPorId($id);
        if (!$promocao) {
            throw new \InvalidArgumentException('Promocao nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['codigo'])) {
            $dadosUpdate['codigo'] = \App\Services\PromocaoAplicacaoService::normalizarCodigo($dados['codigo']);
        }
        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = trim((string) $dados['nome']);
        }
        if (isset($dados['validade'])) {
            $dadosUpdate['validade'] = $dados['validade'] ?: null;
        }
        if (isset($dados['dias'])) {
            $dadosUpdate['dias'] = (int) $dados['dias'];
        }
        if (array_key_exists('valor', $dados)) {
            $dadosUpdate['valor'] = currency_parse($dados['valor'] ?? 0);
        }
        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = $dados['tipo'];
        }
        if (isset($dados['onde_exibir'])) {
            $dadosUpdate['onde_exibir'] = $dados['onde_exibir'];
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('todos_grupos', $dados)) {
            $dadosUpdate['todos_grupos'] = !empty($dados['todos_grupos']) ? 1 : 0;
        }

        if (!empty($dadosUpdate)) {
            $dadosUpdate['updated_at'] = now();
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('promocoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma promocao
     *
     * @param int $id ID da promocao
     * @return int Linhas afetadas
     * @throws \InvalidArgumentException Se promocao nao encontrada
     */
    public function excluir(int $id): int
    {
        $promocao = $this->buscarPorId($id);
        if (!$promocao) {
            throw new \InvalidArgumentException('Promocao nao encontrada');
        }

        // Remover relacoes N:N (CASCADE deve cuidar disso, mas por seguranca)
        $this->qb
            ->table('promocoes_filiais')
            ->where('id_promocao', '=', $id)
            ->delete();
        $this->qb
            ->table('promocoes_grupos')
            ->where('id_promocao', '=', $id)
            ->delete();

        return $this->qb
            ->table('promocoes')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Sincroniza filiais de uma promocao (remove antigas e adiciona novas)
     *
     * @param int $promocaoId ID da promocao
     * @param array $filiaisIds Lista de IDs de filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $promocaoId, array $filiaisIds, string $chave): void
    {
        // Remover todas as filiais existentes
        $this->qb
            ->table('promocoes_filiais')
            ->where('id_promocao', '=', $promocaoId)
            ->delete();

        // Inserir novas filiais
        foreach ($filiaisIds as $filialId) {
            if (empty($filialId)) continue;

            $this->qb
                ->table('promocoes_filiais')
                ->insert([
                    'id_promocao' => $promocaoId,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave,
                ]);
        }
    }

    public function sincronizarGrupos(int $promocaoId, array $gruposIds, string $chave): void
    {
        $this->qb
            ->table('promocoes_grupos')
            ->where('id_promocao', '=', $promocaoId)
            ->delete();

        foreach ($gruposIds as $grupoId) {
            if ((int) $grupoId <= 0) continue;
            $this->qb
                ->table('promocoes_grupos')
                ->insert([
                    'chave' => $chave,
                    'id_promocao' => $promocaoId,
                    'id_grupo' => (int) $grupoId,
                ]);
        }
    }

    /**
     * Executa operacoes relacionadas a promocao na conexao Singleton.
     */
    public function executarEmTransacao(callable $callback): mixed
    {
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();
        try {
            $resultado = $callback();
            $mysqli->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Lista promocoes para select
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param int|null $filialId ID da filial (opcional)
     * @return array Lista de promocoes
     */
    public function listarParaSelect(string $chave, string $search = '', ?int $filialId = null): array
    {
        $query = $this->qb
            ->table('promocoes', 'p')
            ->select(['p.id', 'p.codigo', 'p.nome', 'p.valor', 'p.tipo', 'p.dias'])
            ->where('p.status', '=', 'A');

        // Filtrar por filial se especificado
        if ($filialId) {
            $query->join('promocoes_filiais', 'pf', 'p.id', '=', 'pf.id_promocao')
                  ->where('pf.id_matriz_filial', '=', $filialId);
        }

        if (!empty($search)) {
            $query->whereRaw('(p.nome LIKE ? OR p.codigo LIKE ?)', ['%' . $search . '%', '%' . $search . '%']);
        }

        return $query
            ->orderBy('p.nome', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Verifica se codigo ja existe
     *
     * @param string $codigo Codigo a verificar
     * @param int|null $excluirId ID a excluir da verificacao (para edicao)
     * @return bool True se codigo ja existe
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $codigo = \App\Services\PromocaoAplicacaoService::normalizarCodigo($codigo);
        $query = $this->qb
            ->table('promocoes')
            ->selectRaw('COUNT(*) as total')
            ->where('codigo', '=', $codigo);

        if ($excluirId) {
            $query->where('id', '!=', $excluirId);
        }

        return ($query->first()['total'] ?? 0) > 0;
    }

    /**
     * Busca promocoes para autocomplete
     *
     * @param string $termo Termo de busca
     * @return array Lista de promocoes
     */
    public function buscar(string $termo): array
    {
        return $this->qb
            ->table('promocoes')
            ->select(['id', 'codigo', 'nome', 'valor', 'tipo'])
            ->where('status', '=', 'A')
            ->whereRaw('(nome LIKE ? OR codigo LIKE ?)', ['%' . $termo . '%', '%' . $termo . '%'])
            ->orderBy('nome', 'ASC')
            ->limit(20)
            ->get();
    }

}
