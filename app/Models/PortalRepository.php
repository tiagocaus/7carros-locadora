<?php

namespace App\Models;

use App\Helpers\DateHelper;
use App\Models\Relatorios\FornecedoresReport;

/**
 * Consultas de leitura e atualizacao autorizadas pelo portal.
 *
 * O contexto do tenant deve estar definido antes de instanciar este Model.
 */
class PortalRepository extends Model
{
    public function perfil(string $perfil, int $entidadeId): ?array
    {
        if ($perfil === 'cliente') {
            $cliente = (new Cliente())->buscarPorIdComContatos($entidadeId);
            if (!$cliente) {
                return null;
            }

            return [
                'id' => (int) $cliente['id'],
                'perfil' => 'cliente',
                'nome' => $cliente['nome_rsocial'] ?? '',
                'nome_fantasia' => $cliente['nome_fantasia'] ?? '',
                'cpf_cnpj' => $cliente['cpf_cnpj'] ?? '',
                'rg_ie' => $cliente['rg_ie'] ?? '',
                'email' => $cliente['email'] ?? '',
                'telefone' => $cliente['telefone'] ?? '',
                'preferred_locale' => $cliente['preferred_locale'] ?? 'pt_BR',
                'cep' => $cliente['cep'] ?? '',
                'rua' => $cliente['rua'] ?? '',
                'numero' => $cliente['numero'] ?? '',
                'complemento' => $cliente['complemento'] ?? '',
                'bairro' => $cliente['bairro'] ?? '',
                'cidade' => $cliente['cidade'] ?? '',
                'estado' => $cliente['estado'] ?? '',
                'pais' => $cliente['pais'] ?? '',
                'campos_somente_leitura' => [
                    'nome', 'nome_fantasia', 'cpf_cnpj', 'rg_ie',
                ],
            ];
        }

        $fornecedor = (new Fornecedor())->buscarPorId($entidadeId);
        if (!$fornecedor || (int) ($fornecedor['investidor'] ?? 0) !== 1) {
            return null;
        }

        return [
            'id' => (int) $fornecedor['id'],
            'perfil' => 'investidor',
            'nome' => $fornecedor['nome_rsocial'] ?? '',
            'nome_fantasia' => $fornecedor['nome_fantasia'] ?? '',
            'cpf_cnpj' => $fornecedor['cpf_cnpj'] ?? '',
            'rg_ie' => $fornecedor['rg_ie'] ?? '',
            'email' => $fornecedor['email'] ?? '',
            'telefone' => $fornecedor['tel1'] ?? '',
            'telefone_secundario' => $fornecedor['tel2'] ?? '',
            'cep' => $fornecedor['cep'] ?? '',
            'rua' => $fornecedor['rua'] ?? '',
            'numero' => $fornecedor['num'] ?? '',
            'complemento' => $fornecedor['complemento'] ?? '',
            'bairro' => $fornecedor['bairro'] ?? '',
            'cidade' => $fornecedor['cidade'] ?? '',
            'estado' => $fornecedor['estado'] ?? '',
            'pais' => $fornecedor['pais'] ?? '',
            'pix_chave' => $fornecedor['pix_chave'] ?? '',
            'pix_tipo' => $fornecedor['pix_tipo'] ?? '',
            'banco_codigo' => $fornecedor['banco_codigo'] ?? '',
            'banco_agencia' => $fornecedor['banco_agencia'] ?? '',
            'banco_conta' => $fornecedor['banco_conta'] ?? '',
            'banco_tipo' => $fornecedor['banco_tipo'] ?? '',
            'campos_somente_leitura' => [
                'nome', 'nome_fantasia', 'cpf_cnpj', 'rg_ie', 'pix_chave',
                'pix_tipo', 'banco_codigo', 'banco_agencia', 'banco_conta', 'banco_tipo',
            ],
        ];
    }

    public function dashboardCliente(int $idCliente): array
    {
        $hoje = DateHelper::todayForDatabase();
        $contratosAbertos = $this->qb->table('contratos')
            ->where('id_cliente', '=', $idCliente)->where('status', '=', 'A')->count();
        $contratosFechados = $this->qb->table('contratos')
            ->where('id_cliente', '=', $idCliente)->where('status', '!=', 'A')->count();
        $reservas = $this->qb->table('locacoes')
            ->where('id_cliente', '=', $idCliente)->whereIn('status', ['P', 'R'])->count();
        $locacoesAbertas = $this->qb->table('locacoes')
            ->where('id_cliente', '=', $idCliente)->where('status', '=', 'A')->count();
        $locacoesFechadas = $this->qb->table('locacoes')
            ->where('id_cliente', '=', $idCliente)->where('status', '=', 'F')->count();
        $faturasAbertas = $this->qb->table('financeiro')
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R')->where('pago', '=', 'N')->count();
        $faturasPagas = $this->qb->table('financeiro')
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R')->where('pago', '=', 'S')->count();
        $faturasVencidas = $this->qb->table('financeiro')
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R')
            ->where('pago', '=', 'N')->where('data_venci', '<', $hoje)->count();
        $valorAberto = $this->qb->table('financeiro')
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R')
            ->where('pago', '=', 'N')->sum('valor_total');
        $multasAbertas = $this->qb->table('multas')
            ->where('id_cliente', '=', $idCliente)->where('pago', '=', 'N')->count();
        $multasFechadas = $this->qb->table('multas')
            ->where('id_cliente', '=', $idCliente)->where('pago', '=', 'S')->count();
        $manutencoes = $this->qb->table('manutencoes')
            ->where('id_cliente', '=', $idCliente)->count();

        return [
            'totais' => [
                'veiculos' => count($this->veiculosCliente($idCliente)),
                'contratos_abertos' => $contratosAbertos,
                'contratos_fechados' => $contratosFechados,
                'reservas' => $reservas,
                'locacoes_abertas' => $locacoesAbertas,
                'locacoes_fechadas' => $locacoesFechadas,
                'faturas_abertas' => $faturasAbertas,
                'faturas_pagas' => $faturasPagas,
                'faturas_vencidas' => $faturasVencidas,
                'valor_faturas_abertas' => round($valorAberto, 2),
                'multas_abertas' => $multasAbertas,
                'multas_fechadas' => $multasFechadas,
                'manutencoes' => $manutencoes,
            ],
            'proxima_reserva' => $this->qb
                ->table('locacoes')
                ->select(['id', 'codigo', 'data_saida', 'data_prevista', 'status', 'total_pagar'])
                ->where('id_cliente', '=', $idCliente)
                ->whereIn('status', ['P', 'R'])
                ->where('data_saida', '>=', $hoje . ' 00:00:00')
                ->orderBy('data_saida', 'ASC')
                ->first(),
            'contrato_ativo' => $this->qb
                ->table('contratos')
                ->select(['id', 'codigo', 'data_ini', 'data_fim', 'status', 'total_pagar'])
                ->where('id_cliente', '=', $idCliente)
                ->where('status', '=', 'A')
                ->orderByDesc('data_ini')
                ->first(),
            'atividades' => $this->atividadesCliente($idCliente),
        ];
    }

    public function dashboardInvestidor(int $idFornecedor, string $dataInicio, string $dataFim): array
    {
        $report = (new FornecedoresReport())->investidor(
            $dataInicio,
            $dataFim,
            '',
            [],
            (string) $idFornecedor,
            ''
        );

        $detail = $report['details'][0] ?? [
            'veiculos' => [],
            'qtd_veiculos' => 0,
            'valor_investido' => 0,
            'receita_gerada' => 0,
            'comissao_devida' => 0,
            'comissao_paga' => 0,
            'saldo' => 0,
        ];

        $manutencoesAbertas = $this->qb
            ->table('manutencoes', 'm')
            ->join('veiculos', 'v', 'v.id', '=', 'm.id_veiculo')
            ->whereRaw('v.chave = m.chave')
            ->where('v.id_fornecedor', '=', $idFornecedor)
            ->whereIn('m.status', ['C', 'A'])
            ->count('m.id');

        return [
            'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim],
            'totais' => [
                'veiculos_ativos' => (int) ($detail['qtd_veiculos'] ?? 0),
                'valor_investido' => (float) ($detail['valor_investido'] ?? 0),
                'receita_gerada' => (float) ($detail['receita_gerada'] ?? 0),
                'comissao_pendente' => (float) ($detail['comissao_devida'] ?? 0),
                'comissao_paga' => (float) ($detail['comissao_paga'] ?? 0),
                'saldo' => (float) ($detail['saldo'] ?? 0),
                'manutencoes_abertas' => $manutencoesAbertas,
            ],
            'veiculos' => array_values($detail['veiculos'] ?? []),
            'chart' => $report['chart'] ?? ['labels' => [], 'datasets' => []],
        ];
    }

    public function listarCliente(string $recurso, int $idCliente, int $pagina, int $porPagina): array
    {
        return match ($recurso) {
            'contratos' => $this->paginarContratos($idCliente, $pagina, $porPagina),
            'locacoes' => $this->paginarLocacoes($idCliente, $pagina, $porPagina),
            'faturas' => $this->paginarFaturas($idCliente, $pagina, $porPagina),
            'multas' => $this->paginarMultas($idCliente, $pagina, $porPagina),
            'manutencoes' => $this->paginarManutencoesCliente($idCliente, $pagina, $porPagina),
            'veiculos' => $this->paginarArray($this->veiculosCliente($idCliente), $pagina, $porPagina),
            'indicacao' => ['data' => [(new PortalIndicacao())->resumo($idCliente)], 'pagination' => null],
            default => throw new \InvalidArgumentException('Recurso do cliente invalido.'),
        };
    }

    public function listarInvestidor(
        string $recurso,
        int $idFornecedor,
        int $pagina,
        int $porPagina,
        string $dataInicio,
        string $dataFim
    ): array {
        return match ($recurso) {
            'veiculos' => $this->paginarVeiculosInvestidor($idFornecedor, $pagina, $porPagina),
            'manutencoes' => $this->paginarManutencoesInvestidor($idFornecedor, $pagina, $porPagina),
            'comissoes' => $this->paginarComissoes($idFornecedor, $pagina, $porPagina, $dataInicio, $dataFim),
            'operacoes' => $this->paginarOperacoesInvestidor($idFornecedor, $pagina, $porPagina),
            'desempenho' => [
                'data' => [$this->dashboardInvestidor($idFornecedor, $dataInicio, $dataFim)],
                'pagination' => null,
            ],
            default => throw new \InvalidArgumentException('Recurso do investidor invalido.'),
        };
    }

    public function atualizarPerfil(string $perfil, int $entidadeId, array $dados): array
    {
        $antes = $this->perfil($perfil, $entidadeId);
        if (!$antes) {
            throw new \RuntimeException('Perfil nao encontrado.');
        }

        $permitidosComuns = [
            'email', 'telefone', 'cep', 'rua', 'numero', 'complemento',
            'bairro', 'cidade', 'estado', 'pais',
        ];
        $permitidos = $perfil === 'cliente'
            ? array_merge($permitidosComuns, ['preferred_locale'])
            : array_merge($permitidosComuns, ['telefone_secundario']);
        $entrada = array_intersect_key($dados, array_flip($permitidos));

        if (isset($entrada['email']) && !filter_var($entrada['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Informe um e-mail valido.');
        }

        $this->qb->beginTransaction();
        try {
            if ($perfil === 'cliente') {
                $camposCliente = array_intersect_key($entrada, array_flip([
                    'cep', 'rua', 'numero', 'complemento', 'bairro',
                    'cidade', 'estado', 'pais', 'preferred_locale',
                ]));
                if ($camposCliente !== []) {
                    $this->qb->table('clientes')
                        ->where('id', '=', $entidadeId)
                        ->update($camposCliente);
                }
                if (array_key_exists('email', $entrada)) {
                    $this->atualizarContatoPrincipal(
                        'contatos_emails',
                        $entidadeId,
                        'email',
                        trim((string) $entrada['email']),
                        ['recebe_email' => 'S']
                    );
                }
                if (array_key_exists('telefone', $entrada)) {
                    $this->atualizarContatoPrincipal(
                        'contatos_telefones',
                        $entidadeId,
                        'telefone',
                        trim((string) $entrada['telefone']),
                        ['whatsapp' => 'S', 'telegram' => 'N', 'sms' => 'S']
                    );
                }
            } else {
                $mapa = [
                    'email' => 'email',
                    'telefone' => 'tel1',
                    'telefone_secundario' => 'tel2',
                    'cep' => 'cep',
                    'rua' => 'rua',
                    'numero' => 'num',
                    'complemento' => 'complemento',
                    'bairro' => 'bairro',
                    'cidade' => 'cidade',
                    'estado' => 'estado',
                    'pais' => 'pais',
                ];
                $update = [];
                foreach ($entrada as $campo => $valor) {
                    if (isset($mapa[$campo])) {
                        $update[$mapa[$campo]] = trim((string) $valor);
                    }
                }
                if ($update !== []) {
                    $this->qb->table('fornecedores')
                        ->where('id', '=', $entidadeId)
                        ->where('investidor', '=', 1)
                        ->update($update);
                }
            }
            $this->qb->commit();
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }

        $depois = $this->perfil($perfil, $entidadeId);
        $alterados = [];
        foreach ($permitidos as $campo) {
            $de = (string) ($antes[$campo] ?? '');
            $para = (string) ($depois[$campo] ?? '');
            if ($de !== $para) {
                $alterados[] = ['campo' => $campo, 'de' => $de, 'para' => $para];
            }
        }

        return ['perfil' => $depois, 'alterados' => $alterados];
    }

    /**
     * Atualiza somente o contato principal e preserva os contatos secundarios.
     */
    private function atualizarContatoPrincipal(
        string $tabela,
        int $entidadeId,
        string $campo,
        string $valor,
        array $extras
    ): void {
        $principal = $this->qb->table($tabela)
            ->select(['id'])
            ->where('entidade_tipo', '=', 'cliente')
            ->where('entidade_id', '=', $entidadeId)
            ->where('principal', '=', 'S')
            ->first();

        if ($valor === '') {
            if ($principal) {
                $this->qb->table($tabela)
                    ->where('id', '=', (int) $principal['id'])
                    ->delete();
            }
            return;
        }

        $dados = array_merge([
            $campo => $valor,
            'descricao' => 'Portal',
            'principal' => 'S',
        ], $extras);

        if ($principal) {
            $this->qb->table($tabela)
                ->where('id', '=', (int) $principal['id'])
                ->update($dados);
            return;
        }

        $this->qb->table($tabela)->insert(array_merge($dados, [
            'entidade_tipo' => 'cliente',
            'entidade_id' => $entidadeId,
        ]));
    }

    public function financeiroDoCliente(int $idFinanceiro, int $idCliente): ?array
    {
        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $idFinanceiro)
            ->where('id_cliente', '=', $idCliente)
            ->where('tipo', '=', 'R')
            ->first();
    }

    private function paginarContratos(int $idCliente, int $pagina, int $porPagina): array
    {
        $total = $this->qb->table('contratos')->where('id_cliente', '=', $idCliente)->count();
        $rows = $this->qb->table('contratos')
            ->select(['id', 'codigo', 'data_ini', 'data_fim', 'data_renovacao', 'status', 'total_pagar'])
            ->where('id_cliente', '=', $idCliente)
            ->orderByDesc('data_ini')->paginate($pagina, $porPagina)->get();
        foreach ($rows as &$row) {
            $row['situacao'] = ($row['status'] ?? '') === 'A' ? 'aberto' : 'fechado';
        }
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarLocacoes(int $idCliente, int $pagina, int $porPagina): array
    {
        $total = $this->qb->table('locacoes')->where('id_cliente', '=', $idCliente)->count();
        $rows = $this->qb->table('locacoes')
            ->select(['id', 'codigo', 'status', 'data_saida', 'data_prevista', 'data_chegada', 'total_pagar'])
            ->where('id_cliente', '=', $idCliente)
            ->orderByDesc('data_saida')->paginate($pagina, $porPagina)->get();
        $labels = ['P' => 'reserva_pendente', 'R' => 'reserva', 'A' => 'aberta', 'F' => 'fechada'];
        foreach ($rows as &$row) {
            $row['situacao'] = $labels[$row['status']] ?? 'desconhecida';
        }
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarFaturas(int $idCliente, int $pagina, int $porPagina): array
    {
        $base = fn() => $this->qb->table('financeiro')
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R');
        $total = $base()->count();
        $rows = $this->qb->table('financeiro')
            ->select([
                'id', 'codigo', 'descricao', 'documento', 'parcela', 'total_parcelas',
                'pago', 'data_criada', 'data_venci', 'data_pago', 'valor_total',
                'id_contrato', 'id_locacao', 'id_multa',
            ])
            ->where('id_cliente', '=', $idCliente)
            ->where('tipo', '=', 'R')
            ->orderByDesc('data_venci')->paginate($pagina, $porPagina)->get();
        $hoje = DateHelper::todayForDatabase();
        foreach ($rows as &$row) {
            $row['situacao'] = $row['pago'] === 'S'
                ? 'paga'
                : (($row['data_venci'] ?? '') < $hoje ? 'vencida' : 'aberta');
            $row['pode_pagar'] = $row['pago'] !== 'S';
            $row['pode_emitir_recibo'] = $row['pago'] === 'S';
        }
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarMultas(int $idCliente, int $pagina, int $porPagina): array
    {
        $total = $this->qb->table('multas')->where('id_cliente', '=', $idCliente)->count();
        $rows = $this->qb->table('multas', 'm')
            ->select([
                'm.id', 'm.data_hora', 'm.data_vencimento', 'm.valor', 'm.pago',
                'm.descri', 'm.orgao_autuador', 'm.numero_ait', 'm.status_processamento',
                'v.placa', 'v.marca', 'v.modelo',
            ])
            ->leftJoinRaw('veiculos', 'v', 'v.id = m.id_veiculo AND v.chave = m.chave')
            ->where('m.id_cliente', '=', $idCliente)
            ->orderByDesc('m.data_hora')->paginate($pagina, $porPagina)->get();
        foreach ($rows as &$row) {
            $row['situacao'] = $row['pago'] === 'S' ? 'paga' : 'aberta';
        }
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarManutencoesCliente(int $idCliente, int $pagina, int $porPagina): array
    {
        $total = $this->qb->table('manutencoes')->where('id_cliente', '=', $idCliente)->count();
        $rows = $this->qb->table('manutencoes', 'm')
            ->select([
                'm.id', 'm.os', 'm.data_enviado', 'm.data_retorno', 'm.motivo',
                'm.obs_oficina', 'm.total_servicos', 'm.status',
                'v.placa', 'v.marca', 'v.modelo',
            ])
            ->leftJoinRaw('veiculos', 'v', 'v.id = m.id_veiculo AND v.chave = m.chave')
            ->where('m.id_cliente', '=', $idCliente)
            ->orderByDesc('m.data_enviado')->paginate($pagina, $porPagina)->get();
        $labels = ['C' => 'criada', 'A' => 'aberta', 'F' => 'fechada'];
        foreach ($rows as &$row) {
            $row['situacao'] = $labels[$row['status']] ?? 'desconhecida';
        }
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function veiculosCliente(int $idCliente): array
    {
        $map = [];
        $contratos = $this->qb->table('contratos_veiculos', 'cv')
            ->select([
                'v.id', 'v.foto', 'v.marca', 'v.modelo', 'v.ano', 'v.placa', 'v.cor',
                'cv.data_saida', 'cv.data_entrada',
            ])
            ->join('contratos', 'c', 'c.id', '=', 'cv.id_contrato')
            ->join('veiculos', 'v', 'v.id', '=', 'cv.id_veiculo')
            ->whereRaw('c.chave = cv.chave AND v.chave = cv.chave')
            ->where('c.id_cliente', '=', $idCliente)
            ->orderByDesc('cv.data_saida')->get();
        $locacoes = $this->qb->table('locacoes_veiculos', 'lv')
            ->select([
                'v.id', 'v.foto', 'v.marca', 'v.modelo', 'v.ano', 'v.placa', 'v.cor',
                'lv.data_saida', 'lv.data_entrada',
            ])
            ->join('locacoes', 'l', 'l.id', '=', 'lv.id_locacao')
            ->join('veiculos', 'v', 'v.id', '=', 'lv.id_veiculo')
            ->whereRaw('l.chave = lv.chave AND v.chave = lv.chave')
            ->where('l.id_cliente', '=', $idCliente)
            ->whereNotNull('lv.id_veiculo')
            ->orderByDesc('lv.data_saida')->get();

        foreach (array_merge($contratos, $locacoes) as $row) {
            $id = (int) $row['id'];
            if (!isset($map[$id])) {
                $map[$id] = $row + ['periodos' => []];
                unset($map[$id]['data_saida'], $map[$id]['data_entrada']);
            }
            $map[$id]['periodos'][] = [
                'saida' => $row['data_saida'],
                'entrada' => $row['data_entrada'],
            ];
        }
        return array_values($map);
    }

    private function paginarVeiculosInvestidor(int $idFornecedor, int $pagina, int $porPagina): array
    {
        $total = $this->qb->table('veiculos')->where('id_fornecedor', '=', $idFornecedor)->count();
        $rows = $this->qb->table('veiculos', 'v')
            ->select([
                'v.id', 'v.foto', 'v.marca', 'v.modelo', 'v.ano', 'v.placa', 'v.cor',
                'v.valor_compra', 'v.disponibilidade', 'g.nome AS grupo',
            ])
            ->leftJoinRaw('grupos', 'g', 'g.id = v.id_grupo AND g.chave = v.chave')
            ->where('v.id_fornecedor', '=', $idFornecedor)
            ->orderBy('v.placa', 'ASC')->paginate($pagina, $porPagina)->get();
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarManutencoesInvestidor(int $idFornecedor, int $pagina, int $porPagina): array
    {
        $base = fn() => $this->qb->table('manutencoes', 'm')
            ->join('veiculos', 'v', 'v.id', '=', 'm.id_veiculo')
            ->whereRaw('v.chave = m.chave')
            ->where('v.id_fornecedor', '=', $idFornecedor);
        $total = $base()->count('m.id');
        $rows = $this->qb->table('manutencoes', 'm')
            ->select([
                'm.id', 'm.os', 'm.data_enviado', 'm.data_retorno', 'm.motivo',
                'm.obs_oficina', 'm.total_servicos', 'm.status',
                'v.placa', 'v.marca', 'v.modelo',
            ])
            ->join('veiculos', 'v', 'v.id', '=', 'm.id_veiculo')
            ->whereRaw('v.chave = m.chave')
            ->where('v.id_fornecedor', '=', $idFornecedor)
            ->orderByDesc('m.data_enviado')->paginate($pagina, $porPagina)->get();
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarComissoes(
        int $idFornecedor,
        int $pagina,
        int $porPagina,
        string $dataInicio,
        string $dataFim
    ): array {
        $base = fn() => $this->qb->table('comissoes_investidores')
            ->where('id_fornecedor', '=', $idFornecedor)
            ->whereBetween('data_referencia', $dataInicio, $dataFim);
        $total = $base()->count();
        $rows = $this->qb->table('comissoes_investidores', 'ci')
            ->select([
                'ci.id', 'ci.tipo_origem', 'ci.valor_base', 'ci.comissao_tipo',
                'ci.valor_repasse_investidor', 'ci.status', 'ci.data_referencia',
                'ci.data_pagamento', 'v.placa', 'v.marca', 'v.modelo',
            ])
            ->leftJoinRaw('veiculos', 'v', 'v.id = ci.id_veiculo AND v.chave = ci.chave')
            ->where('ci.id_fornecedor', '=', $idFornecedor)
            ->whereBetween('ci.data_referencia', $dataInicio, $dataFim)
            ->orderByDesc('ci.data_referencia')->paginate($pagina, $porPagina)->get();
        return $this->resultadoPaginado($rows, $total, $pagina, $porPagina);
    }

    private function paginarOperacoesInvestidor(int $idFornecedor, int $pagina, int $porPagina): array
    {
        $veiculoIds = $this->qb->table('veiculos')
            ->where('id_fornecedor', '=', $idFornecedor)->pluck('id');
        if ($veiculoIds === []) {
            return $this->resultadoPaginado([], 0, $pagina, $porPagina);
        }

        $rows = [];
        foreach ($this->qb->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.id', 'lv.id_veiculo', 'lv.data_saida', 'lv.data_entrada',
                'l.status', 'v.placa', 'v.marca', 'v.modelo',
            ])
            ->join('locacoes', 'l', 'l.id', '=', 'lv.id_locacao')
            ->join('veiculos', 'v', 'v.id', '=', 'lv.id_veiculo')
            ->whereRaw('l.chave = lv.chave AND v.chave = lv.chave')
            ->whereIn('lv.id_veiculo', $veiculoIds)->get() as $row) {
            $rows[] = $row + ['tipo' => 'locacao'];
        }
        foreach ($this->qb->table('contratos_veiculos', 'cv')
            ->select([
                'cv.id', 'cv.id_veiculo', 'cv.data_saida', 'cv.data_entrada',
                'c.status', 'v.placa', 'v.marca', 'v.modelo',
            ])
            ->join('contratos', 'c', 'c.id', '=', 'cv.id_contrato')
            ->join('veiculos', 'v', 'v.id', '=', 'cv.id_veiculo')
            ->whereRaw('c.chave = cv.chave AND v.chave = cv.chave')
            ->whereIn('cv.id_veiculo', $veiculoIds)->get() as $row) {
            $rows[] = $row + ['tipo' => 'contrato'];
        }
        usort($rows, fn($a, $b) => strcmp((string) $b['data_saida'], (string) $a['data_saida']));
        foreach ($rows as &$row) {
            $fim = $row['data_entrada'] ?: DateHelper::todayForDatabase('Y-m-d H:i:s');
            $row['dias_ocupados'] = max(1, (int) ceil(
                ((strtotime((string) $fim) ?: 0) - (strtotime((string) $row['data_saida']) ?: 0)) / 86400
            ));
        }
        return $this->paginarArray($rows, $pagina, $porPagina);
    }

    private function atividadesCliente(int $idCliente): array
    {
        $atividades = [];
        $faturas = $this->qb->table('financeiro')
            ->select(['id', 'codigo', 'descricao', 'pago', 'data_pago', 'data_criada', 'valor_total'])
            ->where('id_cliente', '=', $idCliente)->where('tipo', '=', 'R')
            ->orderByDesc('updated_at')->limit(3)->get();
        foreach ($faturas as $fatura) {
            $atividades[] = [
                'tipo' => $fatura['pago'] === 'S' ? 'pagamento' : 'fatura',
                'titulo' => $fatura['pago'] === 'S' ? 'Pagamento confirmado' : 'Fatura disponivel',
                'descricao' => $fatura['descricao'] ?: ($fatura['codigo'] ?? ''),
                'valor' => (float) ($fatura['valor_total'] ?? 0),
                'data' => $fatura['data_pago'] ?: $fatura['data_criada'],
            ];
        }
        return $atividades;
    }

    private function paginarArray(array $rows, int $pagina, int $porPagina): array
    {
        $total = count($rows);
        $data = array_slice($rows, ($pagina - 1) * $porPagina, $porPagina);
        return $this->resultadoPaginado($data, $total, $pagina, $porPagina);
    }

    private function resultadoPaginado(array $data, int $total, int $pagina, int $porPagina): array
    {
        return [
            'data' => array_values($data),
            'pagination' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $porPagina)),
            ],
        ];
    }
}
