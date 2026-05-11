<?php

namespace App\Models;

/**
 * Model Agenda
 *
 * Gerencia eventos gerais da agenda (nao vinculados a locacao/contrato/manutencao).
 * Tabela: agenda (id, chave, titulo, data_ini, data_fim, label, obs, cor)
 */
class Agenda extends Model
{
    /**
     * Lista eventos da agenda dentro de um intervalo de datas
     */
    public function listarPorPeriodo(string $chave, string $inicio, string $fim): array
    {
        return $this->qb
            ->table('agenda')
            ->select(['id', 'titulo', 'data_ini', 'data_fim', 'label', 'obs', 'cor'])
            ->where('data_ini', '<=', $fim)
            ->where('data_fim', '>=', $inicio)
            ->orderBy('data_ini', 'ASC')
            ->get();
    }

    /**
     * Busca um evento por ID (filtro por chave aplicado automaticamente).
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('agenda')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria um evento na agenda
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('agenda')
            ->insert([
                'chave' => $dados['chave'],
                'titulo' => $dados['titulo'],
                'data_ini' => $dados['data_ini'],
                'data_fim' => $dados['data_fim'],
                'label' => $dados['label'] ?? '',
                'cor' => $dados['cor'] ?? 'agenda_roxo',
                'obs' => $dados['obs'] ?? null,
            ]);
    }

    /**
     * Atualiza um evento existente
     */
    public function atualizar(int $id, array $dados): int
    {
        $update = [];
        foreach (['titulo', 'data_ini', 'data_fim', 'label', 'cor', 'obs'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $update[$campo] = $dados[$campo];
            }
        }
        if (empty($update)) return 0;

        return $this->qb
            ->table('agenda')
            ->where('id', '=', $id)
            ->update($update);
    }

    /**
     * Exclui um evento
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('agenda')
            ->where('id', '=', $id)
            ->delete();
    }
}
