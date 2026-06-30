<?php

namespace App\Models;

/**
 * Sincroniza veiculos.disponibilidade com vinculos ativos de locacoes/contratos/manutencoes.
 */
class VeiculoDisponibilidadeSync extends Model
{
    public function marcarLocado(int $veiculoId, ?string $chave = null): int
    {
        $chave = $this->resolverChave($chave);

        return $this->comChave($chave, function () use ($veiculoId): int {
            return $this->qb
                ->table('veiculos')
                ->where('id', '=', $veiculoId)
                ->update([
                    'disponibilidade' => 'L',
                    'updated_at' => now(),
                ]);
        });
    }

    public function marcarOficina(int $veiculoId, ?string $chave = null): int
    {
        $chave = $this->resolverChave($chave);

        if ($this->possuiVinculoAtivo($veiculoId, $chave)) {
            return $this->marcarLocado($veiculoId, $chave);
        }

        return $this->comChave($chave, function () use ($veiculoId): int {
            return $this->qb
                ->table('veiculos')
                ->where('id', '=', $veiculoId)
                ->update([
                    'disponibilidade' => 'O',
                    'updated_at' => now(),
                ]);
        });
    }

    public function liberarSeSemVinculoAtivo(
        int $veiculoId,
        string $statusLivre = 'D',
        ?string $chave = null,
        ?int $ignorarManutencaoId = null
    ): int
    {
        $chave = $this->resolverChave($chave);
        $statusLivre = in_array($statusLivre, ['M', 'O'], true) ? 'O' : 'D';

        if ($this->possuiVinculoAtivo($veiculoId, $chave)) {
            return $this->marcarLocado($veiculoId, $chave);
        }

        if ($statusLivre !== 'O' && $this->possuiManutencaoAberta($veiculoId, $chave, $ignorarManutencaoId)) {
            $statusLivre = 'O';
        }

        return $this->comChave($chave, function () use ($veiculoId, $statusLivre): int {
            return $this->qb
                ->table('veiculos')
                ->where('id', '=', $veiculoId)
                ->update([
                    'disponibilidade' => $statusLivre,
                    'updated_at' => now(),
                ]);
        });
    }

    public function possuiManutencaoAberta(
        int $veiculoId,
        ?string $chave = null,
        ?int $ignorarManutencaoId = null
    ): bool
    {
        $chave = $this->resolverChave($chave);

        return $this->comChave($chave, function () use ($veiculoId, $ignorarManutencaoId): bool {
            $query = $this->qb
                ->table('manutencoes')
                ->where('id_veiculo', '=', $veiculoId)
                ->where('status', '=', 'A');

            if ($ignorarManutencaoId !== null) {
                $query->where('id', '!=', $ignorarManutencaoId);
            }

            return $query->exists();
        });
    }

    public function possuiVinculoAtivo(int $veiculoId, ?string $chave = null): bool
    {
        $chave = $this->resolverChave($chave);

        return $this->comChave($chave, function () use ($veiculoId): bool {
            $temLocacaoAtiva = $this->qb
                ->table('locacoes', 'l')
                ->innerJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
                ->where('l.status', '=', 'A')
                ->where('lv.id_veiculo', '=', $veiculoId)
                ->whereNull('lv.data_entrada')
                ->whereRaw('lv.chave = l.chave')
                ->exists();

            if ($temLocacaoAtiva) {
                return true;
            }

            return $this->qb
                ->table('contratos', 'c')
                ->innerJoin('contratos_veiculos', 'cv', 'cv.id_contrato', '=', 'c.id')
                ->where('c.status', '=', 'A')
                ->where('cv.id_veiculo', '=', $veiculoId)
                ->whereNull('cv.data_entrada')
                ->whereRaw('cv.chave = c.chave')
                ->exists();
        });
    }

    private function resolverChave(?string $chave): string
    {
        $chave = $chave ?: ($_SESSION['chave'] ?? '');
        if ($chave === '') {
            throw new \RuntimeException('Chave do tenant nao definida para sincronizar disponibilidade de veiculo.');
        }

        return $chave;
    }

    private function comChave(string $chave, callable $callback): mixed
    {
        $chaveAnteriorExiste = array_key_exists('chave', $_SESSION);
        $chaveAnterior = $_SESSION['chave'] ?? null;

        $_SESSION['chave'] = $chave;

        try {
            return $callback();
        } finally {
            if ($chaveAnteriorExiste) {
                $_SESSION['chave'] = $chaveAnterior;
            } else {
                unset($_SESSION['chave']);
            }
        }
    }
}
