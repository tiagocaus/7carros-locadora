<?php

namespace App\Models;

use App\Helpers\FileHelper;
use App\Traits\DetectsCrossTenant;

/**
 * Model Veiculo
 *
 * Gerencia veiculos da locadora.
 */
class Veiculo extends Model
{
    use DetectsCrossTenant;

    /**
     * Status de disponibilidade considerados inativos para fins de limite do plano.
     * Veiculos com esses status nao contam no limite de veiculos do plano.
     */
    public const DISPONIBILIDADE_INATIVA = ['V', 'RO', 'E'];

    private const DISPONIBILIDADE_WHMCS = [
        'D' => 'disponiveis',
        'R' => 'reservados',
        'L' => 'locados',
        'O' => 'oficina',
        'V' => 'vendidos',
        'B' => 'batidos',
        'E' => 'excluidos',
        'RO' => 'roubados',
        'LJ' => 'lavaJato',
        'AV' => 'aVenda',
        'UI' => 'usoInterno',
    ];

    /**
     * Lista todos os veiculos do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Lista de veiculos
     */
    public function listar(string $chave): array
    {
        return $this->qb
            ->table('veiculos')
            ->orderBy('modelo', 'ASC')
            ->get();
    }

    /**
     * Lista todos os veiculos do tenant com dados de grupo e disponibilidade
     * (usado na tela de Agenda — arvore grupo -> veiculos)
     */
    public function listarParaAgenda(
        string $chave,
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.disponibilidade',
                'v.id_grupo',
                'v.id_matriz_filial',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->whereNotIn('v.disponibilidade', self::DISPONIBILIDADE_INATIVA);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query
            ->orderBy('g.nome', 'ASC')
            ->orderBy('v.modelo', 'ASC')
            ->orderBy('v.placa', 'ASC')
            ->get();
    }

    /**
     * Lista veiculos do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array Lista de veiculos
     */
    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.ano',
                'v.cor',
                'v.odometro',
                'v.disponibilidade',
                'v.id_grupo',
                'v.id_matriz_filial',
                'g.nome as grupo_nome',
                'f.nome_rsocial as fornecedor_nome',
                'mf.nome_fantasia as filial_nome'
            ])
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = v.chave')
            ->leftJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id');

        // Filtro de filial
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        // Busca por campos visiveis da listagem e identificadores do veiculo
        if (!empty($search)) {
            $query->whereRaw(
                '(v.placa LIKE ? OR v.renavam LIKE ? OR v.chassi LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR g.nome LIKE ? OR mf.nome_fantasia LIKE ?)',
                [
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%'
                ]
            );
        }

        return $query
            ->orderBy('v.modelo', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Lista os veiculos de uma filial para ajuste do valor por fracao.
     *
     * O QueryBuilder aplica automaticamente o filtro de tenant. O filtro de
     * acesso a filial deve ser validado pelo Controller antes desta chamada.
     */
    public function listarParaAjusteValorFracao(int $filialId): array
    {
        return $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.id_grupo',
                'v.id_matriz_filial',
                'v.valor_por_fracao',
                'g.nome AS grupo_nome',
            ])
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->where('v.id_matriz_filial', '=', $filialId)
            ->orderBy('g.nome', 'ASC')
            ->orderBy('v.marca', 'ASC')
            ->orderBy('v.modelo', 'ASC')
            ->orderBy('v.placa', 'ASC')
            ->get();
    }

    /**
     * Atualiza valores por fracao em lote com controle otimista de concorrencia.
     *
     * @param int $filialId Filial unica do lote
     * @param array<int,array{id:int,valor_original:float,novo_valor:float}> $alteracoes
     * @return array<int,array{id:int,placa:string,marca:string,modelo:string,valor_original:float,novo_valor:float}>
     */
    public function atualizarValoresFracaoEmLote(int $filialId, array $alteracoes): array
    {
        if ($filialId <= 0 || empty($alteracoes)) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            $alteracoes
        )));

        if (count($ids) !== count($alteracoes) || in_array(0, $ids, true)) {
            throw new \InvalidArgumentException('A lista de veiculos contem IDs invalidos ou duplicados.');
        }

        $this->qb->beginTransaction();

        try {
            $registros = $this->qb
                ->table('veiculos')
                ->select(['id', 'placa', 'marca', 'modelo', 'valor_por_fracao'])
                ->where('id_matriz_filial', '=', $filialId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if (count($registros) !== count($ids)) {
                throw new \DomainException('A lista de veiculos mudou. Recarregue a tela antes de salvar.');
            }

            $porId = [];
            foreach ($registros as $registro) {
                $porId[(int) $registro['id']] = $registro;
            }

            $atualizados = [];
            foreach ($alteracoes as $alteracao) {
                $id = (int) $alteracao['id'];
                $registro = $porId[$id] ?? null;

                if (!$registro) {
                    throw new \DomainException('A lista de veiculos mudou. Recarregue a tela antes de salvar.');
                }

                $valorBanco = round((float) ($registro['valor_por_fracao'] ?? 0), 2);
                $valorOriginal = round((float) $alteracao['valor_original'], 2);
                $novoValor = round((float) $alteracao['novo_valor'], 2);

                if ((int) round($valorBanco * 100) !== (int) round($valorOriginal * 100)) {
                    throw new \DomainException('Um ou mais valores foram alterados por outro usuario. Recarregue a tela antes de salvar.');
                }

                if ((int) round($valorBanco * 100) === (int) round($novoValor * 100)) {
                    continue;
                }

                $afetadas = $this->qb
                    ->table('veiculos')
                    ->where('id', '=', $id)
                    ->where('id_matriz_filial', '=', $filialId)
                    ->update(['valor_por_fracao' => $novoValor]);

                if ($afetadas !== 1) {
                    throw new \RuntimeException('Nao foi possivel atualizar todos os veiculos do lote.');
                }

                $atualizados[] = [
                    'id' => $id,
                    'placa' => (string) ($registro['placa'] ?? ''),
                    'marca' => (string) ($registro['marca'] ?? ''),
                    'modelo' => (string) ($registro['modelo'] ?? ''),
                    'valor_original' => $valorBanco,
                    'novo_valor' => $novoValor,
                ];
            }

            $this->qb->commit();
            return $atualizados;
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Conta o total de veiculos do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return int Total de registros
     */
    public function contar(
        string $chave,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): int {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->leftJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id');

        // Filtro de filial
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        // Busca
        if (!empty($search)) {
            $query->whereRaw(
                '(v.placa LIKE ? OR v.renavam LIKE ? OR v.chassi LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR g.nome LIKE ? OR mf.nome_fantasia LIKE ?)',
                [
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%'
                ]
            );
        }

        return $query->count();
    }

    /**
     * Conta veiculos ativos do tenant para validacao de limite do plano.
     * Exclui veiculos com disponibilidade inativa (vendido, roubado, excluido)
     * para que esses nao ocupem vaga no limite do plano.
     *
     * @param string $chave Chave do tenant
     * @return int Total de veiculos ativos
     */
    public function contarParaPlano(string $chave): int
    {
        return $this->qb
            ->table('veiculos', 'v')
            ->whereNotIn('v.disponibilidade', self::DISPONIBILIDADE_INATIVA)
            ->count();
    }

    /**
     * Retorna o resumo de disponibilidades de veiculos para integracoes publicas.
     *
     * Este metodo recebe a chave explicitamente porque webhooks publicos nao
     * dependem da sessao do tenant.
     *
     * @param string $chave Chave do tenant
     * @return array Totais por disponibilidade
     */
    public function resumoDisponibilidadeParaWhmcs(string $chave): array
    {
        $counts = array_fill_keys(array_keys(self::DISPONIBILIDADE_WHMCS), 0);
        $somaTotal = 0;

        $stmt = $this->getMysqli()->prepare(
            'SELECT disponibilidade, COUNT(*) AS total
             FROM veiculos
             WHERE chave = ?
             GROUP BY disponibilidade'
        );

        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar consulta de disponibilidade de veiculos');
        }

        $stmt->bind_param('s', $chave);
        $stmt->execute();

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $codigo = (string) ($row['disponibilidade'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            $somaTotal += $total;

            if (array_key_exists($codigo, $counts)) {
                $counts[$codigo] = $total;
            }
        }

        $stmt->close();

        $resumo = [];
        foreach (self::DISPONIBILIDADE_WHMCS as $codigo => $campo) {
            $resumo[$campo] = $counts[$codigo];
        }

        $resumo['somaTotal'] = $somaTotal;
        $resumo['emAtividade'] = $somaTotal - (
            $counts['V'] + $counts['E'] + $counts['RO']
        );

        return $resumo;
    }

    /**
     * Busca um veiculo por ID
     *
     * @param int $id ID do veiculo
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $veiculo = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.*',
                'g.nome as grupo_nome',
                'f.nome_rsocial as fornecedor_nome',
                'mf.nome_fantasia as filial_nome',
                'mfl.nome_fantasia as localizacao_nome'
            ])
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = v.chave')
            ->leftJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('matrizes_filiais', 'mfl', 'v.id_matriz_filial_localizacao', '=', 'mfl.id')
            ->where('v.id', '=', $id)
            ->first();

        if ($veiculo) {
            $veiculo['foto_url'] = FileHelper::url($veiculo['foto'], $veiculo['chave']);
            $veiculo['acessorios_vinculados'] = $this->getAcessoriosVinculados($id);
        }

        return $veiculo;
    }

    /**
     * Busca um veiculo por placa
     *
     * @param string $placa Placa do veiculo
     * @param int|null $ignorarId ID para ignorar na busca (para edicao)
     * @return array|null Dados ou null
     */
    public function buscarPorPlaca(string $placa, ?int $ignorarId = null): ?array
    {
        $placa = strtoupper(str_replace(['-', ' '], '', trim($placa)));

        $query = $this->qb
            ->table('veiculos')
            ->whereRaw("UPPER(REPLACE(REPLACE(placa, '-', ''), ' ', '')) = ?", [$placa]);

        if ($ignorarId !== null) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->first();
    }

    /**
     * Busca veiculo por placa em todos os tenants.
     *
     * Uso permitido para webhooks publicos/cross-tenant, onde nao existe sessao
     * do tenant e a identificacao precisa ser feita pela placa recebida.
     */
    public function buscarPorPlacaCrossTenant(string $placa): ?array
    {
        $placa = strtoupper(trim($placa));

        return $this->qb
            ->table('veiculos')
            ->withoutChave()
            ->select(['id', 'chave', 'placa', 'modelo', 'marca'])
            ->whereRaw("UPPER(REPLACE(placa, '-', '')) = ?", [str_replace('-', '', $placa)])
            ->first();
    }

    /**
     * Lista placas de todos os veiculos ativos do tenant (para consulta SERPRO em lote)
     *
     * @return array Lista de ['id' => int, 'placa' => string]
     */
    public function listarPlacas(): array
    {
        return $this->qb
            ->table('veiculos')
            ->select(['id', 'placa'])
            ->whereNotNull('placa')
            ->whereRaw("placa != ''")
            ->orderBy('placa', 'ASC')
            ->get();
    }

    /**
     * Lista placas de todos os veiculos de um tenant especifico (para CRON)
     *
     * @return array Lista de ['id' => int, 'placa' => string]
     */
    public function listarPlacasPorChave(string $chave): array
    {
        return $this->qb
            ->table('veiculos')
            ->withoutChave()
            ->select(['id', 'placa'])
            ->where('chave', '=', $chave)
            ->whereNotNull('placa')
            ->whereRaw("placa != ''")
            ->orderBy('placa', 'ASC')
            ->get();
    }

    /**
     * Lista placas de veiculos vinculados a filiais brasileiras (para consulta SERPRO em lote)
     * SERPRO eh API exclusiva do Brasil, nao faz sentido consultar veiculos de outros paises.
     *
     * @return array Lista de ['id' => int, 'placa' => string]
     */
    public function listarPlacasBrasileiras(): array
    {
        return $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa'])
            ->innerJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id')
            ->where('mf.pais', '=', 'BR')
            ->whereNotNull('v.placa')
            ->whereRaw("v.placa != ''")
            ->orderBy('v.placa', 'ASC')
            ->get();
    }

    /**
     * Lista placas de veiculos brasileiros de um tenant especifico (para CRON)
     * Filtra apenas veiculos vinculados a filiais com pais = 'BR'.
     *
     * @return array Lista de ['id' => int, 'placa' => string]
     */
    public function listarPlacasBrasileirasPorChave(string $chave): array
    {
        return $this->qb
            ->table('veiculos', 'v')
            ->withoutChave()
            ->select(['v.id', 'v.placa'])
            ->innerJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id')
            ->where('v.chave', '=', $chave)
            ->where('mf.pais', '=', 'BR')
            ->whereNotNull('v.placa')
            ->whereRaw("v.placa != ''")
            ->orderBy('v.placa', 'ASC')
            ->get();
    }

    /**
     * Cria um novo veiculo
     *
     * @param array $dados Dados do veiculo
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        // Processar foto se enviada como base64
        $foto = null;
        if (!empty($dados['foto_base64'])) {
            $foto = FileHelper::save($dados['foto_base64'], 'veiculo');
        }

        $camposPermitidos = [
            'chave', 'id_matriz_filial', 'id_fornecedor', 'id_grupo',
            'placa', 'renavam', 'chassi', 'marca', 'modelo', 'ano', 'cor',
            'motor', 'transmissao', 'peso_max', 'tipo_combustivel',
            'tanque_litros', 'tanque_fracao', 'valor_por_fracao',
            'data_compra', 'valor_compra', 'vender', 'data_venda', 'valor_venda',
            'disponibilidade', 'id_matriz_filial_localizacao', 'odometro',
            'descricao', 'foto', 'diagrama',
            'id_plano_manutencao', 'plano_manutencao_array'
        ];

        $dadosInsert = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        // Usar foto processada
        if ($foto !== null) {
            $dadosInsert['foto'] = $foto;
        }

        return $this->qb
            ->table('veiculos')
            ->insert($dadosInsert);
    }

    /**
     * Atualiza um veiculo existente
     *
     * @param int $id ID do veiculo
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $camposPermitidos = [
            'id_matriz_filial', 'id_fornecedor', 'id_grupo',
            'placa', 'renavam', 'chassi', 'marca', 'modelo', 'ano', 'cor',
            'motor', 'transmissao', 'peso_max', 'tipo_combustivel',
            'tanque_litros', 'tanque_fracao', 'valor_por_fracao',
            'data_compra', 'valor_compra', 'vender', 'data_venda', 'valor_venda',
            'disponibilidade', 'id_matriz_filial_localizacao', 'odometro',
            'descricao', 'foto', 'diagrama',
            'id_plano_manutencao', 'plano_manutencao_array'
        ];

        $dadosUpdate = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        // Processar foto se enviada como base64
        $veiculo = null;
        if (!empty($dados['foto_base64'])) {
            $veiculo = $this->buscarPorId($id);
            if (!empty($veiculo['foto'])) {
                FileHelper::delete($veiculo['foto'], $veiculo['chave']);
            }
            $dadosUpdate['foto'] = FileHelper::save($dados['foto_base64'], 'veiculo');
        }

        // Flag para remover foto
        if (isset($dados['remover_foto']) && $dados['remover_foto']) {
            $veiculo = $veiculo ?? $this->buscarPorId($id);
            if (!empty($veiculo['foto'])) {
                FileHelper::delete($veiculo['foto'], $veiculo['chave']);
            }
            $dadosUpdate['foto'] = null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('veiculos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Atualiza apenas o odometro atual do cadastro do veiculo.
     */
    public function atualizarOdometro(int $id, int $odometro): int
    {
        if ($id <= 0 || $odometro <= 0) {
            return 0;
        }

        return $this->qb
            ->table('veiculos')
            ->where('id', '=', $id)
            ->update(['odometro' => $odometro]);
    }

    /**
     * Atualiza odometro e tanque do cadastro do veiculo a partir do checklist.
     */
    public function atualizarDadosChecklist(int $id, ?int $odometro, ?string $tanque): int
    {
        if ($id <= 0) {
            return 0;
        }

        $dadosUpdate = [];
        if ($odometro !== null && $odometro > 0) {
            $dadosUpdate['odometro'] = $odometro;
        }

        if ($tanque !== null && trim($tanque) !== '') {
            $dadosUpdate['tanque_fracao'] = trim($tanque);
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('veiculos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um veiculo
     *
     * @param int $id ID do veiculo
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        // Deletar foto antes de excluir
        $veiculo = $this->buscarPorId($id);
        if ($veiculo && !empty($veiculo['foto'])) {
            FileHelper::delete($veiculo['foto'], $veiculo['chave']);
        }

        return $this->qb
            ->table('veiculos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se o veiculo possui vinculos que impedem a exclusao
     *
     * @param int $id ID do veiculo
     * @return array ['temVinculos' => bool, 'detalhes' => array]
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        $checks = [
            ['table' => 'locacoes_veiculos', 'label' => 'locacao(oes)'],
            ['table' => 'contratos_veiculos', 'label' => 'contrato(s)'],
            ['table' => 'manutencoes', 'label' => 'manutencao(oes)'],
            ['table' => 'multas', 'label' => 'multa(s)'],
            ['table' => 'financeiro', 'label' => 'lancamento(s) financeiro(s)'],
            ['table' => 'financeiro_itens', 'label' => 'item(ns) financeiro(s)'],
            ['table' => 'checklist', 'label' => 'checklist(s)'],
            ['table' => 'serpro_indicacoes', 'label' => 'indicacao(oes) SERPRO'],
            ['table' => 'comissoes_investidores', 'label' => 'comissao(oes) de investidor'],
            ['table' => 'veiculos_acessorios_vinculados', 'label' => 'acessorio(s) vinculado(s)'],
            ['table' => 'veiculos_encargos', 'label' => 'encargo(s) vinculado(s)'],
        ];

        foreach ($checks as $check) {
            $total = $this->qb
                ->table($check['table'])
                ->where('id_veiculo', '=', $id)
                ->count();

            if ($total > 0) {
                $vinculos[] = "Existem {$total} {$check['label']}";
            }
        }

        return [
            'temVinculos' => !empty($vinculos),
            'detalhes' => $vinculos
        ];
    }

    /**
     * Lista veiculos para select (formato simplificado)
     *
     * @param string $chave Chave do tenant
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array Lista de veiculos
     */
    public function listarParaSelect(
        string $chave,
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa', 'v.modelo', 'v.marca']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query
            ->orderBy('v.modelo', 'ASC')
            ->get();
    }

    /**
     * Retorna IDs dos acessorios vinculados ao veiculo
     *
     * @param int $veiculoId ID do veiculo
     * @return array Lista de IDs dos acessorios
     */
    public function getAcessoriosVinculados(int $veiculoId): array
    {
        return $this->qb
            ->table('veiculos_acessorios_vinculados')
            ->where('id_veiculo', '=', $veiculoId)
            ->pluck('id_acessorio');
    }

    /**
     * Sincroniza acessorios vinculados (remove antigos e adiciona novos)
     *
     * @param int $veiculoId ID do veiculo
     * @param array $acessoriosIds Array de IDs dos acessorios
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarAcessorios(int $veiculoId, array $acessoriosIds, string $chave): void
    {
        $this->qb->beginTransaction();

        try {
            // Remove todos os acessorios existentes
            $this->qb
                ->table('veiculos_acessorios_vinculados')
                ->where('id_veiculo', '=', $veiculoId)
                ->delete();

            // Insere os novos acessorios (chave eh adicionada automaticamente pelo QueryBuilder)
            foreach ($acessoriosIds as $acessorioId) {
                if (!empty($acessorioId)) {
                    $this->qb
                        ->table('veiculos_acessorios_vinculados')
                        ->insert([
                            'id_veiculo' => $veiculoId,
                            'id_acessorio' => (int) $acessorioId,
                        ]);
                }
            }

            $this->qb->commit();
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza a disponibilidade de um veiculo
     *
     * @param int $id ID do veiculo
     * @param string $status Novo status (D, L, R, O, I)
     * @return int Linhas afetadas
     */
    public function atualizarDisponibilidade(int $id, string $status): int
    {
        return $this->qb
            ->table('veiculos')
            ->where('id', '=', $id)
            ->update(['disponibilidade' => $status]);
    }

    /**
     * Lista veiculos disponiveis por grupo
     *
     * @param int $grupoId ID do grupo
     * @param int|null $filialId ID da filial para filtrar (opcional)
     * @return array Lista de veiculos disponiveis
     */
    public function listarDisponivelPorGrupo(int $grupoId, ?int $filialId = null): array
    {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.modelo',
                'v.km_atual'
            ])
            ->where('v.id_grupo', '=', $grupoId)
            ->where('v.disponibilidade', '=', 'D');

        if (!empty($filialId)) {
            $query->where('v.id_matriz_filial', '=', $filialId);
        }

        return $query->orderBy('v.placa', 'ASC')->get();
    }

    /**
     * Lista veiculos disponiveis para contrato (com filtros opcionais)
     *
     * @param int|null $grupoId ID do grupo (opcional)
     * @param int|null $filialId ID da filial (opcional)
     * @param int $limit Limite de resultados
     * @return array Lista de veiculos disponiveis
     */
    /**
     * Resumo agregado da frota para o dashboard.
     *
     * Conta apenas os buckets exibidos na barra de disponibilidade atual (D/L/O).
     * Manutencoes abertas prevalecem sobre o status gravado no veiculo.
     */
    public function dashboardSummary(string $chave): array
    {
        $veiculos = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.disponibilidade'])
            ->selectRaw('MAX(CASE WHEN m.id IS NULL THEN 0 ELSE 1 END) AS em_manutencao')
            ->leftJoinRaw('manutencoes', 'm', "m.id_veiculo = v.id AND m.status = 'A' AND m.chave = v.chave")
            ->whereIn('v.disponibilidade', ['D', 'L', 'O'])
            ->groupBy(['v.id', 'v.disponibilidade'])
            ->get();

        $counts = ['D' => 0, 'L' => 0, 'O' => 0];
        foreach ($veiculos as $v) {
            if ((int) ($v['em_manutencao'] ?? 0) === 1 || ($v['disponibilidade'] ?? '') === 'O') {
                $counts['O']++;
            } elseif (($v['disponibilidade'] ?? '') === 'L') {
                $counts['L']++;
            } elseif (($v['disponibilidade'] ?? '') === 'D') {
                $counts['D']++;
            }
        }

        $total = array_sum($counts);
        $rented = $counts['L'];

        return [
            'total' => $total,
            'available' => $counts['D'],
            'rented' => $rented,
            'reserved' => 0,
            'workshop' => $counts['O'],
            'utilization_rate' => $total > 0 ? round(($rented / $total) * 100, 1) : 0.0,
        ];
    }

    public function listarDisponiveisParaContrato(?int $grupoId = null, ?int $filialId = null, int $limit = 100): array
    {
        $query = $this->qb
            ->table('veiculos')
            ->select(['id', 'placa', 'modelo', 'marca', 'ano', 'cor', 'odometro', 'tanque_fracao', 'tipo_combustivel', 'valor_por_fracao'])
            ->where('disponibilidade', '=', 'D');

        if (!empty($grupoId)) {
            $query->where('id_grupo', '=', $grupoId);
        }

        if (!empty($filialId)) {
            $query->where('id_matriz_filial', '=', $filialId);
        }

        return $query
            ->orderBy('placa', 'ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Lista veiculos ativos para serem escolhidos como preferencia em reservas.
     *
     * Reservas nao bloqueiam disponibilidade operacional; por isso este metodo
     * inclui veiculos locados/reservados/oficina e exclui apenas estados inativos.
     */
    public function listarAtivosParaReserva(?int $grupoId = null, ?int $filialId = null, int $limit = 200): array
    {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.modelo',
                'v.marca',
                'v.ano',
                'v.cor',
                'v.odometro',
                'v.tanque_fracao',
                'v.tipo_combustivel',
                'v.valor_por_fracao',
                'v.disponibilidade',
            ])
            ->whereNotIn('v.disponibilidade', self::DISPONIBILIDADE_INATIVA);

        if (!empty($grupoId)) {
            $query->where('v.id_grupo', '=', $grupoId);
        }

        if (!empty($filialId)) {
            $query->where('v.id_matriz_filial', '=', $filialId);
        }

        return $query
            ->orderBy('v.placa', 'ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Veículos disponíveis exibidos nas subtabs do dashboard simples.
     */
    public function dashboardAvailableVehicles(
        string $chave,
        int $limit = 20,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.ano',
                'v.cor',
                'v.odometro',
                'g.nome AS grupo',
                'mf.nome_fantasia AS filial',
            ])
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->leftJoin('matrizes_filiais', 'mf', 'v.id_matriz_filial', '=', 'mf.id')
            ->where('v.disponibilidade', '=', 'D')
            ->orderBy('v.placa', 'ASC')
            ->limit($limit);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return array_map(function ($row) {
            $vehicleParts = array_filter([
                trim((string) ($row['marca'] ?? '')),
                trim((string) ($row['modelo'] ?? '')),
            ]);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'placa' => (string) ($row['placa'] ?? ''),
                'veiculo' => trim(implode(' ', $vehicleParts)),
                'grupo' => (string) ($row['grupo'] ?? ''),
                'filial' => (string) ($row['filial'] ?? ''),
                'ano' => (string) ($row['ano'] ?? ''),
                'cor' => (string) ($row['cor'] ?? ''),
                'odometro' => isset($row['odometro']) ? number_format((float) $row['odometro'], 0, ',', '.') . ' km' : '',
                'prazo_label' => t('modules.dashboard.subtabs.available_badge'),
                'prazo_tipo' => 'available',
            ];
        }, $query->get());
    }

    /**
     * Retorna, por grupo, quantos veiculos da filial estao livres no periodo.
     * Um veiculo eh livre quando esta disponivel (D) e nao tem locacao/contrato
     * ativo que sobreponha o periodo [$dataSaida, $dataDevolucao]. Formula de conflito:
     *   nova_saida < existente_fim AND nova_devolucao > existente_inicio
     *
     * @param int    $filialId       matriz_filial.id
     * @param string $dataSaida      "YYYY-MM-DD HH:MM:SS"
     * @param string $dataDevolucao  "YYYY-MM-DD HH:MM:SS"
     * @return array<int,int>        mapa id_grupo => qtd_livres (grupos sem veiculo nao aparecem)
     */
    public function gruposDisponiveisPorFilial(int $filialId, string $dataSaida, string $dataDevolucao): array
    {
        $chave = $_SESSION['chave'] ?? '';
        $sql = "
            SELECT
                v.id_grupo AS id_grupo,
                GREATEST(COUNT(*) - COALESCE(rg.qtd_reservas_grupo, 0), 0) AS qtd_livres
            FROM veiculos v
            LEFT JOIN (
                SELECT lv.id_grupo, COUNT(*) AS qtd_reservas_grupo
                FROM locacoes_veiculos lv
                INNER JOIN locacoes l ON l.id = lv.id_locacao AND l.chave = lv.chave
                WHERE lv.chave = ?
                  AND lv.id_veiculo IS NULL
                  AND lv.id_grupo IS NOT NULL
                  AND l.id_matriz_filial_retirada = ?
                  AND l.status IN ('R','P')
                  AND lv.data_entrada IS NULL
                  AND ? < l.data_prevista
                  AND ? > l.data_saida
                GROUP BY lv.id_grupo
            ) rg ON rg.id_grupo = v.id_grupo
            WHERE v.chave = ?
              AND v.id_matriz_filial = ?
              AND v.disponibilidade = 'D'
              AND NOT EXISTS (
                  SELECT 1
                  FROM locacoes_veiculos lv
                  INNER JOIN locacoes l ON l.id = lv.id_locacao AND l.chave = lv.chave
                  WHERE lv.id_veiculo = v.id
                    AND l.status IN ('R','P','A')
                    AND lv.data_entrada IS NULL
                    AND ? < l.data_prevista
                    AND ? > l.data_saida
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM contratos_veiculos cv
                  INNER JOIN contratos c ON c.id = cv.id_contrato
                  WHERE cv.id_veiculo = v.id
                    AND c.status = 'A'
                    AND cv.data_entrada IS NULL
                    AND ? < c.data_fim
                    AND ? > c.data_ini
              )
            GROUP BY v.id_grupo, rg.qtd_reservas_grupo
            HAVING qtd_livres > 0
        ";
        $rows = \App\Core\Database::fetchAll($sql, [
            $chave, $filialId, $dataSaida, $dataDevolucao,
            $chave, $filialId,
            $dataSaida, $dataDevolucao,
            $dataSaida, $dataDevolucao,
        ]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id_grupo']] = (int) $r['qtd_livres'];
        }
        return $out;
    }
}
