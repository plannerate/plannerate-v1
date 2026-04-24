<?php

namespace App\Models\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private static bool $disabled = false;

    public static function disable(): void
    {
        self::$disabled = true;
    }

    public static function enable(): void
    {
        self::$disabled = false;
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$disabled) {
            return;
        }

        $tenant = Tenant::current();

        if ($tenant === null) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenant->getKey());
    }
}
