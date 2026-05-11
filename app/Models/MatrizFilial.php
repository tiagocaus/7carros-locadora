<?php

namespace App\Models;

use App\Traits\Auditable;
use NumberFormatter;
use IntlDateFormatter;

/**
 * Model MatrizFilial
 *
 * Gerencia operações CRUD na tabela matrizes_filiais
 */
class MatrizFilial extends Model
{
    use Auditable;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'a matriz/filial';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'razao_social';
    }

    /**
     * Lista todas as matrizes/filiais do tenant atual
     *
     * @param string|null $where Condição WHERE adicional
     * @param array $params Parâmetros para prepared statement
     * @param string|null $orderBy Ordenação
     * @return array Lista de matrizes/filiais
     */
    public function listar(?string $where = null, array $params = [], ?string $orderBy = 'razao_social ASC'): array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['id', 'chave', 'logo', 'tipo', 'razao_social', 'nome_fantasia', 'cpf_cnpj', 'cidade', 'estado', 'email', 'celular', 'currency_code', 'locale']);

        if (!empty($where)) {
            $query->whereRaw($where, $params);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->get();
    }

    /**
     * Busca o registro da matriz (tipo='M') do tenant atual
     *
     * @return array|null Dados da matriz ou null
     */
    public function buscarMatriz(): ?array
    {
        return $this->qb
            ->table('matrizes_filiais')
            ->where('tipo', '=', 'M')
            ->first();
    }

    /**
     * Busca uma matriz/filial por ID
     *
     * @param int $id ID da matriz/filial
     * @return array|null Dados ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('matrizes_filiais')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria uma nova matriz/filial
     *
     * @param array $dados Dados da matriz/filial
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        // Definir tipo padrão se não fornecido
        if (!isset($dados['tipo'])) {
            $dados['tipo'] = 'M'; // Matriz
        }

        // Definir valores padrão para configurações
        $defaults = [
            'locale' => 'pt_BR',
            'currency_code' => 'BRL',
            'date_format' => 'd/m/Y H:i:s',
            'sequencia_locacoes' => 1,
            'sequencia_contratos' => 1,
            'sequencia_financeiro' => 1,
            'notificacao_sms' => 'N',
            'notificacao_email' => 'N',
            'notificacao_whatsapp' => 'N',
            'impressao_variavel_negrito' => 'N',
            'impressao_remover_tarja_amarela' => 'N',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($dados[$key])) {
                $dados[$key] = $value;
            }
        }

        $id = $this->qb
            ->table('matrizes_filiais')
            ->insert($dados);

        // Nova arquitetura multi-moeda: cria linhas zeradas em grupos_precos_filiais
        // pra cada grupo existente do tenant, garantindo que todo grupo tenha tabela
        // de preços nessa nova filial desde o primeiro acesso.
        (new GrupoPrecoFilial())->garantirEntriesParaFilial($id);

        return $id;
    }

    /**
     * Atualiza uma matriz/filial existente
     *
     * @param int $id ID da matriz/filial
     * @param array $dados Dados para atualizar
     * @return int Número de linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        return $this->qb
            ->table('matrizes_filiais')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui uma matriz/filial (delete real, sem soft delete)
     *
     * @param int $id ID da matriz/filial
     * @return int Número de linhas afetadas
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('matrizes_filiais')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Busca matrizes/filiais por termo de pesquisa
     *
     * @param string $termo Termo para buscar
     * @return array Lista encontrada
     */
    public function buscar(string $termo, ?string $filialWhere = null, array $filialParams = []): array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['id', 'razao_social AS text', 'razao_social AS nome', 'nome_fantasia', 'currency_code', 'locale']);

        // Aplicar filtro de filiais permitidas
        if (!empty($filialWhere) && $filialWhere !== '1=1') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        // Aplicar filtro de busca se fornecido
        if (!empty($termo)) {
            $searchTerm = "%{$termo}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('razao_social', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        return $query->orderBy('razao_social', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista matrizes/filiais com paginação e busca
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @param string|null $search Termo de busca (opcional)
     * @return array Lista da página
     */
    public function listarPaginado(int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['id', 'chave', 'logo', 'tipo', 'razao_social', 'nome_fantasia', 'cpf_cnpj', 'cidade', 'estado', 'email', 'celular', 'currency_code', 'locale']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('razao_social', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('cidade', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('razao_social', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de matrizes/filiais (com filtro de busca opcional)
     *
     * @param string|null $search Termo de busca (opcional)
     * @return int Total
     */
    public function contar(?string $search = null): int
    {
        $query = $this->qb
            ->table('matrizes_filiais');

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('razao_social', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('cidade', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Incrementa e retorna a próxima sequência
     *
     * @param int $id ID da matriz/filial
     * @param string $tipo Tipo de sequência (locacoes, contratos, financeiro)
     * @return int Próximo número da sequência
     */
    public function incrementarSequencia(int $id, string $tipo): int
    {
        $coluna = "sequencia_{$tipo}";

        // Buscar sequência atual
        $matriz = $this->buscarPorId($id);
        if (!$matriz) {
            throw new \RuntimeException("Matriz/Filial não encontrada: {$id}");
        }

        $proximoNumero = (int) ($matriz[$coluna] ?? 1);

        // Incrementar no banco
        $this->atualizar($id, [$coluna => $proximoNumero + 1]);

        return $proximoNumero;
    }

    /**
     * Formata um valor monetário usando o locale da matriz/filial
     *
     * @param float $valor Valor a formatar
     * @param int|null $matrizId ID da matriz/filial (null usa a primeira do tenant)
     * @return string Valor formatado
     */
    public function formatarMoeda(float $valor, ?int $matrizId = null): string
    {
        $matriz = $matrizId ? $this->buscarPorId($matrizId) : $this->listar()[0] ?? null;

        if (!$matriz) {
            return number_format($valor, 2, ',', '.');
        }

        $locale = $matriz['locale'] ?? 'pt_BR';
        $currency = $matriz['currency_code'] ?? 'BRL';

        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($valor, $currency);
        }

        // Fallback se NumberFormatter não disponível
        return $currency . ' ' . number_format($valor, 2, ',', '.');
    }

    /**
     * Formata uma data usando o locale da matriz/filial
     *
     * @param string $data Data a formatar (Y-m-d ou Y-m-d H:i:s)
     * @param int|null $matrizId ID da matriz/filial
     * @param bool $comHora Incluir hora na formatação
     * @return string Data formatada
     */
    public function formatarData(string $data, ?int $matrizId = null, bool $comHora = false): string
    {
        $matriz = $matrizId ? $this->buscarPorId($matrizId) : $this->listar()[0] ?? null;

        if (!$matriz) {
            return $comHora ? date('d/m/Y H:i:s', strtotime($data)) : date('d/m/Y', strtotime($data));
        }

        $locale = $matriz['locale'] ?? 'pt_BR';
        $formato = $matriz['date_format'] ?? 'd/m/Y H:i:s';

        // Se não quer hora, remover parte de hora do formato
        if (!$comHora) {
            $formato = preg_replace('/\s*H:i(:s)?/', '', $formato);
        }

        $timestamp = strtotime($data);

        if (class_exists('IntlDateFormatter')) {
            $dateType = IntlDateFormatter::SHORT;
            $timeType = $comHora ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE;
            $formatter = new IntlDateFormatter($locale, $dateType, $timeType);
            return $formatter->format($timestamp);
        }

        // Fallback
        return date($formato, $timestamp);
    }

    /**
     * Verifica vínculos que impedem a exclusão
     *
     * @param int $id ID da matriz/filial
     * @return array ['temVinculos' => bool, 'detalhes' => array]
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];
        $temVinculos = false;

        // Verificar clientes vinculados
        $countClientes = $this->qb
            ->table('clientes')
            ->where('id_matriz_filial', '=', $id)
            ->count();

        if ($countClientes > 0) {
            $vinculos['clientes'] = $countClientes;
            $temVinculos = true;
        }

        // Verificar funcionários vinculados
        $countFuncionarios = $this->qb
            ->table('funcionarios')
            ->where('id_matriz_filial', '=', $id)
            ->count();

        if ($countFuncionarios > 0) {
            $vinculos['funcionarios'] = $countFuncionarios;
            $temVinculos = true;
        }

        // Verificar veículos vinculados
        $countVeiculos = $this->qb
            ->table('veiculos')
            ->where('id_matriz_filial', '=', $id)
            ->count();

        if ($countVeiculos > 0) {
            $vinculos['veiculos'] = $countVeiculos;
            $temVinculos = true;
        }

        return [
            'temVinculos' => $temVinculos,
            'detalhes' => $vinculos
        ];
    }

    /**
     * Busca configuração de moeda da filial principal do usuário logado
     *
     * @return array|null ['locale' => string, 'currency_code' => string]
     */
    public function buscarConfigMoeda(): ?array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['locale', 'currency_code']);

        // Usa filial principal do usuário se disponível
        if (!empty($_SESSION['id_matriz_filial'])) {
            $query->withoutChave()
                  ->where('id', '=', $_SESSION['id_matriz_filial']);
        } else {
            // Fallback: prioriza matriz (tipo='M')
            $query->orderBy('tipo', 'DESC');
        }

        return $query->first();
    }

    /**
     * Busca configuração de data da filial principal do usuário logado
     *
     * @return array|null ['date_format' => string, 'datetime_format' => string, 'locale' => string]
     */
    public function buscarConfigData(): ?array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['date_format', 'datetime_format', 'locale']);

        // Usa filial principal do usuário se disponível
        if (!empty($_SESSION['id_matriz_filial'])) {
            $query->withoutChave()
                  ->where('id', '=', $_SESSION['id_matriz_filial']);
        } else {
            // Fallback: prioriza matriz (tipo='M')
            $query->orderBy('tipo', 'DESC');
        }

        return $query->first();
    }

    /**
     * Lista matrizes/filiais para select (dropdown)
     *
     * @param string|null $where Condição WHERE adicional
     * @param array $params Parâmetros para prepared statement
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarParaSelect(?string $where = null, array $params = [], string $search = ''): array
    {
        $query = $this->qb
            ->table('matrizes_filiais')
            ->select(['id', 'razao_social', 'nome_fantasia']);

        if (!empty($where) && $where !== '1=1') {
            $query->whereRaw($where, $params);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereRaw('(razao_social LIKE ? OR nome_fantasia LIKE ?)', [$searchTerm, $searchTerm]);
        }

        $resultados = $query->orderBy('razao_social', 'ASC')->limit(50)->get();

        // Processar resultados para extrair id e nome
        $result = [];
        foreach ($resultados as $row) {
            $nome = $row['razao_social'] ?? $row['nome_fantasia'] ?? 'Matriz/Filial #' . $row['id'];
            $result[] = [
                'id' => $row['id'],
                'nome' => $nome
            ];
        }

        return $result;
    }

    /**
     * Busca dados da empresa pelo chave do tenant (para paginas publicas)
     *
     * @param string $chave Chave do tenant
     * @return array|null Dados da empresa
     */
    public function buscarDadosEmpresaPorChave(string $chave): ?array
    {
        return $this->qb
            ->table('matrizes_filiais')
            ->select([
                'nome_fantasia',
                'razao_social',
                'cpf_cnpj',
                'celular',
                'email',
                'cidade',
                'estado'
            ])
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->where('tipo', '=', 'M')
            ->first();
    }

    /**
     * Busca dados da empresa/filial para impressao
     *
     * @param int|null $filialId ID da filial especifica (opcional)
     * @return array|null Dados da empresa ou null
     */
    public function buscarDadosEmpresa(?int $filialId = null): ?array
    {
        // Primeiro tenta buscar a filial especifica
        if ($filialId) {
            $filial = $this->qb
                ->table('matrizes_filiais')
                ->where('id', '=', $filialId)
                ->first();

            if ($filial) {
                return $filial;
            }
        }

        // Se nao encontrou, busca a matriz
        return $this->qb
            ->table('matrizes_filiais')
            ->where('tipo', '=', 'M')
            ->first();
    }
}
