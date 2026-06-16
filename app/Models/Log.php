<?php

namespace App\Models;

/**
 * Model Log
 *
 * Gerencia operações de leitura na tabela logs (auditoria)
 */
class Log extends Model
{
    /**
     * Lista logs com paginação e busca
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @param string|null $search Termo de busca (opcional)
     * @return array Lista de logs da página
     */
    public function listarPaginado(int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return [];
        }

        $query = $this->qb
            ->table('logs', 'l')
            ->select([
                'l.id',
                'l.data',
                'l.ip',
                'l.mensagem',
                'l.campos_alterados',
                'f.nome as usuario_nome'
            ])
            ->leftJoinRaw('funcionarios', 'f', 'l.id_funcionario = f.id AND l.chave = f.chave')
            ->withoutChave()
            ->where('l.chave', '=', $chave);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('l.mensagem', 'LIKE', $searchTerm)
                  ->orWhere('f.nome', 'LIKE', $searchTerm)
                  ->orWhere('l.ip', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderByDesc('l.data')
            ->orderByDesc('l.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de logs (com filtro de busca opcional)
     *
     * @param string|null $search Termo de busca (opcional)
     * @return int Total de logs
     */
    public function contar(?string $search = null): int
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return 0;
        }

        $query = $this->qb
            ->table('logs', 'l')
            ->leftJoinRaw('funcionarios', 'f', 'l.id_funcionario = f.id AND l.chave = f.chave')
            ->withoutChave()
            ->where('l.chave', '=', $chave);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('l.mensagem', 'LIKE', $searchTerm)
                  ->orWhere('f.nome', 'LIKE', $searchTerm)
                  ->orWhere('l.ip', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }
}
