<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRepository
{
    /**
     * Query base de produtos para o tenant/client atual.
     * Usa a conexão 'tenant' (database do client).
     */
    public function baseQuery(?string $tenantId = null, ?string $clientId = null, ?string $connection = null): Builder
    {
        $tenantId = $tenantId ?? config('app.current_tenant_id');
        $clientId = $clientId ?? config('app.current_client_id');
        $connection = $connection ?? 'tenant';

        return Product::on($connection)
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->when($clientId !== null, fn (Builder $q) => $q->where('client_id', $clientId));
    }

    /**
     * Retorna produtos para exportação (todas as colunas necessárias para as 3 abas).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Product>
     */
    public function getForExport(array $filters = []): Collection
    {
        $query = $this->baseQuery(
            data_get($filters, 'tenant_id'),
            data_get($filters, 'client_id'),
            'tenant'
        );

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('trashed', $filters) && $filters['trashed'] === true) {
            $query->onlyTrashed();
        } elseif (! (data_get($filters, 'with_trashed') === true)) {
            $query->withoutTrashed();
        }

        return $query->with('category')->orderBy('ean')->orderBy('name')->get();
    }
}
