<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Policies;

use App\Models\Store;
use App\Services\TenantLimitService;
use Callcocam\LaravelRaptor\Policies\AbstractPolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;

class StorePolicy extends AbstractPolicy
{
    protected ?string $permission = 'stores';

    public function create(Authorizable $user): bool
    {
        if (! parent::create($user)) {
            return false;
        }

        if (! app()->bound('current.tenant')) {
            return true;
        }

        $tenant = app('current.tenant');
        $count = Store::where('tenant_id', $tenant->id)->withoutTrashed()->count();

        return ! app(TenantLimitService::class)->hasReachedLimit('max_stores', $count);
    }
}
