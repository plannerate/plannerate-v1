<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Concerns;

trait HasPlanogramTabs
{
    use HasWorkflowToggle;

    /**
     * Retorna as tabs de navegação de planogramas, ocultando a aba Kanban
     * quando o tenant não tem o módulo de workflow habilitado.
     *
     * @param  string|null  $active  Slug da tab ativa: 'lista' | 'kanban' | 'maps'
     * @return array<int, array{key: string, label: string, href: string, icon: string, active: bool}>
     */
    protected function planogramTabs(?string $active = null): array
    {
        $tabs = [
            [
                'key' => 'lista',
                'label' => 'Lista',
                'href' => '/planograms',
                'icon' => 'LayoutListIcon',
                'active' => $active === 'lista',
            ],
        ];

        if ($this->isWorkflowEnabled()) {
            $tabs[] = [
                'key' => 'kanban',
                'label' => 'Kanban',
                'href' => '/kanbans/planogramas',
                'icon' => 'KanbanIcon',
                'active' => $active === 'kanban',
            ];
        }

        $tabs[] = [
            'key' => 'maps',
            'label' => 'Maps',
            'href' => '/maps',
            'icon' => 'MapIcon',
            'active' => $active === 'maps',
        ];

        return $tabs;
    }
}
