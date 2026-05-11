<?php

namespace App\Models;

/**
 * Model TemporadaGrupo
 *
 * Gerencia os ajustes percentuais de preco por grupo de veiculo em cada temporada.
 */
class TemporadaGrupo extends Model
{
    /**
     * Lista ajustes de uma temporada
     *
     * @param int $idTemporada ID da temporada
     * @return array Lista de ajustes com dados do grupo
     */
    public function listarPorTemporada(int $idTemporada): array
    {
        return $this->qb
            ->table('temporadas_grupos', 'tg')
            ->select([
                'tg.*',
                'g.nome as grupo_nome'
            ])
            ->innerJoin('grupos', 'g', 'g.id', '=', 'tg.id_grupo')
            ->where('tg.id_temporada', '=', $idTemporada)
            ->orderBy('g.nome', 'ASC')
            ->get();
    }

    /**
     * Lista ajustes de um grupo
     *
     * @param int $idGrupo ID do grupo
     * @param string $chave Chave do tenant
     * @return array Lista de ajustes com dados da temporada
     */
    public function listarPorGrupo(int $idGrupo, string $chave): array
    {
        return $this->qb
            ->table('temporadas_grupos', 'tg')
            ->select([
                'tg.*',
                't.nome as temporada_nome',
                't.mes_inicio',
                't.dia_inicio',
                't.mes_fim',
                't.dia_fim'
            ])
            ->innerJoin('temporadas', 't', 't.id', '=', 'tg.id_temporada')
            ->where('tg.id_grupo', '=', $idGrupo)
            ->where('t.ativo', '=', 1)
            ->orderBy('t.nome', 'ASC')
            ->get();
    }

    /**
     * Busca ajuste por temporada e grupo
     *
     * @param int $idTemporada ID da temporada
     * @param int $idGrupo ID do grupo
     * @return array|null Dados do ajuste ou null
     */
    public function buscarPorTemporadaGrupo(int $idTemporada, int $idGrupo): ?array
    {
        return $this->qb
            ->table('temporadas_grupos')
            ->where('id_temporada', '=', $idTemporada)
            ->where('id_grupo', '=', $idGrupo)
            ->first();
    }

    /**
     * Busca um ajuste por ID
     *
     * @param int $id ID do ajuste
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('temporadas_grupos')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria ou atualiza um ajuste
     *
     * @param array $dados Dados do ajuste
     * @return int ID criado/atualizado
     */
    public function salvar(array $dados): int
    {
        $existente = $this->buscarPorTemporadaGrupo(
            (int) $dados['id_temporada'],
            (int) $dados['id_grupo']
        );

        if ($existente) {
            $this->qb
                ->table('temporadas_grupos')
                ->where('id', '=', $existente['id'])
                ->update(['ajuste_percentual' => (float) $dados['ajuste_percentual']]);
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('temporadas_grupos')
            ->insert([
                'chave' => $dados['chave'],
                'id_temporada' => (int) $dados['id_temporada'],
                'id_grupo' => (int) $dados['id_grupo'],
                'ajuste_percentual' => (float) $dados['ajuste_percentual'],
            ]);
    }

    /**
     * Salva ajustes em lote para uma temporada
     *
     * @param int $idTemporada ID da temporada
     * @param string $chave Chave do tenant
     * @param array $ajustes Array de [id_grupo => ajuste_percentual]
     * @return int Quantidade de registros salvos
     */
    public function salvarEmLote(int $idTemporada, string $chave, array $ajustes): int
    {
        $count = 0;

        foreach ($ajustes as $idGrupo => $percentual) {
            if ($percentual === null || $percentual === '') {
                // Remove ajuste se valor vazio
                $this->excluirPorTemporadaGrupo($idTemporada, (int) $idGrupo);
            } else {
                $this->salvar([
                    'chave' => $chave,
                    'id_temporada' => $idTemporada,
                    'id_grupo' => (int) $idGrupo,
                    'ajuste_percentual' => (float) $percentual,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Exclui um ajuste
     *
     * @param int $id ID do ajuste
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('temporadas_grupos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Exclui ajuste por temporada e grupo
     *
     * @param int $idTemporada ID da temporada
     * @param int $idGrupo ID do grupo
     * @return int Linhas afetadas
     */
    public function excluirPorTemporadaGrupo(int $idTemporada, int $idGrupo): int
    {
        return $this->qb
            ->table('temporadas_grupos')
            ->where('id_temporada', '=', $idTemporada)
            ->where('id_grupo', '=', $idGrupo)
            ->delete();
    }

    /**
     * Exclui todos os ajustes de uma temporada
     *
     * @param int $idTemporada ID da temporada
     * @return int Linhas afetadas
     */
    public function excluirPorTemporada(int $idTemporada): int
    {
        return $this->qb
            ->table('temporadas_grupos')
            ->where('id_temporada', '=', $idTemporada)
            ->delete();
    }
}
