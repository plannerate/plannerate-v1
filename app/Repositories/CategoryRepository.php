<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryRepository
{
    /**
     * Query base de categorias para o tenant atual.
     * Usa a conexão 'tenant' (database do client) para que o job use o banco correto.
     */
    public function baseQuery(?string $tenantId = null, ?string $connection = null): Builder
    {
        $tenantId = $tenantId ?? config('app.current_tenant_id');
        $connection = $connection ?? 'tenant';

        return Category::on($connection)
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->with('parent');
    }

    /**
     * Retorna categorias preparadas para exportação, respeitando a hierarquia.
     * Ordena por nivel e full_path para manter a árvore.
     *
     * @param  array<string, mixed>  $filters  Filtros (ex.: status, tenant_id)
     * @return Collection<int, Category>
     */
    public function getForExport(array $filters = []): Collection
    {
        $query = $this->baseQuery(data_get($filters, 'tenant_id'));

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('trashed', $filters) && $filters['trashed'] === true) {
            $query->onlyTrashed();
        } elseif (! (data_get($filters, 'with_trashed') === true)) {
            $query->withoutTrashed();
        }

        return $query
            ->orderByRaw('COALESCE(hierarchy_position, 0)')
            ->orderBy('full_path')
            ->orderBy('name')
            ->get();
    }
}
