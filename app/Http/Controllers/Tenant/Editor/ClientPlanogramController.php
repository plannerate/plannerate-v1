<?php

namespace App\Http\Controllers\Tenant\Editor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\Planogram;
use App\Models\Store;
use App\Support\Authorization\PermissionName;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class ClientPlanogramController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
          $this->authorize('viewAny', Planogram::class);

        $search = $this->requestString($request, 'search');
        $storeId = $this->requestString($request, 'store_id');
        $categoryId = $this->requestString($request, 'category_id');

        $today = Date::today();

        $stats = [
            'total_published' => Planogram::query()->where('status', 'published')->count(),
            'total_stores' => Planogram::query()
                ->where('status', 'published')
                ->whereNotNull('store_id')
                ->distinct('store_id')
                ->count('store_id'),
            'active_count' => Planogram::query()
                ->where('status', 'published')
                ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $today))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
                ->count(),
        ];

        return $this->renderDeferredIndex('tenant/editor/planograms/Index', 'planograms', fn (): LengthAwarePaginator => $this->planogramsPaginator(
            $search,
            $storeId,
            $categoryId,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'store_id' => $storeId,
                'category_id' => $categoryId,
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
            'stats' => $stats,
        ]);
    }

    private function planogramsPaginator(
        string $search,
        string $storeId,
        string $categoryId,
        int $perPage,
    ): LengthAwarePaginator {
        return Planogram::query()
            ->with(['store:id,name', 'category:id,name'])
            ->where('status', 'published')
            ->when($search !== '', fn ($query) => $query->where(function ($where) use ($search): void {
                $where
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->when($storeId !== '', fn ($query) => $query->where('store_id', $storeId))
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Planogram $planogram): array => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'slug' => $planogram->slug,
                'type' => $planogram->type,
                'store_id' => $planogram->store_id,
                'store' => $planogram->store?->name,
                'category' => $planogram->category?->name,
                'start_date' => $planogram->start_date?->toDateString(),
                'end_date' => $planogram->end_date?->toDateString(),
                'description' => $planogram->description,
            ]);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function storesForSelect(): array
    {
        return Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])
            ->all();
    }
}
