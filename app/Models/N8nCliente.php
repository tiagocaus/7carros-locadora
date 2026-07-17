<?php

namespace App\Models;

/**
 * Consulta cross-tenant de contatos disponibilizados para automacoes do n8n.
 *
 * O uso de withoutChave() e intencional: esta Model atende exclusivamente uma
 * integracao publica autenticada que precisa localizar clientes entre tenants.
 */
class N8nCliente extends Model
{
    /**
     * Lista Proprietarios ativos com os contatos necessarios para a automacao.
     */
    public function listarProprietariosAtivosComContato(): array
    {
        return $this->qb
            ->table('funcionarios', 'f')
            ->select([
                'f.id',
                'f.chave',
                'f.tel_cel',
                'f.email',
            ])
            ->selectRaw(
                "COALESCE(
                    (SELECT MIN(mf.created_at)
                       FROM matrizes_filiais mf
                      WHERE mf.chave = f.chave
                        AND mf.tipo = 'M'),
                    (SELECT MIN(f2.created_at)
                       FROM funcionarios f2
                      WHERE f2.chave = f.chave)
                ) AS empresa_created_at"
            )
            ->innerJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->withoutChave()
            ->where('r.name', '=', 'Proprietário')
            ->where('f.status', '=', 'A')
            ->whereRaw("NULLIF(TRIM(f.tel_cel), '') IS NOT NULL")
            ->whereRaw("NULLIF(TRIM(f.email), '') IS NOT NULL")
            ->orderBy('f.chave', 'ASC')
            ->orderBy('f.id', 'ASC')
            ->get();
    }
}
