<?php

/**
 * Migration 00356: Normalizar IDs de funcionarios_roles
 *
 * Reordena funcionarios_roles.id para iniciar em 1 e ajusta as tabelas
 * vinculadas. Esta migration e intencionalmente irreversivel: apos remapear
 * chaves primarias e estrangeiras, restaurar IDs antigos exigiria backup.
 */

use App\Database\Migration;

return new class extends Migration
{
    private const FK_FUNCIONARIOS_ROLE = 'fk_funcionarios_role_id';
    private const FK_ROLE_PERMISSIONS_ROLE = 'fk_funcionarios_role_permissions_role_id';

    private const SYSTEM_ROLE_ORDER = [
        'Proprietário',
        'Gerente',
        'Coordenador Administrativo',
        'Assistente Administrativo',
        'Atendente',
        'Vistoriador',
        'Manobrador',
        'Serviços Gerais',
    ];

    public function up(): void
    {
        if (!$this->tableExists('funcionarios_roles')) {
            return;
        }

        $roles = $this->loadRolesInTargetOrder();
        if (empty($roles)) {
            $this->execute('ALTER TABLE funcionarios_roles AUTO_INCREMENT = 1');
            return;
        }

        $this->assertNoOrphanReferences();

        $idMap = $this->buildIdMap($roles);
        if (!$this->needsNormalization($idMap)) {
            $nextId = count($roles) + 1;
            $this->execute("ALTER TABLE funcionarios_roles AUTO_INCREMENT = {$nextId}");
            return;
        }

        $this->dropRoleForeignKeys();

        try {
            $startedTransaction = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $offset = $this->calculateSafeOffset($roles);

            $this->execute("UPDATE funcionarios_roles SET id = id + {$offset}");
            $this->execute("UPDATE funcionarios SET id_role = id_role + {$offset} WHERE id_role IS NOT NULL");
            $this->execute("UPDATE funcionarios_role_permissions SET role_id = role_id + {$offset}");

            foreach ($idMap as $oldId => $newId) {
                $tempId = $oldId + $offset;

                $this->updateRoleId($tempId, $newId);
                $this->updateFuncionarioRoleId($tempId, $newId);
                $this->updateRolePermissionRoleId($tempId, $newId);
            }

            $this->assertNoOrphanReferences();
            $this->assertContiguousRoleIds(count($roles));
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->recreateRoleForeignKeys();
            $nextId = count($roles) + 1;
            $this->execute("ALTER TABLE funcionarios_roles AUTO_INCREMENT = {$nextId}");
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->recreateRoleForeignKeys();
            throw $e;
        }
    }

    public function down(): void
    {
        // Irreversivel com seguranca sem armazenar snapshot dos IDs antigos.
    }

    private function loadRolesInTargetOrder(): array
    {
        $stmt = $this->pdo->query('SELECT id, chave, name FROM funcionarios_roles');
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $systemOrder = array_flip(self::SYSTEM_ROLE_ORDER);

        usort($roles, static function (array $a, array $b) use ($systemOrder): int {
            $aSystemOrder = $a['chave'] === '0' && array_key_exists($a['name'], $systemOrder)
                ? $systemOrder[$a['name']]
                : null;
            $bSystemOrder = $b['chave'] === '0' && array_key_exists($b['name'], $systemOrder)
                ? $systemOrder[$b['name']]
                : null;

            if ($aSystemOrder !== null || $bSystemOrder !== null) {
                if ($aSystemOrder === null) {
                    return 1;
                }
                if ($bSystemOrder === null) {
                    return -1;
                }
                return $aSystemOrder <=> $bSystemOrder;
            }

            return [$a['chave'], $a['name'], (int) $a['id']]
                <=> [$b['chave'], $b['name'], (int) $b['id']];
        });

        return $roles;
    }

    private function buildIdMap(array $roles): array
    {
        $idMap = [];
        $nextId = 1;

        foreach ($roles as $role) {
            $idMap[(int) $role['id']] = $nextId++;
        }

        return $idMap;
    }

    private function needsNormalization(array $idMap): bool
    {
        foreach ($idMap as $oldId => $newId) {
            if ((int) $oldId !== (int) $newId) {
                return true;
            }
        }

        return false;
    }

    private function calculateSafeOffset(array $roles): int
    {
        $maxId = 0;
        foreach ($roles as $role) {
            $maxId = max($maxId, (int) $role['id']);
        }

        $offset = $maxId + count($roles) + 1;
        if ($offset + $maxId >= 4294967295) {
            throw new \RuntimeException('Nao foi possivel calcular offset seguro para normalizar funcionarios_roles.');
        }

        return $offset;
    }

    private function assertNoOrphanReferences(): void
    {
        $orphanFuncionarios = (int) $this->pdo
            ->query(
                'SELECT COUNT(*)
                   FROM funcionarios f
              LEFT JOIN funcionarios_roles r ON r.id = f.id_role
                  WHERE f.id_role IS NOT NULL
                    AND r.id IS NULL'
            )
            ->fetchColumn();

        if ($orphanFuncionarios > 0) {
            throw new \RuntimeException("Existem {$orphanFuncionarios} funcionarios com id_role orfao.");
        }

        $orphanPermissions = (int) $this->pdo
            ->query(
                'SELECT COUNT(*)
                   FROM funcionarios_role_permissions rp
              LEFT JOIN funcionarios_roles r ON r.id = rp.role_id
                  WHERE r.id IS NULL'
            )
            ->fetchColumn();

        if ($orphanPermissions > 0) {
            throw new \RuntimeException("Existem {$orphanPermissions} permissoes de role com role_id orfao.");
        }
    }

    private function assertContiguousRoleIds(int $expectedCount): void
    {
        $stats = $this->pdo
            ->query('SELECT COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id FROM funcionarios_roles')
            ->fetch(\PDO::FETCH_ASSOC);

        if (
            (int) $stats['total'] !== $expectedCount
            || (int) $stats['min_id'] !== 1
            || (int) $stats['max_id'] !== $expectedCount
        ) {
            throw new \RuntimeException('Normalizacao de funcionarios_roles nao gerou IDs continuos iniciando em 1.');
        }
    }

    private function dropRoleForeignKeys(): void
    {
        $this->dropForeignKeyIfExists('funcionarios', self::FK_FUNCIONARIOS_ROLE);
        $this->dropForeignKeyIfExists('funcionarios_role_permissions', self::FK_ROLE_PERMISSIONS_ROLE);
    }

    private function recreateRoleForeignKeys(): void
    {
        $this->addForeignKeyIfNotExists(
            'funcionarios',
            'id_role',
            'funcionarios_roles',
            'id',
            'SET NULL',
            'CASCADE',
            self::FK_FUNCIONARIOS_ROLE
        );

        $this->addForeignKeyIfNotExists(
            'funcionarios_role_permissions',
            'role_id',
            'funcionarios_roles',
            'id',
            'CASCADE',
            'CASCADE',
            self::FK_ROLE_PERMISSIONS_ROLE
        );
    }

    private function updateRoleId(int $fromId, int $toId): void
    {
        $stmt = $this->pdo->prepare('UPDATE funcionarios_roles SET id = ? WHERE id = ?');
        $stmt->execute([$toId, $fromId]);
    }

    private function updateFuncionarioRoleId(int $fromId, int $toId): void
    {
        $stmt = $this->pdo->prepare('UPDATE funcionarios SET id_role = ? WHERE id_role = ?');
        $stmt->execute([$toId, $fromId]);
    }

    private function updateRolePermissionRoleId(int $fromId, int $toId): void
    {
        $stmt = $this->pdo->prepare('UPDATE funcionarios_role_permissions SET role_id = ? WHERE role_id = ?');
        $stmt->execute([$toId, $fromId]);
    }
};
