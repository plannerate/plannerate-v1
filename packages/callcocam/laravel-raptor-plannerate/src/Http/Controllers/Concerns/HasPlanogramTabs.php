<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns;

trait HasPlanogramTabs
{
    /**
     * Retorna as tabs de navegação de planogramas.
     *
     * @param  string|null  $active  Slug da tab ativa: 'lista'
     * @return array<int, array{key: string, label: string, href: string, icon: string, active: bool}>
     */
    protected function planogramTabs(?string $active = null): array
    {
        return [
            [
                'key' => 'lista',
                'label' => 'Lista',
                'href' => '/planograms',
                'icon' => 'LayoutListIcon',
                'active' => $active === 'lista',
            ],
        ];
    }
}
