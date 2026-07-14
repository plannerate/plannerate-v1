<?php

namespace App\Support\Modules;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class TenantModuleService
{
    public function tenantHasActiveModule(Tenant $tenant, string $slug): bool
    {
        $variants = ModuleSlug::variants($slug);

        if ($tenant->relationLoaded('modules')) {
            /** @var Collection<int, Module> $modules */
            $modules = $tenant->getRelation('modules');

            return $modules->contains(
                fn ($module): bool => $module->is_active && in_array($module->slug, $variants, true)
            );
        }

        return $tenant->modules()
            ->whereIn('modules.slug', $variants)
            ->where('modules.is_active', true)
            ->exists();
    }

    /**
     * Slugs canônicos dos módulos ativos do tenant — aliases em PT-BR são normalizados.
     *
     * @return list<string>
     */
    public function tenantActiveModuleSlugs(Tenant $tenant): array
    {
        if ($tenant->relationLoaded('modules')) {
            /** @var Collection<int, Module> $modules */
            $modules = $tenant->getRelation('modules');

            $slugs = $modules
                ->where('is_active', true)
                ->pluck('slug');
        } else {
            $slugs = $tenant->modules()
                ->where('modules.is_active', true)
                ->orderBy('modules.slug')
                ->pluck('modules.slug');
        }

        return $slugs
            ->map(fn (mixed $slug): string => ModuleSlug::canonical((string) $slug))
            ->unique()
            ->values()
            ->all();
    }
}
