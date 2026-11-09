<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;
use App\Helpers\CodigoHelper;
use App\Helpers\FilialHelper;
use App\Core\Database;
use App\Services\AuditLogService;

/**
 * Model Manutencao
 *
 * Gerencia ordens de servico de manutencao de veiculos.
 *
 * Status:
 * - C = Criada (ainda nao enviado para oficina)
 * - A = Aberta (veiculo em manutencao)
 * - F = Fechada (manutencao concluida)
 *
 * Valores:
 * - total_servicos = Soma de todos os itens
 * - total_pago = Soma dos itens pagos
 * - total_pendente = Soma dos itens pendentes
 */
class Manutencao extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'a manutencao';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'os';
    }

    /**
     * Lista manutencoes do tenant com paginacao e busca
     */
    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.*',
                'v.placa AS veiculo_placa',
                'v.marca AS veiculo_marca',
                'v.modelo AS veiculo_modelo',
                'o.empresa AS oficina_nome',
                'mf.nome_fantasia AS filial_nome'
            ])
            ->selectSubquery(function ($q) {
                $q->table('manutencoes_itens', 'mi')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('mi.id_manutencao = m.id');
            }, 'qtd_itens')
            ->selectSubquery(function ($q) {
                $q->table('manutencoes_itens', 'mi')
                  ->selectRaw('COUNT(DISTINCT mi.id_financeiro)')
                  ->whereRaw('mi.id_manutencao = m.id')
                  ->whereNotNull('mi.id_financeiro');
            }, 'qtd_financeiros_itens')
            ->selectSubquery(function ($q) {
                $q->table('manutencoes_itens', 'mi')
                  ->selectRaw('COUNT(*)')
                  ->innerJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
                  ->whereRaw('mi.id_manutencao = m.id')
                  ->where('e.baixa_automatica', '=', 'S');
            }, 'qtd_itens_estoque')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('oficinas', 'o', 'm.id_oficina', '=', 'o.id')
            ->leftJoin('matrizes_filiais', 'mf', 'm.id_matriz_filial', '=', 'mf.id');

        // Filtro de busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('m.os', 'LIKE', $searchTerm)
                  ->orWhere('m.motivo', 'LIKE', $searchTerm)
                  ->orWhere('v.placa', 'LIKE', $searchTerm)
                  ->orWhere('v.marca', 'LIKE', $searchTerm)
                  ->orWhere('v.modelo', 'LIKE', $searchTerm)
                  ->orWhere('o.empresa', 'LIKE', $searchTerm);
            });
        }

        // Filtro de filial (permissoes do usuario)
        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        return $query
            ->orderByRaw("FIELD(m.status, 'C', 'A', 'F')")
            ->orderByDesc('m.data_enviado')
            ->orderByDesc('m.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de manutencoes com filtros
     */
    public function contar(
        string $chave,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = []
    ): int {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('oficinas', 'o', 'm.id_oficina', '=', 'o.id');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('m.os', 'LIKE', $searchTerm)
                  ->orWhere('m.motivo', 'LIKE', $searchTerm)
                  ->orWhere('v.placa', 'LIKE', $searchTerm)
                  ->orWhere('v.marca', 'LIKE', $searchTerm)
                  ->orWhere('v.modelo', 'LIKE', $searchTerm)
                  ->orWhere('o.empresa', 'LIKE', $searchTerm);
            });
        }

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        return $query->count();
    }

    /**
     * Lista manutencoes (criadas C ou abertas A) cujo intervalo intersecta [$inicio, $fim].
     * Usado na tela de Agenda.
     *
     * - Status C (Criada) = programada: usa data_enviado como inicio. Se nao tem data_enviado, ignora.
     * - Status A (Aberta) = em andamento: usa data_enviado como inicio, COALESCE(data_retorno, fim) como fim.
     */
    public function listarEventosAgenda(
        string $chave,
        string $inicio,
        string $fim,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.id',
                'm.os',
                'm.status',
                'm.data_enviado',
                'm.data_retorno',
                'm.motivo',
                'm.id_veiculo',
            ])
            ->whereIn('m.status', ['C', 'A'])
            ->whereNotNull('m.data_enviado')
            ->whereNotNull('m.id_veiculo')
            ->where('m.data_enviado', '<=', $fim)
            ->whereRaw('COALESCE(m.data_retorno, ?) >= ?', [$fim, $inicio]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        return $query
            ->orderBy('m.data_enviado', 'ASC')
            ->get();
    }

    /**
     * Lista manutencoes de um veiculo especifico
     */
    public function listarPorVeiculo(int $idVeiculo): array
    {
        return $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.id',
                'm.os',
                'm.status',
                'm.data_enviado',
                'm.data_retorno',
                'm.total_servicos',
                'm.motivo',
                'o.empresa AS oficina_nome'
            ])
            ->selectSubquery(function ($q) {
                $q->table('manutencoes_itens', 'mi')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('mi.id_manutencao = m.id');
            }, 'qtd_itens')
            ->leftJoin('oficinas', 'o', 'm.id_oficina', '=', 'o.id')
            ->where('m.id_veiculo', '=', $idVeiculo)
            ->orderByRaw("FIELD(m.status, 'C', 'A', 'F')")
            ->orderByDesc('m.data_enviado')
            ->orderByDesc('m.id')
            ->get();
    }

    /**
     * Busca uma manutencao por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.*',
                'v.placa AS veiculo_placa',
                'v.marca AS veiculo_marca',
                'v.modelo AS veiculo_modelo',
                'o.empresa AS oficina_nome',
                'c.nome_rsocial AS cliente_nome',
                'mf.nome_fantasia AS filial_nome'
            ])
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('oficinas', 'o', 'm.id_oficina', '=', 'o.id')
            ->leftJoin('clientes', 'c', 'm.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'mf', 'm.id_matriz_filial', '=', 'mf.id')
            ->where('m.id', '=', $id)
            ->first();
    }

    /**
     * Busca uma manutencao por ID com seus itens
     */
    public function buscarPorIdComItens(int $id): ?array
    {
        $manutencao = $this->buscarPorId($id);

        if ($manutencao) {
            $itemModel = new ManutencaoItem();
            $manutencao['itens'] = $itemModel->listarPorManutencao($id);
        }

        return $manutencao;
    }

    /**
     * Cria uma nova manutencao
     */
    public function criar(array $dados): int
    {
        $idMatrizFilial = $this->resolverIdMatrizFilial($dados);

        if (empty($dados['id_matriz_filial']) && $idMatrizFilial !== null) {
            $dados['id_matriz_filial'] = $idMatrizFilial;
        }

        // Gerar OS se nao informado
        if (empty($dados['os'])) {
            $dados['os'] = $this->gerarOs($dados['chave']);
        }

        // Status inicial
        if (empty($dados['status'])) {
            $dados['status'] = 'C';
        }

        // Campos permitidos
        $camposPermitidos = [
            'chave', 'os', 'id_matriz_filial', 'id_veiculo', 'id_oficina', 'id_cliente',
            'data_enviado', 'odo_enviado', 'tanque_enviado', 'motivo',
            'data_retorno', 'odo_retorno', 'tanque_retorno', 'obs_oficina',
            'trocou_oleo', 'trocou_pneus', 'status'
        ];

        $dadosInsert = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosInsert[$campo] = $dados[$campo] === '' ? null : $dados[$campo];
            }
        }

        return $this->qb->table('manutencoes')->insert($dadosInsert);
    }

    /**
     * Atualiza uma manutencao
     */
    public function atualizar(int $id, array $dados): int
    {
        $camposPermitidos = [
            'id_matriz_filial', 'id_veiculo', 'id_oficina', 'id_cliente',
            'data_enviado', 'odo_enviado', 'tanque_enviado', 'motivo',
            'data_retorno', 'odo_retorno', 'tanque_retorno', 'obs_oficina',
            'trocou_oleo', 'trocou_pneus', 'status',
            'id_financeiro_principal'
        ];

        $dadosUpdate = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo] === '' ? null : $dados[$campo];
            }
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('manutencoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Deleta uma manutencao
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('manutencoes')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Resolve a filial da manutencao a partir do formulario ou do veiculo.
     */
    private function resolverIdMatrizFilial(array $dados): ?int
    {
        if (!empty($dados['id_matriz_filial'])) {
            return (int) $dados['id_matriz_filial'];
        }

        if (empty($dados['id_veiculo'])) {
            return null;
        }

        $veiculo = $this->qb
            ->table('veiculos')
            ->select(['id_matriz_filial'])
            ->where('id', '=', (int) $dados['id_veiculo'])
            ->first();

        return !empty($veiculo['id_matriz_filial']) ? (int) $veiculo['id_matriz_filial'] : null;
    }

    /**
     * Gera codigo unico para a OS no mesmo principio de contratos/locacoes.
     * Formato: MA + 7 caracteres alfanumericos.
     */
    public function gerarOs(string $chave): string
    {
        for ($tentativa = 0; $tentativa < 20; $tentativa++) {
            $codigo = CodigoHelper::gerarComPrefixo('MA');

            $existente = $this->qb
                ->table('manutencoes')
                ->withChave($chave)
                ->where('os', '=', $codigo)
                ->first();

            if (!$existente) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Nao foi possivel gerar um codigo de OS unico');
    }

    /**
     * Abre uma manutencao (status = 'A') e bloqueia o veiculo
     */
    public function abrir(int $id): bool
    {
        $manutencao = $this->buscarPorId($id);

        if (!$manutencao) {
            throw new \InvalidArgumentException('Manutencao nao encontrada');
        }

        if ($manutencao['status'] !== 'C') {
            throw new \InvalidArgumentException('Apenas manutencoes criadas podem ser abertas');
        }

        if (empty($manutencao['id_veiculo'])) {
            throw new \InvalidArgumentException('Veiculo nao informado');
        }

        // Verificar se veiculo esta disponivel
        $veiculo = $this->qb
            ->table('veiculos')
            ->where('id', '=', $manutencao['id_veiculo'])
            ->first();

        if (!$veiculo) {
            throw new \InvalidArgumentException('Veiculo nao encontrado');
        }

        if ($veiculo['disponibilidade'] !== 'D' && $veiculo['disponibilidade'] !== null) {
            throw new \InvalidArgumentException('Veiculo nao esta disponivel para manutencao');
        }

        // Atualizar status da manutencao
        $this->qb
            ->table('manutencoes')
            ->where('id', '=', $id)
            ->update([
                'status' => 'A',
                'data_enviado' => $manutencao['data_enviado'] ?? now()
            ]);

        // Bloquear veiculo
        $this->bloquearVeiculo($manutencao['id_veiculo']);

        // Log de mudanca de disponibilidade
        AuditLogService::registrar(
            "Manutencao [{$manutencao['os']}] mudou disponibilidade do veiculo [{$veiculo['placa']}] de D para O"
        );

        return true;
    }

    /**
     * Fecha uma manutencao (status = 'F') e libera o veiculo
     */
    public function fechar(int $id): bool
    {
        $manutencao = $this->buscarPorId($id);

        if (!$manutencao) {
            throw new \InvalidArgumentException('Manutencao nao encontrada');
        }

        if ($manutencao['status'] !== 'A') {
            throw new \InvalidArgumentException('Apenas manutencoes abertas podem ser fechadas');
        }

        // Atualizar status da manutencao
        $this->qb
            ->table('manutencoes')
            ->where('id', '=', $id)
            ->update([
                'status' => 'F',
                'data_retorno' => $manutencao['data_retorno'] ?? now()
            ]);

        // Liberar veiculo
        if (!empty($manutencao['id_veiculo'])) {
            $this->liberarVeiculo($manutencao['id_veiculo']);

            // Log de mudanca de disponibilidade
            AuditLogService::registrar(
                "Manutencao [{$manutencao['os']}] mudou disponibilidade do veiculo [{$manutencao['veiculo_placa']}] de O para D"
            );
        }

        return true;
    }

    /**
     * Muda o status da manutencao com regras de negocio
     *
     * Transicoes permitidas:
     * - C -> A: Bloqueia veiculo, preenche dados de envio
     * - C -> F: Nao mexe no veiculo (registro retroativo)
     * - A -> F: Libera veiculo
     * - F -> A: Bloqueia veiculo novamente
     *
     * @param int $id ID da manutencao
     * @param string $novoStatus Novo status (A ou F)
     * @param array|null $dadosVeiculo Dados do veiculo (odometro, tanque) para preencher automaticamente
     * @return array Resultado com sucesso e mensagem
     */
    public function mudarStatus(int $id, string $novoStatus, ?array $dadosVeiculo = null): array
    {
        $manutencao = $this->buscarPorId($id);

        if (!$manutencao) {
            return ['success' => false, 'message' => 'Manutencao nao encontrada'];
        }

        $statusAtual = $manutencao['status'];

        // Se status nao mudou, nao faz nada
        if ($statusAtual === $novoStatus) {
            return ['success' => true, 'message' => 'Status nao alterado'];
        }

        // Validar transicoes
        $transicoesValidas = [
            'C' => ['A', 'F'],
            'A' => ['F'],
            'F' => ['A']
        ];

        if (!isset($transicoesValidas[$statusAtual]) || !in_array($novoStatus, $transicoesValidas[$statusAtual], true)) {
            return ['success' => false, 'message' => 'Transicao de status nao permitida'];
        }

        // Verificar se tem veiculo quando for abrir
        if ($novoStatus === 'A' && empty($manutencao['id_veiculo'])) {
            return ['success' => false, 'message' => 'Veiculo nao informado'];
        }

        // Verificar disponibilidade do veiculo quando for abrir
        if ($novoStatus === 'A' && !empty($manutencao['id_veiculo'])) {
            $veiculo = $this->qb
                ->table('veiculos')
                ->where('id', '=', $manutencao['id_veiculo'])
                ->first();

            if (!$veiculo) {
                return ['success' => false, 'message' => 'Veiculo nao encontrado'];
            }

            // Verificar se veiculo esta disponivel (aceita D, null ou O se for reabrir)
            $disponibilidadesPermitidas = ['D', null];
            if ($statusAtual === 'F') {
                // Se estiver reabrindo, aceita tambem O (pode ter ficado bloqueado)
                $disponibilidadesPermitidas[] = 'O';
                $disponibilidadesPermitidas[] = 'M';
            }

            if (!in_array($veiculo['disponibilidade'], $disponibilidadesPermitidas, true)) {
                return ['success' => false, 'message' => 'Veiculo nao esta disponivel para manutencao'];
            }
        }

        // Preparar dados de atualizacao
        $dadosUpdate = ['status' => $novoStatus];

        // Transicao para Aberta: preencher dados de envio
        if ($novoStatus === 'A') {
            // Preencher data de envio se nao tiver
            if (empty($manutencao['data_enviado'])) {
                $dadosUpdate['data_enviado'] = now();
            }

            // Preencher odometro e tanque do veiculo se fornecidos
            if ($dadosVeiculo) {
                if (isset($dadosVeiculo['odometro']) && empty($manutencao['odo_enviado'])) {
                    $dadosUpdate['odo_enviado'] = $dadosVeiculo['odometro'];
                }
                if (isset($dadosVeiculo['tanque']) && empty($manutencao['tanque_enviado'])) {
                    $dadosUpdate['tanque_enviado'] = $dadosVeiculo['tanque'];
                }
            }
        }

        // Transicao para Fechada: preencher data de retorno
        if ($novoStatus === 'F' && empty($manutencao['data_retorno'])) {
            $dadosUpdate['data_retorno'] = now();
        }

        // Atualizar manutencao
        $this->qb
            ->table('manutencoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);

        // Acoes no veiculo
        if (!empty($manutencao['id_veiculo'])) {
            // Abrir (C->A ou F->A): bloquear veiculo
            if ($novoStatus === 'A') {
                $this->bloquearVeiculo($manutencao['id_veiculo']);

                // Log de mudanca de disponibilidade
                AuditLogService::registrar(
                    "Manutencao [{$manutencao['os']}] mudou disponibilidade do veiculo [{$manutencao['veiculo_placa']}] de D para O"
                );
            }

            // Fechar vindo de Aberta (A->F): liberar veiculo
            if ($novoStatus === 'F' && $statusAtual === 'A') {
                $this->liberarVeiculo($manutencao['id_veiculo']);

                // Log de mudanca de disponibilidade
                AuditLogService::registrar(
                    "Manutencao [{$manutencao['os']}] mudou disponibilidade do veiculo [{$manutencao['veiculo_placa']}] de O para D"
                );
            }
            // Fechar vindo de Criada (C->F): nao mexe no veiculo (retroativo)
        }

        return ['success' => true, 'message' => 'Status alterado com sucesso'];
    }

    /**
     * Bloqueia veiculo para manutencao (disponibilidade = 'O' - Oficina)
     */
    public function bloquearVeiculo(int $idVeiculo): void
    {
        (new VeiculoDisponibilidadeSync())->marcarOficina($idVeiculo);
    }

    /**
     * Libera veiculo da manutencao (disponibilidade = 'D')
     */
    public function liberarVeiculo(int $idVeiculo, ?int $ignorarManutencaoId = null): void
    {
        (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo($idVeiculo, 'D', null, $ignorarManutencaoId);
    }

    /**
     * Verifica se veiculo pode ser usado para manutencao
     */
    public function veiculoPodeManutencao(int $idVeiculo, string $chave): array
    {
        $veiculo = $this->qb
            ->table('veiculos')
            ->where('id', '=', $idVeiculo)
            ->first();

        if (!$veiculo) {
            return ['pode' => false, 'motivo' => 'Veiculo nao encontrado'];
        }

        // Verificar se ja tem manutencao aberta
        $manutencaoAberta = $this->qb
            ->table('manutencoes')
            ->where('id_veiculo', '=', $idVeiculo)
            ->where('status', '=', 'A')
            ->first();

        if ($manutencaoAberta) {
            return ['pode' => false, 'motivo' => 'Veiculo ja possui manutencao aberta'];
        }

        return ['pode' => true, 'motivo' => ''];
    }

    /**
     * Verifica vinculos antes de excluir
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        // Verificar itens
        $itens = $this->qb
            ->table('manutencoes_itens')
            ->where('id_manutencao', '=', $id)
            ->count();

        if ($itens > 0) {
            $vinculos[] = "{$itens} item(ns) de servico";
        }

        // Verificar lancamentos financeiros
        $financeiro = $this->qb
            ->table('financeiro')
            ->where('id', '=', function ($q) use ($id) {
                $q->table('manutencoes')
                  ->select('id_financeiro_principal')
                  ->where('id', '=', $id);
            })
            ->count();

        if ($financeiro > 0) {
            $vinculos[] = "lancamento(s) financeiro(s)";
        }

        return [
            'temVinculos' => !empty($vinculos),
            'detalhes' => $vinculos
        ];
    }

    /**
     * Lista todos os lancamentos financeiros vinculados a uma manutencao.
     */
    public function listarFinanceirosVinculados(int $id): array
    {
        $manutencao = $this->buscarPorId($id);
        if (!$manutencao) {
            return [];
        }

        $ids = [];
        if (!empty($manutencao['id_financeiro_principal'])) {
            $ids[] = (int) $manutencao['id_financeiro_principal'];
        }

        $idsItens = $this->qb
            ->table('manutencoes_itens')
            ->where('id_manutencao', '=', $id)
            ->whereNotNull('id_financeiro')
            ->pluck('id_financeiro');

        foreach ($idsItens as $idFinanceiro) {
            if (!empty($idFinanceiro)) {
                $ids[] = (int) $idFinanceiro;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Verifica se ha item com baixa automatica de estoque na manutencao.
     */
    public function temEstoqueUtilizado(int $id): bool
    {
        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->innerJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->where('mi.id_manutencao', '=', $id)
            ->where('e.baixa_automatica', '=', 'S')
            ->count() > 0;
    }

    /**
     * Exclui uma manutencao e seus impactos opcionais em uma unica transacao.
     */
    public function excluirComImpactos(
        int $id,
        array $financeirosVinculados,
        bool $excluirFinanceiro,
        bool $reporEstoque
    ): void {
        $manutencao = $this->buscarPorId($id);
        if (!$manutencao) {
            throw new \InvalidArgumentException('Manutencao nao encontrada');
        }

        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            if ($manutencao['status'] === 'A' && !empty($manutencao['id_veiculo'])) {
                $this->liberarVeiculo((int) $manutencao['id_veiculo'], $id);
            }

            if ($excluirFinanceiro && !empty($financeirosVinculados)) {
                $financeiroModel = new Financeiro();
                foreach ($financeirosVinculados as $idFinanceiro) {
                    $financeiroModel->deletar((int) $idFinanceiro);
                }
            }

            $itemModel = new ManutencaoItem();
            if ($reporEstoque) {
                $itemModel->reporEstoquePorManutencao($id);
            }

            $itemModel->deletarTodosPorManutencao($id);
            $this->deletar($id);

            $mysqli->commit();
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    // ===== METODOS FINANCEIROS =====

    /**
     * Cria lancamento financeiro completo da manutencao
     */
    public function criarLancamentoFinanceiro(int $id, array $dadosFinanceiro): int
    {
        $manutencao = $this->buscarPorIdComItens($id);

        if (!$manutencao) {
            throw new \InvalidArgumentException('Manutencao nao encontrada');
        }

        if (empty($manutencao['itens'])) {
            throw new \InvalidArgumentException('Manutencao nao possui itens');
        }

        return $this->persistirFinanceiroManutencao($manutencao, $manutencao['itens'], $dadosFinanceiro, true);
    }

    /**
     * Cria lancamento financeiro parcial (apenas itens selecionados).
     */
    public function criarLancamentoParcial(int $id, array $idsItens, array $dadosFinanceiro): int
    {
        $manutencao = $this->buscarPorId($id);
        if (!$manutencao) {
            throw new \InvalidArgumentException('Manutencao nao encontrada');
        }
        if (empty($idsItens)) {
            throw new \InvalidArgumentException('Nenhum item selecionado');
        }

        $itens = (new ManutencaoItem())->buscarPorIds($idsItens);
        if (count($itens) !== count(array_unique($idsItens))) {
            throw new \InvalidArgumentException('Itens nao encontrados');
        }

        return $this->persistirFinanceiroManutencao($manutencao, $itens, $dadosFinanceiro, false);
    }

    /**
     * Grava parcelas e vinculos atomicamente. Os itens financeiros representam
     * somente a primeira parcela, nunca o valor integral da ordem de servico.
     */
    private function persistirFinanceiroManutencao(array $manutencao, array $itens, array $dados, bool $completo): int
    {
        $total = array_sum(array_column($itens, 'valor_total'));
        $tipo = !empty($manutencao['id_cliente']) ? 'R' : 'D';
        $parcelas = $this->normalizarParcelasFinanceiras($dados, $total, $tipo);
        $chave = $manutencao['chave'];
        $id = (int) $manutencao['id'];
        $idsItens = array_map('intval', array_column($itens, 'id'));

        // O helper usa outra conexao: reservar antes de adquirir locks/FKs.
        $sequencias = \App\Helpers\SequenciaHelper::proximasSequencias(
            $chave, (int) $manutencao['id_matriz_filial'], 'financeiro', count($parcelas)
        );
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            $atual = $this->qb->table('manutencoes')->where('id', '=', $id)->lockForUpdate()->first();
            if (!$atual || $atual['chave'] !== $chave
                || $atual['id_cliente'] != $manutencao['id_cliente']
                || $atual['id_matriz_filial'] != $manutencao['id_matriz_filial']) {
                throw new \InvalidArgumentException('Manutencao alterada. Recarregue a pagina');
            }

            $query = $this->qb->table('manutencoes_itens')->where('id_manutencao', '=', $id);
            if (!$completo) {
                $query->whereIn('id', $idsItens);
            }
            $itensAtuais = $query->orderBy('ordem')->orderBy('id')->lockForUpdate()->get();
            if (count($itensAtuais) !== count($idsItens)) {
                throw new \InvalidArgumentException('Itens alterados. Recarregue a pagina');
            }
            foreach ($itensAtuais as $item) {
                if ($item['pago'] === 'S' || !empty($item['id_financeiro'])) {
                    throw new \InvalidArgumentException('Um ou mais itens ja possuem lancamento financeiro');
                }
            }
            // Revalidar a soma depois de bloquear os itens contra edicao concorrente.
            $this->normalizarParcelasFinanceiras($dados, array_sum(array_column($itensAtuais, 'valor_total')), $tipo);

            $financeiroModel = new Financeiro();
            $idOrigem = null;
            $idsFinanceiros = [];
            foreach ($parcelas as $index => $parcela) {
                $idFinanceiro = $financeiroModel->criar([
                    'chave' => $chave,
                    'sequencia' => $sequencias[$index],
                    'tipo' => $tipo,
                    'id_matriz_filial' => $atual['id_matriz_filial'],
                    'id_cliente' => $atual['id_cliente'],
                    'id_oficina' => $atual['id_oficina'],
                    'id_veiculo' => $atual['id_veiculo'],
                    'id_conta' => $parcela['id_conta'],
                    'id_forma_pagamento' => $parcela['id_forma_pagamento'],
                    'id_plano_de_conta' => $parcela['id_plano_de_conta'],
                    'descricao' => "Manutencao OS #{$atual['os']}" . ($completo ? '' : ' - Fechamento Parcial'),
                    'valor_subtotal' => $parcela['valor'],
                    'data_criada' => today(),
                    'data_venci' => $parcela['data_vencimento'],
                    // Baixar somente apos todos os valores e itens estarem definitivos.
                    'pago' => 'N',
                    'parcela' => $index + 1,
                    'total_parcelas' => count($parcelas),
                    'id_financeiro_origem' => $idOrigem,
                ]);
                $idsFinanceiros[] = $idFinanceiro;
                if ($index === 0) {
                    $idOrigem = $idFinanceiro;
                    $this->criarItensPrimeiraParcela($idFinanceiro, $atual, $itensAtuais, $parcela);
                }
            }

            (new ManutencaoItem())->marcarComosPagos($idsItens, $idOrigem);
            if ($completo) {
                $this->atualizar($id, ['id_financeiro_principal' => $idOrigem]);
            }
            foreach ($parcelas as $index => $parcela) {
                if ($parcela['pago'] === 'S') {
                    $financeiroModel->atualizar($idsFinanceiros[$index], ['pago' => 'S', 'data_pago' => today()], true);
                }
            }

            $mysqli->commit();
            return $idOrigem;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Rateio acumulado em centavos evita sobra negativa com muitos itens pequenos.
     * Os itens da manutencao permanecem com seus valores integrais.
     */
    private function criarItensPrimeiraParcela(int $idFinanceiro, array $manutencao, array $itens, array $parcela): void
    {
        $totalCentavos = array_sum(array_map(static fn ($item) => (int) round((float) $item['valor_total'] * 100), $itens));
        $parcelaCentavos = (int) round($parcela['valor'] * 100);
        $acumulado = 0;
        $distribuido = 0;
        $financeiroItem = new FinanceiroItem();

        foreach ($itens as $index => $item) {
            $acumulado += (int) round((float) $item['valor_total'] * 100);
            $alvo = (int) round($parcelaCentavos * ($acumulado / $totalCentavos));
            $financeiroItem->criar([
                'chave' => $manutencao['chave'],
                'id_financeiro' => $idFinanceiro,
                'id_veiculo' => $manutencao['id_veiculo'],
                'id_plano_de_conta' => $parcela['id_plano_de_conta'],
                'descricao' => $item['descricao'],
                'ordem' => $index + 1,
                'valor' => ($alvo - $distribuido) / 100,
            ]);
            $distribuido = $alvo;
        }
    }

    /**
     * Normaliza e valida as parcelas editadas na aba Financeiro da manutencao.
     */
    private function normalizarParcelasFinanceiras(array $dadosFinanceiro, float $total, string $tipoFinanceiro): array
    {
        if (empty($dadosFinanceiro['parcelas_geradas']) || !is_array($dadosFinanceiro['parcelas_geradas'])) {
            throw new \InvalidArgumentException('Gere e confira as parcelas antes de criar o lancamento financeiro');
        }

        $parcelas = [];
        $soma = 0.0;
        $planosValidados = [];
        $planoModel = new PlanoDeContas();

        foreach ($dadosFinanceiro['parcelas_geradas'] as $index => $parcela) {
            $numero = (int) ($parcela['numero'] ?? ($index + 1));
            $idConta = (int) ($parcela['id_conta'] ?? 0);
            $idFormaPagamento = (int) ($parcela['id_forma_pagamento'] ?? 0);
            $idPlanoDeConta = (int) ($parcela['id_plano_de_conta'] ?? 0);
            $dataVencimento = trim((string) ($parcela['data_vencimento'] ?? ''));
            $valor = currency_parse($parcela['valor'] ?? 0);
            $pago = ($parcela['pago'] ?? 'N') === 'S' ? 'S' : 'N';

            if ($numero < 1 || $idConta <= 0 || $idFormaPagamento <= 0 || $idPlanoDeConta <= 0 || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVencimento)) {
                throw new \InvalidArgumentException('Preencha corretamente todas as parcelas geradas');
            }

            if (!array_key_exists($idPlanoDeConta, $planosValidados)) {
                $plano = $planoModel->buscarPorId($idPlanoDeConta);
                if (!$plano || ($plano['tipo'] ?? '') !== $tipoFinanceiro) {
                    throw new \InvalidArgumentException('Plano de contas invalido para o tipo do lancamento');
                }
                $planosValidados[$idPlanoDeConta] = true;
            }

            $parcelas[] = [
                'numero' => $numero,
                'id_conta' => $idConta,
                'id_forma_pagamento' => $idFormaPagamento,
                'id_plano_de_conta' => $idPlanoDeConta,
                'data_vencimento' => $dataVencimento,
                'valor' => round($valor, 2),
                'pago' => $pago,
            ];

            $soma += $valor;
        }

        usort($parcelas, fn ($a, $b) => $a['numero'] <=> $b['numero']);

        if (abs(round($soma, 2) - round($total, 2)) > 0.01) {
            throw new \InvalidArgumentException('A soma das parcelas deve ser igual ao total selecionado');
        }

        return $parcelas;
    }

    // ===== METODOS PARA SELECTS =====

    /**
     * Lista veiculos para select
     */
    public function listarVeiculosSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('veiculos')
            ->select(['id', 'placa', 'marca', 'modelo']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('placa', 'LIKE', $searchTerm)
                  ->orWhere('marca', 'LIKE', $searchTerm)
                  ->orWhere('modelo', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('placa', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista oficinas para select
     */
    public function listarOficinasSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('oficinas')
            ->select(['id', 'empresa AS nome']);

        if (!empty($search)) {
            $query->where('empresa', 'LIKE', '%' . $search . '%');
        }

        return $query
            ->orderBy('empresa', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista formas de pagamento para select
     */
    public function listarFormasPagamentoSelect(string $chave): array
    {
        return $this->qb
            ->table('formas_pagamento')
            ->select(['id', 'nome'])
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista produtos do estoque para select
     */
    public function listarEstoqueSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('estoque')
            ->select([
                'id',
                'produto_codigo AS codigo',
                'produto_nome AS nome',
                'produto_unidade AS unidade',
                'valor_venda',
                'produto_estoque_atual AS estoque_atual'
            ]);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('produto_codigo', 'LIKE', $searchTerm)
                  ->orWhere('produto_nome', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('produto_nome', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Busca produto do estoque por ID
     */
    public function buscarEstoquePorId(string $chave, int $id): ?array
    {
        return $this->qb
            ->table('estoque')
            ->select([
                'id',
                'produto_codigo AS codigo',
                'produto_nome AS nome',
                'produto_unidade AS unidade',
                'valor_venda',
                'produto_estoque_atual AS estoque_atual'
            ])
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Conta manutencoes abertas (status C=Criada ou A=Aberta)
     */
    public function contarAbertas(): int
    {
        return $this->qb
            ->table('manutencoes')
            ->whereRaw("status IN ('C','A')")
            ->count();
    }

    /**
     * Lista manutencoes abertas para a tela de notificacoes.
     * Retorna payload pronto para a UI agregada.
     */
    public function listarParaNotificacoes(int $limit = 25, int $offset = 0): array
    {
        return $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.id', 'm.os', 'm.status', 'm.data_enviado', 'm.id_veiculo',
                'v.placa AS veiculo_placa', 'v.modelo AS veiculo_modelo',
            ])
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->whereRaw("m.status IN ('C','A')")
            ->orderByDesc('m.data_enviado')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
}
