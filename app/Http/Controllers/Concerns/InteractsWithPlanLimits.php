<?php

namespace App\Http\Controllers\Concerns;

use App\Services\PlanLimitService;

trait InteractsWithPlanLimits
{
    use InteractsWithResourceAbilities;

    /**
     * Build the `can` array for an index action, combining the resource abilities
     * (create/update/delete) with plan limit info.
     *
     * @param  class-string  $modelClass
     * @return array{create: bool, update: bool, delete: bool, limit_reached: bool, limit_message: string|null, upgrade_url: string|null}
     */
    protected function resolveCanCreate(string $modelClass, string $limitKey, int $currentCount): array
    {
        /** @var PlanLimitService $service */
        $service = app(PlanLimitService::class);
        $limitReached = $service->isLimitReached($limitKey, $currentCount);

        return [
            ...$this->resolveResourceAbilities($modelClass),
            'limit_reached' => $limitReached,
            'limit_message' => $limitReached ? $service->getLimitMessage($limitKey) : null,
            'upgrade_url' => $limitReached ? $service->getUpgradeUrl($limitKey) : null,
        ];
    }
}
