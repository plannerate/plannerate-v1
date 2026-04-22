<?php

namespace App\Support\Authorization;

final class PermissionName
{
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

    public const TENANT_DASHBOARD_VIEW = 'tenant.dashboard.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
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
            self::TENANT_DASHBOARD_VIEW,
        ];
    }
}
