<?php

namespace App\Models;

class SiteDeployLog extends Model
{
    /**
     * Registra um deploy no historico
     */
    public function registrar(string $versao, string $tipo, string $status, ?array $detalhes = null, ?int $funcionarioId = null): int
    {
        return $this->qb
            ->table('site_deploy_log')
            ->insert([
                'chave'          => $_SESSION['chave'],
                'versao'         => $versao,
                'tipo'           => $tipo,
                'status'         => $status,
                'detalhes'       => $detalhes ? json_encode($detalhes) : null,
                'funcionario_id' => $funcionarioId,
            ]);
    }

    /**
     * Lista deploys recentes
     */
    public function listarRecentes(int $limit = 20): array
    {
        $rows = $this->qb
            ->table('site_deploy_log', 'd')
            ->select([
                'd.*',
                'f.nome AS funcionario_nome',
            ])
            ->leftJoin('funcionarios', 'f', 'd.funcionario_id', '=', 'f.id')
            ->orderBy('d.created_at', 'DESC')
            ->limit($limit)
            ->get();

        foreach ($rows as &$row) {
            if (!empty($row['detalhes'])) {
                $row['detalhes'] = json_decode($row['detalhes'], true);
            }
        }

        return $rows;
    }

    /**
     * Retorna o ultimo deploy com sucesso
     */
    public function ultimoDeploy(): ?array
    {
        $row = $this->qb
            ->table('site_deploy_log')
            ->select(['*'])
            ->where('status', '=', 'sucesso')
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->first();

        if ($row && !empty($row['detalhes'])) {
            $row['detalhes'] = json_decode($row['detalhes'], true);
        }

        return $row;
    }

    /**
     * Atualiza o status de um deploy
     */
    public function atualizarStatus(int $id, string $status, ?array $detalhes = null): int
    {
        $dados = ['status' => $status];
        if ($detalhes !== null) {
            $dados['detalhes'] = json_encode($detalhes);
        }

        return $this->qb
            ->table('site_deploy_log')
            ->where('id', '=', $id)
            ->update($dados);
    }
}
