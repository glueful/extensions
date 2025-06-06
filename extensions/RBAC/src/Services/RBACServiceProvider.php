<?php

namespace Glueful\Extensions\RBAC\Services;

use Glueful\DI\Interfaces\ServiceProviderInterface;
use Glueful\DI\Interfaces\ContainerInterface;
use Glueful\Extensions\RBAC\RBACPermissionProvider;
use Glueful\Extensions\RBAC\Repositories\RoleRepository;
use Glueful\Extensions\RBAC\Repositories\PermissionRepository;
use Glueful\Extensions\RBAC\Repositories\UserRoleRepository;
use Glueful\Extensions\RBAC\Repositories\UserPermissionRepository;
use Glueful\Extensions\RBAC\Repositories\RolePermissionRepository;

/**
 * RBAC Service Provider
 *
 * Registers all RBAC services with the dependency injection container
 *
 * Services registered:
 * - RBAC repositories
 * - Permission provider
 * - RBAC services
 * - Middleware components
 */
class RBACServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services with the container
     */
    public function register(ContainerInterface $container): void
    {
        // Register repositories
        $container->bind('rbac.repository.role', function () {
            return new RoleRepository();
        });

        $container->bind('rbac.repository.permission', function () {
            return new PermissionRepository();
        });

        $container->bind('rbac.repository.user_role', function () {
            return new UserRoleRepository();
        });

        $container->bind('rbac.repository.user_permission', function () {
            return new UserPermissionRepository();
        });

        $container->bind('rbac.repository.role_permission', function () {
            return new RolePermissionRepository();
        });

        // Register permission provider
        $container->bind('rbac.permission_provider', function () {
            return new RBACPermissionProvider();
        });

        // Register RBAC services
        $container->bind('rbac.role_service', function ($container) {
            return new RoleService(
                $container->get('rbac.repository.role'),
                $container->get('rbac.repository.user_role')
            );
        });

        $container->bind('rbac.permission_service', function ($container) {
            return new PermissionAssignmentService(
                $container->get('rbac.repository.permission'),
                $container->get('rbac.repository.user_permission'),
                $container->get('rbac.repository.role'),
                $container->get('rbac.repository.user_role'),
                $container->get('rbac.repository.role_permission')
            );
        });

        // Register utility services
        $container->bind('rbac.audit_service', function () {
            return new AuditService();
        });
    }

    /**
     * Boot services after registration
     */
    public function boot(ContainerInterface $container): void
    {
        // Initialize permission provider
        $permissionProvider = $container->get('rbac.permission_provider');
        $permissionProvider->initialize();

        // Register global permission provider
        if ($container->has('permission.manager')) {
            $permissionManager = $container->get('permission.manager');
            $permissionManager->registerProviders(['rbac' => $permissionProvider]);
        }
    }

    /**
     * Get service metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'RBAC Service Provider',
            'version' => '1.0.0',
            'services' => [
                'rbac.repository.role',
                'rbac.repository.permission',
                'rbac.repository.user_role',
                'rbac.repository.user_permission',
                'rbac.repository.role_permission',
                'rbac.permission_provider',
                'rbac.role_service',
                'rbac.permission_service',
                'rbac.audit_service'
            ],
            'dependencies' => [
                'core_permissions'
            ]
        ];
    }
}
