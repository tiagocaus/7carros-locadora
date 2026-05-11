<?php

namespace App\Models;

/**
 * Model MatrizFilialLocal
 *
 * Locais de atendimento (aliases) de uma matriz/filial.
 * Cada filial pode ter N locais onde tambem atende. No site cliente os locais
 * aparecem como opcoes no select de retirada/devolucao, mas todos resolvem
 * pra filial dona (id_matriz_filial) — mesma moeda, precos, contratos.
 */
class MatrizFilialLocal extends Model
{
    public function listarPorFilial(int $filialId): array
    {
        return $this->qb
            ->table('matrizes_filiais_locais')
            ->where('id_matriz_filial', '=', $filialId)
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('matrizes_filiais_locais')
            ->where('id', '=', $id)
            ->first();
    }

    public function criar(array $dados): int
    {
        return $this->qb
            ->table('matrizes_filiais_locais')
            ->insert([
                'chave' => $dados['chave'],
                'id_matriz_filial' => (int) $dados['id_matriz_filial'],
                'nome' => $dados['nome'] ?? null,
                'cep' => $dados['cep'] ?? null,
                'rua' => $dados['rua'] ?? null,
                'numero' => $dados['numero'] ?? null,
                'complemento' => $dados['complemento'] ?? null,
                'bairro' => $dados['bairro'] ?? '',
                'cidade' => $dados['cidade'] ?? '',
                'estado' => $dados['estado'] ?? '',
                'pais' => $dados['pais'] ?? 'BR',
            ]);
    }

    public function atualizar(int $id, array $dados): int
    {
        $campos = ['nome', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais'];
        $update = [];
        foreach ($campos as $c) {
            if (array_key_exists($c, $dados)) {
                $update[$c] = $dados[$c];
            }
        }
        if (empty($update)) {
            return 0;
        }
        return $this->qb
            ->table('matrizes_filiais_locais')
            ->where('id', '=', $id)
            ->update($update);
    }

    public function excluir(int $id): int
    {
        return $this->qb
            ->table('matrizes_filiais_locais')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Sincroniza locais de uma filial: substitui o conjunto atual por `locais`.
     *
     * - Itens sem `id` viram INSERT
     * - Itens com `id` viram UPDATE
     * - Itens existentes fora da lista recebida viram DELETE
     *
     * Tudo dentro de transacao implicita via shared mysqli.
     */
    public function sincronizar(int $filialId, string $chave, array $locais): void
    {
        $existentes = $this->listarPorFilial($filialId);
        $idsExistentes = array_map(fn($l) => (int) $l['id'], $existentes);
        $idsEnviados = [];

        foreach ($locais as $local) {
            $localData = $local;
            $localData['chave'] = $chave;
            $localData['id_matriz_filial'] = $filialId;

            $idEnviado = isset($local['id']) ? (int) $local['id'] : 0;
            if ($idEnviado > 0 && in_array($idEnviado, $idsExistentes, true)) {
                $this->atualizar($idEnviado, $localData);
                $idsEnviados[] = $idEnviado;
            } else {
                $novoId = $this->criar($localData);
                $idsEnviados[] = $novoId;
            }
        }

        $paraRemover = array_diff($idsExistentes, $idsEnviados);
        foreach ($paraRemover as $id) {
            $this->excluir((int) $id);
        }
    }
}
