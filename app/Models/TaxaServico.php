<?php

namespace App\Models;

use App\Core\Auth;

/**
 * Model TaxaServico
 *
 * Gerencia taxas e servicos disponiveis para locacoes.
 * Suporta relacionamento N:N com filiais atraves da tabela taxaseservicos_filiais.
 */
class TaxaServico extends Model
{
    /**
     * Lista taxas com paginacao, busca e filtro de filiais
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array Lista de taxas
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('taxaseservicos', 't')
            ->selectRaw("
                t.id,
                t.chave,
                t.id_matriz_filial,
                t.nome,
                t.base_calculo,
                t.tipo_valor,
                CASE
                    WHEN t.tipo_valor = 'MON'
                    THEN COALESCE(
                        CAST(
                            SUBSTRING_INDEX(
                                GROUP_CONCAT(
                                    CASE WHEN tsvf.valor > 0 THEN tsvf.valor END
                                    ORDER BY tsvf.id_matriz_filial
                                    SEPARATOR ','
                                ),
                                ',',
                                1
                            ) AS DECIMAL(10,2)
                        ),
                        t.valor
                    )
                    ELSE t.valor
                END AS valor,
                t.aplicar,
                t.onde_usar,
                t.created_at,
                t.updated_at,
                GROUP_CONCAT(DISTINCT mf.nome_fantasia ORDER BY mf.nome_fantasia SEPARATOR ', ') as filiais_nomes
            ")
            ->leftJoin('taxaseservicos_filiais', 'tsf', 't.id', '=', 'tsf.id_taxaservico')
            ->leftJoin('matrizes_filiais', 'mf', 'tsf.id_matriz_filial', '=', 'mf.id')
            ->leftJoinRaw(
                'taxaseservicos_valores_filiais',
                'tsvf',
                'tsvf.id_taxaservico = t.id AND tsvf.id_matriz_filial = tsf.id_matriz_filial AND tsvf.chave = t.chave'
            )
            ->groupBy('t.id');

        // Filtro de filiais (N:N)
        if (!empty($filialWhere)) {
            // Substituir o campo para usar a tabela de relacionamento
            $filialWhereAjustado = str_replace('id_matriz_filial', 'tsf.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWhereAjustado, $filialParams);
        }

        // Busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('t.nome', 'LIKE', $searchTerm);
        }

        return $query
            ->orderBy('t.nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de taxas com filtros
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
            ->table('taxaseservicos', 't');

        // Filtro de filiais (N:N)
        if (!empty($filialWhere)) {
            $query->join('taxaseservicos_filiais', 'tsf', 't.id', '=', 'tsf.id_taxaservico');
            $filialWhereAjustado = str_replace('id_matriz_filial', 'tsf.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWhereAjustado, $filialParams);
        }

        // Busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('t.nome', 'LIKE', $searchTerm);
        }

        // Usar COUNT DISTINCT para evitar contagem duplicada com JOIN
        return $query->selectRaw('COUNT(DISTINCT t.id) as total')->first()['total'] ?? 0;
    }

    /**
     * Busca uma taxa por ID com suas filiais vinculadas
     *
     * @param int $id ID da taxa
     * @return array|null Dados da taxa ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $taxa = $this->qb
            ->table('taxaseservicos')
            ->where('id', '=', $id)
            ->first();

        if ($taxa) {
            $taxa['filiais'] = $this->listarFiliaisDaTaxa($id);
            // Mapa id_filial => valor para UI (so relevante se tipo_valor=MON)
            $mapaValores = [];
            foreach ((new TaxaServicoValorFilial())->listarPorTaxa($id) as $r) {
                $mapaValores[(int) $r['id_matriz_filial']] = (float) $r['valor'];
            }
            $taxa['valores_filiais'] = $mapaValores;
        }

        return $taxa;
    }

    /**
     * Resolve o valor de uma taxa para uma filial:
     * - tipo_valor=MON e tem entry em taxaseservicos_valores_filiais -> valor da filial
     * - caso contrario (POR ou sem entry) -> taxaseservicos.valor
     */
    public function resolverValor(array $taxa, ?int $filialId): float
    {
        if (!$filialId || ($taxa['tipo_valor'] ?? '') !== 'MON') {
            return (float) ($taxa['valor'] ?? 0);
        }
        $entry = (new TaxaServicoValorFilial())->buscarPorTaxaFilial((int) $taxa['id'], $filialId);
        if ($entry) {
            return (float) $entry['valor'];
        }
        return (float) ($taxa['valor'] ?? 0);
    }

    /**
     * Lista filiais vinculadas a uma taxa
     *
     * @param int $taxaId ID da taxa
     * @return array Lista de IDs das filiais
     */
    public function listarFiliaisDaTaxa(int $taxaId): array
    {
        $resultados = $this->qb
            ->table('taxaseservicos_filiais', 'tsf')
            ->select(['tsf.id_matriz_filial', 'mf.nome_fantasia'])
            ->leftJoin('matrizes_filiais', 'mf', 'tsf.id_matriz_filial', '=', 'mf.id')
            ->where('tsf.id_taxaservico', '=', $taxaId)
            ->orderBy('mf.nome_fantasia', 'ASC')
            ->get();

        return array_map(fn($r) => [
            'id' => (int) $r['id_matriz_filial'],
            'nome' => $r['nome_fantasia']
        ], $resultados);
    }

    /**
     * Cria uma nova taxa
     *
     * @param array $dados Dados da taxa
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('taxaseservicos')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'base_calculo' => $dados['base_calculo'] ?? 'FIX',
                'tipo_valor' => $dados['tipo_valor'] ?? 'MON',
                'valor' => currency_parse($dados['valor'] ?? 0),
                'aplicar' => $dados['aplicar'] ?? 'N',
                'onde_usar' => $dados['onde_usar'] ?? 'SIS',
            ]);
    }

    /**
     * Atualiza uma taxa existente
     *
     * @param int $id ID da taxa
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $taxa = $this->buscarPorId($id);
        if (!$taxa) {
            throw new \InvalidArgumentException('Taxa/servico nao encontrado');
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['base_calculo'])) {
            $dadosUpdate['base_calculo'] = $dados['base_calculo'];
        }
        if (isset($dados['tipo_valor'])) {
            $dadosUpdate['tipo_valor'] = $dados['tipo_valor'];
        }
        if (array_key_exists('valor', $dados)) {
            $dadosUpdate['valor'] = currency_parse($dados['valor'] ?? 0);
        }
        if (isset($dados['aplicar'])) {
            $dadosUpdate['aplicar'] = $dados['aplicar'];
        }
        if (isset($dados['onde_usar'])) {
            $dadosUpdate['onde_usar'] = $dados['onde_usar'];
        }

        if (!empty($dadosUpdate)) {
            $dadosUpdate['updated_at'] = now();
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('taxaseservicos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma taxa (verifica vinculos em contratos)
     *
     * @param int $id ID da taxa
     * @return int Linhas afetadas
     * @throws \InvalidArgumentException Se taxa nao encontrada ou tem vinculos
     */
    public function excluir(int $id): int
    {
        $taxa = $this->buscarPorId($id);
        if (!$taxa) {
            throw new \InvalidArgumentException('Taxa/servico nao encontrado');
        }

        // Verificar vinculos em contratos
        $vinculos = $this->verificarVinculos($id);
        if ($vinculos['temVinculos']) {
            throw new \InvalidArgumentException(
                'Nao e possivel excluir: esta taxa esta vinculada a ' .
                $vinculos['totalContratos'] . ' contrato(s)'
            );
        }

        // Remover relacoes N:N (CASCADE deve cuidar disso, mas por seguranca)
        $this->qb
            ->table('taxaseservicos_filiais')
            ->where('id_taxaservico', '=', $id)
            ->delete();

        return $this->qb
            ->table('taxaseservicos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se a taxa tem vinculos em contratos
     *
     * @param int $id ID da taxa
     * @return array Informacoes sobre vinculos
     */
    public function verificarVinculos(int $id): array
    {
        $totalContratos = $this->qb
            ->table('contratos_taxaseservicos')
            ->selectRaw('COUNT(*) as total')
            ->where('id_taxa', '=', $id)
            ->first()['total'] ?? 0;

        return [
            'temVinculos' => $totalContratos > 0,
            'totalContratos' => (int) $totalContratos,
        ];
    }

    /**
     * Sincroniza filiais de uma taxa (remove antigas e adiciona novas)
     *
     * @param int $taxaId ID da taxa
     * @param array $filiaisIds Lista de IDs de filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $taxaId, array $filiaisIds, string $chave): void
    {
        // Remover todas as filiais existentes
        $this->qb
            ->table('taxaseservicos_filiais')
            ->where('id_taxaservico', '=', $taxaId)
            ->delete();

        // Inserir novas filiais
        foreach ($filiaisIds as $filialId) {
            if (empty($filialId)) continue;

            $this->qb
                ->table('taxaseservicos_filiais')
                ->insert([
                    'id_taxaservico' => $taxaId,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave,
                ]);
        }
    }

    /**
     * Lista taxas para select (usada em contratos)
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param int|null $filialId ID da filial (opcional)
     * @return array Lista de taxas
     */
    public function listarParaSelect(string $chave, string $search = '', ?int $filialId = null): array
    {
        $query = $this->qb
            ->table('taxaseservicos', 't')
            ->selectRaw("t.id, t.nome, t.base_calculo, t.tipo_valor,
                CASE WHEN t.tipo_valor = 'MON' AND tsvf.valor IS NOT NULL
                     THEN tsvf.valor ELSE t.valor END AS valor");

        // Filtrar por filial e resolver valor da filial quando tipo_valor=MON
        if ($filialId) {
            $query->join('taxaseservicos_filiais', 'tsf', 't.id', '=', 'tsf.id_taxaservico')
                  ->leftJoinRaw('taxaseservicos_valores_filiais', 'tsvf',
                      "tsvf.id_taxaservico = t.id AND tsvf.id_matriz_filial = " . (int) $filialId)
                  ->where('tsf.id_matriz_filial', '=', $filialId);
        } else {
            $query->leftJoinRaw('taxaseservicos_valores_filiais', 'tsvf', '1 = 0');
        }

        if (!empty($search)) {
            $query->where('t.nome', 'LIKE', '%' . $search . '%');
        }

        return $query
            ->orderBy('t.nome', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista servicos publicados no website com as filiais permitidas.
     *
     * Registros sem vinculo em taxaseservicos_filiais nao sao publicados: no
     * cadastro de taxas, nenhuma filial selecionada nao representa todas.
     */
    public function listarParaWebsite(): array
    {
        $resultados = $this->qb
            ->table('taxaseservicos', 't')
            ->selectRaw("t.id, t.nome, t.valor, t.tipo_valor, t.base_calculo, t.aplicar,
                GROUP_CONCAT(DISTINCT tsf.id_matriz_filial ORDER BY tsf.id_matriz_filial SEPARATOR ',') AS filiais_ids")
            ->joinRaw(
                'taxaseservicos_filiais',
                'tsf',
                'tsf.id_taxaservico = t.id AND tsf.chave = t.chave'
            )
            ->whereRaw("FIND_IN_SET('SITE', t.onde_usar)")
            ->groupBy('t.id')
            ->orderBy('t.nome', 'ASC')
            ->get();

        foreach ($resultados as &$taxa) {
            $taxa['valor'] = (float) ($taxa['valor'] ?? 0);
            $taxa['aplicar'] = ($taxa['aplicar'] ?? 'N') === 'S' ? 'S' : 'N';
            $taxa['filiais_ids'] = array_values(array_filter(
                array_map('intval', explode(',', (string) ($taxa['filiais_ids'] ?? ''))),
                static fn(int $id): bool => $id > 0
            ));
        }
        unset($taxa);

        return $resultados;
    }

    /**
     * Lista servicos validos para uma reserva do website na filial informada.
     * O valor retornado ja e o valor oficial da filial para taxas monetarias.
     */
    public function listarParaWebsitePorFilial(int $filialId): array
    {
        if ($filialId <= 0) {
            return [];
        }

        return $this->qb
            ->table('taxaseservicos', 't')
            ->selectRaw("t.id, t.nome, t.tipo_valor, t.base_calculo, t.aplicar,
                CASE WHEN t.tipo_valor = 'MON' AND tsvf.valor IS NOT NULL
                     THEN tsvf.valor ELSE t.valor END AS valor")
            ->joinRaw(
                'taxaseservicos_filiais',
                'tsf',
                'tsf.id_taxaservico = t.id AND tsf.chave = t.chave'
            )
            ->leftJoinRaw(
                'taxaseservicos_valores_filiais',
                'tsvf',
                'tsvf.id_taxaservico = t.id'
                    . ' AND tsvf.id_matriz_filial = tsf.id_matriz_filial'
                    . ' AND tsvf.chave = t.chave'
            )
            ->where('tsf.id_matriz_filial', '=', $filialId)
            ->whereRaw("FIND_IN_SET('SITE', t.onde_usar)")
            ->orderBy('t.nome', 'ASC')
            ->get();
    }

    /**
     * Lista taxas com aplicar='S' e onde_usar contendo 'SIS' para auto-adicionar
     *
     * @param string $chave Chave do tenant
     * @param int|null $filialId ID da filial (opcional)
     * @return array Lista de taxas
     */
    public function listarAutoAplicar(string $chave, ?int $filialId = null): array
    {
        $query = $this->qb
            ->table('taxaseservicos', 't')
            ->selectRaw("t.id, t.nome, t.base_calculo, t.tipo_valor,
                CASE WHEN t.tipo_valor = 'MON' AND tsvf.valor IS NOT NULL
                     THEN tsvf.valor ELSE t.valor END AS valor")
            ->where('t.aplicar', '=', 'S')
            ->whereRaw("FIND_IN_SET('SIS', t.onde_usar)");

        // Filtrar por filial e resolver valor da filial quando tipo_valor=MON
        if ($filialId) {
            $query->join('taxaseservicos_filiais', 'tsf', 't.id', '=', 'tsf.id_taxaservico')
                  ->leftJoinRaw('taxaseservicos_valores_filiais', 'tsvf',
                      "tsvf.id_taxaservico = t.id AND tsvf.id_matriz_filial = " . (int) $filialId)
                  ->where('tsf.id_matriz_filial', '=', $filialId);
        } else {
            $query->leftJoinRaw('taxaseservicos_valores_filiais', 'tsvf', '1 = 0');
        }

        return $query
            ->orderBy('t.nome', 'ASC')
            ->get();
    }

}
