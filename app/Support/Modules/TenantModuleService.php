<?php

namespace App\Support\Modules;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class TenantModuleService
{
    public function tenantHasActiveModule(Tenant $tenant, string $slug): bool
    {
        if ($tenant->relationLoaded('modules')) {
            /** @var Collection<int, Module> $modules */
            $modules = $tenant->getRelation('modules');

            return $modules->contains(fn ($module): bool => $module->is_active && $module->slug === $slug);
        }

        return $tenant->modules()
            ->where('modules.slug', $slug)
            ->where('modules.is_active', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function tenantActiveModuleSlugs(Tenant $tenant): array
    {
        if ($tenant->relationLoaded('modules')) {
            /** @var Collection<int, Module> $modules */
            $modules = $tenant->getRelation('modules');

            return $modules
                ->where('is_active', true)
                ->pluck('slug')
                ->map(fn (mixed $slug): string => (string) $slug)
                ->values()
                ->all();
        }

        return $tenant->modules()
            ->where('modules.is_active', true)
            ->orderBy('modules.slug')
            ->pluck('modules.slug')
            ->map(fn (mixed $slug): string => (string) $slug)
            ->values()
            ->all();
    }
}
