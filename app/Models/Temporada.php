<?php

namespace App\Models;

/**
 * Model Temporada
 *
 * Gerencia temporadas (alta/baixa) para ajuste de precos por tenant.
 *
 * Temporadas template (chave='0') sao visiveis para todas as empresas.
 * Quando o cliente ativa um template, uma copia e criada com sua chave.
 */
class Temporada extends Model
{
    /**
     * Paises disponiveis
     */
    public const PAISES = [
        'BR' => 'Brasil',
        'US' => 'Estados Unidos',
        'IT' => 'Italia',
        'ES' => 'Espanha',
        'PT' => 'Portugal',
    ];

    /**
     * Lista todas as temporadas do tenant
     *
     * @param string $chave Chave do tenant
     * @param string|null $pais Filtrar por pais (opcional)
     * @param bool|null $ativo Filtrar por status ativo (opcional)
     * @return array Lista de temporadas
     */
    public function listar(string $chave, ?string $pais = null, ?bool $ativo = null): array
    {
        $query = $this->qb->table('temporadas');

        if ($pais !== null) {
            $query->where('pais', '=', $pais);
        }

        if ($ativo !== null) {
            $query->where('ativo', '=', $ativo ? 1 : 0);
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista temporadas do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de temporadas
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb->table('temporadas');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('pais', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de temporadas do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = ''): int
    {
        $query = $this->qb->table('temporadas');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('pais', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Lista templates do sistema (chave='0')
     *
     * @param string|null $pais Filtrar por pais (opcional)
     * @return array Lista de templates
     */
    public function listarTemplates(?string $pais = null): array
    {
        $query = $this->qb
            ->table('temporadas')
            ->withoutChave()
            ->where('chave', '=', '0');

        if ($pais !== null) {
            $query->where('pais', '=', $pais);
        }

        return $query
            ->orderBy('pais', 'ASC')
            ->orderBy('mes_inicio', 'ASC')
            ->orderBy('dia_inicio', 'ASC')
            ->get();
    }

    /**
     * Lista temporadas ativas do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Lista de temporadas ativas
     */
    public function listarAtivas(string $chave): array
    {
        return $this->listar($chave, null, true);
    }

    /**
     * Busca uma temporada por ID
     *
     * @param int $id ID da temporada
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('temporadas')
            ->withGlobals()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Verifica se uma data esta dentro de uma temporada ativa
     *
     * @param string $chave Chave do tenant
     * @param \DateTime|string $data Data a verificar
     * @return array|null Dados da temporada ou null se nao estiver em nenhuma
     */
    public function getTemporadaParaData(string $chave, $data): ?array
    {
        if (is_string($data)) {
            $data = new \DateTime($data);
        }

        $mes = (int) $data->format('n');
        $dia = (int) $data->format('j');

        // Busca temporadas ativas do tenant usando whereRaw para query complexa
        $sql = "(
            (mes_inicio <= mes_fim AND (
                (mes_inicio < ? OR (mes_inicio = ? AND dia_inicio <= ?)) AND
                (mes_fim > ? OR (mes_fim = ? AND dia_fim >= ?))
            ))
            OR
            (mes_inicio > mes_fim AND (
                (mes_inicio < ? OR (mes_inicio = ? AND dia_inicio <= ?)) OR
                (mes_fim > ? OR (mes_fim = ? AND dia_fim >= ?))
            ))
        )";

        $params = [
            $mes, $mes, $dia,
            $mes, $mes, $dia,
            $mes, $mes, $dia,
            $mes, $mes, $dia,
        ];

        return $this->qb
            ->table('temporadas')
            ->where('ativo', '=', 1)
            ->whereRaw($sql, $params)
            ->orderBy('id', 'ASC')
            ->first();
    }

    /**
     * Ativa um template do sistema para o tenant
     *
     * Cria uma copia do template com a chave do tenant.
     *
     * @param int $templateId ID do template (chave='0')
     * @param string $chave Chave do tenant
     * @return int ID da nova temporada criada
     */
    public function ativarTemplate(int $templateId, string $chave): int
    {
        $template = $this->buscarPorId($templateId);

        if (!$template || $template['chave'] !== '0') {
            throw new \InvalidArgumentException('Template nao encontrado ou invalido');
        }

        return $this->qb
            ->table('temporadas')
            ->insert([
                'chave' => $chave,
                'pais' => $template['pais'],
                'nome' => $template['nome'],
                'mes_inicio' => $template['mes_inicio'],
                'dia_inicio' => $template['dia_inicio'],
                'mes_fim' => $template['mes_fim'],
                'dia_fim' => $template['dia_fim'],
                'ativo' => 1,
            ]);
    }

    /**
     * Cria uma nova temporada
     *
     * @param array $dados Dados da temporada
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('temporadas')
            ->insert([
                'chave' => $dados['chave'],
                'pais' => $dados['pais'] ?? 'BR',
                'nome' => $dados['nome'],
                'mes_inicio' => (int) $dados['mes_inicio'],
                'dia_inicio' => (int) $dados['dia_inicio'],
                'mes_fim' => (int) $dados['mes_fim'],
                'dia_fim' => (int) $dados['dia_fim'],
                'ativo' => isset($dados['ativo']) ? (int) $dados['ativo'] : 1,
            ]);
    }

    /**
     * Atualiza uma temporada existente
     *
     * @param int $id ID da temporada
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        // Verifica se nao e template do sistema
        $temporada = $this->buscarPorId($id);
        if ($temporada && $temporada['chave'] === '0') {
            throw new \InvalidArgumentException('Templates do sistema nao podem ser editados');
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['pais'])) {
            $dadosUpdate['pais'] = $dados['pais'];
        }
        if (isset($dados['mes_inicio'])) {
            $dadosUpdate['mes_inicio'] = (int) $dados['mes_inicio'];
        }
        if (isset($dados['dia_inicio'])) {
            $dadosUpdate['dia_inicio'] = (int) $dados['dia_inicio'];
        }
        if (isset($dados['mes_fim'])) {
            $dadosUpdate['mes_fim'] = (int) $dados['mes_fim'];
        }
        if (isset($dados['dia_fim'])) {
            $dadosUpdate['dia_fim'] = (int) $dados['dia_fim'];
        }
        if (isset($dados['ativo'])) {
            $dadosUpdate['ativo'] = (int) $dados['ativo'];
        }

        return $this->qb
            ->table('temporadas')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma temporada
     *
     * @param int $id ID da temporada
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        // Verifica se nao e template do sistema
        $temporada = $this->buscarPorId($id);
        if ($temporada && $temporada['chave'] === '0') {
            throw new \InvalidArgumentException('Templates do sistema nao podem ser excluidos');
        }

        return $this->qb
            ->table('temporadas')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Formata periodo da temporada para exibicao
     *
     * @param array $temporada Dados da temporada
     * @return string Periodo formatado (ex: "20/Dez a 05/Jan")
     */
    public function formatarPeriodo(array $temporada): string
    {
        $meses = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];

        $inicio = sprintf('%02d/%s', $temporada['dia_inicio'], $meses[$temporada['mes_inicio']]);
        $fim = sprintf('%02d/%s', $temporada['dia_fim'], $meses[$temporada['mes_fim']]);

        if ($temporada['mes_inicio'] === $temporada['mes_fim'] && $temporada['dia_inicio'] === $temporada['dia_fim']) {
            return $inicio;
        }

        return "{$inicio} a {$fim}";
    }
}
