<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;

class PlanLimitService
{
    private ?Plan $plan = null;

    /**
     * Return the raw limit value for a plan item key, or null if unlimited / no plan.
     */
    public function getLimit(string $key): int|bool|string|null
    {
        $plan = $this->resolvePlan();

        if (! $plan instanceof Plan) {
            return null;
        }

        $plan->loadMissing('items');

        return $plan->getLimit($key);
    }

    /**
     * Return true when the given count is still within the plan's limit.
     * A null limit (not set or unlimited) always allows.
     */
    public function withinLimit(string $key, int $currentCount): bool
    {
        $limit = $this->getLimit($key);

        if ($limit === null) {
            return true;
        }

        if (! is_int($limit)) {
            return true;
        }

        return $currentCount < $limit;
    }

    protected function currentTenant(): ?Tenant
    {
        return Tenant::current();
    }

    private function resolvePlan(): ?Plan
    {
        if ($this->plan instanceof Plan) {
            return $this->plan;
        }

        $tenant = $this->currentTenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $tenant->loadMissing('plan');

        $this->plan = $tenant->plan instanceof Plan ? $tenant->plan : null;

        return $this->plan;
    }
}
