<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model Documento
 *
 * Gerencia modelos de documentos (clausulas, contratos, etc.)
 * com variaveis auto-preenchidas pelo sistema.
 */
class Documento extends Model
{
    use Auditable;

    /**
     * Tipos de documento disponiveis
     */
    public const TIPOS = [
        0 => 'Contrato/Locação',
        1 => 'Contrato',
        2 => 'Locação',
        3 => 'Multa',
    ];

    /**
     * Status disponiveis
     */
    public const STATUS = [
        0 => 'Inativo',
        1 => 'Ativo',
    ];

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o documento';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'titulo';
    }

    /**
     * Lista todos os documentos do tenant, incluindo modelos globais do sistema.
     *
     * @param int|null $tipo Filtrar por tipo (opcional)
     * @return array Lista de documentos
     */
    public function listar(?int $tipo = null): array
    {
        $query = $this->qb
            ->table('documentos')
            ->withGlobals()
            ->select(['id', 'chave', 'titulo', 'tipo', 'status', 'created_at', 'updated_at']);

        if ($tipo !== null) {
            $query->where('tipo', '=', $tipo);
        }

        return $this->preferTenantRows(
            $query
                ->orderByRaw("CASE WHEN chave = '0' THEN 1 ELSE 0 END")
                ->orderBy('titulo', 'ASC')
                ->get()
        );
    }

    /**
     * Lista documentos com paginacao e busca, incluindo modelos globais do sistema.
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param int|null $tipo Filtrar por tipo (opcional)
     * @param int|null $status Filtrar por status (opcional)
     * @return array Lista de documentos
     */
    public function listarPaginado(int $page, int $perPage, string $search = '', ?int $tipo = null, ?int $status = null): array
    {
        $query = $this->qb
            ->table('documentos')
            ->withGlobals()
            ->select(['id', 'chave', 'titulo', 'tipo', 'status', 'created_at', 'updated_at']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('titulo', 'LIKE', $searchTerm);
        }

        if ($tipo !== null) {
            $query->where('tipo', '=', $tipo);
        }

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        $rows = $query
            ->orderByRaw("CASE WHEN chave = '0' THEN 1 ELSE 0 END")
            ->orderBy('titulo', 'ASC')
            ->get();

        $rows = $this->preferTenantRows($rows);
        return array_slice($rows, ($page - 1) * $perPage, $perPage);
    }

    /**
     * Conta o total de documentos do tenant, incluindo modelos globais do sistema.
     *
     * @param string $search Termo de busca (opcional)
     * @param int|null $tipo Filtrar por tipo (opcional)
     * @param int|null $status Filtrar por status (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = '', ?int $tipo = null, ?int $status = null): int
    {
        $query = $this->qb
            ->table('documentos')
            ->withGlobals()
            ->select(['id', 'chave', 'titulo', 'tipo']);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('titulo', 'LIKE', $searchTerm);
        }

        if ($tipo !== null) {
            $query->where('tipo', '=', $tipo);
        }

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        return count($this->preferTenantRows($query->get()));
    }

    /**
     * Busca um documento por ID
     *
     * @param int $id ID do documento
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('documentos')
            ->withGlobals()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo documento
     *
     * @param array $dados Dados do documento
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('documentos')
            ->insert([
                'chave' => $dados['chave'],
                'titulo' => $dados['titulo'],
                'texto' => $dados['texto'] ?? '',
                'tipo' => (int) ($dados['tipo'] ?? 0),
                'status' => (int) ($dados['status'] ?? 1),
            ]);
    }

    /**
     * Cria uma cópia tenant de um modelo global do sistema.
     */
    public function criarCopiaTenant(array $documentoGlobal, string $chave, array $dados = []): int
    {
        if (($documentoGlobal['chave'] ?? '') !== '0') {
            throw new \InvalidArgumentException('Documento informado não é um modelo do sistema');
        }

        return $this->criar([
            'chave' => $chave,
            'titulo' => $dados['titulo'] ?? $documentoGlobal['titulo'],
            'texto' => array_key_exists('texto', $dados) ? $dados['texto'] : ($documentoGlobal['texto'] ?? ''),
            'tipo' => isset($dados['tipo']) ? (int) $dados['tipo'] : (int) ($documentoGlobal['tipo'] ?? 0),
            'status' => isset($dados['status']) ? (int) $dados['status'] : (int) ($documentoGlobal['status'] ?? 1),
        ]);
    }

    /**
     * Atualiza um documento existente
     *
     * @param int $id ID do documento
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $documento = $this->buscarPorId($id);
        if (!$documento) {
            throw new \InvalidArgumentException('Documento não encontrado');
        }

        $dadosUpdate = [];

        if (isset($dados['titulo'])) {
            $dadosUpdate['titulo'] = $dados['titulo'];
        }

        if (array_key_exists('texto', $dados)) {
            $dadosUpdate['texto'] = $dados['texto'];
        }

        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = (int) $dados['tipo'];
        }

        if (isset($dados['status'])) {
            $dadosUpdate['status'] = (int) $dados['status'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('documentos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um documento
     *
     * @param int $id ID do documento
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $documento = $this->buscarPorId($id);
        if (!$documento) {
            throw new \InvalidArgumentException('Documento não encontrado');
        }

        return $this->qb
            ->table('documentos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Lista documentos para select (busca server-side)
     *
     * @param string $search Termo de busca (opcional)
     * @param int|null $tipo Filtrar por tipo (opcional)
     * @return array Lista com id e titulo
     */
    public function listarParaSelect(string $search = '', ?int $tipo = null): array
    {
        $query = $this->qb
            ->table('documentos')
            ->withGlobals()
            ->select(['id', 'chave', 'titulo', 'tipo'])
            ->where('status', '=', 1);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('titulo', 'LIKE', $searchTerm);
        }

        if ($tipo !== null) {
            $query->where('tipo', '=', $tipo);
        }

        return array_slice(
            $this->preferTenantRows(
                $query
                    ->orderByRaw("CASE WHEN chave = '0' THEN 1 ELSE 0 END")
                    ->orderBy('titulo', 'ASC')
                    ->get()
            ),
            0,
            50
        );
    }

    /**
     * Remove duplicidades entre modelos globais e customizados, priorizando tenant.
     */
    private function preferTenantRows(array $rows): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $key = (int) ($row['tipo'] ?? 0) . '|' . strtolower(trim((string) ($row['titulo'] ?? '')));
            if (!isset($unique[$key]) || (($unique[$key]['chave'] ?? '0') === '0' && ($row['chave'] ?? '0') !== '0')) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }

    /**
     * Retorna o nome do tipo
     *
     * @param int $tipo Codigo do tipo
     * @return string Nome do tipo
     */
    public static function getNomeTipo(int $tipo): string
    {
        return self::TIPOS[$tipo] ?? 'Desconhecido';
    }

    /**
     * Retorna o nome do status
     *
     * @param int $status Codigo do status
     * @return string Nome do status
     */
    public static function getNomeStatus(int $status): string
    {
        return self::STATUS[$status] ?? 'Desconhecido';
    }
}
