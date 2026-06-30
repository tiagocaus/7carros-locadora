<?php

namespace App\Models;

/**
 * Model HorarioExcecao
 *
 * Gerencia exceções de horário para datas específicas (feriados, Black Friday, etc).
 * Permite definir se a filial está fechada ou com horário especial.
 */
class HorarioExcecao extends Model
{
    /**
     * Tipos de exceção
     */
    public const TIPOS = [
        'fechado' => 'Fechado',
        'especial' => 'Horário Especial',
    ];

    /**
     * Lista exceções de uma matriz/filial
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param string|null $dataInicio Filtrar a partir de (Y-m-d)
     * @param string|null $dataFim Filtrar até (Y-m-d)
     * @return array Lista de exceções
     */
    public function listarPorMatriz(int $matrizFilialId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $query = $this->qb
            ->table('horarios_excecoes')
            ->select(['id', 'data', 'tipo', 'abertura', 'fechamento', 'descricao'])
            ->where('matriz_filial_id', '=', $matrizFilialId);

        if ($dataInicio !== null) {
            $query->where('data', '>=', $dataInicio);
        }

        if ($dataFim !== null) {
            $query->where('data', '<=', $dataFim);
        }

        $excecoes = $query->orderBy('data', 'ASC')->get();

        // Formatar dados
        return array_map(function ($e) {
            return [
                'id' => $e['id'],
                'data' => $e['data'],
                'data_formatada' => format_date($e['data']),
                'tipo' => $e['tipo'],
                'tipo_nome' => self::TIPOS[$e['tipo']] ?? $e['tipo'],
                'abertura' => $e['abertura'] ? substr($e['abertura'], 0, 5) : null,
                'fechamento' => $e['fechamento'] ? substr($e['fechamento'], 0, 5) : null,
                'descricao' => $e['descricao'],
            ];
        }, $excecoes);
    }

    /**
     * Busca exceção por ID
     *
     * @param int $id ID da exceção
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('horarios_excecoes')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca exceção para uma data específica
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param string $data Data (Y-m-d)
     * @return array|null Exceção ou null
     */
    public function buscarPorData(int $matrizFilialId, string $data): ?array
    {
        $e = $this->qb
            ->table('horarios_excecoes')
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->where('data', '=', $data)
            ->first();

        if (!$e) {
            return null;
        }

        return [
            'id' => $e['id'],
            'data' => $e['data'],
            'data_formatada' => format_date($e['data']),
            'tipo' => $e['tipo'],
            'tipo_nome' => self::TIPOS[$e['tipo']] ?? $e['tipo'],
            'abertura' => $e['abertura'] ? substr($e['abertura'], 0, 5) : null,
            'fechamento' => $e['fechamento'] ? substr($e['fechamento'], 0, 5) : null,
            'descricao' => $e['descricao'],
        ];
    }

    /**
     * Cria ou atualiza uma exceção (upsert por data)
     *
     * @param array $dados Dados da exceção
     * @return int ID da exceção
     */
    public function salvar(array $dados): int
    {
        $matrizFilialId = (int) $dados['matriz_filial_id'];
        $data = $dados['data'];

        // Verificar se já existe exceção para esta data
        $existente = $this->buscarPorData($matrizFilialId, $data);

        $dadosInsert = [
            'matriz_filial_id' => $matrizFilialId,
            'data' => $data,
            'tipo' => $dados['tipo'],
            'abertura' => $dados['tipo'] === 'especial' ? ($dados['abertura'] ?? null) : null,
            'fechamento' => $dados['tipo'] === 'especial' ? ($dados['fechamento'] ?? null) : null,
            'descricao' => $dados['descricao'] ?? null,
        ];

        if ($existente) {
            // Atualizar
            $this->qb
                ->table('horarios_excecoes')
                    ->where('id', '=', $existente['id'])
                ->update($dadosInsert);
            return (int) $existente['id'];
        }

        // Inserir
        return $this->qb
            ->table('horarios_excecoes')
            ->insert($dadosInsert);
    }

    /**
     * Cria uma nova exceção
     *
     * @param array $dados Dados da exceção
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('horarios_excecoes')
            ->insert([
                'matriz_filial_id' => (int) $dados['matriz_filial_id'],
                'data' => $dados['data'],
                'tipo' => $dados['tipo'],
                'abertura' => $dados['tipo'] === 'especial' ? ($dados['abertura'] ?? null) : null,
                'fechamento' => $dados['tipo'] === 'especial' ? ($dados['fechamento'] ?? null) : null,
                'descricao' => $dados['descricao'] ?? null,
            ]);
    }

    /**
     * Atualiza uma exceção
     *
     * @param int $id ID da exceção
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['data'])) {
            $dadosUpdate['data'] = $dados['data'];
        }
        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = $dados['tipo'];
            // Se mudou para fechado, limpar horários
            if ($dados['tipo'] === 'fechado') {
                $dadosUpdate['abertura'] = null;
                $dadosUpdate['fechamento'] = null;
            }
        }
        if (isset($dados['abertura'])) {
            $dadosUpdate['abertura'] = $dados['abertura'];
        }
        if (isset($dados['fechamento'])) {
            $dadosUpdate['fechamento'] = $dados['fechamento'];
        }
        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }

        return $this->qb
            ->table('horarios_excecoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma exceção
     *
     * @param int $id ID da exceção
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('horarios_excecoes')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Exclui todas as exceções de uma matriz/filial
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @return int Linhas afetadas
     */
    public function excluirPorMatriz(int $matrizFilialId): int
    {
        return $this->qb
            ->table('horarios_excecoes')
            ->where('matriz_filial_id', '=', $matrizFilialId)
            ->delete();
    }

    /**
     * Lista exceções futuras (a partir de hoje)
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param int $limite Limite de resultados (default 10)
     * @return array Lista de exceções
     */
    public function listarFuturas(int $matrizFilialId, int $limite = 10): array
    {
        $hoje = today();
        return $this->listarPorMatriz($matrizFilialId, $hoje, null);
    }

    /**
     * Verifica se uma data tem exceção
     *
     * @param int $matrizFilialId ID da matriz/filial
     * @param string $data Data (Y-m-d)
     * @return bool True se tem exceção
     */
    public function hasExcecao(int $matrizFilialId, string $data): bool
    {
        return $this->buscarPorData($matrizFilialId, $data) !== null;
    }
}
