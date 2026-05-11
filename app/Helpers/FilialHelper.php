<?php

namespace App\Helpers;

use App\Core\Auth;

/**
 * Filial Helper
 *
 * Helper para filtrar queries por filiais permitidas do funcionário.
 * Usado para controle de acesso multi-filial.
 */
class FilialHelper
{
    /**
     * Retorna cláusula WHERE para filtrar por filiais permitidas
     *
     * @param string $coluna Nome da coluna de filial (ex: 'id_matriz_filial')
     * @param string|null $alias Alias da tabela (ex: 'c' para 'clientes c')
     * @return array [whereClause, params]
     */
    public static function whereFiliais(string $coluna = 'id_matriz_filial', ?string $alias = null): array
    {
        $filiais = Auth::filiaisPermitidas();

        // Se vazio, não restringe (admin/proprietário sem restrição)
        if (empty($filiais)) {
            return ['1=1', []];
        }

        $column = $alias ? "{$alias}.{$coluna}" : $coluna;
        $placeholders = implode(',', array_fill(0, count($filiais), '?'));
        $whereClause = "{$column} IN ({$placeholders})";

        return [$whereClause, $filiais];
    }

    /**
     * Retorna cláusula WHERE para locações (considera retirada OU devolução)
     *
     * @param string|null $alias Alias da tabela
     * @return array [whereClause, params]
     */
    public static function whereLocacoes(?string $alias = null): array
    {
        $filiais = Auth::filiaisPermitidas();

        if (empty($filiais)) {
            return ['1=1', []];
        }

        $prefix = $alias ? "{$alias}." : '';
        $placeholders = implode(',', array_fill(0, count($filiais), '?'));

        // Mostrar locação se usuário tem acesso a filial de retirada OU devolução
        $whereClause = "({$prefix}id_matriz_filial_retirada IN ({$placeholders}) OR {$prefix}id_matriz_filial_devolucao IN ({$placeholders}))";

        // Duplicar params (uma vez para cada IN)
        $params = array_merge($filiais, $filiais);

        return [$whereClause, $params];
    }

    /**
     * Retorna cláusula WHERE para contratos
     *
     * @param string|null $alias Alias da tabela
     * @return array [whereClause, params]
     */
    public static function whereContratos(?string $alias = null): array
    {
        return self::whereFiliais('id_matriz_filial_retirada', $alias);
    }

    /**
     * Verifica se usuário tem acesso a uma filial específica
     *
     * @param int|null $filialId ID da filial
     * @return bool
     */
    public static function temAcessoFilial(?int $filialId): bool
    {
        if ($filialId === null) {
            return true; // Registros sem filial são acessíveis
        }

        $filiais = Auth::filiaisPermitidas();

        // Se vazio, tem acesso total
        if (empty($filiais)) {
            return true;
        }

        return in_array($filialId, $filiais);
    }

    /**
     * Retorna as filiais permitidas do usuário logado
     *
     * @return array IDs das filiais
     */
    public static function getFiliaisPermitidas(): array
    {
        return Auth::filiaisPermitidas();
    }

    /**
     * Verifica se o usuário tem restrição de filiais
     *
     * @return bool true se há restrição, false se acesso total
     */
    public static function temRestricaoFiliais(): bool
    {
        $filiais = Auth::filiaisPermitidas();
        return !empty($filiais);
    }
}
