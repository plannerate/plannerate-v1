<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Workflow\Kanban;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\User;
use Spatie\Permission\Models\Role;

class KanbanFilterOptionsProvider
{
    /**
     * @param  array<int, array{id: string, name: string, stepIds: array<string>}>  $groupConfigs
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<int, array<string, mixed>>  $groupConfigs
     * @param  array<int, string>  $orderedRoleIds  Role IDs na ordem das etapas do fluxo.
     *                                              Quando não vazio, sobrescreve a ordenação padrão (alfabética).
     * @return array<int, array<string, mixed>>
     */
    public function getOptionsFilters(array $groupConfigs, array $orderedRoleIds = []): array
    {
        return array_values(array_filter([
            [
                'key' => 'planogram_id',
                'label' => 'Planograma',
                'options' => $groupConfigs,
                'component' => 'filter-select-with-clear',
                'classes' => 'w-72',
            ],
            [
                'key' => 'loja_id',
                'label' => 'Loja',
                'options' => $this->getLojas(),
                'component' => 'filter-select-with-clear',
            ],
            [
                'key' => 'user_id',
                'label' => 'Usuário',
                'options' => $this->getUsers(),
                'component' => 'filter-select-with-clear',
            ],
            [
                'key' => 'assigned_to',
                'label' => 'Função',
                'options' => $this->getRoles($orderedRoleIds),
                'component' => 'filter-select-with-clear',
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'component' => 'filter-select-with-clear',
                'options' => [
                    ['id' => 'pending', 'name' => 'Pendente'],
                    ['id' => 'in_progress', 'name' => 'Em Andamento'],
                    ['id' => 'blocked', 'name' => 'Bloqueada'],
                    ['id' => 'completed', 'name' => 'Concluída'],
                ],
            ],
        ]));
    }

    /**
     * @return array<int, array{id: string, name: string, email: string}>
     */
    protected function getUsers(): array
    {
        return User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function getLojas(): array
    {
        return Store::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    /**
     * @param  array<int, string>  $orderedIds  Quando informado, retorna roles nessa ordem exata.
     * @return array<int, array{id: string, name: string}>
     */
    protected function getRoles(array $orderedIds = []): array
    {
        $query = Role::query()->select('id', 'name');

        if (empty($orderedIds)) {
            return $query->orderBy('name')->get()->toArray();
        }

        // Retorna apenas as roles presentes no fluxo, na ordem das etapas.
        // Roles sem etapa correspondente são omitidas do filtro.
        $rolesById = $query->whereIn('id', $orderedIds)->get()->keyBy('id');

        return collect($orderedIds)
            ->unique()
            ->filter(fn (string $id): bool => $rolesById->has($id))
            ->map(fn (string $id): array => $rolesById->get($id)->toArray())
            ->values()
            ->toArray();
    }
}
