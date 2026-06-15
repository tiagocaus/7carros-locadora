<?php

namespace App\Models;

/**
 * Model SerproIndicacao
 *
 * Gerencia indicacoes de real infrator e principal condutor
 * enviadas ao sistema de consultas online.
 */
class SerproIndicacao extends Model
{
    /**
     * Lista indicacoes paginadas do tenant
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $filtroTipo = '',
        ?string $filtroStatus = null,
        ?string $filtroPlaca = null
    ): array {
        $query = $this->qb
            ->table('serpro_indicacoes', 'si')
            ->select([
                'si.id',
                'si.tipo',
                'si.placa',
                'si.codigo_orgao',
                'si.numero_ait',
                'si.cpf_indicado',
                'si.nome_indicado',
                'si.chave_indicacao',
                'si.status_serpro',
                'si.data_indicacao',
                'si.data_resposta',
                'si.created_at',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'cl.nome_rsocial AS cliente_nome',
            ])
            ->leftJoin('veiculos', 'v', 'si.id_veiculo', '=', 'v.id')
            ->leftJoin('clientes', 'cl', 'si.id_cliente', '=', 'cl.id');

        if (!empty($filtroTipo)) {
            $query->where('si.tipo', '=', $filtroTipo);
        }

        if (!empty($filtroStatus)) {
            $query->where('si.status_serpro', '=', $filtroStatus);
        }

        if (!empty($filtroPlaca)) {
            $query->where('si.placa', '=', $filtroPlaca);
        }

        return $query
            ->orderByDesc('si.created_at')
            ->orderByDesc('si.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de indicacoes com filtros
     */
    public function contar(
        string $filtroTipo = '',
        ?string $filtroStatus = null,
        ?string $filtroPlaca = null
    ): int {
        $query = $this->qb
            ->table('serpro_indicacoes', 'si');

        if (!empty($filtroTipo)) {
            $query->where('si.tipo', '=', $filtroTipo);
        }

        if (!empty($filtroStatus)) {
            $query->where('si.status_serpro', '=', $filtroStatus);
        }

        if (!empty($filtroPlaca)) {
            $query->where('si.placa', '=', $filtroPlaca);
        }

        return $query->count();
    }

    /**
     * Busca indicacao por ID com dados completos
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('serpro_indicacoes', 'si')
            ->select([
                'si.*',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.placa AS veiculo_placa',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
            ])
            ->leftJoin('veiculos', 'v', 'si.id_veiculo', '=', 'v.id')
            ->leftJoin('clientes', 'cl', 'si.id_cliente', '=', 'cl.id')
            ->where('si.id', '=', $id)
            ->first();
    }

    /**
     * Cria nova indicacao
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('serpro_indicacoes')
            ->insert([
                'chave' => $_SESSION['chave'],
                'tipo' => $dados['tipo'],
                'id_multa' => !empty($dados['id_multa']) ? (int) $dados['id_multa'] : null,
                'id_veiculo' => !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null,
                'id_cliente' => !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
                'id_contrato' => !empty($dados['id_contrato']) ? (int) $dados['id_contrato'] : null,
                'id_locacao' => !empty($dados['id_locacao']) ? (int) $dados['id_locacao'] : null,
                'placa' => $dados['placa'],
                'codigo_orgao' => $dados['codigo_orgao'] ?? null,
                'numero_ait' => $dados['numero_ait'] ?? null,
                'codigo_infracao' => $dados['codigo_infracao'] ?? null,
                'cpf_indicado' => preg_replace('/\D/', '', $dados['cpf_indicado']),
                'nome_indicado' => $dados['nome_indicado'] ?? null,
                'cnh_indicado' => $dados['cnh_indicado'] ?? null,
                'chave_indicacao' => $dados['chave_indicacao'] ?? null,
                'status_serpro' => $dados['status_serpro'] ?? 'enviado',
                'data_indicacao' => $dados['data_indicacao'] ?? date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Atualiza status da indicacao (resposta da SERPRO)
     */
    public function atualizarStatus(int $id, array $dados): int
    {
        $dadosUpdate = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('status_serpro', $dados)) {
            $dadosUpdate['status_serpro'] = $dados['status_serpro'];
        }
        if (array_key_exists('chave_indicacao', $dados)) {
            $dadosUpdate['chave_indicacao'] = $dados['chave_indicacao'];
        }
        if (array_key_exists('motivo_rejeicao', $dados)) {
            $dadosUpdate['motivo_rejeicao'] = $dados['motivo_rejeicao'];
        }
        if (array_key_exists('documento_assinado', $dados)) {
            $dadosUpdate['documento_assinado'] = $dados['documento_assinado'];
        }
        if (array_key_exists('data_resposta', $dados)) {
            $dadosUpdate['data_resposta'] = $dados['data_resposta'];
        }
        if (array_key_exists('data_expiracao', $dados)) {
            $dadosUpdate['data_expiracao'] = $dados['data_expiracao'];
        }

        return $this->qb
            ->table('serpro_indicacoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Busca indicacao pela chave SERPRO
     */
    public function buscarPorChaveIndicacao(string $chaveIndicacao): ?array
    {
        return $this->qb
            ->table('serpro_indicacoes')
            ->where('chave_indicacao', '=', $chaveIndicacao)
            ->first();
    }

    /**
     * Lista indicacoes enviadas que ainda precisam de sincronizacao de status.
     *
     * Uso exclusivo de CRON: precisa atravessar tenants, mas cada atualizacao
     * posterior define a sessao do tenant antes de usar os Models.
     */
    public function listarParaSincronizarStatus(int $limit = 100): array
    {
        return $this->qb
            ->table('serpro_indicacoes')
            ->withoutChave()
            ->select([
                'id',
                'chave',
                'tipo',
                'id_multa',
                'chave_indicacao',
                'status_serpro',
            ])
            ->whereIn('status_serpro', ['enviado', 'processando', 'pendente'])
            ->whereRaw("chave_indicacao IS NOT NULL AND chave_indicacao <> ''")
            ->whereRaw('(updated_at IS NULL OR updated_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE))')
            ->orderBy('updated_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Resumo de indicacoes do tenant (para dashboard)
     */
    public function resumo(): array
    {
        $resultado = $this->qb
            ->table('serpro_indicacoes')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(CASE WHEN status_serpro = 'enviado' THEN 1 END) AS enviadas,
                COUNT(CASE WHEN status_serpro = 'aceito' THEN 1 END) AS aceitas,
                COUNT(CASE WHEN status_serpro = 'rejeitado' THEN 1 END) AS rejeitadas,
                COUNT(CASE WHEN status_serpro IN ('enviado', 'processando', 'pendente') THEN 1 END) AS pendentes
            ")
            ->first();

        return $resultado ?: [
            'total' => 0,
            'enviadas' => 0,
            'aceitas' => 0,
            'rejeitadas' => 0,
            'pendentes' => 0,
        ];
    }
}
