<?php

namespace App\Support\Authorization;

final class PermissionName
{
    public const LANDLORD_USERS_VIEW_ANY = 'landlord.users.viewAny';

    public const LANDLORD_USERS_VIEW = 'landlord.users.view';

    public const LANDLORD_USERS_CREATE = 'landlord.users.create';

    public const LANDLORD_USERS_UPDATE = 'landlord.users.update';

    public const LANDLORD_USERS_DELETE = 'landlord.users.delete';

    public const LANDLORD_PERMISSIONS_VIEW_ANY = 'landlord.permissions.viewAny';

    public const LANDLORD_PERMISSIONS_VIEW = 'landlord.permissions.view';

    public const LANDLORD_PERMISSIONS_CREATE = 'landlord.permissions.create';

    public const LANDLORD_PERMISSIONS_UPDATE = 'landlord.permissions.update';

    public const LANDLORD_PERMISSIONS_DELETE = 'landlord.permissions.delete';

    public const LANDLORD_PLANS_VIEW_ANY = 'landlord.plans.viewAny';

    public const LANDLORD_PLANS_VIEW = 'landlord.plans.view';

    public const LANDLORD_PLANS_CREATE = 'landlord.plans.create';

    public const LANDLORD_PLANS_UPDATE = 'landlord.plans.update';

    public const LANDLORD_PLANS_DELETE = 'landlord.plans.delete';

    public const LANDLORD_TENANTS_VIEW_ANY = 'landlord.tenants.viewAny';

    public const LANDLORD_TENANTS_VIEW = 'landlord.tenants.view';

    public const LANDLORD_TENANTS_CREATE = 'landlord.tenants.create';

    public const LANDLORD_TENANTS_UPDATE = 'landlord.tenants.update';

    public const LANDLORD_TENANTS_DELETE = 'landlord.tenants.delete';

    public const LANDLORD_ROLES_VIEW_ANY = 'landlord.roles.viewAny';

    public const LANDLORD_ROLES_VIEW = 'landlord.roles.view';

    public const LANDLORD_ROLES_CREATE = 'landlord.roles.create';

    public const LANDLORD_ROLES_UPDATE = 'landlord.roles.update';

    public const LANDLORD_ROLES_DELETE = 'landlord.roles.delete';

    public const LANDLORD_MODULES_VIEW_ANY = 'landlord.modules.viewAny';

    public const LANDLORD_MODULES_VIEW = 'landlord.modules.view';

    public const LANDLORD_MODULES_CREATE = 'landlord.modules.create';

    public const LANDLORD_MODULES_UPDATE = 'landlord.modules.update';

    public const LANDLORD_MODULES_DELETE = 'landlord.modules.delete';

    public const TENANT_DASHBOARD_VIEW = 'tenant.dashboard.view';

    public const TENANT_CATEGORIES_VIEW_ANY = 'tenant.categories.viewAny';

    public const TENANT_CATEGORIES_VIEW = 'tenant.categories.view';

    public const TENANT_CATEGORIES_CREATE = 'tenant.categories.create';

    public const TENANT_CATEGORIES_UPDATE = 'tenant.categories.update';

    public const TENANT_CATEGORIES_DELETE = 'tenant.categories.delete';

    public const TENANT_PRODUCTS_VIEW_ANY = 'tenant.products.viewAny';

    public const TENANT_PRODUCTS_VIEW = 'tenant.products.view';

    public const TENANT_PRODUCTS_CREATE = 'tenant.products.create';

    public const TENANT_PRODUCTS_UPDATE = 'tenant.products.update';

    public const TENANT_PRODUCTS_DELETE = 'tenant.products.delete';

    public const TENANT_STORES_VIEW_ANY = 'tenant.stores.viewAny';

    public const TENANT_STORES_VIEW = 'tenant.stores.view';

    public const TENANT_STORES_CREATE = 'tenant.stores.create';

    public const TENANT_STORES_UPDATE = 'tenant.stores.update';

    public const TENANT_STORES_DELETE = 'tenant.stores.delete';

    public const TENANT_CLUSTERS_VIEW_ANY = 'tenant.clusters.viewAny';

    public const TENANT_CLUSTERS_VIEW = 'tenant.clusters.view';

    public const TENANT_CLUSTERS_CREATE = 'tenant.clusters.create';

    public const TENANT_CLUSTERS_UPDATE = 'tenant.clusters.update';

    public const TENANT_CLUSTERS_DELETE = 'tenant.clusters.delete';

    public const TENANT_PROVIDERS_VIEW_ANY = 'tenant.providers.viewAny';

    public const TENANT_PROVIDERS_VIEW = 'tenant.providers.view';

    public const TENANT_PROVIDERS_CREATE = 'tenant.providers.create';

    public const TENANT_PROVIDERS_UPDATE = 'tenant.providers.update';

    public const TENANT_PROVIDERS_DELETE = 'tenant.providers.delete';

    public const TENANT_PLANOGRAMS_VIEW_ANY = 'tenant.planograms.viewAny';

    public const TENANT_PLANOGRAMS_VIEW = 'tenant.planograms.view';

    public const TENANT_PLANOGRAMS_CREATE = 'tenant.planograms.create';

    public const TENANT_PLANOGRAMS_UPDATE = 'tenant.planograms.update';

    public const TENANT_PLANOGRAMS_DELETE = 'tenant.planograms.delete';

    public const TENANT_GONDOLAS_VIEW_ANY = 'tenant.gondolas.viewAny';

    public const TENANT_GONDOLAS_VIEW = 'tenant.gondolas.view';

    public const TENANT_GONDOLAS_CREATE = 'tenant.gondolas.create';

    public const TENANT_GONDOLAS_UPDATE = 'tenant.gondolas.update';

    public const TENANT_GONDOLAS_DELETE = 'tenant.gondolas.delete';

    public const LANDLORD_KANBAN_TEMPLATES_VIEW_ANY = 'landlord.kanban.templates.viewAny';

    public const LANDLORD_KANBAN_TEMPLATES_CREATE = 'landlord.kanban.templates.create';

    public const LANDLORD_KANBAN_TEMPLATES_UPDATE = 'landlord.kanban.templates.update';

    public const LANDLORD_KANBAN_TEMPLATES_DELETE = 'landlord.kanban.templates.delete';

    public const TENANT_KANBAN_VIEW_ANY = 'tenant.kanban.viewAny';

    public const TENANT_KANBAN_EXECUTIONS_START = 'tenant.kanban.executions.start';

    public const TENANT_KANBAN_EXECUTIONS_MOVE = 'tenant.kanban.executions.move';

    public const TENANT_KANBAN_EXECUTIONS_MANAGE = 'tenant.kanban.executions.manage';

    public const TENANT_KANBAN_EXECUTIONS_RESTORE = 'tenant.kanban.executions.restore';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LANDLORD_USERS_VIEW_ANY,
            self::LANDLORD_USERS_VIEW,
            self::LANDLORD_USERS_CREATE,
            self::LANDLORD_USERS_UPDATE,
            self::LANDLORD_USERS_DELETE,
            self::LANDLORD_PERMISSIONS_VIEW_ANY,
            self::LANDLORD_PERMISSIONS_VIEW,
            self::LANDLORD_PERMISSIONS_CREATE,
            self::LANDLORD_PERMISSIONS_UPDATE,
            self::LANDLORD_PERMISSIONS_DELETE,
            self::LANDLORD_PLANS_VIEW_ANY,
            self::LANDLORD_PLANS_VIEW,
            self::LANDLORD_PLANS_CREATE,
            self::LANDLORD_PLANS_UPDATE,
            self::LANDLORD_PLANS_DELETE,
            self::LANDLORD_TENANTS_VIEW_ANY,
            self::LANDLORD_TENANTS_VIEW,
            self::LANDLORD_TENANTS_CREATE,
            self::LANDLORD_TENANTS_UPDATE,
            self::LANDLORD_TENANTS_DELETE,
            self::LANDLORD_ROLES_VIEW_ANY,
            self::LANDLORD_ROLES_VIEW,
            self::LANDLORD_ROLES_CREATE,
            self::LANDLORD_ROLES_UPDATE,
            self::LANDLORD_ROLES_DELETE,
            self::LANDLORD_MODULES_VIEW_ANY,
            self::LANDLORD_MODULES_VIEW,
            self::LANDLORD_MODULES_CREATE,
            self::LANDLORD_MODULES_UPDATE,
            self::LANDLORD_MODULES_DELETE,
            self::TENANT_DASHBOARD_VIEW,
            self::TENANT_CATEGORIES_VIEW_ANY,
            self::TENANT_CATEGORIES_VIEW,
            self::TENANT_CATEGORIES_CREATE,
            self::TENANT_CATEGORIES_UPDATE,
            self::TENANT_CATEGORIES_DELETE,
            self::TENANT_PRODUCTS_VIEW_ANY,
            self::TENANT_PRODUCTS_VIEW,
            self::TENANT_PRODUCTS_CREATE,
            self::TENANT_PRODUCTS_UPDATE,
            self::TENANT_PRODUCTS_DELETE,
            self::TENANT_STORES_VIEW_ANY,
            self::TENANT_STORES_VIEW,
            self::TENANT_STORES_CREATE,
            self::TENANT_STORES_UPDATE,
            self::TENANT_STORES_DELETE,
            self::TENANT_CLUSTERS_VIEW_ANY,
            self::TENANT_CLUSTERS_VIEW,
            self::TENANT_CLUSTERS_CREATE,
            self::TENANT_CLUSTERS_UPDATE,
            self::TENANT_CLUSTERS_DELETE,
            self::TENANT_PROVIDERS_VIEW_ANY,
            self::TENANT_PROVIDERS_VIEW,
            self::TENANT_PROVIDERS_CREATE,
            self::TENANT_PROVIDERS_UPDATE,
            self::TENANT_PROVIDERS_DELETE,
            self::TENANT_PLANOGRAMS_VIEW_ANY,
            self::TENANT_PLANOGRAMS_VIEW,
            self::TENANT_PLANOGRAMS_CREATE,
            self::TENANT_PLANOGRAMS_UPDATE,
            self::TENANT_PLANOGRAMS_DELETE,
            self::TENANT_GONDOLAS_VIEW_ANY,
            self::TENANT_GONDOLAS_VIEW,
            self::TENANT_GONDOLAS_CREATE,
            self::TENANT_GONDOLAS_UPDATE,
            self::TENANT_GONDOLAS_DELETE,
            self::LANDLORD_KANBAN_TEMPLATES_VIEW_ANY,
            self::LANDLORD_KANBAN_TEMPLATES_CREATE,
            self::LANDLORD_KANBAN_TEMPLATES_UPDATE,
            self::LANDLORD_KANBAN_TEMPLATES_DELETE,
            self::TENANT_KANBAN_VIEW_ANY,
            self::TENANT_KANBAN_EXECUTIONS_START,
            self::TENANT_KANBAN_EXECUTIONS_MOVE,
            self::TENANT_KANBAN_EXECUTIONS_MANAGE,
            self::TENANT_KANBAN_EXECUTIONS_RESTORE,
        ];
    }

    public static function typeFor(string $permissionName): ?string
    {
        return RbacType::fromName($permissionName);
    }
}
