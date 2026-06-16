<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;
use App\Helpers\FilialHelper;

/**
 * Model Financeiro
 *
 * Gerencia lancamentos financeiros (faturas/documentos).
 * Cada lancamento pode ter multiplos itens via financeiro_itens.
 *
 * Tipos:
 * - D = Despesa (contas a pagar)
 * - R = Receita (contas a receber)
 *
 * Status:
 * - pago = 'S' -> Pago
 * - pago = 'N' -> Pendente/Vencido
 *
 * Valores:
 * - valor_subtotal = Cache de SUM(financeiro_itens.valor), mantido por triggers
 * - juros = Juros por atraso
 * - multa = Multa por atraso
 * - desconto = Desconto aplicado
 * - valor_total = CALCULADO (valor_subtotal + juros + multa - desconto)
 */
class Financeiro extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o lancamento financeiro';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'descricao';
    }

    /**
     * Lista lancamentos do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param string $filialWhere Filtro de filial (opcional)
     * @param array $filialParams Parametros do filtro de filial
     * @param string $filialId Filtro de filial especifica (opcional)
     * @param string $ano Filtro de ano (opcional)
     * @param string $mes Filtro de mes (opcional)
     * @return array Lista de lancamentos
     */
    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $filialId = '',
        string $ano = '',
        string $mes = '',
        string $status = ''
    ): array {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.*',
                'c.nome_rsocial AS cliente_nome',
                'fo.nome_rsocial AS fornecedor_nome',
                'func.nome AS funcionario_nome',
                'pc.descricao_i18n AS plano_conta_descricao_i18n',
                'pc.hierarquia AS plano_conta_hierarquia',
                'fp.nome AS forma_pagamento_descricao',
                'mf.nome_fantasia AS filial_nome',
                'mf.currency_code AS filial_currency_code',
                'mf.locale AS filial_locale'
            ])
            ->selectSubquery(function ($q) {
                $q->table('financeiro_itens', 'fi')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('fi.id_financeiro = f.id');
            }, 'qtd_itens')
            ->selectSubquery(function ($q) {
                $q->table('nfse', 'nf')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('nf.id_financeiro = f.id AND nf.status != \'cancelada\'');
            }, 'tem_nfse')
            ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->leftJoin('funcionarios', 'func', 'f.id_funcionario', '=', 'func.id')
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('matrizes_filiais', 'mf', 'f.id_matriz_filial', '=', 'mf.id');

        // Filtro de busca (busca em todas as colunas visiveis + status por palavra-chave)
        $this->aplicarBuscaListagem($query, $search);

        // Filtro de filial (permissoes do usuario)
        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        // Filtro de filial especifica
        if (!empty($filialId) && is_numeric($filialId)) {
            $query->where('f.id_matriz_filial', '=', (int) $filialId);
        }

        // Filtro de ano (pela data_venci)
        if (!empty($ano) && is_numeric($ano)) {
            $query->whereRaw('YEAR(f.data_venci) = ?', [(int) $ano]);
        }

        // Filtro de mes (pela data_venci)
        if (!empty($mes) && is_numeric($mes) && $mes >= 1 && $mes <= 12) {
            $query->whereRaw('MONTH(f.data_venci) = ?', [(int) $mes]);
        }

        // Filtro de status (pago/em aberto/vencido/vence hoje)
        $this->aplicarFiltroStatus($query, $status);

        return $query
            ->orderByDesc('f.data_venci')
            ->orderByDesc('f.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de lancamentos com filtros
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string $filialWhere Filtro de filial
     * @param array $filialParams Parametros do filtro
     * @param string $filialId Filtro de filial especifica (opcional)
     * @param string $ano Filtro de ano (opcional)
     * @param string $mes Filtro de mes (opcional)
     * @return int Total de registros
     */
    public function contar(
        string $chave,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $filialId = '',
        string $ano = '',
        string $mes = '',
        string $status = ''
    ): int {
        // Mesmo schema da listagem (com JOINs) para que a busca por
        // cliente/fornecedor/funcionario funcione tambem na contagem.
        $query = $this->qb
            ->table('financeiro', 'f')
            ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->leftJoin('funcionarios', 'func', 'f.id_funcionario', '=', 'func.id');

        $this->aplicarBuscaListagem($query, $search);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        // Filtro de filial especifica
        if (!empty($filialId) && is_numeric($filialId)) {
            $query->where('f.id_matriz_filial', '=', (int) $filialId);
        }

        // Filtro de ano (pela data_venci)
        if (!empty($ano) && is_numeric($ano)) {
            $query->whereRaw('YEAR(f.data_venci) = ?', [(int) $ano]);
        }

        // Filtro de mes (pela data_venci)
        if (!empty($mes) && is_numeric($mes) && $mes >= 1 && $mes <= 12) {
            $query->whereRaw('MONTH(f.data_venci) = ?', [(int) $mes]);
        }

        // Filtro de status (pago/em aberto/vencido/vence hoje)
        $this->aplicarFiltroStatus($query, $status);

        return $query->count();
    }

    /**
     * Aplica filtro por status do lancamento.
     *
     * Valores aceitos:
     * - 'paid'      → pago = 'S'
     * - 'overdue'   → pago = 'N' AND data_venci < CURDATE()
     * - 'due_today' → pago = 'N' AND data_venci = CURDATE()
     * - 'open'      → pago = 'N' AND data_venci > CURDATE()
     * - ''          → sem filtro
     */
    private function aplicarFiltroStatus($query, string $status): void
    {
        switch ($status) {
            case 'paid':
                $query->where('f.pago', '=', 'S');
                break;
            case 'overdue':
                $query->where('f.pago', '=', 'N')->whereRaw('f.data_venci < CURDATE()');
                break;
            case 'due_today':
                $query->where('f.pago', '=', 'N')->whereRaw('f.data_venci = CURDATE()');
                break;
            case 'open':
                $query->where('f.pago', '=', 'N')->whereRaw('f.data_venci > CURDATE()');
                break;
        }
    }

    /**
     * Aplica filtro de busca textual em todas as colunas visiveis da listagem
     * (Seq, Descrição, Cliente/Fornec/Func, Vencimento, Valor) + Status via
     * palavras-chave (paga/vencida/pendente).
     *
     * Requer alias 'f' na tabela principal e LEFT JOINs com clientes (c),
     * fornecedores (fo) e funcionarios (func).
     */
    private function aplicarBuscaListagem($query, string $search): void
    {
        if (empty($search)) {
            return;
        }

        $term = '%' . $search . '%';
        $searchLower = mb_strtolower(trim($search));

        // Detecta palavra-chave de status e monta branch SQL adicional
        $statusBranch = null;
        if (in_array($searchLower, ['paga', 'pago', 'pagas'], true)) {
            $statusBranch = "f.pago = 'S'";
        } elseif (in_array($searchLower, ['vencida', 'vencidas', 'venceu', 'vencido'], true)) {
            $statusBranch = "f.pago = 'N' AND f.data_venci < CURDATE()";
        } elseif (in_array($searchLower, ['pendente', 'em aberto', 'aberto', 'a vencer'], true)) {
            $statusBranch = "f.pago = 'N' AND f.data_venci >= CURDATE()";
        }

        $query->whereNested(function ($q) use ($term, $statusBranch) {
            $q->where('f.descricao', 'LIKE', $term)
              ->orWhere('f.documento', 'LIKE', $term)
              ->orWhere('f.codigo', 'LIKE', $term)
              ->orWhereRaw('CAST(f.sequencia AS CHAR) LIKE ?', [$term])
              ->orWhere('c.nome_rsocial', 'LIKE', $term)
              ->orWhere('fo.nome_rsocial', 'LIKE', $term)
              ->orWhere('func.nome', 'LIKE', $term)
              ->orWhereRaw("DATE_FORMAT(f.data_venci, '%d/%m/%Y') LIKE ?", [$term])
              ->orWhereRaw('CAST(f.valor_total AS CHAR) LIKE ?', [$term]);

            if ($statusBranch !== null) {
                $q->orWhereRaw('(' . $statusBranch . ')');
            }
        });
    }

    /**
     * Busca um lancamento por ID
     *
     * @param int $id ID do lancamento
     * @return array|null Dados do lancamento ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.*',
                'c.nome_rsocial AS cliente_nome',
                'c.cpf_cnpj AS cliente_cpf_cnpj',
                'c.email AS cliente_email',
                'fo.nome_rsocial AS fornecedor_nome',
                'pc.descricao_i18n AS plano_conta_descricao_i18n',
                'pc.hierarquia AS plano_conta_hierarquia',
                'fp.nome AS forma_pagamento_descricao',
                'mf.nome_fantasia AS filial_nome',
                'mf.razao_social AS filial_razao_social',
                'mf.cpf_cnpj AS filial_cnpj',
                'ct.nome AS conta_descricao',
                'func.nome AS funcionario_nome'
            ])
            ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('matrizes_filiais', 'mf', 'f.id_matriz_filial', '=', 'mf.id')
            ->leftJoin('contas_bancarias', 'ct', 'f.id_conta', '=', 'ct.id')
            ->leftJoin('funcionarios', 'func', 'f.id_funcionario', '=', 'func.id')
            ->where('f.id', '=', $id)
            ->first();
    }

    /**
     * Busca um lancamento com seus itens
     *
     * @param int $id ID do lancamento
     * @return array|null Dados do lancamento com itens ou null
     */
    public function buscarPorIdComItens(int $id): ?array
    {
        $lancamento = $this->buscarPorId($id);

        if (!$lancamento) {
            return null;
        }

        // Carregar itens
        $financeiroItem = new FinanceiroItem();
        $lancamento['itens'] = $financeiroItem->listarComRelacionamentos($id);

        return $lancamento;
    }

    /**
     * Cria um novo lancamento
     *
     * @param array $dados Dados do lancamento
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        $parcela = !empty($dados['parcela']) ? (int) $dados['parcela'] : 0;
        $totalParcelasLancamento = !empty($dados['total_parcelas']) ? (int) $dados['total_parcelas'] : 0;
        $pago = $dados['pago'] ?? 'N';

        // Auto-calcular taxa para receitas com forma de pagamento
        if (($dados['tipo'] ?? 'D') === 'R' && !empty($dados['id_forma_pagamento']) && !isset($dados['valor_taxa'])) {
            $formaPagamento = (new FormaPagamento())->buscarPorId((int) $dados['id_forma_pagamento']);
            if ($formaPagamento) {
                $totalParcelas = max((int) ($dados['total_parcelas'] ?? 1), 1);
                $valorParcela = currency_parse($dados['valor_subtotal'] ?? 0);
                $dados['valor_taxa'] = $this->calcularTaxaParcela($formaPagamento, $valorParcela, $totalParcelas);
                $dados['taxa_percentual_snapshot'] = $formaPagamento['taxa_percentual_parcela'];
                $dados['taxa_fixa_snapshot'] = $formaPagamento['taxa_fixa'];
                $dados['taxa_fixa_parcela_snapshot'] = $formaPagamento['taxa_fixa_parcela'];
            }
        }

        return $this->qb
            ->table('financeiro')
            ->insert([
                'chave' => $dados['chave'],
                'sequencia' => $dados['sequencia'] ?? null,
                'codigo' => $dados['codigo'] ?? null,
                'id_matriz_filial' => !empty($dados['id_matriz_filial']) ? (int) $dados['id_matriz_filial'] : null,
                'id_cliente' => !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
                'id_fornecedor' => !empty($dados['id_fornecedor']) ? (int) $dados['id_fornecedor'] : null,
                'id_funcionario' => !empty($dados['id_funcionario']) ? (int) $dados['id_funcionario'] : null,
                'id_conta' => !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null,
                'id_forma_pagamento' => !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null,
                'id_plano_de_conta' => !empty($dados['id_plano_de_conta']) ? (int) $dados['id_plano_de_conta'] : null,
                'id_promissoria' => !empty($dados['id_promissoria']) ? (int) $dados['id_promissoria'] : null,
                'id_multa' => !empty($dados['id_multa']) ? (int) $dados['id_multa'] : null,
                'id_oficina' => !empty($dados['id_oficina']) ? (int) $dados['id_oficina'] : null,
                'tipo' => $dados['tipo'] ?? 'D',
                'pago' => $pago,
                'parcela' => $parcela,
                'total_parcelas' => $totalParcelasLancamento,
                'documento' => $dados['documento'] ?? null,
                'descricao' => $dados['descricao'] ?? null,
                'data_criada' => $dados['data_criada'] ?? date('Y-m-d'),
                'data_venci' => $dados['data_venci'] ?? date('Y-m-d'),
                'data_pago' => $pago === 'S' ? ($dados['data_pago'] ?? date('Y-m-d')) : null,
                'valor_subtotal' => currency_parse($dados['valor_subtotal'] ?? 0),
                'juros' => currency_parse($dados['juros'] ?? 0),
                'multa' => currency_parse($dados['multa'] ?? 0),
                'desconto' => currency_parse($dados['desconto'] ?? 0),
                'valor_total' => $this->calcularValorTotal($dados),
                'valor_taxa' => currency_parse($dados['valor_taxa'] ?? 0),
                'taxa_percentual_snapshot' => $dados['taxa_percentual_snapshot'] ?? null,
                'taxa_fixa_snapshot' => $dados['taxa_fixa_snapshot'] ?? null,
                'taxa_fixa_parcela_snapshot' => $dados['taxa_fixa_parcela_snapshot'] ?? null,
                'id_contrato' => !empty($dados['id_contrato']) ? (int) $dados['id_contrato'] : null,
                'id_locacao' => !empty($dados['id_locacao']) ? (int) $dados['id_locacao'] : null,
                'id_veiculo' => !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null,
            ]);
    }

    /**
     * Cria um lancamento completo (lancamento + itens + parcelas) de forma atomica.
     *
     * Orquestra em uma unica transacao:
     * 1. Geracao de sequencia (se nao informada)
     * 2. Criacao do lancamento principal
     * 3. Insercao de itens (se enviados)
     * 4. Criacao de parcelas adicionais + ajuste da primeira parcela (se parcelado)
     *
     * @param array $dados Dados do lancamento. Pode conter 'itens' e 'parcelas'.
     * @return int ID do lancamento criado
     */
    public function criarCompleto(array $dados): int
    {
        $chave = $dados['chave'];
        $parcelas = !empty($dados['parcelas']) && is_array($dados['parcelas']) ? $dados['parcelas'] : [];
        $totalParcelas = count($parcelas);
        $parcelasAdicionais = $totalParcelas > 1 ? array_slice($parcelas, 1) : [];
        $sequenciasParcelas = [];

        $idMatrizFilial = (int) ($dados['id_matriz_filial'] ?? 0);
        $quantidadeSequencias = (empty($dados['sequencia']) ? 1 : 0) + count($parcelasAdicionais);

        if ($idMatrizFilial > 0 && $quantidadeSequencias > 0) {
            $sequenciasReservadas = \App\Helpers\SequenciaHelper::proximasSequencias(
                $chave,
                $idMatrizFilial,
                'financeiro',
                $quantidadeSequencias
            );

            if (empty($dados['sequencia'])) {
                $dados['sequencia'] = array_shift($sequenciasReservadas);
            }

            $sequenciasParcelas = $sequenciasReservadas;
        }

        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            // Gerar sequencia se nao informada
            if (empty($dados['sequencia']) && $idMatrizFilial > 0) {
                $dados['sequencia'] = \App\Helpers\SequenciaHelper::proximaSequencia(
                    $chave,
                    $idMatrizFilial,
                    'financeiro'
                );
            }

            $id = $this->criar($dados);

            // Salvar itens se enviados
            if (!empty($dados['itens']) && is_array($dados['itens'])) {
                (new FinanceiroItem())->salvarTodos($id, $chave, $dados['itens']);
            }

            // Criar parcelas se enviadas
            if ($totalParcelas > 0) {
                // A primeira parcela ja foi criada (o lancamento principal)
                // Precisamos criar as demais (a partir do indice 1)
                if (!empty($parcelasAdicionais)) {
                    $lancamentoCriado = $this->buscarPorId($id);
                    $this->criarParcelas($id, $parcelasAdicionais, $lancamentoCriado, $sequenciasParcelas);
                }

                // Atualizar a primeira parcela com dados de parcelamento
                $valorPrimeiraParcela = (float) $parcelas[0]['valor'];

                $this->atualizar($id, [
                    'parcela' => 1,
                    'total_parcelas' => $totalParcelas,
                    'id_financeiro_origem' => null,
                    'valor_subtotal' => $valorPrimeiraParcela,
                    'valor_total' => $valorPrimeiraParcela
                ]);
            }

            $mysqli->commit();
            return $id;

        } catch (\Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza um lancamento existente
     *
     * @param int $id ID do lancamento
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $lancamento = $this->buscarPorId($id);
        if (!$lancamento) {
            throw new \InvalidArgumentException('Lancamento nao encontrado');
        }

        $dadosUpdate = [];

        // Relacionamentos
        if (isset($dados['id_matriz_filial'])) {
            $dadosUpdate['id_matriz_filial'] = !empty($dados['id_matriz_filial']) ? (int) $dados['id_matriz_filial'] : null;
        }
        if (isset($dados['id_cliente'])) {
            $dadosUpdate['id_cliente'] = !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null;
        }
        if (isset($dados['id_fornecedor'])) {
            $dadosUpdate['id_fornecedor'] = !empty($dados['id_fornecedor']) ? (int) $dados['id_fornecedor'] : null;
        }
        if (isset($dados['id_funcionario'])) {
            $dadosUpdate['id_funcionario'] = !empty($dados['id_funcionario']) ? (int) $dados['id_funcionario'] : null;
        }
        if (isset($dados['id_conta'])) {
            $dadosUpdate['id_conta'] = !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null;
        }
        if (isset($dados['id_forma_pagamento'])) {
            $dadosUpdate['id_forma_pagamento'] = !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null;
        }
        if (isset($dados['id_plano_de_conta'])) {
            $dadosUpdate['id_plano_de_conta'] = !empty($dados['id_plano_de_conta']) ? (int) $dados['id_plano_de_conta'] : null;
        }
        if (isset($dados['id_contrato'])) {
            $dadosUpdate['id_contrato'] = !empty($dados['id_contrato']) ? (int) $dados['id_contrato'] : null;
        }
        if (isset($dados['id_locacao'])) {
            $dadosUpdate['id_locacao'] = !empty($dados['id_locacao']) ? (int) $dados['id_locacao'] : null;
        }
        if (isset($dados['id_veiculo'])) {
            $dadosUpdate['id_veiculo'] = !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null;
        }

        // Dados basicos
        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = $dados['tipo'];
        }
        if (isset($dados['pago'])) {
            $dadosUpdate['pago'] = $dados['pago'];
            // Se marcou como pago e nao tem data_pago, usar hoje
            if ($dados['pago'] === 'S' && empty($dados['data_pago'])) {
                $dadosUpdate['data_pago'] = date('Y-m-d');
            }
        }
        if (array_key_exists('parcela', $dados)) {
            $dadosUpdate['parcela'] = (int) $dados['parcela'];
        }
        if (array_key_exists('documento', $dados)) {
            $dadosUpdate['documento'] = $dados['documento'];
        }
        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }

        // Datas
        if (isset($dados['data_venci'])) {
            $dadosUpdate['data_venci'] = $dados['data_venci'];
        }
        if (isset($dados['data_pago'])) {
            $dadosUpdate['data_pago'] = $dados['data_pago'];
        }

        // Valores de juros/multa/desconto
        if (isset($dados['juros'])) {
            $dadosUpdate['juros'] = currency_parse($dados['juros']);
        }
        if (isset($dados['multa'])) {
            $dadosUpdate['multa'] = currency_parse($dados['multa']);
        }
        if (isset($dados['desconto'])) {
            $dadosUpdate['desconto'] = currency_parse($dados['desconto']);
        }

        // Recalcular valor_total se algum valor foi alterado
        if (isset($dados['juros']) || isset($dados['multa']) || isset($dados['desconto'])) {
            $novosDados = array_merge($lancamento, $dadosUpdate);
            $dadosUpdate['valor_total'] = $this->calcularValorTotal($novosDados);
        }

        // Taxa de operadora
        if (isset($dados['valor_taxa'])) {
            $dadosUpdate['valor_taxa'] = currency_parse($dados['valor_taxa']);
        }
        if (array_key_exists('taxa_percentual_snapshot', $dados)) {
            $dadosUpdate['taxa_percentual_snapshot'] = $dados['taxa_percentual_snapshot'];
        }
        if (array_key_exists('taxa_fixa_snapshot', $dados)) {
            $dadosUpdate['taxa_fixa_snapshot'] = $dados['taxa_fixa_snapshot'];
        }
        if (array_key_exists('taxa_fixa_parcela_snapshot', $dados)) {
            $dadosUpdate['taxa_fixa_parcela_snapshot'] = $dados['taxa_fixa_parcela_snapshot'];
        }

        // Campos de parcelamento (usados ao criar/ajustar parcelas)
        if (array_key_exists('total_parcelas', $dados)) {
            $dadosUpdate['total_parcelas'] = (int) $dados['total_parcelas'];
        }
        if (array_key_exists('id_financeiro_origem', $dados)) {
            $dadosUpdate['id_financeiro_origem'] = $dados['id_financeiro_origem'];
        }

        // valor_subtotal e valor_total podem ser atualizados diretamente
        // quando estamos ajustando parcelas
        if (isset($dados['valor_subtotal'])) {
            $dadosUpdate['valor_subtotal'] = currency_parse($dados['valor_subtotal']);
        }
        if (isset($dados['valor_total']) && !isset($dados['juros']) && !isset($dados['multa']) && !isset($dados['desconto'])) {
            // Se valor_total foi passado explicitamente e nao ha juros/multa/desconto, usar direto
            $dadosUpdate['valor_total'] = currency_parse($dados['valor_total']);
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um lancamento
     *
     * @param int $id ID do lancamento
     * @return int Linhas afetadas
     */
    public function deletar(int $id): int
    {
        // Itens sao deletados automaticamente via FK CASCADE
        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Recalcula o valor_total de um lancamento
     *
     * Usado apos alteracoes em itens (trigger ja faz isso automaticamente)
     *
     * @param int $id ID do lancamento
     * @return float Novo valor_total
     */
    public function recalcularTotal(int $id): float
    {
        $lancamento = $this->buscarPorId($id);
        if (!$lancamento) {
            return 0.0;
        }

        // valor_subtotal = soma dos itens (cache)
        $financeiroItem = new FinanceiroItem();
        $somaItens = $financeiroItem->somarValores($id);

        // valor_total = valor_subtotal + juros + multa - desconto
        $valorTotal = $somaItens
            + (float) $lancamento['juros']
            + (float) $lancamento['multa']
            - (float) $lancamento['desconto'];

        $this->qb
            ->table('financeiro')
            ->where('id', '=', $id)
            ->update([
                'valor_subtotal' => $somaItens,
                'valor_total' => $valorTotal,
            ]);

        return $valorTotal;
    }

    /**
     * Verifica se um lancamento possui vinculos que impedem exclusao
     *
     * @param int $id ID do lancamento
     * @return array ['temVinculos' => bool, 'detalhes' => [...]]
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        // Promissorias vinculadas
        $promissorias = $this->qb
            ->table('promissorias')
            ->where('id_financeiro', '=', $id)
            ->count();

        if ($promissorias > 0) {
            $vinculos[] = "{$promissorias} promissoria(s)";
        }

        return [
            'temVinculos' => count($vinculos) > 0,
            'detalhes' => $vinculos,
        ];
    }

    /**
     * Lista lancamentos pendentes de um cliente
     *
     * @param int $idCliente ID do cliente
     * @return array Lista de lancamentos pendentes
     */
    public function listarPendentesCliente(int $idCliente): array
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_cliente', '=', $idCliente)
            ->where('pago', '=', 'N')
            ->where('tipo', '=', 'R')
            ->orderBy('data_venci', 'ASC')
            ->get();
    }

    /**
     * Lista lancamentos pendentes de um fornecedor
     *
     * @param int $idFornecedor ID do fornecedor
     * @return array Lista de lancamentos pendentes
     */
    public function listarPendentesFornecedor(int $idFornecedor): array
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_fornecedor', '=', $idFornecedor)
            ->where('pago', '=', 'N')
            ->where('tipo', '=', 'D')
            ->orderBy('data_venci', 'ASC')
            ->get();
    }

    /**
     * Gera proxima sequencia para o tenant
     *
     * @param string $chave Chave do tenant
     * @return int Proxima sequencia
     */
    public function gerarSequencia(string $chave): int
    {
        $maxSequencia = $this->qb
            ->table('financeiro')
            ->max('sequencia');

        return ($maxSequencia ?? 0) + 1;
    }

    /**
     * Calcula valor_total baseado nos dados fornecidos
     *
     * @param array $dados Dados do lancamento
     * @return float Valor total calculado
     */
    private function calcularValorTotal(array $dados): float
    {
        // valor_subtotal eh cache de SUM(financeiro_itens.valor); triggers
        // sincronizam ao inserir/atualizar/excluir itens. Aqui calculamos o
        // total inicial (antes da insercao dos itens) usando o que veio.
        $valorSubtotal = currency_parse($dados['valor_subtotal'] ?? 0);
        $juros = currency_parse($dados['juros'] ?? 0);
        $multa = currency_parse($dados['multa'] ?? 0);
        $desconto = currency_parse($dados['desconto'] ?? 0);

        return $valorSubtotal + $juros + $multa - $desconto;
    }

    /**
     * Lista clientes para select do formulario
     */
    public function listarClientesSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial AS nome', 'cpf_cnpj AS documento']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista fornecedores para select do formulario
     */
    public function listarFornecedoresSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial AS nome', 'cpf_cnpj AS documento']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista planos de contas para select
     */
    public function listarPlanosDeContasSelect(string $chave, string $tipo = '', string $search = ''): array
    {
        $query = $this->qb
            ->table('planos_de_contas')
            ->select([
                'id',
                'tipo',
                "CONCAT(hierarquia, ' - ', JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR'))) AS text"
            ])
            ->withGlobals();

        if (!empty($tipo)) {
            $query->where('tipo', '=', $tipo);
        }

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) LIKE ?", [$searchTerm])
                  ->orWhere('hierarquia', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('hierarquia', 'ASC')
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
     * Lista veiculos para select
     */
    public function listarVeiculosSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('veiculos')
            ->select(['id', 'placa', 'modelo']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('placa', 'LIKE', $searchTerm)
                  ->orWhere('modelo', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('placa', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista contas bancárias para select
     */
    public function listarContasBancariasSelect(string $chave): array
    {
        return $this->qb
            ->table('contas_bancarias')
            ->select(['id', 'nome'])
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista funcionarios para select do formulario
     */
    public function listarFuncionariosSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('funcionarios')
            ->select(['id', 'nome']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->limit(50)
            ->get();
    }

    // ==========================================
    // METODOS DE PARCELAMENTO
    // ==========================================

    /**
     * Lista parcelas de um lancamento (incluindo o proprio se for a origem)
     *
     * @param int $idOrigem ID do lancamento origem (primeira parcela)
     * @return array Lista de parcelas
     */
    public function listarParcelas(int $idOrigem): array
    {
        // Buscar todas as parcelas: a origem e as que apontam para ela
        // Exclui registros que nao sao parcelas (parcela = 0 ou NULL)
        return $this->qb
            ->table('financeiro')
            ->select(['id', 'parcela', 'total_parcelas', 'data_venci', 'valor_subtotal', 'pago', 'data_pago'])
            ->whereNested(function ($q) use ($idOrigem) {
                $q->where('id', '=', $idOrigem)
                  ->orWhere('id_financeiro_origem', '=', $idOrigem);
            })
            ->whereNotNull('parcela')
            ->where('parcela', '>', 0)
            ->orderBy('parcela', 'ASC')
            ->get();
    }

    /**
     * Conta o numero de parcelas de um lancamento
     *
     * @param int $idOrigem ID do lancamento origem
     * @return int Total de parcelas
     */
    public function contarParcelas(int $idOrigem): int
    {
        return $this->qb
            ->table('financeiro')
            ->whereNested(function ($q) use ($idOrigem) {
                $q->where('id', '=', $idOrigem)
                  ->orWhere('id_financeiro_origem', '=', $idOrigem);
            })
            ->count();
    }

    /**
     * Cria parcelas em lote a partir de um lancamento base
     *
     * @param int $idOrigem ID do lancamento que sera a primeira parcela
     * @param array $parcelas Array de parcelas a criar (exceto a primeira)
     * @param array $dadosBase Dados base herdados do lancamento original
     * @param array<int,int>|null $sequenciasReservadas Sequencias financeiras ja reservadas
     * @return array IDs das parcelas criadas
     */
    public function criarParcelas(int $idOrigem, array $parcelas, array $dadosBase, ?array $sequenciasReservadas = null): array
    {
        $idsGerados = [];
        $totalParcelas = count($parcelas) + 1; // +1 porque a primeira parcela eh o lancamento original

        // Nota: A atualizacao do lancamento original (parcela, total_parcelas, valor)
        // eh feita no Controller para consolidar a logica em um unico lugar

        // Pre-fetch forma de pagamento para calculo de taxa (antes do loop para evitar reset do QB)
        $formaPagamento = null;
        $isReceita = ($dadosBase['tipo'] ?? 'D') === 'R';
        if ($isReceita && !empty($dadosBase['id_forma_pagamento'])) {
            $formaPagamento = (new FormaPagamento())->buscarPorId((int) $dadosBase['id_forma_pagamento']);
        }

        // Criar demais parcelas
        foreach ($parcelas as $index => $parcela) {
            $numeroParcela = $index + 2; // Comeca em 2 (a 1 eh o original)

            // Calcular taxa para esta parcela
            $valorTaxa = 0;
            $taxaPercentualSnapshot = null;
            $taxaFixaSnapshot = null;
            $taxaFixaParcelaSnapshot = null;

            if ($formaPagamento) {
                $valorParcela = currency_parse($parcela['valor'] ?? 0);
                $valorTaxa = $this->calcularTaxaParcela($formaPagamento, $valorParcela, $totalParcelas);
                $taxaPercentualSnapshot = $formaPagamento['taxa_percentual_parcela'];
                $taxaFixaSnapshot = $formaPagamento['taxa_fixa'];
                $taxaFixaParcelaSnapshot = $formaPagamento['taxa_fixa_parcela'];
            }

            $sequencia = $sequenciasReservadas[$index] ?? null;
            if ($sequencia === null && !empty($dadosBase['id_matriz_filial'])) {
                // Fallback para chamadas antigas que nao reservam sequencias em lote.
                $sequencia = \App\Helpers\SequenciaHelper::proximaSequencia(
                    $dadosBase['chave'],
                    (int) $dadosBase['id_matriz_filial'],
                    'financeiro'
                );
            }

            $id = $this->qb
                ->table('financeiro')
                ->insert([
                    'chave' => $dadosBase['chave'],
                    'sequencia' => $sequencia,
                    'id_matriz_filial' => $dadosBase['id_matriz_filial'] ?? null,
                    'id_cliente' => $dadosBase['id_cliente'] ?? null,
                    'id_fornecedor' => $dadosBase['id_fornecedor'] ?? null,
                    'id_funcionario' => $dadosBase['id_funcionario'] ?? null,
                    'id_conta' => $dadosBase['id_conta'] ?? null,
                    'id_forma_pagamento' => $dadosBase['id_forma_pagamento'] ?? null,
                    'id_plano_de_conta' => $dadosBase['id_plano_de_conta'] ?? null,
                    'tipo' => $dadosBase['tipo'] ?? 'D',
                    'pago' => 'N',
                    'parcela' => $numeroParcela,
                    'total_parcelas' => $totalParcelas,
                    'id_financeiro_origem' => $idOrigem,
                    'documento' => $dadosBase['documento'] ?? null,
                    'descricao' => $dadosBase['descricao'] ?? null,
                    'data_criada' => $dadosBase['data_criada'] ?? date('Y-m-d'),
                    'data_venci' => $parcela['dataVenci'] ?? date('Y-m-d'),
                    'data_pago' => null,
                    'valor_subtotal' => currency_parse($parcela['valor'] ?? 0),
                    'juros' => 0,
                    'multa' => 0,
                    'desconto' => 0,
                    'valor_total' => currency_parse($parcela['valor'] ?? 0),
                    'valor_taxa' => $valorTaxa,
                    'taxa_percentual_snapshot' => $taxaPercentualSnapshot,
                    'taxa_fixa_snapshot' => $taxaFixaSnapshot,
                    'taxa_fixa_parcela_snapshot' => $taxaFixaParcelaSnapshot,
                    'id_contrato' => !empty($dadosBase['id_contrato']) ? (int) $dadosBase['id_contrato'] : null,
                    'id_locacao' => !empty($dadosBase['id_locacao']) ? (int) $dadosBase['id_locacao'] : null,
                    'id_veiculo' => !empty($dadosBase['id_veiculo']) ? (int) $dadosBase['id_veiculo'] : null,
                ]);

            $idsGerados[] = $id;
        }

        return $idsGerados;
    }

    /**
     * Atualiza parcelas em lote
     *
     * @param array $ids IDs das parcelas a atualizar
     * @param array $campos Campos a atualizar
     * @param string $chave Chave do tenant para validacao
     * @return int Quantidade de parcelas atualizadas
     */
    public function atualizarParcelasLote(array $ids, array $campos, string $chave): int
    {
        if (empty($ids) || empty($campos)) {
            return 0;
        }

        $dadosUpdate = [];

        if (isset($campos['data_venci'])) {
            $dadosUpdate['data_venci'] = $campos['data_venci'];
        }

        if (isset($campos['pago'])) {
            $dadosUpdate['pago'] = $campos['pago'];
            if ($campos['pago'] === 'S' && empty($campos['data_pago'])) {
                $dadosUpdate['data_pago'] = date('Y-m-d');
            } elseif ($campos['pago'] === 'N') {
                $dadosUpdate['data_pago'] = null;
            }
        }

        if (isset($campos['data_pago'])) {
            $dadosUpdate['data_pago'] = $campos['data_pago'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('financeiro')
            ->whereIn('id', $ids)
            ->update($dadosUpdate);
    }

    /**
     * Exclui parcelas em lote
     *
     * @param array $ids IDs das parcelas a excluir
     * @param string $chave Chave do tenant para validacao
     * @return int Quantidade de parcelas excluidas
     */
    public function excluirParcelasLote(array $ids, string $chave): int
    {
        if (empty($ids)) {
            return 0;
        }

        // Nao permitir excluir a primeira parcela (origem)
        // Apenas parcelas que tem id_financeiro_origem preenchido
        return $this->qb
            ->table('financeiro')
            ->whereIn('id', $ids)
            ->whereNotNull('id_financeiro_origem') // Protege a primeira parcela
            ->delete();
    }

    /**
     * Verifica se um lancamento eh a primeira parcela (origem)
     *
     * @param int $id ID do lancamento
     * @return bool True se for origem
     */
    public function ehParcelaOrigem(int $id): bool
    {
        $lancamento = $this->qb
            ->table('financeiro')
            ->select(['id_financeiro_origem', 'total_parcelas'])
            ->where('id', '=', $id)
            ->first();

        if (!$lancamento) {
            return false;
        }

        // Eh origem se: tem total_parcelas > 1 e id_financeiro_origem eh null
        return $lancamento['total_parcelas'] > 1 && $lancamento['id_financeiro_origem'] === null;
    }

    /**
     * Busca o ID da parcela origem de um lancamento
     *
     * @param int $id ID do lancamento
     * @return int|null ID da origem ou null se nao for parcela
     */
    public function buscarIdOrigem(int $id): ?int
    {
        $lancamento = $this->qb
            ->table('financeiro')
            ->select(['id', 'id_financeiro_origem'])
            ->where('id', '=', $id)
            ->first();

        if (!$lancamento) {
            return null;
        }

        // Se tem origem, retorna ela. Se nao tem mas eh parcelado, ele mesmo eh a origem
        return $lancamento['id_financeiro_origem'] ?? $id;
    }

    /**
     * Calcula a taxa da operadora para uma parcela individual
     *
     * @param array $formaPagamento Dados da forma de pagamento (taxa_fixa, taxa_fixa_parcela, taxa_percentual_parcela)
     * @param float $valorParcela Valor desta parcela
     * @param int $totalParcelas Total de parcelas do lancamento
     * @return float Taxa calculada para esta parcela
     */
    public function calcularTaxaParcela(array $formaPagamento, float $valorParcela, int $totalParcelas): float
    {
        $totalParcelas = max($totalParcelas, 1);
        $taxaFixaDiluida = ((float) ($formaPagamento['taxa_fixa'] ?? 0)) / $totalParcelas;
        $taxaFixaParcela = (float) ($formaPagamento['taxa_fixa_parcela'] ?? 0);
        $taxaPercentual = $valorParcela * (((float) ($formaPagamento['taxa_percentual_parcela'] ?? 0)) / 100);

        return round($taxaFixaDiluida + $taxaFixaParcela + $taxaPercentual, 2);
    }

    /**
     * Conta faturas vencidas (nao pagas e com data de vencimento anterior a hoje)
     */
    public function contarVencidas(): int
    {
        return $this->qb
            ->table('financeiro')
            ->where('pago', '=', 'N')
            ->whereRaw('data_venci < CURDATE()')
            ->count();
    }

    /**
     * Lista faturas vencidas para a tela de notificacoes.
     * Inclui receitas e despesas — a coluna 'tipo' indica qual.
     */
    public function listarParaNotificacoes(int $limit = 25, int $offset = 0): array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id', 'f.codigo', 'f.sequencia', 'f.descricao', 'f.tipo',
                'f.data_venci', 'f.valor_total',
                'c.nome_rsocial AS cliente_nome',
                'fo.nome_rsocial AS fornecedor_nome',
            ])
            ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->where('f.pago', '=', 'N')
            ->whereRaw('f.data_venci < CURDATE()')
            ->orderBy('f.data_venci', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Resumo financeiro para o dashboard.
     *
     * Receita/despesa = somatorio de pagamentos efetuados no mes corrente (data_pago).
     * Vencidas = receitas pendentes com data_venci anterior a hoje.
     * maintenance_cost_pct fica como TODO ate identificarmos o plano de contas de manutencao.
     */
    public function dashboardFinancialSummary(string $chave): array
    {
        $row = $this->qb
            ->table('financeiro')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipo='R' AND pago='S' AND DATE_FORMAT(data_pago, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN valor_total ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN tipo='D' AND pago='S' AND DATE_FORMAT(data_pago, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN valor_total ELSE 0 END), 0) AS expenses,
                COALESCE(SUM(CASE WHEN tipo='R' AND pago='N' AND data_venci < CURDATE() THEN valor_total ELSE 0 END), 0) AS overdue_total,
                COALESCE(SUM(CASE WHEN tipo='R' AND pago='N' AND data_venci < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue_count
            ")
            ->first();

        $revenue = (float) ($row['revenue'] ?? 0);
        $expenses = (float) ($row['expenses'] ?? 0);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'balance' => $revenue - $expenses,
            'maintenance_cost_pct' => 0.0,
            'overdue_total' => (float) ($row['overdue_total'] ?? 0),
            'overdue_count' => (int) ($row['overdue_count'] ?? 0),
        ];
    }

    /**
     * Top N clientes com debitos vencidos (receitas em atraso).
     */
    public function dashboardOverdueAccounts(string $chave, int $limit = 5): array
    {
        $rows = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COALESCE(cl.nome_rsocial, "—") AS cliente, COALESCE(SUM(f.valor_total), 0) AS valor')
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->where('f.tipo', '=', 'R')
            ->where('f.pago', '=', 'N')
            ->whereRaw('f.data_venci < CURDATE()')
            ->whereNotNull('f.id_cliente')
            ->groupBy(['f.id_cliente', 'cl.nome_rsocial'])
            ->orderByRaw('valor DESC')
            ->limit($limit)
            ->get();

        return array_map(function ($r) {
            return [
                'cliente' => $r['cliente'],
                'valor' => (float) $r['valor'],
            ];
        }, $rows);
    }

    /**
     * Despesas a vencer nos proximos N dias.
     */
    public function dashboardUpcomingDue(string $chave, int $limit = 5, int $days = 30): array
    {
        $rows = $this->qb
            ->table('financeiro')
            ->select(['descricao', 'valor_total', 'data_venci'])
            ->where('tipo', '=', 'D')
            ->where('pago', '=', 'N')
            ->whereRaw('data_venci BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)', [$days])
            ->orderBy('data_venci', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(function ($r) {
            return [
                'descricao' => $r['descricao'] ?: '—',
                'valor' => (float) ($r['valor_total'] ?? 0),
            ];
        }, $rows);
    }
}
