<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;
use App\Core\Auth;
use App\Core\Database;
use App\Helpers\CurrencyHelper;
use App\Helpers\DateHelper;

/**
 * Model Promissoria
 *
 * Gerencia promissorias com parcelas armazenadas na propria tabela.
 * Cada linha representa uma parcela, agrupadas pelo codigo_base.
 *
 * Estrutura do codigo:
 * - codigo: PRO1010513-2 (codigo completo com parcela)
 * - codigo_base: PRO1010513 (codigo base para agrupamento)
 *
 * Status:
 * - pago = 'S' -> Pago
 * - pago = 'N' -> Pendente
 *
 * Relacionamentos:
 * - id_cliente -> clientes
 * - id_matriz_filial -> matrizes_filiais
 */
class Promissoria extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'a promissoria';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'codigo';
    }

    /**
     * Gera codigo base unico para promissoria
     * Formato: PRO + 7-8 digitos aleatorios
     *
     * @return string Ex: PRO1010513
     */
    public function gerarCodigoBase(): string
    {
        $chave = Auth::chave();
        $maxTentativas = 10;

        for ($i = 0; $i < $maxTentativas; $i++) {
            // Gera numero aleatorio de 7-8 digitos
            $numero = mt_rand(1000000, 99999999);
            $codigoBase = 'PRO' . $numero;

            // Verifica se ja existe
            $existe = $this->qb
                ->table('promissorias')
                ->where('chave', '=', $chave)
                ->where('codigo_base', '=', $codigoBase)
                ->count();

            if ($existe === 0) {
                return $codigoBase;
            }
        }

        // Fallback: usa timestamp para garantir unicidade
        return 'PRO' . \App\Helpers\DateHelper::timestamp() . mt_rand(100, 999);
    }

    /**
     * Lista promissorias AGRUPADAS por codigo base
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param string $filialWhere Filtro de filial (opcional)
     * @param array $filialParams Parametros do filtro de filial
     * @param string $status Filtro de status: 'S' = Quitado, 'N' = Pendente, '' = Todos
     * @return array Lista de promissorias agrupadas
     */
    public function listarAgrupado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): array {
        $offset = ($page - 1) * $perPage;

        // Subquery para agrupar por codigo_base
        $sql = "
            SELECT
                p.codigo_base,
                MAX(p.id_cliente) AS id_cliente,
                MAX(p.id_matriz_filial) AS id_matriz_filial,
                MAX(p.codigo_contrato_locacao) AS codigo_contrato_locacao,
                MAX(p.obs) AS obs,
                MAX(c.nome_rsocial) AS cliente_nome,
                MAX(c.cpf_cnpj) AS cliente_cpf_cnpj,
                MAX(mf.nome_fantasia) AS filial_nome,
                SUM(p.valor_parcela) AS valor_total,
                COUNT(*) AS qtd_parcelas,
                SUM(CASE WHEN p.pago = 'S' THEN 1 ELSE 0 END) AS qtd_pagas,
                (
                    SELECT asn.id
                    FROM assinaturas asn
                    WHERE asn.chave = p.chave
                      AND asn.codigo_promissoria = p.codigo_base
                    ORDER BY asn.created_at DESC
                    LIMIT 1
                ) AS id_assinatura,
                CASE
                    WHEN SUM(CASE WHEN p.pago = 'N' THEN 1 ELSE 0 END) = 0 THEN 'S'
                    ELSE 'N'
                END AS status_quitado,
                MIN(CASE WHEN p.pago = 'N' THEN p.data_vencimento ELSE NULL END) AS proximo_vencimento,
                MAX(p.created_at) AS created_at
            FROM promissorias p
            LEFT JOIN clientes c ON p.id_cliente = c.id
            LEFT JOIN matrizes_filiais mf ON p.id_matriz_filial = mf.id
            WHERE p.chave = ?
            AND p.codigo_base IS NOT NULL
        ";

        $params = [$chave];

        // Filtro de busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $sql .= " AND (c.nome_rsocial LIKE ? OR c.cpf_cnpj LIKE ? OR p.codigo_base LIKE ? OR p.codigo_contrato_locacao LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filtro de filial
        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'p.id_matriz_filial', $filialWhere);
            $sql .= " AND " . $filialWherePrefixed;
            $params = array_merge($params, $filialParams);
        }

        $sql .= " GROUP BY p.codigo_base";

        // Filtro de status (aplicado apos o GROUP BY via HAVING)
        if ($status === 'S') {
            $sql .= " HAVING status_quitado = 'S'";
        } elseif ($status === 'N') {
            $sql .= " HAVING status_quitado = 'N'";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de promissorias agrupadas
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string $filialWhere Filtro de filial
     * @param array $filialParams Parametros do filtro
     * @param string $status Filtro de status
     * @return int Total de registros agrupados
     */
    public function contarAgrupado(
        string $chave,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): int {
        $sql = "
            SELECT COUNT(*) FROM (
                SELECT
                    p.codigo_base,
                    CASE
                        WHEN SUM(CASE WHEN p.pago = 'N' THEN 1 ELSE 0 END) = 0 THEN 'S'
                        ELSE 'N'
                    END AS status_quitado
                FROM promissorias p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                WHERE p.chave = ?
                AND p.codigo_base IS NOT NULL
        ";

        $params = [$chave];

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $sql .= " AND (c.nome_rsocial LIKE ? OR c.cpf_cnpj LIKE ? OR p.codigo_base LIKE ? OR p.codigo_contrato_locacao LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'p.id_matriz_filial', $filialWhere);
            $sql .= " AND " . $filialWherePrefixed;
            $params = array_merge($params, $filialParams);
        }

        $sql .= " GROUP BY p.codigo_base";

        if ($status === 'S') {
            $sql .= " HAVING status_quitado = 'S'";
        } elseif ($status === 'N') {
            $sql .= " HAVING status_quitado = 'N'";
        }

        $sql .= ") AS subquery";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca todas as parcelas de uma promissoria pelo codigo base
     *
     * @param string $codigoBase Ex: PRO1010513
     * @return array Lista de parcelas com dados do cliente e filial
     */
    public function buscarPorCodigoBase(string $codigoBase): array
    {
        return $this->qb
            ->table('promissorias', 'p')
            ->select([
                'p.*',
                'c.nome_rsocial AS cliente_nome',
                'c.cpf_cnpj AS cliente_cpf_cnpj',
                'c.rg_ie AS cliente_rg',
                'c.preferred_locale AS cliente_preferred_locale',
                'mf.nome_fantasia AS filial_nome',
                'mf.razao_social AS filial_razao_social',
                'mf.cpf_cnpj AS filial_cnpj'
            ])
            ->leftJoin('clientes', 'c', 'p.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'mf', 'p.id_matriz_filial', '=', 'mf.id')
            ->where('p.codigo_base', '=', $codigoBase)
            ->orderBy('p.numero_parcela', 'ASC')
            ->get();
    }

    /**
     * Busca dados resumidos de uma promissoria pelo codigo base
     *
     * @param string $codigoBase Ex: PRO1010513
     * @return array|null Dados resumidos ou null
     */
    public function buscarResumoPorCodigoBase(string $codigoBase): ?array
    {
        $parcelas = $this->buscarPorCodigoBase($codigoBase);

        if (empty($parcelas)) {
            return null;
        }

        $primeiraParcela = $parcelas[0];
        $totalPago = 0;
        $totalPendente = 0;

        foreach ($parcelas as $p) {
            if ($p['pago'] === 'S') {
                $totalPago += (float) $p['valor_parcela'];
            } else {
                $totalPendente += (float) $p['valor_parcela'];
            }
        }

        $clienteEmail = '';
        $clienteTelefone = '';

        if (!empty($primeiraParcela['id_cliente'])) {
            $emailPrincipal = (new ContatoEmail())->getPrincipal('cliente', (int) $primeiraParcela['id_cliente']);
            $telefonePrincipal = (new ContatoTelefone())->getPrincipal('cliente', (int) $primeiraParcela['id_cliente']);
            $clienteEmail = $emailPrincipal['email'] ?? '';
            $clienteTelefone = $telefonePrincipal['telefone'] ?? '';
        }

        return [
            'codigo_base' => $codigoBase,
            'id_cliente' => $primeiraParcela['id_cliente'],
            'id_matriz_filial' => $primeiraParcela['id_matriz_filial'],
            'codigo_contrato_locacao' => $primeiraParcela['codigo_contrato_locacao'],
            'obs' => $primeiraParcela['obs'],
            'cliente_nome' => $primeiraParcela['cliente_nome'],
            'cliente_cpf_cnpj' => $primeiraParcela['cliente_cpf_cnpj'],
            'cliente_preferred_locale' => $primeiraParcela['cliente_preferred_locale'] ?? null,
            'cliente_email' => $clienteEmail,
            'cliente_telefone' => $clienteTelefone,
            'filial_nome' => $primeiraParcela['filial_nome'],
            'chave' => $primeiraParcela['chave'],
            'parcelas' => $parcelas,
            'qtd_parcelas' => count($parcelas),
            'qtd_pagas' => count(array_filter($parcelas, fn($p) => $p['pago'] === 'S')),
            'valor_total' => $totalPago + $totalPendente,
            'total_pago' => $totalPago,
            'total_pendente' => $totalPendente,
            'quitado' => $totalPendente === 0.0,
        ];
    }

    /**
     * Busca promissoria por codigo base em contexto publico, sem depender da sessao atual.
     *
     * Usado em links publicos como /assinar/{codigo}, onde o visitante pode nao
     * ter sessao ou pode estar logado em outro tenant no mesmo navegador.
     */
    public function buscarPublicoPorCodigo(string $codigoBase): ?array
    {
        $row = $this->qb
            ->table('promissorias')
            ->withoutChave()
            ->select(['chave'])
            ->where('codigo_base', '=', $codigoBase)
            ->orderBy('id', 'ASC')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->buscarResumoComChaveTemporaria($codigoBase, (string) $row['chave']);
    }

    /**
     * Executa buscarResumoPorCodigoBase usando a chave do registro publico e restaura a sessao.
     */
    private function buscarResumoComChaveTemporaria(string $codigoBase, string $chave): ?array
    {
        $hadChave = isset($_SESSION['chave']);
        $previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;
        CurrencyHelper::clearCache();
        DateHelper::clearCache();

        try {
            return $this->buscarResumoPorCodigoBase($codigoBase);
        } finally {
            if ($hadChave) {
                $_SESSION['chave'] = $previousChave;
            } else {
                unset($_SESSION['chave']);
            }
            CurrencyHelper::clearCache();
            DateHelper::clearCache();
        }
    }

    /**
     * Busca uma parcela especifica por ID
     *
     * @param int $id ID da parcela
     * @return array|null Dados da parcela ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('promissorias', 'p')
            ->select([
                'p.*',
                'c.nome_rsocial AS cliente_nome',
                'c.cpf_cnpj AS cliente_cpf_cnpj',
                'mf.nome_fantasia AS filial_nome'
            ])
            ->leftJoin('clientes', 'c', 'p.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'mf', 'p.id_matriz_filial', '=', 'mf.id')
            ->where('p.id', '=', $id)
            ->first();
    }

    /**
     * Busca uma parcela especifica com todos os dados para impressao
     *
     * @param int $idParcela ID da parcela
     * @return array|null Dados completos da parcela ou null
     */
    public function buscarParcelaCompleta(int $idParcela): ?array
    {
        return $this->qb
            ->table('promissorias', 'p')
            ->select([
                'p.*',
                'c.nome_rsocial AS cliente_nome',
                'c.cpf_cnpj AS cliente_cpf_cnpj',
                'c.rg_ie AS cliente_rg',
                'c.rua AS cliente_endereco',
                'c.numero AS cliente_numero',
                'c.bairro AS cliente_bairro',
                'c.cidade AS cliente_cidade',
                'c.estado AS cliente_estado',
                'c.cep AS cliente_cep',
                'mf.nome_fantasia AS filial_nome',
                'mf.razao_social AS filial_razao_social',
                'mf.cpf_cnpj AS filial_cnpj'
            ])
            ->leftJoin('clientes', 'c', 'p.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'mf', 'p.id_matriz_filial', '=', 'mf.id')
            ->where('p.id', '=', $idParcela)
            ->first();
    }

    /**
     * Cria promissoria com todas as parcelas
     *
     * @param array $dados Dados da promissoria:
     *   - id_cliente (obrigatorio)
     *   - id_matriz_filial (obrigatorio)
     *   - valor_total (obrigatorio)
     *   - primeiro_vencimento (obrigatorio, Y-m-d)
     *   - num_parcelas (obrigatorio)
     *   - intervalo_dias (opcional, default 30)
     *   - codigo_contrato_locacao (opcional)
     *   - obs (opcional)
     * @return string Codigo base gerado
     */
    public function criarComParcelas(array $dados): string
    {
        $chave = Auth::chave();
        $codigoBase = $this->gerarCodigoBase();

        $numParcelas = (int) ($dados['num_parcelas'] ?? 1);
        $valorTotal = currency_parse($dados['valor_total']);
        $valorParcela = round($valorTotal / $numParcelas, 2);
        $intervaloDias = (int) ($dados['intervalo_dias'] ?? 30);
        $dataVenci = new \DateTime($dados['primeiro_vencimento']);

        // Ajustar ultima parcela para compensar arredondamento
        $somaAnteriores = $valorParcela * ($numParcelas - 1);
        $valorUltimaParcela = round($valorTotal - $somaAnteriores, 2);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            for ($i = 1; $i <= $numParcelas; $i++) {
                $valor = ($i === $numParcelas) ? $valorUltimaParcela : $valorParcela;
                $codigo = $codigoBase . '-' . $i;

                $this->qb
                    ->table('promissorias')
                    ->insert([
                        'chave' => $chave,
                        'codigo' => $codigo,
                        'id_cliente' => (int) $dados['id_cliente'],
                        'id_matriz_filial' => (int) $dados['id_matriz_filial'],
                        'codigo_contrato_locacao' => $dados['codigo_contrato_locacao'] ?? null,
                        'numero_parcela' => $i,
                        'total_parcelas' => $numParcelas,
                        'valor_parcela' => $valor,
                        'data_vencimento' => $dataVenci->format('Y-m-d'),
                        'data_criada' => today(),
                        'pago' => 'N',
                        'obs' => $dados['obs'] ?? null,
                    ]);

                // Avancar para proxima data de vencimento
                $dataVenci->modify("+{$intervaloDias} days");
            }

            $pdo->commit();
            return $codigoBase;

        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Adiciona nova parcela a uma promissoria existente
     *
     * @param string $codigoBase Codigo base da promissoria
     * @param array $dadosParcela Dados da nova parcela:
     *   - valor_parcela (obrigatorio)
     *   - data_vencimento (obrigatorio, Y-m-d)
     * @return int ID da nova parcela
     */
    public function adicionarParcela(string $codigoBase, array $dadosParcela): int
    {
        $chave = Auth::chave();

        // Buscar dados da promissoria existente
        $parcelaExistente = $this->qb
            ->table('promissorias')
            ->where('chave', '=', $chave)
            ->where('codigo_base', '=', $codigoBase)
            ->orderByDesc('numero_parcela')
            ->first();

        if (!$parcelaExistente) {
            throw new \Exception('Promissoria nao encontrada');
        }

        $novaParcela = (int) $parcelaExistente['numero_parcela'] + 1;
        $novoTotal = (int) $parcelaExistente['total_parcelas'] + 1;
        $codigo = $codigoBase . '-' . $novaParcela;

        // Atualizar total_parcelas de todas as parcelas existentes
        $this->qb
            ->table('promissorias')
            ->where('codigo_base', '=', $codigoBase)
            ->update(['total_parcelas' => $novoTotal]);

        // Inserir nova parcela
        return $this->qb
            ->table('promissorias')
            ->insert([
                'chave' => $chave,
                'codigo' => $codigo,
                'id_cliente' => $parcelaExistente['id_cliente'],
                'id_matriz_filial' => $parcelaExistente['id_matriz_filial'],
                'codigo_contrato_locacao' => $parcelaExistente['codigo_contrato_locacao'],
                'numero_parcela' => $novaParcela,
                'total_parcelas' => $novoTotal,
                'valor_parcela' => currency_parse($dadosParcela['valor_parcela']),
                'data_vencimento' => $dadosParcela['data_vencimento'],
                'data_criada' => today(),
                'pago' => 'N',
                'obs' => $parcelaExistente['obs'],
            ]);
    }

    /**
     * Atualiza uma parcela especifica
     *
     * @param int $id ID da parcela
     * @param array $dados Dados a atualizar (valor_parcela, data_vencimento)
     * @return int Linhas afetadas
     */
    public function atualizarParcela(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['valor_parcela'])) {
            $dadosUpdate['valor_parcela'] = currency_parse($dados['valor_parcela']);
        }
        if (isset($dados['data_vencimento'])) {
            $dadosUpdate['data_vencimento'] = $dados['data_vencimento'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('promissorias')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Remove uma parcela especifica
     *
     * @param int $id ID da parcela
     * @return int Linhas afetadas
     */
    public function excluirParcela(int $id): int
    {
        $parcela = $this->buscarPorId($id);

        if (!$parcela) {
            return 0;
        }

        $codigoBase = $parcela['codigo_base'];

        // Excluir a parcela
        $result = $this->qb
            ->table('promissorias')
            ->where('id', '=', $id)
            ->delete();

        // Atualizar total_parcelas das demais
        $this->qb
            ->table('promissorias')
            ->where('codigo_base', '=', $codigoBase)
            ->update([
                'total_parcelas' => $this->qb->raw('total_parcelas - 1')
            ]);

        return $result;
    }

    /**
     * Atualiza dados gerais de todas as parcelas do mesmo codigo base
     *
     * @param string $codigoBase Codigo base da promissoria
     * @param array $dados Dados a atualizar (id_cliente, id_matriz_filial, codigo_contrato_locacao, obs)
     * @return int Linhas afetadas
     */
    public function atualizarDadosGerais(string $codigoBase, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['id_cliente'])) {
            $dadosUpdate['id_cliente'] = !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null;
        }
        if (isset($dados['id_matriz_filial'])) {
            $dadosUpdate['id_matriz_filial'] = !empty($dados['id_matriz_filial']) ? (int) $dados['id_matriz_filial'] : null;
        }
        if (isset($dados['codigo_contrato_locacao'])) {
            $dadosUpdate['codigo_contrato_locacao'] = $dados['codigo_contrato_locacao'] ?: null;
        }
        if (array_key_exists('obs', $dados)) {
            $dadosUpdate['obs'] = $dados['obs'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('promissorias')
            ->where('codigo_base', '=', $codigoBase)
            ->update($dadosUpdate);
    }

    /**
     * Marca parcela individual como paga
     *
     * @param int $id ID da parcela
     * @param string $dataPagamento Data do pagamento (Y-m-d)
     * @return int Linhas afetadas
     */
    public function marcarParcelaPaga(int $id, string $dataPagamento): int
    {
        return $this->qb
            ->table('promissorias')
            ->where('id', '=', $id)
            ->update([
                'pago' => 'S',
                'data_pagamento' => $dataPagamento
            ]);
    }

    /**
     * Marca TODAS as parcelas como pagas
     *
     * @param string $codigoBase Codigo base da promissoria
     * @param string $dataPagamento Data do pagamento (Y-m-d)
     * @return int Linhas afetadas
     */
    public function marcarTodasPagas(string $codigoBase, string $dataPagamento): int
    {
        return $this->qb
            ->table('promissorias')
            ->where('codigo_base', '=', $codigoBase)
            ->where('pago', '=', 'N')
            ->update([
                'pago' => 'S',
                'data_pagamento' => $dataPagamento
            ]);
    }

    /**
     * Exclui todas as parcelas de uma promissoria pelo codigo base
     *
     * @param string $codigoBase Codigo base da promissoria
     * @return int Linhas afetadas
     */
    public function excluirPorCodigoBase(string $codigoBase): int
    {
        (new Assinatura())->excluirPorPromissoria($codigoBase);

        return $this->qb
            ->table('promissorias')
            ->where('codigo_base', '=', $codigoBase)
            ->delete();
    }

    /**
     * Verifica se todas as parcelas estao pagas
     *
     * @param string $codigoBase Codigo base da promissoria
     * @return bool True se quitada
     */
    public function verificarQuitacao(string $codigoBase): bool
    {
        $chave = Auth::chave();

        $pendentes = $this->qb
            ->table('promissorias')
            ->where('chave', '=', $chave)
            ->where('codigo_base', '=', $codigoBase)
            ->where('pago', '=', 'N')
            ->count();

        return $pendentes === 0;
    }

    /**
     * Retorna conexão mysqli para uso em Services
     *
     * @return \mysqli
     */
    public function getMysqliConnection(): \mysqli
    {
        return $this->getMysqli();
    }
}
