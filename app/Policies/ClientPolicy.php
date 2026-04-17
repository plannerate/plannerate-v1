<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Policies;

use App\Models\Client;
use App\Services\TenantLimitService;
use Callcocam\LaravelRaptor\Policies\AbstractPolicy;
use Illuminate\Contracts\Auth\Access\Authorizable;

class ClientPolicy extends AbstractPolicy
{
    protected ?string $permission = 'clients';

    public function create(Authorizable $user): bool
    {
        if (! parent::create($user)) {
            return false;
        }

        if (! app()->bound('current.tenant')) {
            return true;
        }

        $tenant = app('current.tenant');
        $count = Client::where('tenant_id', $tenant->id)->withoutTrashed()->count();

        return ! app(TenantLimitService::class)->hasReachedLimit('max_clients', $count);
    }
}
