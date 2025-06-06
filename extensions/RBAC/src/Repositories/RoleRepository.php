<?php

namespace Glueful\Extensions\RBAC\Repositories;

use Glueful\Repository\BaseRepository;
use Glueful\Extensions\RBAC\Models\Role;
use Glueful\Helpers\Utils;

/**
 * Role Repository
 *
 * Handles CRUD operations and queries for roles
 *
 * Features:
 * - Hierarchical role management
 * - Role inheritance queries
 * - System role protection
 * - Metadata-based queries
 */
class RoleRepository extends BaseRepository
{
    protected string $table = 'roles';
    protected array $defaultFields = [
        'uuid', 'name', 'slug', 'description', 'parent_uuid',
        'level', 'is_system', 'metadata', 'status',
        'created_at', 'updated_at', 'deleted_at'
    ];

    public function getTableName(): string
    {
        return $this->table;
    }

    public function create(array $data): string
    {
        if (!isset($data['uuid'])) {
            $data['uuid'] = Utils::generateNanoID();
        }

        $success = $this->db->insert($this->table, $data);

        if (!$success) {
            throw new \RuntimeException('Failed to create role');
        }

        return $data['uuid'];
    }

    public function createRole(array $data): ?Role
    {
        $uuid = $this->create($data);
        return $this->findByUuid($uuid);
    }

    public function findByUuid(string $uuid): ?Role
    {
        $result = $this->db->select($this->table, $this->defaultFields)
            ->where(['uuid' => $uuid])
            ->limit(1)
            ->get();

        return $result ? new Role($result[0]) : null;
    }

    public function findBySlug(string $slug): ?Role
    {
        $result = $this->db->select($this->table, $this->defaultFields)
            ->where(['slug' => $slug])
            ->limit(1)
            ->get();

        return $result ? new Role($result[0]) : null;
    }

    public function findByName(string $name): ?Role
    {
        $result = $this->db->select($this->table, $this->defaultFields)
            ->where(['name' => $name])
            ->limit(1)
            ->get();

        return $result ? new Role($result[0]) : null;
    }

    public function update(string $uuid, array $data): bool
    {
        if ($this->hasUpdatedAt) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->db->update($this->table, $data, ['uuid' => $uuid]);
    }

    public function delete(string $uuid): bool
    {
        return $this->db->delete($this->table, ['uuid' => $uuid]);
    }

    public function softDeleteRole(string $uuid): bool
    {
        return $this->update($uuid, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function findAllRoles(array $filters = []): array
    {
        $query = $this->db->select($this->table, $this->defaultFields);

        if (isset($filters['status'])) {
            $query->where(['status' => $filters['status']]);
        }

        if (isset($filters['is_system'])) {
            $query->where(['is_system' => $filters['is_system']]);
        }

        if (isset($filters['parent_uuid'])) {
            $query->where(['parent_uuid' => $filters['parent_uuid']]);
        }

        if (isset($filters['exclude_deleted']) && $filters['exclude_deleted']) {
            $query->where(['deleted_at' => null]);
        }

        $query->orderBy(['level DESC', 'name ASC']);

        $results = $query->get();
        return array_map(fn($row) => new Role($row), $results);
    }

    public function findByLevel(int $level): array
    {
        $results = $this->db->select($this->table, $this->defaultFields)
            ->where(['level' => $level])
            ->orderBy(['name ASC'])
            ->get();

        return array_map(fn($row) => new Role($row), $results);
    }

    public function findChildren(string $parentUuid): array
    {
        $results = $this->db->select($this->table, $this->defaultFields)
            ->where(['parent_uuid' => $parentUuid])
            ->orderBy(['level ASC', 'name ASC'])
            ->get();

        return array_map(fn($row) => new Role($row), $results);
    }

    public function findRootRoles(): array
    {
        $results = $this->db->select($this->table, $this->defaultFields)
            ->where(['parent_uuid' => null])
            ->orderBy(['level DESC', 'name ASC'])
            ->get();

        return array_map(fn($row) => new Role($row), $results);
    }

    public function getRoleHierarchy(string $roleUuid): array
    {
        $hierarchy = [];
        $currentRole = $this->findByUuid($roleUuid);

        while ($currentRole && !in_array($currentRole->getUuid(), array_column($hierarchy, 'uuid'))) {
            $hierarchy[] = $currentRole;

            if ($currentRole->hasParent()) {
                $currentRole = $this->findByUuid($currentRole->getParentUuid());
            } else {
                break;
            }
        }

        return $hierarchy;
    }

    public function findSystemRoles(): array
    {
        return $this->findAllRoles(['is_system' => 1, 'exclude_deleted' => true]);
    }

    public function findActiveRoles(): array
    {
        return $this->findAllRoles(['status' => 'active', 'exclude_deleted' => true]);
    }

    public function roleExists(string $name, ?string $excludeUuid = null): bool
    {
        $query = $this->db->select($this->table, ['uuid'])
            ->where(['name' => $name])
            ->limit(1);

        if ($excludeUuid) {
            $query->where(['uuid' => ['!=', $excludeUuid]]);
        }

        $result = $query->get();
        return !empty($result);
    }

    public function slugExists(string $slug, ?string $excludeUuid = null): bool
    {
        $query = $this->db->select($this->table, ['uuid'])
            ->where(['slug' => $slug])
            ->limit(1);

        if ($excludeUuid) {
            $query->where(['uuid' => ['!=', $excludeUuid]]);
        }

        $result = $query->get();
        return !empty($result);
    }

    public function countRoles(array $filters = []): int
    {
        $query = $this->db->select($this->table, ['COUNT(*) as count']);

        if (isset($filters['status'])) {
            $query->where(['status' => $filters['status']]);
        }

        if (isset($filters['is_system'])) {
            $query->where(['is_system' => $filters['is_system']]);
        }

        if (isset($filters['exclude_deleted']) && $filters['exclude_deleted']) {
            $query->where(['deleted_at' => null]);
        }

        $result = $query->get();
        return (int)($result[0]['count'] ?? 0);
    }
}
