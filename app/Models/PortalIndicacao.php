<?php

namespace App\Models;

class PortalIndicacao extends Model
{
    public function buscarOuCriarCodigo(int $idCliente): array
    {
        $existente = $this->qb
            ->table('portal_indicacao_codigos')
            ->where('id_cliente', '=', $idCliente)
            ->first();

        if ($existente) {
            return $existente;
        }

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $codigo = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
            $duplicado = $this->qb
                ->table('portal_indicacao_codigos')
                ->where('codigo', '=', $codigo)
                ->exists();

            if ($duplicado) {
                continue;
            }

            $id = $this->qb
                ->table('portal_indicacao_codigos')
                ->insert([
                    'id_cliente' => $idCliente,
                    'codigo' => $codigo,
                ]);

            return [
                'id' => $id,
                'id_cliente' => $idCliente,
                'codigo' => $codigo,
            ];
        }

        throw new \RuntimeException('Nao foi possivel gerar o codigo de indicacao.');
    }

    public function registrarClique(int $idCodigo, ?string $visitanteHash): int
    {
        return $this->qb
            ->table('portal_indicacao_eventos')
            ->insert([
                'id_codigo' => $idCodigo,
                'tipo' => 'clique',
                'visitante_hash' => $visitanteHash,
            ]);
    }

    public function resumo(int $idCliente): array
    {
        $codigo = $this->buscarOuCriarCodigo($idCliente);
        $eventos = $this->qb
            ->table('portal_indicacao_eventos')
            ->selectRaw("
                COUNT(CASE WHEN tipo = 'clique' THEN 1 END) AS cliques,
                COUNT(CASE WHEN tipo = 'conversao' THEN 1 END) AS conversoes
            ")
            ->where('id_codigo', '=', (int) $codigo['id'])
            ->first();

        return [
            'codigo' => $codigo['codigo'],
            'cliques' => (int) ($eventos['cliques'] ?? 0),
            'conversoes' => (int) ($eventos['conversoes'] ?? 0),
        ];
    }
}
