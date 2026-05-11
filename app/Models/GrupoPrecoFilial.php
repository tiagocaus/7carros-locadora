<?php

namespace App\Models;

/**
 * Model GrupoPrecoFilial
 *
 * Precos de grupo por filial (multi-moeda). Cada filial cadastra valores na sua
 * propria moeda (EUR, USD, BRL, etc). Grupos têm 1 linha por filial do tenant.
 *
 * Os campos monetarios + contadores migrados de `grupos`:
 *  - Planos: valor_plano_km_pago, valor_plano_km_controlado, valor_plano_km_livre,
 *            valor_km_excedente, km_franquia
 *  - Seguros: valor_seguro_carro, valor_seguro_terceiros,
 *             cobertura_carro, cobertura_terceiros
 *  - Extras: minutos_tolerancia, valor_tolerancia, valor_km_retorno,
 *            valor_condutor_adicional
 */
class GrupoPrecoFilial extends Model
{
    /**
     * Campos editaveis (todos os valores por filial)
     */
    public const CAMPOS = [
        'valor_plano_km_pago', 'valor_plano_km_controlado', 'valor_plano_km_livre',
        'valor_km_excedente', 'km_franquia',
        'valor_seguro_carro', 'valor_seguro_terceiros',
        'cobertura_carro', 'cobertura_terceiros',
        'minutos_tolerancia', 'valor_tolerancia',
        'valor_km_retorno', 'valor_condutor_adicional',
    ];

    /**
     * Busca os valores de um grupo em uma filial especifica
     */
    public function buscarPorGrupoFilial(int $grupoId, int $filialId): ?array
    {
        return $this->qb
            ->table('grupos_precos_filiais')
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->first();
    }

    /**
     * Lista todas as tabelas de valores de um grupo (uma por filial)
     */
    public function listarPorGrupo(int $grupoId): array
    {
        return $this->qb
            ->table('grupos_precos_filiais')
            ->where('id_grupo', '=', $grupoId)
            ->orderBy('id_matriz_filial', 'ASC')
            ->get();
    }

    /**
     * Lista todos os grupos de uma filial (com seus valores)
     */
    public function listarPorFilial(int $filialId): array
    {
        return $this->qb
            ->table('grupos_precos_filiais')
            ->where('id_matriz_filial', '=', $filialId)
            ->orderBy('id_grupo', 'ASC')
            ->get();
    }

    /**
     * Insere ou atualiza a linha de um grupo-filial (upsert por uk_grupo_filial)
     *
     * @param array $dados Deve conter id_grupo, id_matriz_filial e valores (veja CAMPOS)
     * @return int ID da linha criada/atualizada
     */
    public function upsert(array $dados): int
    {
        $grupoId = (int) $dados['id_grupo'];
        $filialId = (int) $dados['id_matriz_filial'];
        $chave = $dados['chave'] ?? ($_SESSION['chave'] ?? '');

        $existente = $this->buscarPorGrupoFilial($grupoId, $filialId);

        $payload = ['chave' => $chave, 'id_grupo' => $grupoId, 'id_matriz_filial' => $filialId];
        foreach (self::CAMPOS as $campo) {
            if (array_key_exists($campo, $dados)) {
                $payload[$campo] = $this->normalizarValor($campo, $dados[$campo]);
            }
        }

        if ($existente) {
            $this->qb
                ->table('grupos_precos_filiais')
                ->where('id', '=', (int) $existente['id'])
                ->update($payload);
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('grupos_precos_filiais')
            ->insert($payload);
    }

    /**
     * Atualiza valores de um grupo-filial existente
     */
    public function atualizarPorGrupoFilial(int $grupoId, int $filialId, array $dados): int
    {
        $payload = [];
        foreach (self::CAMPOS as $campo) {
            if (array_key_exists($campo, $dados)) {
                $payload[$campo] = $this->normalizarValor($campo, $dados[$campo]);
            }
        }
        if (empty($payload)) {
            return 0;
        }
        return $this->qb
            ->table('grupos_precos_filiais')
            ->where('id_grupo', '=', $grupoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->update($payload);
    }

    /**
     * Garante que o grupo tenha uma linha em grupos_precos_filiais pra CADA filial do tenant.
     * Usado ao criar um grupo novo ou quando detectar filiais sem entry.
     */
    public function garantirEntriesParaGrupo(int $grupoId): void
    {
        $chave = $_SESSION['chave'] ?? null;
        if (!$chave) {
            return;
        }

        $filiais = $this->qb
            ->table('matrizes_filiais')
            ->select(['id'])
            ->where('chave', '=', $chave)
            ->pluck('id');

        foreach ($filiais as $filialId) {
            $existente = $this->buscarPorGrupoFilial($grupoId, (int) $filialId);
            if ($existente) {
                continue;
            }
            $this->qb
                ->table('grupos_precos_filiais')
                ->insert([
                    'chave' => $chave,
                    'id_grupo' => $grupoId,
                    'id_matriz_filial' => (int) $filialId,
                ]);
        }
    }

    /**
     * Garante que a filial tenha uma linha pra CADA grupo do tenant.
     * Usado ao criar uma filial nova.
     */
    public function garantirEntriesParaFilial(int $filialId): void
    {
        $chave = $_SESSION['chave'] ?? null;
        if (!$chave) {
            return;
        }

        $grupos = $this->qb
            ->table('grupos')
            ->select(['id'])
            ->pluck('id');

        foreach ($grupos as $grupoId) {
            $existente = $this->buscarPorGrupoFilial((int) $grupoId, $filialId);
            if ($existente) {
                continue;
            }
            $this->qb
                ->table('grupos_precos_filiais')
                ->insert([
                    'chave' => $chave,
                    'id_grupo' => (int) $grupoId,
                    'id_matriz_filial' => $filialId,
                ]);
        }
    }

    /**
     * Exclui um registro (raramente usado — FK CASCADE trata deletes de grupo/filial)
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('grupos_precos_filiais')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Normaliza um valor de entrada para o tipo esperado da coluna
     */
    private function normalizarValor(string $campo, $valor): mixed
    {
        if (in_array($campo, ['km_franquia', 'minutos_tolerancia'], true)) {
            return (int) $valor;
        }
        if (is_string($valor)) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }
        return (float) $valor;
    }
}
