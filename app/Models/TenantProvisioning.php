<?php

namespace App\Models;

/**
 * Acesso a dados administrativo para o ciclo de vida de tenants.
 */
class TenantProvisioning extends Model
{
    /**
     * Tabelas internas que preservam o vínculo com o tenant após o cancelamento.
     */
    private const EXCLUDED_TENANT_TABLES = [
        'feature_request_followers',
        'feature_request_votes',
        'feature_requests',
    ];

    private ?string $ultimaTabelaProcessada = null;

    public function tenantExiste(string $chave): bool
    {
        return $this->qb
            ->table('funcionarios')
            ->withChave($chave)
            ->exists();
    }

    public function roleIds(string $chave): array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->withChave($chave)
            ->pluck('id');
    }

    public function apagarPermissoesRoles(array $roleIds): void
    {
        foreach ($roleIds as $roleId) {
            $this->qb
                ->table('funcionarios_role_permissions')
                ->withoutChave()
                ->where('role_id', '=', $roleId)
                ->delete();
        }
    }

    /**
     * Retorna o diagnóstico do schema sem alterar dados.
     */
    public function diagnosticarInventario(): array
    {
        $atuais = $this->listarTabelasTenant();
        $gerenciadas = array_values(array_diff($atuais, self::EXCLUDED_TENANT_TABLES));
        $excluidas = array_values(array_intersect($atuais, self::EXCLUDED_TENANT_TABLES));
        $foreignKeys = $this->listarForeignKeys();
        [$ordem, $ciclos] = $this->ordenarPorDependencias($gerenciadas, $foreignKeys);
        $dependenciasSemChave = [];
        $bloqueiosExclusoes = [];

        foreach ($foreignKeys as $foreignKey) {
            $child = $foreignKey['child_table'];
            $parent = $foreignKey['parent_table'];

            if (!in_array($parent, $gerenciadas, true)) {
                continue;
            }

            if (in_array($child, $excluidas, true)) {
                if ($foreignKey['delete_rule'] !== 'SET NULL') {
                    $bloqueiosExclusoes[] = $foreignKey;
                }
                continue;
            }

            if (!in_array($child, $gerenciadas, true)) {
                $dependenciasSemChave[] = $foreignKey;
            }
        }

        return [
            'atuais' => $atuais,
            'gerenciadas' => $gerenciadas,
            'excluidas' => $excluidas,
            'ordem' => $ordem,
            'ciclos' => $ciclos,
            'dependencias_sem_chave' => $dependenciasSemChave,
            'bloqueios_exclusoes' => $bloqueiosExclusoes,
        ];
    }

    /**
     * Descobre e ordena as tabelas do banco atualmente conectado.
     */
    public function tabelasParaTermino(): array
    {
        $diagnostico = $this->diagnosticarInventario();

        if ($diagnostico['ciclos'] !== []) {
            throw new \RuntimeException(
                'Ciclo entre tabelas tenant impede ordem segura de término: '
                . implode(', ', $diagnostico['ciclos'])
            );
        }

        if ($diagnostico['bloqueios_exclusoes'] !== []) {
            throw new \RuntimeException(
                'Tabela interna preservada depende de tabela tenant: '
                . $this->formatarDependencias($diagnostico['bloqueios_exclusoes'])
            );
        }

        $restritivasSemChave = array_values(array_filter(
            $diagnostico['dependencias_sem_chave'],
            static fn(array $fk): bool => in_array(
                $fk['delete_rule'],
                ['RESTRICT', 'NO ACTION'],
                true
            )
        ));

        if ($restritivasSemChave !== []) {
            throw new \RuntimeException(
                'Tabela sem chave bloqueia término do tenant: '
                . $this->formatarDependencias($restritivasSemChave)
            );
        }

        return $diagnostico['ordem'];
    }

    public function apagarDadosTenant(string $chave, array $tabelas): array
    {
        $counts = [];
        $this->ultimaTabelaProcessada = null;

        foreach ($tabelas as $tabela) {
            $this->ultimaTabelaProcessada = $tabela;
            $deleted = $this->qb
                ->table($tabela)
                ->withChave($chave)
                ->delete();

            if ($deleted > 0) {
                $counts[$tabela] = $deleted;
            }
        }

        return $counts;
    }

    public function ultimaTabelaProcessada(): ?string
    {
        return $this->ultimaTabelaProcessada;
    }

    public function beginTransaction(): void
    {
        $this->qb->beginTransaction();
    }

    public function commit(): void
    {
        $this->qb->commit();
    }

    public function rollback(): void
    {
        $this->qb->rollback();
    }

    private function listarTabelasTenant(): array
    {
        $mysqli = $this->getMysqli();
        $sql = <<<'SQL'
            SELECT DISTINCT t.TABLE_NAME
            FROM information_schema.TABLES t
            INNER JOIN information_schema.COLUMNS c
                ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
                AND c.TABLE_NAME = t.TABLE_NAME
                AND c.COLUMN_NAME = 'chave'
            WHERE t.TABLE_SCHEMA = DATABASE()
              AND t.TABLE_TYPE = 'BASE TABLE'
            ORDER BY t.TABLE_NAME
        SQL;

        $result = $mysqli->query($sql);
        if ($result === false) {
            throw new \RuntimeException(
                'Erro ao consultar tabelas tenant: ' . $mysqli->error
            );
        }

        return array_column($result->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME');
    }

    private function listarForeignKeys(): array
    {
        $mysqli = $this->getMysqli();
        $sql = <<<'SQL'
            SELECT
                k.TABLE_NAME AS child_table,
                k.REFERENCED_TABLE_NAME AS parent_table,
                r.DELETE_RULE AS delete_rule
            FROM information_schema.KEY_COLUMN_USAGE k
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                AND r.TABLE_NAME = k.TABLE_NAME
                AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.CONSTRAINT_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
            GROUP BY k.TABLE_NAME, k.REFERENCED_TABLE_NAME, r.DELETE_RULE
            ORDER BY k.TABLE_NAME, k.REFERENCED_TABLE_NAME
        SQL;

        $result = $mysqli->query($sql);
        if ($result === false) {
            throw new \RuntimeException(
                'Erro ao consultar dependências de tabelas: ' . $mysqli->error
            );
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function ordenarPorDependencias(array $tabelas, array $foreignKeys): array
    {
        sort($tabelas);
        $tabelasSet = array_fill_keys($tabelas, true);
        $arestas = array_fill_keys($tabelas, []);
        $grauEntrada = array_fill_keys($tabelas, 0);

        foreach ($foreignKeys as $foreignKey) {
            $child = $foreignKey['child_table'];
            $parent = $foreignKey['parent_table'];

            if (
                !in_array($foreignKey['delete_rule'], ['RESTRICT', 'NO ACTION'], true)
                || $child === $parent
                || !isset($tabelasSet[$child], $tabelasSet[$parent])
                || isset($arestas[$child][$parent])
            ) {
                continue;
            }

            // Apenas RESTRICT/NO ACTION exigem a filha antes da tabela-pai.
            $arestas[$child][$parent] = true;
            $grauEntrada[$parent]++;
        }

        $fila = [];
        foreach ($grauEntrada as $tabela => $grau) {
            if ($grau === 0) {
                $fila[] = $tabela;
            }
        }
        sort($fila);

        $ordem = [];
        while ($fila !== []) {
            $tabela = array_shift($fila);
            $ordem[] = $tabela;

            foreach (array_keys($arestas[$tabela]) as $parent) {
                $grauEntrada[$parent]--;
                if ($grauEntrada[$parent] === 0) {
                    $fila[] = $parent;
                    sort($fila);
                }
            }
        }

        $ciclos = [];
        foreach ($grauEntrada as $tabela => $grau) {
            if ($grau > 0) {
                $ciclos[] = $tabela;
            }
        }
        sort($ciclos);

        return [$ordem, $ciclos];
    }

    private function formatarDependencias(array $foreignKeys): string
    {
        return implode(', ', array_map(
            static fn(array $fk): string => sprintf(
                '%s -> %s (%s)',
                $fk['child_table'],
                $fk['parent_table'],
                $fk['delete_rule']
            ),
            $foreignKeys
        ));
    }
}
