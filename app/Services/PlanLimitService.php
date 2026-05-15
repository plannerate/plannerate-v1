<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanItem;
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

        if ($limit === null || ! is_int($limit)) {
            return true;
        }

        return $currentCount < $limit;
    }

    /**
     * Return true when a numeric limit IS configured and the count has reached or exceeded it.
     */
    public function isLimitReached(string $key, int $currentCount): bool
    {
        $limit = $this->getLimit($key);

        if ($limit === null || ! is_int($limit)) {
            return false;
        }

        return $currentCount >= $limit;
    }

    /**
     * Return the custom message stored on the plan item, or null if not configured.
     */
    public function getLimitMessage(string $key): ?string
    {
        return $this->resolvePlanItem($key)?->limit_message;
    }

    /**
     * Return the upgrade URL stored on the plan item, or null if not configured.
     */
    public function getUpgradeUrl(string $key): ?string
    {
        return $this->resolvePlanItem($key)?->upgrade_url;
    }

    protected function currentTenant(): ?Tenant
    {
        return Tenant::current();
    }

    private function resolvePlanItem(string $key): ?PlanItem
    {
        $plan = $this->resolvePlan();

        if (! $plan instanceof Plan) {
            return null;
        }

        $plan->loadMissing('items');

        return $plan->items->firstWhere('key', $key);
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
