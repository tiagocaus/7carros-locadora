<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model Fornecedor
 *
 * Gerencia fornecedores da locadora, incluindo investidores
 * que disponibilizam veiculos para locacao com comissao.
 */
class Fornecedor extends Model
{
    use Auditable;

    /**
     * Retorna o nome da entidade para auditoria
     */
    public function getEntidadeAuditoria(): string
    {
        return 'Fornecedor';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    public function getCampoIdentificador(): string
    {
        return 'nome_rsocial';
    }

    /**
     * Lista todos os fornecedores do tenant
     *
     * @param string $chave Chave do tenant
     * @param bool $apenasInvestidores Filtrar apenas investidores
     * @return array Lista de fornecedores
     */
    public function listar(string $chave, bool $apenasInvestidores = false): array
    {
        $query = $this->qb->table('fornecedores');

        if ($apenasInvestidores) {
            $query->where('investidor', '=', 1);
        }

        return $query->orderBy('nome_rsocial', 'ASC')->get();
    }

    /**
     * Lista fornecedores do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param string $tipo Filtro por tipo: 'todos', 'fornecedor', 'investidor'
     * @return array Lista de fornecedores
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = '', string $tipo = 'todos'): array
    {
        $query = $this->qb->table('fornecedores');

        // Filtro por tipo
        if ($tipo === 'investidor') {
            $query->where('investidor', '=', 1);
        } elseif ($tipo === 'fornecedor') {
            $query->where('investidor', '=', 0);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de fornecedores do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @param string $tipo Filtro por tipo: 'todos', 'fornecedor', 'investidor'
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = '', string $tipo = 'todos'): int
    {
        $query = $this->qb->table('fornecedores');

        // Filtro por tipo
        if ($tipo === 'investidor') {
            $query->where('investidor', '=', 1);
        } elseif ($tipo === 'fornecedor') {
            $query->where('investidor', '=', 0);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca um fornecedor por ID
     *
     * @param int $id ID do fornecedor
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('fornecedores')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Lista investidores para select (usado em veiculos)
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return array Lista de investidores
     */
    public function listarInvestidoresSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial', 'nome_fantasia', 'cpf_cnpj'])
            ->where('investidor', '=', 1);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        $rows = $query
            ->orderBy('nome_rsocial', 'ASC')
            ->limit(50)
            ->get();

        return array_map(static function (array $row): array {
            $nome = trim((string) ($row['nome_rsocial'] ?? ''));
            $documento = trim((string) ($row['cpf_cnpj'] ?? ''));
            $texto = $nome;

            if ($documento !== '') {
                $texto .= ' (' . $documento . ')';
            }

            $row['nome'] = $nome;
            $row['text'] = $texto !== '' ? $texto : ('Investidor #' . ($row['id'] ?? ''));

            return $row;
        }, $rows);
    }

    /**
     * Lista fornecedores para select
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return array Lista de fornecedores
     */
    public function listarFornecedoresSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial AS nome', 'nome_fantasia', 'cpf_cnpj']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('nome_fantasia', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista fornecedores de carro (de_carro = 'S') para select
     *
     * @param string $chave Chave do tenant
     * @return array Lista de fornecedores
     */
    public function listarDeCarro(string $chave): array
    {
        return $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial', 'nome_fantasia', 'cpf_cnpj'])
            ->where('de_carro', '=', 'S')
            ->orderBy('nome_rsocial', 'ASC')
            ->get();
    }

    /**
     * Lista fornecedores de carro para select com busca server-side
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarDeCarroParaSelect(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial', 'nome_fantasia'])
            ->where('de_carro', '=', 'S');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereRaw('(nome_rsocial LIKE ? OR nome_fantasia LIKE ?)', [$searchTerm, $searchTerm]);
        }

        $resultados = $query->orderBy('nome_rsocial', 'ASC')->limit(50)->get();

        return array_map(function ($f) {
            return [
                'id' => $f['id'],
                'nome' => $f['nome_fantasia'] ?: $f['nome_rsocial'] ?: 'Fornecedor #' . $f['id']
            ];
        }, $resultados);
    }

    /**
     * Cria um novo fornecedor
     *
     * @param array $dados Dados do fornecedor
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('fornecedores')
            ->insert([
                'chave' => $dados['chave'],
                'tipo' => $dados['tipo'] ?? 'PJ',
                'de_carro' => $dados['de_carro'] ?? 'N',
                'cpf_cnpj' => $dados['cpf_cnpj'] ?? null,
                'nome_rsocial' => $dados['nome_rsocial'] ?? null,
                'nome_fantasia' => $dados['nome_fantasia'] ?? null,
                'rg_ie' => $dados['rg_ie'] ?? null,
                'ins_mun' => $dados['ins_mun'] ?? null,
                'cep' => $dados['cep'] ?? null,
                'rua' => $dados['rua'] ?? null,
                'num' => $dados['num'] ?? null,
                'complemento' => $dados['complemento'] ?? null,
                'pais' => $dados['pais'] ?? 'Brasil',
                'estado' => $dados['estado'] ?? null,
                'cidade' => $dados['cidade'] ?? null,
                'bairro' => $dados['bairro'] ?? null,
                'email' => $dados['email'] ?? null,
                'senha' => $dados['senha'] ?? null,
                'tel1' => $dados['tel1'] ?? null,
                'tel2' => $dados['tel2'] ?? null,
                'obs' => $dados['obs'] ?? null,
                'investidor' => isset($dados['investidor']) ? (int) $dados['investidor'] : 0,
                'split_gateway' => $dados['split_gateway'] ?? null,
                'split_gateway_conta' => $dados['split_gateway_conta'] ?? null,
                'pix_chave' => $dados['pix_chave'] ?? null,
                'pix_tipo' => $dados['pix_tipo'] ?? null,
                'banco_codigo' => $dados['banco_codigo'] ?? null,
                'banco_agencia' => $dados['banco_agencia'] ?? null,
                'banco_conta' => $dados['banco_conta'] ?? null,
                'banco_tipo' => $dados['banco_tipo'] ?? null,
            ]);
    }

    /**
     * Atualiza um fornecedor existente
     *
     * @param int $id ID do fornecedor
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $fornecedor = $this->buscarPorId($id);
        if (!$fornecedor) {
            throw new \InvalidArgumentException('Fornecedor nao encontrado');
        }

        $dadosUpdate = [];

        // Campos basicos
        $camposTexto = [
            'tipo', 'de_carro', 'cpf_cnpj', 'nome_rsocial', 'nome_fantasia',
            'rg_ie', 'ins_mun', 'cep', 'rua', 'num', 'complemento',
            'pais', 'estado', 'cidade', 'bairro', 'email', 'tel1', 'tel2', 'obs'
        ];

        foreach ($camposTexto as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        // Campos de investidor
        if (array_key_exists('investidor', $dados)) {
            $dadosUpdate['investidor'] = (int) $dados['investidor'];
        }

        $camposInvestidor = [
            'split_gateway', 'split_gateway_conta',
            'pix_chave', 'pix_tipo',
            'banco_codigo', 'banco_agencia', 'banco_conta', 'banco_tipo'
        ];

        foreach ($camposInvestidor as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo] ?: null;
            }
        }

        if (array_key_exists('senha', $dados) && $dados['senha'] !== '') {
            $dadosUpdate['senha'] = $dados['senha'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = now();

        return $this->qb
            ->table('fornecedores')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Busca investidor para login publico por email ou CPF/CNPJ.
     * Retorna ate dois registros para detectar ambiguidade.
     */
    public function buscarInvestidoresParaLogin(string $usuario): array
    {
        $usuario = trim($usuario);
        if ($usuario === '') {
            return [];
        }

        $documento = preg_replace('/\D/', '', $usuario);
        $query = $this->qb
            ->table('fornecedores')
            ->select(['id', 'nome_rsocial', 'nome_fantasia', 'email', 'senha', 'investidor'])
            ->where('investidor', '=', 1);

        if ($documento !== '' && preg_match('/^[\d.\/\-\s]+$/', $usuario) === 1) {
            $query->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') = ?",
                [$documento]
            );
        } elseif (filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', '=', $usuario);
        } else {
            return [];
        }

        return $query->limit(2)->get();
    }

    /**
     * Exclui um fornecedor
     *
     * @param int $id ID do fornecedor
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $fornecedor = $this->buscarPorId($id);
        if (!$fornecedor) {
            throw new \InvalidArgumentException('Fornecedor nao encontrado');
        }

        return $this->qb
            ->table('fornecedores')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se fornecedor tem vinculos que impedem exclusao
     *
     * @param int $id ID do fornecedor
     * @return array Lista de vinculos encontrados
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        // Verificar veiculos
        $veiculos = $this->qb
            ->table('veiculos')
            ->where('id_fornecedor', '=', $id)
            ->count();

        if ($veiculos > 0) {
            $vinculos[] = "{$veiculos} veiculo(s) vinculado(s)";
        }

        // Verificar financeiro
        $financeiro = $this->qb
            ->table('financeiro')
            ->where('id_fornecedor', '=', $id)
            ->count();

        if ($financeiro > 0) {
            $vinculos[] = "{$financeiro} lancamento(s) financeiro(s)";
        }

        // Verificar comissoes
        $comissoes = $this->qb
            ->table('comissoes_investidores')
            ->where('id_fornecedor', '=', $id)
            ->count();

        if ($comissoes > 0) {
            $vinculos[] = "{$comissoes} comissao(oes) de investidor";
        }

        return $vinculos;
    }
}
