<?php

namespace App\Models;

use App\Core\Auth;

/**
 * Model ChecklistModelo
 *
 * Gerencia modelos de checklist para vistorias de veiculos.
 */
class ChecklistModelo extends Model
{
    /**
     * Lista modelos com paginacao e busca
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca
     * @return array Lista de modelos
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = ''
    ): array {
        $query = $this->qb
            ->table('checklist_modelos')
            ->withGlobals()
            ->select(['id', 'chave', 'nome', 'tipo', 'status', 'created_at', 'updated_at']);

        // Busca por nome
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        $rows = $query
            ->orderByRaw("CASE WHEN chave = '0' THEN 1 ELSE 0 END")
            ->orderBy('nome', 'ASC')
            ->get();

        $rows = $this->preferTenantRows($rows);
        return array_slice($rows, ($page - 1) * $perPage, $perPage);
    }

    /**
     * Conta total de modelos com filtros
     *
     * @param string $search Termo de busca
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('checklist_modelos')
            ->withGlobals()
            ->select(['id', 'chave', 'nome', 'tipo']);

        // Busca por nome
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return count($this->preferTenantRows($query->get()));
    }

    /**
     * Busca um modelo por ID
     *
     * @param int $id ID do modelo
     * @return array|null Dados do modelo ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('checklist_modelos')
            ->withGlobals()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo modelo
     *
     * @param array $dados Dados do modelo
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('checklist_modelos')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'questoes' => $dados['questoes'] ?? '[]',
                'vistoria' => $dados['vistoria'] ?? '[]',
                'tipo' => (int) ($dados['tipo'] ?? 0),
                'status' => $dados['status'] ?? 'A',
            ]);
    }

    /**
     * Atualiza um modelo existente
     *
     * @param int $id ID do modelo
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $modelo = $this->buscarPorId($id);
        if (!$modelo) {
            throw new \InvalidArgumentException('Modelo de checklist nao encontrado');
        }
        if (($modelo['chave'] ?? '') === '0') {
            throw new \InvalidArgumentException('Modelos do sistema devem ser copiados antes de editar');
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['questoes'])) {
            $dadosUpdate['questoes'] = $dados['questoes'];
        }
        if (isset($dados['vistoria'])) {
            $dadosUpdate['vistoria'] = $dados['vistoria'];
        }
        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = (int) $dados['tipo'];
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('checklist_modelos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um modelo (verifica vinculos em checklists)
     *
     * @param int $id ID do modelo
     * @return int Linhas afetadas
     * @throws \InvalidArgumentException Se modelo nao encontrado ou tem vinculos
     */
    public function excluir(int $id): int
    {
        $modelo = $this->buscarPorId($id);
        if (!$modelo) {
            throw new \InvalidArgumentException('Modelo de checklist nao encontrado');
        }
        if (($modelo['chave'] ?? '') === '0') {
            throw new \InvalidArgumentException('Modelos do sistema nao podem ser excluidos');
        }

        // Verificar vinculos em checklists
        $vinculos = $this->verificarVinculos($id);
        if ($vinculos['temVinculos']) {
            throw new \InvalidArgumentException(
                'Nao e possivel excluir: este modelo esta vinculado a ' .
                $vinculos['totalChecklists'] . ' checklist(s)'
            );
        }

        return $this->qb
            ->table('checklist_modelos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se o modelo tem vinculos em checklists
     *
     * @param int $id ID do modelo
     * @return array Informacoes sobre vinculos
     */
    public function verificarVinculos(int $id): array
    {
        $totalChecklists = $this->qb
            ->table('checklist')
            ->selectRaw('COUNT(*) as total')
            ->withoutChave()
            ->where('id_modelo', '=', $id)
            ->first()['total'] ?? 0;

        return [
            'temVinculos' => $totalChecklists > 0,
            'totalChecklists' => (int) $totalChecklists,
        ];
    }

    /**
     * Lista modelos para select (sem paginacao)
     *
     * @param string $search Termo de busca
     * @return array Lista de modelos
     */
    public function listarParaSelect(string $search = ''): array
    {
        $query = $this->qb
            ->table('checklist_modelos')
            ->withGlobals()
            ->select(['id', 'chave', 'nome', 'tipo'])
            ->where('status', '=', 'A');

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        $rows = $query
            ->orderByRaw("CASE WHEN chave = '0' THEN 1 ELSE 0 END")
            ->orderBy('nome', 'ASC')
            ->get();

        return array_slice($this->preferTenantRows($rows), 0, 50);
    }

    /**
     * Remove duplicidades entre modelos globais e customizados, priorizando tenant.
     */
    private function preferTenantRows(array $rows): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $key = (int) ($row['tipo'] ?? 0) . '|' . strtolower(trim((string) ($row['nome'] ?? '')));
            if (!isset($unique[$key]) || (($unique[$key]['chave'] ?? '0') === '0' && ($row['chave'] ?? '0') !== '0')) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }
}
