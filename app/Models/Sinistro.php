<?php

namespace App\Models;

class Sinistro extends Model
{
    public const PLANO_CONTA_SINISTROS = '4.2.2.05';
    public const STATUS_ABERTO = 'A';
    public const STATUS_CONCLUIDO = 'C';

    public const TIPOS = [
        'colisao',
        'furto_roubo',
        'incendio',
        'alagamento',
        'danos_terceiros',
        'perda_total',
        'outros',
    ];

    public function listarPorVinculo(string $vinculo, int $idVinculo): array
    {
        $campo = $vinculo === 'contrato' ? 's.id_contrato' : 's.id_locacao';

        return $this->qb
            ->table('sinistros', 's')
            ->select([
                's.*',
                'v.placa AS veiculo_placa',
                'v.marca AS veiculo_marca',
                'v.modelo AS veiculo_modelo',
                'f.codigo AS financeiro_codigo',
                'f.pago AS financeiro_pago',
                'f.valor_total AS financeiro_valor',
                'f.data_venci AS financeiro_vencimento',
            ])
            ->leftJoin('veiculos', 'v', 's.id_veiculo', '=', 'v.id')
            ->leftJoin('financeiro', 'f', 's.id_financeiro', '=', 'f.id')
            ->where($campo, '=', $idVinculo)
            ->orderByDesc('s.data_ocorrencia')
            ->orderByDesc('s.id')
            ->get();
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('sinistros')
            ->where('id', '=', $id)
            ->first();
    }

    public function buscarPorIdParaAtualizacao(int $id): ?array
    {
        return $this->qb
            ->table('sinistros')
            ->where('id', '=', $id)
            ->lockForUpdate()
            ->first();
    }

    public function criar(array $dados): int
    {
        return $this->qb->table('sinistros')->insert($dados);
    }

    public function atualizar(int $id, array $dados): bool
    {
        return $this->qb
            ->table('sinistros')
            ->where('id', '=', $id)
            ->update($dados);
    }

    public function deletar(int $id): int
    {
        return $this->qb
            ->table('sinistros')
            ->where('id', '=', $id)
            ->delete();
    }

    public function veiculoPertenceAoVinculo(string $vinculo, int $idVinculo, int $idVeiculo): bool
    {
        $tabela = $vinculo === 'contrato' ? 'contratos_veiculos' : 'locacoes_veiculos';
        $campo = $vinculo === 'contrato' ? 'id_contrato' : 'id_locacao';

        return $this->qb
            ->table($tabela)
            ->where($campo, '=', $idVinculo)
            ->where('id_veiculo', '=', $idVeiculo)
            ->exists();
    }
}
