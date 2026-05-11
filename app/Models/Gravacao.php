<?php

namespace App\Models;

/**
 * Model Gravacao
 *
 * Gerencia operações na tabela sistema_gravacoes
 */
class Gravacao extends Model
{
    /**
     * Lista gravacoes com paginacao
     *
     * @param int $page Pagina atual (comeca em 1)
     * @param int $perPage Registros por pagina
     * @return array Lista de gravacoes da pagina
     */
    public function listarPaginado(int $page = 1, int $perPage = 10): array
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return [];
        }

        return $this->qb
            ->table('sistema_gravacoes', 'g')
            ->select([
                'g.id',
                'g.arquivo',
                'g.size',
                'g.created_at',
                'DATEDIFF(DATE_ADD(g.created_at, INTERVAL 30 DAY), NOW()) as dias_restantes'
            ])
            ->orderByDesc('g.created_at')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de gravacoes
     *
     * @return int Total de gravacoes
     */
    public function contar(): int
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return 0;
        }

        return $this->qb
            ->table('sistema_gravacoes')
            ->count();
    }

    /**
     * Busca gravacao por ID
     *
     * @param int $id ID da gravacao
     * @return array|null Dados da gravacao ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return null;
        }

        $result = $this->qb
            ->table('sistema_gravacoes')
            ->where('id', '=', $id)
            ->first();

        return $result ?: null;
    }

    /**
     * Cria nova gravacao
     *
     * @param array $dados Dados da gravacao
     * @return int|false ID da gravacao criada ou false em caso de erro
     */
    public function criar(array $dados): int|false
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return false;
        }

        return $this->qb
            ->table('sistema_gravacoes')
            ->insert($dados);
    }

    /**
     * Deleta gravacao por ID
     *
     * @param int $id ID da gravacao
     * @return bool Sucesso da operacao
     */
    public function deletar(int $id): bool
    {
        $chave = $_SESSION['chave'] ?? null;

        if (!$chave) {
            return false;
        }

        return $this->qb
            ->table('sistema_gravacoes')
            ->where('id', '=', $id)
            ->delete() > 0;
    }

    /**
     * Lista gravacoes antigas (para limpeza via CRON)
     *
     * @param int $dias Dias de retencao
     * @return array Lista de gravacoes antigas
     */
    public function listarAntigas(int $dias = 30): array
    {
        return $this->qb
            ->table('sistema_gravacoes')
            ->select(['id', 'chave', 'arquivo'])
            ->withoutChave()
            ->whereRaw('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$dias])
            ->get();
    }

    /**
     * Deleta gravacao por ID sem filtro de chave (uso interno do CRON)
     *
     * @param int $id ID da gravacao
     * @return bool Sucesso da operacao
     */
    public function deletarPorIdSemChave(int $id): bool
    {
        return $this->qb
            ->table('sistema_gravacoes')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete() > 0;
    }
}
