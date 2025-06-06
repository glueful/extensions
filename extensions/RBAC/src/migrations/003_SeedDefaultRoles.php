<?php

namespace Glueful\Extensions\RBAC\Database\Migrations;

use Glueful\Database\Migrations\MigrationInterface;
use Glueful\Database\Schema\SchemaManager;
use Glueful\Helpers\Utils;
use Glueful\Database\Connection;
use Glueful\Database\QueryBuilder;

/**
 * RBAC Default Roles Seeder
 *
 * Seeds default roles and permissions:
 * - System roles (superuser, admin, user)
 * - Core permissions (user management, system access)
 * - Default role-permission assignments
 *
 * Features:
 * - Core system permissions
 * - Hierarchical role structure
 * - Clean database seeding
 */
class SeedDefaultRoles implements MigrationInterface
{
    /** @var QueryBuilder Database interaction instance */
    private QueryBuilder $db;

    /**
     * Execute the migration
     */
    public function up(SchemaManager $schema): void
    {
        $connection = new Connection();
        $this->db = new QueryBuilder($connection->getPDO(), $connection->getDriver());

        // Generate UUIDs for roles
        $superuserUuid = Utils::generateNanoID();
        $adminUuid = Utils::generateNanoID();
        $managerUuid = Utils::generateNanoID();
        $userUuid = Utils::generateNanoID();

        // Insert default roles
        $roles = [
            [
                'uuid' => $superuserUuid,
                'name' => 'Superuser',
                'slug' => 'superuser',
                'description' => 'System administrator with full access',
                'level' => 100,
                'is_system' => 1,
                'status' => 'active'
            ],
            [
                'uuid' => $adminUuid,
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Site administrator with management access',
                'level' => 80,
                'is_system' => 1,
                'status' => 'active'
            ],
            [
                'uuid' => $managerUuid,
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'User manager with limited admin access',
                'level' => 60,
                'is_system' => 1,
                'status' => 'active'
            ],
            [
                'uuid' => $userUuid,
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Standard user with basic access',
                'level' => 10,
                'is_system' => 1,
                'status' => 'active'
            ]
        ];

        foreach ($roles as $role) {
            $roleId = $this->db->insert('roles', $role);
            if (!$roleId) {
                throw new \RuntimeException('Failed to create role: ' . $role['name']);
            }
        }

        // Generate UUIDs for permissions
        $permissionUuids = [
            'sys_access' => Utils::generateNanoID(),
            'sys_config' => Utils::generateNanoID(),
            'usr_view' => Utils::generateNanoID(),
            'usr_create' => Utils::generateNanoID(),
            'usr_edit' => Utils::generateNanoID(),
            'usr_delete' => Utils::generateNanoID(),
            'rol_view' => Utils::generateNanoID(),
            'rol_create' => Utils::generateNanoID(),
            'rol_edit' => Utils::generateNanoID(),
            'rol_delete' => Utils::generateNanoID(),
            'rol_assign' => Utils::generateNanoID(),
            'cnt_view' => Utils::generateNanoID(),
            'cnt_create' => Utils::generateNanoID(),
            'cnt_edit' => Utils::generateNanoID(),
            'cnt_delete' => Utils::generateNanoID()
        ];

        // Insert default permissions
        $permissions = [
            // System permissions
            [
                'uuid' => $permissionUuids['sys_access'],
                'name' => 'System Access',
                'slug' => 'system.access',
                'category' => 'system',
                'description' => 'Access to system functionality'
            ],
            [
                'uuid' => $permissionUuids['sys_config'],
                'name' => 'System Configuration',
                'slug' => 'system.config',
                'category' => 'system',
                'description' => 'Modify system configuration'
            ],

            // User management
            [
                'uuid' => $permissionUuids['usr_view'],
                'name' => 'View Users',
                'slug' => 'users.view',
                'category' => 'users',
                'description' => 'View user accounts'
            ],
            [
                'uuid' => $permissionUuids['usr_create'],
                'name' => 'Create Users',
                'slug' => 'users.create',
                'category' => 'users',
                'description' => 'Create new user accounts'
            ],
            [
                'uuid' => $permissionUuids['usr_edit'],
                'name' => 'Edit Users',
                'slug' => 'users.edit',
                'category' => 'users',
                'description' => 'Edit user accounts'
            ],
            [
                'uuid' => $permissionUuids['usr_delete'],
                'name' => 'Delete Users',
                'slug' => 'users.delete',
                'category' => 'users',
                'description' => 'Delete user accounts'
            ],

            // Role management
            [
                'uuid' => $permissionUuids['rol_view'],
                'name' => 'View Roles',
                'slug' => 'roles.view',
                'category' => 'roles',
                'description' => 'View role definitions'
            ],
            [
                'uuid' => $permissionUuids['rol_create'],
                'name' => 'Create Roles',
                'slug' => 'roles.create',
                'category' => 'roles',
                'description' => 'Create new roles'
            ],
            [
                'uuid' => $permissionUuids['rol_edit'],
                'name' => 'Edit Roles',
                'slug' => 'roles.edit',
                'category' => 'roles',
                'description' => 'Edit role definitions'
            ],
            [
                'uuid' => $permissionUuids['rol_delete'],
                'name' => 'Delete Roles',
                'slug' => 'roles.delete',
                'category' => 'roles',
                'description' => 'Delete roles'
            ],
            [
                'uuid' => $permissionUuids['rol_assign'],
                'name' => 'Assign Roles',
                'slug' => 'roles.assign',
                'category' => 'roles',
                'description' => 'Assign roles to users'
            ],

            // Content management
            [
                'uuid' => $permissionUuids['cnt_view'],
                'name' => 'View Content',
                'slug' => 'content.view',
                'category' => 'content',
                'description' => 'View content'
            ],
            [
                'uuid' => $permissionUuids['cnt_create'],
                'name' => 'Create Content',
                'slug' => 'content.create',
                'category' => 'content',
                'description' => 'Create new content'
            ],
            [
                'uuid' => $permissionUuids['cnt_edit'],
                'name' => 'Edit Content',
                'slug' => 'content.edit',
                'category' => 'content',
                'description' => 'Edit content'
            ],
            [
                'uuid' => $permissionUuids['cnt_delete'],
                'name' => 'Delete Content',
                'slug' => 'content.delete',
                'category' => 'content',
                'description' => 'Delete content'
            ]
        ];

        foreach ($permissions as $permission) {
            $permission['is_system'] = 1;
            $permissionId = $this->db->insert('permissions', $permission);
            if (!$permissionId) {
                throw new \RuntimeException('Failed to create permission: ' . $permission['name']);
            }
        }

        // Assign permissions to roles
        $rolePermissions = [
            // Superuser gets all permissions
            $superuserUuid => [
                $permissionUuids['sys_access'], $permissionUuids['sys_config'],
                $permissionUuids['usr_view'], $permissionUuids['usr_create'],
                $permissionUuids['usr_edit'], $permissionUuids['usr_delete'],
                $permissionUuids['rol_view'], $permissionUuids['rol_create'],
                $permissionUuids['rol_edit'], $permissionUuids['rol_delete'],
                $permissionUuids['rol_assign'],
                $permissionUuids['cnt_view'], $permissionUuids['cnt_create'],
                $permissionUuids['cnt_edit'], $permissionUuids['cnt_delete']
            ],
            // Administrator gets most permissions except system config
            $adminUuid => [
                $permissionUuids['sys_access'], $permissionUuids['usr_view'],
                $permissionUuids['usr_create'], $permissionUuids['usr_edit'],
                $permissionUuids['usr_delete'], $permissionUuids['rol_view'],
                $permissionUuids['rol_assign'], $permissionUuids['cnt_view'],
                $permissionUuids['cnt_create'], $permissionUuids['cnt_edit'],
                $permissionUuids['cnt_delete']
            ],
            // Manager gets user and content management
            $managerUuid => [
                $permissionUuids['sys_access'], $permissionUuids['usr_view'],
                $permissionUuids['usr_edit'], $permissionUuids['rol_view'],
                $permissionUuids['rol_assign'], $permissionUuids['cnt_view'],
                $permissionUuids['cnt_create'], $permissionUuids['cnt_edit'],
                $permissionUuids['cnt_delete']
            ],
            // User gets basic content access
            $userUuid => [
                $permissionUuids['cnt_view'], $permissionUuids['cnt_create'],
                $permissionUuids['cnt_edit']
            ]
        ];

        foreach ($rolePermissions as $roleUuid => $permissionUuidList) {
            foreach ($permissionUuidList as $permissionUuid) {
                $uuid = Utils::generateNanoID();
                $assignmentId = $this->db->insert('role_permissions', [
                    'uuid' => $uuid,
                    'role_uuid' => $roleUuid,
                    'permission_uuid' => $permissionUuid
                ]);
                if (!$assignmentId) {
                    throw new \RuntimeException('Failed to assign permission to role');
                }
            }
        }
    }

    /**
     * Reverse the migration
     */
    public function down(SchemaManager $schema): void
    {
        $connection = new Connection();
        $this->db = new QueryBuilder($connection->getPDO(), $connection->getDriver());

        // Delete all system roles and permissions (will cascade to role_permissions)
        $this->db->delete('permissions', ['is_system' => 1], false);
        $this->db->delete('roles', ['is_system' => 1], false);
    }

    /**
     * Get migration description
     */
    public function getDescription(): string
    {
        return 'Seed default RBAC roles, permissions, and role-permission assignments';
    }
}
