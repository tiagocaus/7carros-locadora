<?php

namespace App\Models;

use App\Config\Planos;
use App\Traits\DetectsCrossTenant;

/**
 * Model Funcionario
 *
 * Gerencia operações CRUD na tabela funcionarios
 */
class Funcionario extends Model
{
    use DetectsCrossTenant;

    /**
     * Resolve o plano que deve ser herdado por um novo funcionario.
     *
     * O plano vem exclusivamente do usuario autenticado, nunca do formulario.
     *
     * @param array<string, mixed>|null $usuarioAutenticado
     */
    public static function planoParaNovoCadastro(?array $usuarioAutenticado): string
    {
        $plano = strtoupper(trim((string) ($usuarioAutenticado['plano'] ?? '')));

        if (!Planos::existe($plano)) {
            throw new \UnexpectedValueException(
                'Não foi possível identificar um plano válido para o usuário autenticado.'
            );
        }

        return $plano;
    }

    /**
     * Agrupa funcionarios sem plano pelo unico plano valido de cada tenant.
     *
     * @param array<int, array<string, mixed>> $funcionarios
     * @return array<string, array<int, int>> IDs agrupados pelo plano herdado
     */
    public static function agruparIdsParaNormalizacaoPlano(array $funcionarios): array
    {
        $planosPorTenant = [];
        foreach ($funcionarios as $funcionario) {
            $chave = (string) ($funcionario['chave'] ?? '');
            $plano = strtoupper(trim((string) ($funcionario['plano'] ?? '')));

            if ($chave !== '' && Planos::existe($plano)) {
                $planosPorTenant[$chave][$plano] = true;
            }
        }

        $idsPorPlano = [];
        foreach ($funcionarios as $funcionario) {
            if (trim((string) ($funcionario['plano'] ?? '')) !== '') {
                continue;
            }

            $chave = (string) ($funcionario['chave'] ?? '');
            $planosValidos = array_keys($planosPorTenant[$chave] ?? []);
            if (count($planosValidos) !== 1) {
                continue;
            }

            $idsPorPlano[$planosValidos[0]][] = (int) $funcionario['id'];
        }

        return $idsPorPlano;
    }

    /**
     * Lista todos os funcionários do tenant atual
     *
     * @param string|null $where Condição WHERE adicional
     * @param array $params Parâmetros para prepared statement
     * @param string|null $orderBy Ordenação (ex: 'nome ASC')
     * @return array Lista de funcionários
     */
    public function listar(?string $where = null, array $params = [], ?string $orderBy = 'nome ASC'): array
    {
        $query = $this->qb
            ->table('funcionarios', 'f')
            ->select([
                'f.id',
                'f.nome',
                'f.usuario',
                'f.email',
                'f.status',
                'f.foto',
                'f.id_role',
                'r.name AS role_name',
                'r.name AS funcao',
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id');

        if (!empty($where)) {
            $query->whereRaw($where, $params);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->get();
    }

    /**
     * Busca um funcionário por ID
     *
     * @param int $id ID do funcionário
     * @return array|null Dados do funcionário ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('funcionarios', 'f')
            ->select([
                'f.*',
                'r.name AS role_name',
                'r.name AS funcao',
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->where('f.id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo funcionário
     *
     * @param array $dados Dados do funcionário
     * @return int ID do funcionário criado
     */
    public function criar(array $dados): int
    {
        // Definir status padrão se não fornecida
        if (!isset($dados['status'])) {
            $dados['status'] = 'A'; // Ativo
        }

        // Hash da senha se fornecida
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_ARGON2ID);
        }

        return $this->qb
            ->table('funcionarios')
            ->insert($dados);
    }

    /**
     * Atualiza um funcionário existente
     *
     * @param int $id ID do funcionário
     * @param array $dados Dados para atualizar
     * @return int Número de linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        // Hash da senha se fornecida
        if (isset($dados['senha']) && !empty($dados['senha'])) {
            $dados['senha'] = password_hash($dados['senha'], PASSWORD_ARGON2ID);
        } else {
            // Remover senha se não fornecida (não atualizar)
            unset($dados['senha']);
        }

        return $this->qb
            ->table('funcionarios')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui um funcionário (hard delete)
     *
     * @param int $id ID do funcionário
     * @return int Número de linhas afetadas
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('funcionarios')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Busca funcionários por termo de pesquisa
     *
     * @param string $termo Termo para buscar em nome, email ou usuário
     * @return array Lista de funcionários encontrados
     */
    public function buscar(string $termo): array
    {
        $searchTerm = "%{$termo}%";

        return $this->qb
            ->table('funcionarios', 'f')
            ->select([
                'f.id',
                'f.nome',
                'f.usuario',
                'f.email',
                'f.status',
                'f.foto',
                'r.name AS role_name',
                'r.name AS funcao',
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('f.nome', 'LIKE', $searchTerm)
                  ->orWhere('f.email', 'LIKE', $searchTerm)
                  ->orWhere('f.usuario', 'LIKE', $searchTerm);
            })
            ->orderBy('f.nome', 'ASC')
            ->get();
    }

    /**
     * Lista funcionários com paginação e busca
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @param string|null $search Termo de busca (opcional)
     * @return array Lista de funcionários da página
     */
    public function listarPaginado(int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        $chave = $_SESSION['chave'] ?? null;

        $query = $this->qb
            ->table('funcionarios', 'f')
            ->select([
                'f.id',
                'f.nome',
                'f.usuario',
                'f.email',
                'f.status',
                'f.foto',
                'f.id_role',
                'COALESCE(r.name, \'-\') as role_name',
                'COALESCE(r.name, \'-\') as funcao'
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->withoutChave();

        if ($chave) {
            $query->where('f.chave', '=', $chave);
        }

        // Excluir usuarios de suporte da listagem
        $query->where('f.usuario', 'NOT LIKE', 'suporte%');

        // Se houver termo de busca, adicionar condição WHERE
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('f.nome', 'LIKE', $searchTerm)
                  ->orWhere('f.email', 'LIKE', $searchTerm)
                  ->orWhere('f.usuario', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('f.nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de funcionários (com filtro de busca opcional)
     *
     * @param string|null $search Termo de busca (opcional)
     * @return int Total de funcionários
     */
    public function contar(?string $search = null): int
    {
        $query = $this->qb
            ->table('funcionarios')
            ->where('usuario', 'NOT LIKE', 'suporte%');

        // Se houver termo de busca, adicionar condição WHERE
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm)
                  ->orWhere('usuario', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Retorna IDs das filiais que o funcionário tem acesso
     *
     * @param int $funcionarioId ID do funcionário
     * @return array Lista de IDs das filiais
     */
    public function getFiliaisPermitidas(int $funcionarioId): array
    {
        return $this->qb
            ->table('funcionarios_filiais')
            ->where('id_funcionario', '=', $funcionarioId)
            ->pluck('id_matriz_filial');
    }

    /**
     * Sincroniza filiais permitidas (remove antigas e adiciona novas)
     *
     * @param int $funcionarioId ID do funcionário
     * @param array $filiaisIds Array de IDs das filiais
     * @return void
     */
    public function sincronizarFiliais(int $funcionarioId, array $filiaisIds): void
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            throw new \RuntimeException('Chave do tenant não encontrada');
        }

        $this->qb->beginTransaction();

        try {
            // Remove todas as filiais existentes
            $this->qb
                ->table('funcionarios_filiais')
                ->where('id_funcionario', '=', $funcionarioId)
                ->delete();

            // Insere as novas filiais (chave adicionada automaticamente pelo QueryBuilder)
            foreach ($filiaisIds as $filialId) {
                if (!empty($filialId)) {
                    $this->qb
                        ->table('funcionarios_filiais')
                        ->insert([
                            'id_funcionario' => $funcionarioId,
                            'id_matriz_filial' => (int) $filialId,
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
     * Busca funcionário com filiais permitidas
     *
     * @param int $id ID do funcionário
     * @return array|null Dados do funcionário com 'filiais_permitidas'
     */
    public function buscarPorIdComFiliais(int $id): ?array
    {
        $funcionario = $this->buscarPorId($id);

        if ($funcionario) {
            $funcionario['filiais_permitidas'] = $this->getFiliaisPermitidas($id);
        }

        return $funcionario;
    }

    /**
     * Conta contratos vinculados ao funcionário
     *
     * @param int $id ID do funcionário
     * @return int Total de contratos
     */
    public function contarContratos(int $id): int
    {
        return $this->qb
            ->table('contratos')
            ->where('id_funcionario', '=', $id)
            ->count();
    }

    /**
     * Conta locações vinculadas ao funcionário
     *
     * @param int $id ID do funcionário
     * @return int Total de locações
     */
    public function contarLocacoes(int $id): int
    {
        return $this->qb
            ->table('locacoes')
            ->where('id_funcionario', '=', $id)
            ->count();
    }

    /**
     * Conta registros financeiros vinculados ao funcionário
     *
     * @param int $id ID do funcionário
     * @return int Total de registros financeiros
     */
    public function contarFinanceiro(int $id): int
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_funcionario', '=', $id)
            ->count();
    }

    /**
     * Verifica se um nome de usuário está disponível
     *
     * @param string $usuario Nome de usuário
     * @param int|null $excludeId ID a excluir da verificação (para edição)
     * @return bool True se disponível, false se já em uso
     */
    public function usuarioDisponivel(string $usuario, ?int $excludeId = null): bool
    {
        $query = $this->qb
            ->table('funcionarios')
            ->where('usuario', '=', $usuario);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Retorna o plano do tenant atual lendo de qualquer funcionario existente.
     * O plano eh replicado em todos os funcionarios do mesmo chave (gerenciado pelo WHMCS).
     */
    public function getPlanoTenant(): string
    {
        $rows = $this->qb
            ->table('funcionarios')
            ->select(['plano'])
            ->where('usuario', 'NOT LIKE', 'suporte%')
            ->whereRaw("TRIM(plano) <> ''")
            ->get();

        $planos = [];
        foreach ($rows as $row) {
            $plano = strtoupper(trim((string) ($row['plano'] ?? '')));
            if (Planos::existe($plano)) {
                $planos[$plano] = true;
            }
        }

        return count($planos) === 1 ? (string) array_key_first($planos) : '';
    }

    /**
     * Busca usuario de suporte do tenant atual
     *
     * @return array|null Dados do usuario de suporte ou null
     */
    public function buscarUsuarioSuporte(): ?array
    {
        return $this->qb
            ->table('funcionarios')
            ->select(['id', 'usuario', 'created_at'])
            ->where('usuario', 'LIKE', 'suporte%')
            ->first();
    }

    /**
     * Exclui funcionario permanentemente (hard delete)
     *
     * @param int $id ID do funcionario
     * @return int Numero de linhas afetadas
     */
    public function excluirPermanente(int $id): int
    {
        return $this->qb
            ->table('funcionarios')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Atualiza o locale (idioma) do funcionario
     *
     * @param int $id ID do funcionario
     * @param string $locale Codigo do locale (ex: pt_BR, en_US)
     * @return int Numero de linhas afetadas
     */
    public function atualizarLocale(int $id, string $locale): int
    {
        return $this->qb
            ->table('funcionarios')
            ->where('id', '=', $id)
            ->update(['ui_locale' => $locale]);
    }

    /**
     * Lista funcionarios ativos de uma filial autorizados a receber uma notificacao.
     *
     * @return array<int, array{id:int,nome:string,email:string,id_matriz_filial:int}>
     */
    public function listarAtivosComPermissaoNaFilial(string $permission, int $filialId): array
    {
        return $this->qb
            ->table('funcionarios', 'f')
            ->select(['f.id', 'f.nome', 'f.email', 'f.id_matriz_filial'])
            ->distinct()
            ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->innerJoin('funcionarios_role_permissions', 'rp', 'r.id', '=', 'rp.role_id')
            ->innerJoin('permissions', 'p', 'rp.permission_id', '=', 'p.id')
            ->where('f.status', '=', 'A')
            ->where('f.id_matriz_filial', '=', $filialId)
            ->whereRaw("TRIM(COALESCE(f.email, '')) <> ''")
            ->whereRaw('p.`key` = ?', [$permission])
            ->orderBy('f.nome', 'ASC')
            ->get();
    }
}
