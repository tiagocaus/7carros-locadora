<?php

namespace App\Models;

use App\Core\Auth;
use App\Helpers\FileHelper;
use App\Helpers\SequenciaHelper;

/**
 * Model Multa
 *
 * Gerencia multas de transito vinculadas a contratos ou locacoes.
 * Integra com o financeiro para lancamento automatico de receitas/despesas.
 */
class Multa extends Model
{
    /**
     * Lista multas com paginacao, busca e filtros
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = [],
        string $filtroTipo = '',
        string $filtroPago = ''
    ): array {
        $query = $this->qb
            ->table('multas', 'm')
            ->select([
                'm.id',
                'm.tipo',
                'm.data_hora',
                'm.data_vencimento',
                'm.valor',
                'm.pago',
                'm.n_infracao',
                'm.orgao_autuador',
                'm.id_financeiro',
                'm.id_contrato',
                'm.id_locacao',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'mf.nome_fantasia AS filial_nome',
                'c.codigo AS contrato_codigo',
                'l.codigo AS locacao_codigo'
            ])
            ->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('matrizes_filiais', 'mf', 'm.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('contratos', 'c', 'm.id_contrato', '=', 'c.id')
            ->leftJoin('locacoes', 'l', 'm.id_locacao', '=', 'l.id');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereRaw(
                '(cl.nome_rsocial LIKE ? OR v.placa LIKE ? OR m.n_infracao LIKE ? OR m.orgao_autuador LIKE ?)',
                [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
            );
        }

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filtroTipo)) {
            $query->where('m.tipo', '=', $filtroTipo);
        }

        if (!empty($filtroPago)) {
            $query->where('m.pago', '=', $filtroPago);
        }

        return $query
            ->orderByDesc('m.data_hora')
            ->orderByDesc('m.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de multas com filtros
     */
    public function contar(
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = [],
        string $filtroTipo = '',
        string $filtroPago = ''
    ): int {
        $query = $this->qb
            ->table('multas', 'm');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
                ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
                ->whereRaw(
                    '(cl.nome_rsocial LIKE ? OR v.placa LIKE ? OR m.n_infracao LIKE ? OR m.orgao_autuador LIKE ?)',
                    [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
                );
        }

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filtroTipo)) {
            $query->where('m.tipo', '=', $filtroTipo);
        }

        if (!empty($filtroPago)) {
            $query->where('m.pago', '=', $filtroPago);
        }

        return $query->count();
    }

    /**
     * Busca multa por ID com JOINs completos
     */
    public function buscarPorId(int $id): ?array
    {
        $multa = $this->qb
            ->table('multas', 'm')
            ->select([
                'm.*',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'mf.nome_fantasia AS filial_nome',
                'c.codigo AS contrato_codigo',
                'l.codigo AS locacao_codigo'
            ])
            ->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('matrizes_filiais', 'mf', 'm.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('contratos', 'c', 'm.id_contrato', '=', 'c.id')
            ->leftJoin('locacoes', 'l', 'm.id_locacao', '=', 'l.id')
            ->where('m.id', '=', $id)
            ->first();

        if ($multa && !empty($multa['foto'])) {
            $multa['foto_url'] = FileHelper::url($multa['foto'], $multa['chave']);
        }

        return $multa;
    }

    /**
     * Lista multas que devem aparecer na fatura de uma locacao.
     *
     * Considera apenas multas vinculadas diretamente por id_locacao.
     * A identificacao do responsavel/vinculo eh responsabilidade do modulo de multas.
     */
    public function listarParaFaturaLocacao(int $locacaoId): array
    {
        return $this->qb
            ->table('multas', 'm')
            ->select([
                'm.id',
                'm.n_infracao',
                'm.numero_ait',
                'm.data_hora',
                'm.data_vencimento',
                'm.valor',
                'm.pago',
                'm.descri',
                'm.orgao_autuador',
                'm.local',
                'm.cidade',
                'm.estado',
                'v.placa AS veiculo_placa'
            ])
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->where('m.id_locacao', '=', $locacaoId)
            ->orderBy('m.data_hora', 'ASC')
            ->orderBy('m.id', 'ASC')
            ->get();
    }

    /**
     * Busca responsavel por multa em contratos e locacoes
     *
     * Usa ContratoVeiculo::findResponsavelByMulta() e LocacaoVeiculo::findResponsavelByMulta()
     * que ja existem no sistema.
     */
    public function buscarResponsavel(int $veiculoId, string $dataHoraMulta): ?array
    {
        // 1. Buscar em contratos
        $contratoVeiculo = new ContratoVeiculo();
        $responsavelContrato = $contratoVeiculo->findResponsavelByMulta($veiculoId, $dataHoraMulta);

        if ($responsavelContrato) {
            $contrato = $this->qb
                ->table('contratos')
                ->select(['id', 'id_matriz_filial_retirada'])
                ->where('id', '=', $responsavelContrato['contrato_id'])
                ->first();

            return [
                'tipo' => 'C',
                'id_contrato' => (int) $responsavelContrato['contrato_id'],
                'contrato_codigo' => $responsavelContrato['contrato_codigo'],
                'id_locacao' => null,
                'locacao_codigo' => null,
                'id_cliente' => (int) $responsavelContrato['id_cliente'],
                'cliente_nome' => $responsavelContrato['cliente_nome'],
                'cliente_cpf_cnpj' => $responsavelContrato['cliente_cpf_cnpj'],
                'id_matriz_filial' => $contrato ? (int) $contrato['id_matriz_filial_retirada'] : null,
            ];
        }

        // 2. Buscar em locacoes
        $locacaoVeiculo = new LocacaoVeiculo();
        $responsavelLocacao = $locacaoVeiculo->findResponsavelByMulta($veiculoId, $dataHoraMulta);

        if ($responsavelLocacao) {
            $locacao = $this->qb
                ->table('locacoes')
                ->select(['id', 'id_matriz_filial_retirada'])
                ->where('id', '=', $responsavelLocacao['locacao_id'])
                ->first();

            return [
                'tipo' => 'L',
                'id_contrato' => null,
                'contrato_codigo' => null,
                'id_locacao' => (int) $responsavelLocacao['locacao_id'],
                'locacao_codigo' => $responsavelLocacao['locacao_codigo'],
                'id_cliente' => (int) $responsavelLocacao['id_cliente'],
                'cliente_nome' => $responsavelLocacao['cliente_nome'],
                'cliente_cpf_cnpj' => $responsavelLocacao['cliente_cpf_cnpj'],
                'id_matriz_filial' => $locacao ? (int) $locacao['id_matriz_filial_retirada'] : null,
            ];
        }

        return null;
    }

    /**
     * Cria uma nova multa com lancamento financeiro vinculado
     */
    public function criar(array $dados): int
    {
        $pagador = $this->normalizarPagador($dados['pagador'] ?? null);
        $valor = currency_parse($dados['valor'] ?? 0);
        $dataVencimento = $this->normalizarDataVencimento($dados['data_vencimento'] ?? null);
        $sequenciaFinanceiro = null;
        if (!empty($dados['id_matriz_filial'])) {
            $sequenciaFinanceiro = SequenciaHelper::proximaSequencia(
                $dados['chave'],
                (int) $dados['id_matriz_filial'],
                'financeiro'
            );
        }

        $this->qb->beginTransaction();

        try {
            $idMulta = $this->qb
                ->table('multas')
                ->insert([
                    'chave' => $dados['chave'],
                    'tipo' => $dados['tipo'] ?? '',
                    'id_matriz_filial' => !empty($dados['id_matriz_filial']) ? (int) $dados['id_matriz_filial'] : null,
                    'id_contrato' => !empty($dados['id_contrato']) ? (int) $dados['id_contrato'] : null,
                    'id_locacao' => !empty($dados['id_locacao']) ? (int) $dados['id_locacao'] : null,
                    'id_cliente' => !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
                    'id_veiculo' => !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null,
                    'local' => $dados['local'] ?? '',
                    'cidade' => $dados['cidade'] ?? '',
                    'estado' => $dados['estado'] ?? '',
                    'data_hora' => $dados['data_hora'],
                    'data_vencimento' => $dataVencimento,
                    'valor' => $valor,
                    'pago' => 'N',
                    'pagador' => $pagador,
                    'descri' => $dados['descri'] ?? '',
                    'orgao_autuador' => $dados['orgao_autuador'] ?? '',
                    'n_infracao' => $dados['n_infracao'] ?? '',
                    'foto' => $dados['foto'] ?? null,
                ]);

            // Criar lancamento financeiro vinculado.
            $financeiro = new Financeiro();
            $descFinanceiro = 'Multa de transito';
            if (!empty($dados['n_infracao'])) {
                $descFinanceiro .= ' - ' . $dados['n_infracao'];
            }

            $dadosFinanceiro = [
                'chave' => $dados['chave'],
                'id_matriz_filial' => $dados['id_matriz_filial'] ?? null,
                'id_cliente' => $pagador === 'cliente' ? ($dados['id_cliente'] ?? null) : null,
                'id_fornecedor' => null,
                'id_multa' => $idMulta,
                'id_contrato' => $dados['id_contrato'] ?? null,
                'id_locacao' => $dados['id_locacao'] ?? null,
                'id_veiculo' => $dados['id_veiculo'] ?? null,
                'tipo' => $pagador === 'cliente' ? 'R' : 'D',
                'pago' => 'N',
                'descricao' => $descFinanceiro,
                'data_criada' => today(),
                'data_venci' => $dataVencimento,
                'valor_subtotal' => $valor,
            ];

            if ($sequenciaFinanceiro !== null) {
                $dadosFinanceiro['sequencia'] = $sequenciaFinanceiro;
            }

            $idFinanceiro = $financeiro->criar($dadosFinanceiro);

            // Vincular financeiro na multa
            $this->qb
                ->table('multas')
                ->where('id', '=', $idMulta)
                ->update(['id_financeiro' => $idFinanceiro]);

            $this->qb->commit();
            return $idMulta;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza uma multa existente
     */
    public function atualizar(int $id, array $dados): int
    {
        $multa = $this->buscarPorId($id);
        if (!$multa) {
            throw new \InvalidArgumentException('Multa nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['local'])) {
            $dadosUpdate['local'] = $dados['local'];
        }
        if (isset($dados['cidade'])) {
            $dadosUpdate['cidade'] = $dados['cidade'];
        }
        if (isset($dados['estado'])) {
            $dadosUpdate['estado'] = $dados['estado'];
        }
        if (array_key_exists('data_vencimento', $dados)) {
            $dadosUpdate['data_vencimento'] = $this->normalizarDataVencimento(
                $dados['data_vencimento'],
                $multa['data_vencimento'] ?? null
            );
        }
        if (array_key_exists('valor', $dados)) {
            $dadosUpdate['valor'] = currency_parse($dados['valor'] ?? 0);
        }
        if (array_key_exists('pagador', $dados)) {
            $dadosUpdate['pagador'] = $this->normalizarPagador($dados['pagador'] ?? null);
        }
        if (isset($dados['descri'])) {
            $dadosUpdate['descri'] = $dados['descri'];
        }
        if (isset($dados['orgao_autuador'])) {
            $dadosUpdate['orgao_autuador'] = $dados['orgao_autuador'];
        }
        if (isset($dados['n_infracao'])) {
            $dadosUpdate['n_infracao'] = $dados['n_infracao'];
        }
        if (array_key_exists('foto', $dados)) {
            $dadosUpdate['foto'] = $dados['foto'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = now();

        $result = $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update($dadosUpdate);

        if (!empty($multa['id_financeiro'])) {
            $this->sincronizarFinanceiroVinculado($multa, $dadosUpdate);
        }

        return $result;
    }

    private function normalizarPagador(?string $pagador): string
    {
        return in_array($pagador, ['cliente', 'empresa'], true) ? $pagador : 'cliente';
    }

    private function normalizarDataVencimento(mixed $data, ?string $fallback = null): string
    {
        $data = trim((string) ($data ?? ''));
        if ($data !== '') {
            return $data;
        }

        $fallback = trim((string) ($fallback ?? ''));
        return $fallback !== '' ? $fallback : today();
    }

    private function sincronizarFinanceiroVinculado(array $multa, array $dadosUpdate): void
    {
        $pagador = $this->normalizarPagador($dadosUpdate['pagador'] ?? ($multa['pagador'] ?? 'cliente'));
        $valor = array_key_exists('valor', $dadosUpdate)
            ? (float) $dadosUpdate['valor']
            : (float) ($multa['valor'] ?? 0);
        $dataVencimento = $this->normalizarDataVencimento(
            $dadosUpdate['data_vencimento'] ?? null,
            $multa['data_vencimento'] ?? null
        );

        $this->qb
            ->table('financeiro')
            ->where('id', '=', (int) $multa['id_financeiro'])
            ->update([
                'tipo' => $pagador === 'cliente' ? 'R' : 'D',
                'id_cliente' => $pagador === 'cliente' ? (!empty($multa['id_cliente']) ? (int) $multa['id_cliente'] : null) : null,
                'id_fornecedor' => null,
                'data_venci' => $dataVencimento,
                'valor_subtotal' => $valor,
                'valor_total' => $valor,
                'updated_at' => now(),
            ]);
    }

    /**
     * Exclui uma multa e seu lancamento financeiro vinculado
     */
    public function excluir(int $id): int
    {
        $multa = $this->buscarPorId($id);
        if (!$multa) {
            throw new \InvalidArgumentException('Multa nao encontrada');
        }

        if ($multa['pago'] === 'S') {
            throw new \InvalidArgumentException('Nao e possivel excluir uma multa ja paga');
        }

        $this->qb->beginTransaction();

        try {
            // Excluir lancamento financeiro vinculado
            if (!empty($multa['id_financeiro'])) {
                $this->qb
                    ->table('financeiro')
                    ->where('id', '=', (int) $multa['id_financeiro'])
                    ->delete();
            }

            $result = $this->qb
                ->table('multas')
                ->where('id', '=', $id)
                ->delete();

            $this->qb->commit();
            return $result;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Marca multa como paga e sincroniza financeiro
     */
    public function marcarPago(int $id, string $dataPago): void
    {
        $multa = $this->buscarPorId($id);
        if (!$multa) {
            throw new \InvalidArgumentException('Multa nao encontrada');
        }

        $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update([
                'pago' => 'S',
                'updated_at' => now(),
            ]);

        // Sincronizar financeiro
        if (!empty($multa['id_financeiro'])) {
            $financeiro = new Financeiro();
            $financeiro->atualizar((int) $multa['id_financeiro'], [
                'pago' => 'S',
                'data_pago' => $dataPago,
            ]);
        }
    }

    /**
     * Reverte pagamento da multa e sincroniza financeiro
     */
    public function marcarNaoPago(int $id): void
    {
        $multa = $this->buscarPorId($id);
        if (!$multa) {
            throw new \InvalidArgumentException('Multa nao encontrada');
        }

        $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update([
                'pago' => 'N',
                'updated_at' => now(),
            ]);

        if (!empty($multa['id_financeiro'])) {
            $financeiro = new Financeiro();
            $financeiro->atualizar((int) $multa['id_financeiro'], [
                'pago' => 'N',
                'data_pago' => null,
            ]);
        }
    }

    /**
     * Marca multa como paga SEM sincronizar financeiro
     * Usado pelo FinanceiroController para evitar loop infinito
     */
    public function marcarPagoSemSyncFinanceiro(int $id): void
    {
        $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update([
                'pago' => 'S',
                'updated_at' => now(),
            ]);
    }

    /**
     * Marca multa como nao paga SEM sincronizar financeiro
     * Usado pelo FinanceiroController para evitar loop infinito
     */
    public function marcarNaoPagoSemSyncFinanceiro(int $id): void
    {
        $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update([
                'pago' => 'N',
                'updated_at' => now(),
            ]);
    }

    // =========================================================================
    // METODOS CENTRAL DE MULTAS
    // =========================================================================

    /**
     * Calcula KPIs do dashboard da Central de Multas
     */
    public function calcularKpis(string $filialWhere = '1=1', array $filialParams = []): array
    {
        $hoje = today();
        $em30dias = \App\Helpers\DateHelper::addDaysForDatabase(30);
        $chave = $_SESSION['chave'];

        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN pago = 'N' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN pago = 'S' THEN 1 ELSE 0 END) AS pagas,
            SUM(CASE WHEN pago = 'N' AND data_vencimento < ? THEN 1 ELSE 0 END) AS vencidas,
            SUM(CASE WHEN pago = 'N' AND data_vencimento >= ? AND data_vencimento <= ? THEN 1 ELSE 0 END) AS vencendo,
            SUM(CASE WHEN pago = 'N' AND data_vencimento > ? THEN 1 ELSE 0 END) AS em_dia,
            COALESCE(SUM(CASE WHEN pago = 'N' THEN valor ELSE 0 END), 0) AS valor_pendente,
            COALESCE(SUM(valor), 0) AS valor_total,
            SUM(CASE WHEN origem = 'serpro_consulta' THEN 1 ELSE 0 END) AS origem_serpro_consulta,
            SUM(CASE WHEN origem = 'serpro_evento' THEN 1 ELSE 0 END) AS origem_serpro_evento,
            SUM(CASE WHEN origem = 'manual' OR origem IS NULL THEN 1 ELSE 0 END) AS origem_manual,
            SUM(CASE WHEN status_processamento = 'novo' THEN 1 ELSE 0 END) AS status_novo,
            SUM(CASE WHEN status_processamento IN ('pendente_indicacao','indicacao_enviada') THEN 1 ELSE 0 END) AS status_pendente_indicacao
        FROM multas m
        WHERE m.chave = ?";

        $params = [$hoje, $hoje, $em30dias, $em30dias, $chave];
        $types = 'sssss';

        if ($filialWhere && $filialWhere !== '1=1') {
            $sql .= " AND {$filialWhere}";
            foreach ($filialParams as $p) {
                $params[] = $p;
                $types .= is_int($p) ? 'i' : 's';
            }
        }

        $mysqli = $this->getMysqli();
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pendentes' => (int) ($row['pendentes'] ?? 0),
            'pagas' => (int) ($row['pagas'] ?? 0),
            'vencidas' => (int) ($row['vencidas'] ?? 0),
            'vencendo' => (int) ($row['vencendo'] ?? 0),
            'em_dia' => (int) ($row['em_dia'] ?? 0),
            'valor_pendente' => (float) ($row['valor_pendente'] ?? 0),
            'valor_total' => (float) ($row['valor_total'] ?? 0),
            'origem_manual' => (int) ($row['origem_manual'] ?? 0),
            'origem_serpro_consulta' => (int) ($row['origem_serpro_consulta'] ?? 0),
            'origem_serpro_evento' => (int) ($row['origem_serpro_evento'] ?? 0),
            'status_novo' => (int) ($row['status_novo'] ?? 0),
            'status_pendente_indicacao' => (int) ($row['status_pendente_indicacao'] ?? 0),
        ];
    }

    /**
     * Ranking de veiculos por quantidade de multas
     */
    public function rankingVeiculos(int $limite = 10): array
    {
        return $this->qb
            ->table('multas', 'm')
            ->selectRaw("
                v.placa,
                v.modelo,
                v.marca,
                COUNT(*) AS total_multas,
                SUM(CASE WHEN m.pago = 'N' THEN 1 ELSE 0 END) AS pendentes,
                COALESCE(SUM(CASE WHEN m.pago = 'N' THEN m.valor ELSE 0 END), 0) AS valor_pendente
            ")
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->whereNotNull('m.id_veiculo')
            ->groupBy(['v.placa', 'v.modelo', 'v.marca'])
            ->orderByDesc('total_multas')
            ->limit($limite)
            ->get();
    }

    /**
     * Lista multas com filtros avancados (inclui campos SERPRO)
     * Usado pela Central de Multas
     */
    public function listarPaginadoCentral(
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = [],
        string $filtroTipo = '',
        string $filtroPago = '',
        string $filtroOrigem = '',
        string $filtroStatus = '',
        string $filtroPlaca = '',
        string $filtroVencimento = ''
    ): array {
        $query = $this->qb
            ->table('multas', 'm')
            ->select([
                'm.id', 'm.tipo', 'm.data_hora', 'm.data_vencimento',
                'm.valor', 'm.pago', 'm.n_infracao', 'm.orgao_autuador',
                'm.codigo_orgao', 'm.numero_ait', 'm.codigo_infracao',
                'm.origem', 'm.status_processamento', 'm.valor_desconto_40',
                'm.serpro_sync_at',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
            ])
            ->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id');

        $this->aplicarFiltrosCentral(
            $query, $search, $filialWhere ?? '1=1', $filialParams,
            $filtroTipo, $filtroPago, $filtroOrigem, $filtroStatus, $filtroPlaca, $filtroVencimento
        );

        return $query
            ->orderByDesc('m.data_hora')
            ->orderByDesc('m.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta multas com filtros avancados (Central de Multas)
     */
    public function contarCentral(
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = [],
        string $filtroTipo = '',
        string $filtroPago = '',
        string $filtroOrigem = '',
        string $filtroStatus = '',
        string $filtroPlaca = '',
        string $filtroVencimento = ''
    ): int {
        $query = $this->qb
            ->table('multas', 'm');

        if (!empty($search) || !empty($filtroPlaca)) {
            $query->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
                ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id');
        }

        $this->aplicarFiltrosCentral(
            $query, $search, $filialWhere ?? '1=1', $filialParams,
            $filtroTipo, $filtroPago, $filtroOrigem, $filtroStatus, $filtroPlaca, $filtroVencimento
        );

        return $query->count();
    }

    /**
     * Aplica filtros avancados comuns a queries da Central de Multas
     */
    private function aplicarFiltrosCentral(
        $query,
        string $search,
        string $filialWhere,
        array $filialParams,
        string $filtroTipo,
        string $filtroPago,
        string $filtroOrigem,
        string $filtroStatus,
        string $filtroPlaca,
        string $filtroVencimento
    ): void {
        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->whereRaw(
                '(cl.nome_rsocial LIKE ? OR v.placa LIKE ? OR m.n_infracao LIKE ? OR m.numero_ait LIKE ?)',
                [$term, $term, $term, $term]
            );
        }

        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($filtroTipo)) {
            $query->where('m.tipo', '=', $filtroTipo);
        }
        if (!empty($filtroPago)) {
            $query->where('m.pago', '=', $filtroPago);
        }
        if (!empty($filtroOrigem)) {
            $query->where('m.origem', '=', $filtroOrigem);
        }
        if (!empty($filtroStatus)) {
            $query->where('m.status_processamento', '=', $filtroStatus);
        }
        if (!empty($filtroPlaca)) {
            $query->where('v.placa', '=', strtoupper($filtroPlaca));
        }

        if (!empty($filtroVencimento)) {
            $hoje = today();
            $em30dias = \App\Helpers\DateHelper::addDaysForDatabase(30);
            if ($filtroVencimento === 'vencidas') {
                $query->whereRaw("m.pago = 'N' AND m.data_vencimento < ?", [$hoje]);
            } elseif ($filtroVencimento === 'vencendo') {
                $query->whereRaw("m.pago = 'N' AND m.data_vencimento >= ? AND m.data_vencimento <= ?", [$hoje, $em30dias]);
            } elseif ($filtroVencimento === 'em_dia') {
                $query->whereRaw("m.pago = 'N' AND m.data_vencimento > ?", [$em30dias]);
            }
        }
    }

    // =========================================================================
    // METODOS SERPRO
    // =========================================================================

    /**
     * Busca multa pelas chaves SERPRO (codigo_orgao + numero_ait + codigo_infracao)
     */
    public function buscarPorChavesSerpro(string $codigoOrgao, string $numeroAit, string $codigoInfracao): ?array
    {
        return $this->qb
            ->table('multas')
            ->where('codigo_orgao', '=', $codigoOrgao)
            ->where('numero_ait', '=', $numeroAit)
            ->where('codigo_infracao', '=', $codigoInfracao)
            ->first();
    }

    /**
     * Cria multa a partir de dados SERPRO (sem lancamento financeiro)
     * O lancamento financeiro sera criado quando o tenant decidir processar a multa
     */
    public function criarDeSerpro(array $dados): int
    {
        $dados = $this->normalizarInfracaoSerpro($dados);
        $dataHora = $this->normalizarDataHoraSerpro($dados['data_hora'] ?? null);
        $dadosResponsavel = $this->resolverResponsavelSerpro([
            'id_veiculo' => $dados['id_veiculo'] ?? null,
            'data_hora' => $dataHora,
        ]);

        return $this->qb
            ->table('multas')
            ->insert(array_merge([
                'chave' => $_SESSION['chave'],
                'tipo' => '',
                'id_veiculo' => $dados['id_veiculo'] ?? null,
                'local' => $dados['local'] ?? '',
                'cidade' => $dados['cidade'] ?? '',
                'estado' => $dados['estado'] ?? '',
                'data_hora' => $dataHora ?? now(),
                'data_vencimento' => $this->normalizarDataSerpro($dados['data_vencimento'] ?? null) ?? today(),
                'valor' => (float) ($dados['valor'] ?? 0),
                'pago' => 'N',
                'descri' => $dados['descricao'] ?? '',
                'orgao_autuador' => $dados['codigo_orgao'] ?? '',
                'n_infracao' => $dados['numero_ait'] ?? '',
                'codigo_orgao' => $dados['codigo_orgao'] ?? null,
                'numero_ait' => $dados['numero_ait'] ?? null,
                'codigo_infracao' => $dados['codigo_infracao'] ?? null,
                'origem' => $dados['origem'] ?? 'serpro_consulta',
                'status_processamento' => $dados['status_processamento'] ?? 'novo',
                'valor_desconto_40' => $dados['valor_desconto_40'] ?? null,
                'data_notificacao_autuacao' => $this->normalizarDataSerpro($dados['data_notificacao_autuacao'] ?? null),
                'data_notificacao_penalidade' => $this->normalizarDataSerpro($dados['data_notificacao_penalidade'] ?? null),
                'serpro_sync_at' => $dados['serpro_sync_at'] ?? now(),
                'array' => $dados['payload_serpro'] ?? null,
            ], $dadosResponsavel));
    }

    /**
     * Normaliza payloads oficiais e legados da API de consultas online.
     */
    public function normalizarInfracaoSerpro(array $dados): array
    {
        $infracao = isset($dados['infracao']) && is_array($dados['infracao'])
            ? array_merge($dados, $dados['infracao'])
            : $dados;

        $dataHora = $this->primeiroValor($infracao, ['dataHoraInfracao', 'dataInfracao', 'data_hora']);
        if (empty($dataHora)) {
            $dataAutuacao = $this->primeiroValor($infracao, ['dataAutuacao']);
            $horaAutuacao = $this->primeiroValor($infracao, ['horaAutuacao']);
            if (!empty($dataAutuacao)) {
                $dataHora = trim($dataAutuacao . ' ' . ($horaAutuacao ?: '00:00:00'));
            }
        }

        $normalizado = [
            'id_veiculo' => $dados['id_veiculo'] ?? null,
            'placa' => $this->primeiroValor($infracao, ['placa']) ?? ($dados['placa'] ?? null),
            'codigo_orgao' => $this->primeiroValor($infracao, ['codigo_orgao', 'codigoOrgao', 'codigoOrgaoAutuador']),
            'numero_ait' => $this->primeiroValor($infracao, ['numero_ait', 'numeroAit', 'numeroAutoInfracao']),
            'codigo_infracao' => $this->primeiroValor($infracao, ['codigo_infracao', 'codigoInfracao']),
            'descricao' => $this->primeiroValor($infracao, ['descricao', 'descricaoInfracao', 'descricaoTipoPenalidade']) ?? 'Infracao importada por consulta online',
            'valor' => $this->primeiroValor($infracao, ['valor', 'valorInfracao', 'valorOriginal', 'valorIntegralInfracao']) ?? 0,
            'valor_desconto_40' => $this->primeiroValor($infracao, ['valor_desconto_40', 'valorDesconto']),
            'data_hora' => $dataHora,
            'data_vencimento' => $this->primeiroValor($infracao, [
                'data_vencimento',
                'dataVencimento',
                'dataVencimentoPenalidade',
                'dataVencimentoNotificacaoPenalidade',
                'dataVencimentoNotificacao',
            ]),
            'local' => $this->primeiroValor($infracao, ['local', 'localInfracao', 'localAutuacao']),
            'cidade' => $this->primeiroValor($infracao, ['cidade', 'descricaoMunicipioAutuacao']),
            'estado' => $this->normalizarUf($this->primeiroValor($infracao, ['estado', 'siglaUfLocalAutuacao', 'ufOrgaoAutuador'])),
            'data_notificacao_autuacao' => $this->primeiroValor($infracao, ['dataEmissaoNotificacaoAutuacao']),
            'data_notificacao_penalidade' => $this->primeiroValor($infracao, ['dataEmissaoNotificacaoPenalidade']),
            'origem' => $dados['origem'] ?? 'serpro_consulta',
            'status_processamento' => $dados['status_processamento'] ?? 'novo',
            'serpro_sync_at' => $dados['serpro_sync_at'] ?? now(),
            'indicador_foto_recebida' => $this->primeiroValor($infracao, ['indicadorFotoRecebida']),
            'id_rastreamento' => $this->primeiroValor($infracao, ['idRastreamento']),
            'tipo_evento' => $this->primeiroValor($infracao, ['tipoEvento']),
            'chave_infracao' => $this->primeiroValor($infracao, ['chaveInfracao']),
        ];

        if (isset($dados['payload_serpro'])) {
            $normalizado['payload_serpro'] = $dados['payload_serpro'];
        } else {
            $normalizado['payload_serpro'] = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return array_merge($dados, array_filter($normalizado, static fn($valor) => $valor !== null));
    }

    /**
     * Resolve contrato/locacao responsavel para multas recebidas via SERPRO.
     */
    public function resolverResponsavelSerpro(array $dados): array
    {
        $veiculoId = !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null;
        $dataHora = $this->normalizarDataHoraSerpro($dados['data_hora'] ?? null);

        if (!$veiculoId || !$dataHora) {
            return [];
        }

        $responsavel = $this->buscarResponsavel($veiculoId, $dataHora);
        if (!$responsavel) {
            return [];
        }

        return [
            'tipo' => $responsavel['tipo'],
            'id_contrato' => $responsavel['id_contrato'],
            'id_locacao' => $responsavel['id_locacao'],
            'id_cliente' => $responsavel['id_cliente'],
            'id_matriz_filial' => $responsavel['id_matriz_filial'],
        ];
    }

    /**
     * Normaliza datas vindas da API para comparacao com periodos de contratos/locacoes.
     */
    private function normalizarDataHoraSerpro(?string $dataHora): ?string
    {
        if (empty($dataHora)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($dataHora))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $dataHora;
        }
    }

    private function normalizarDataSerpro(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($data))->format('Y-m-d');
        } catch (\Exception $e) {
            return $data;
        }
    }

    private function primeiroValor(array $dados, array $chaves): mixed
    {
        foreach ($chaves as $chave) {
            if (array_key_exists($chave, $dados) && $dados[$chave] !== null && $dados[$chave] !== '') {
                return $dados[$chave];
            }
        }

        return null;
    }

    private function normalizarUf(mixed $uf): ?string
    {
        if ($uf === null || $uf === '') {
            return null;
        }

        return substr(strtoupper((string) $uf), 0, 2);
    }

    /**
     * Atualiza dados SERPRO de uma multa existente
     */
    public function atualizarDadosSerpro(int $id, array $dados): int
    {
        $dadosUpdate = [];

        $camposPermitidos = [
            'codigo_orgao', 'numero_ait', 'codigo_infracao', 'origem',
            'status_processamento', 'valor_desconto_40', 'na_pdf_path',
            'np_pdf_path', 'data_notificacao_autuacao', 'data_notificacao_penalidade',
            'serpro_sync_at', 'tipo', 'id_contrato', 'id_locacao', 'id_cliente',
            'id_matriz_filial', 'local', 'cidade', 'estado', 'data_hora',
            'data_vencimento', 'valor', 'descri', 'orgao_autuador', 'n_infracao',
            'array',
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = now();

        return $this->qb
            ->table('multas')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

}
