<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Planogram;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $totals = [
            'planograms' => Planogram::query()->count(),
            'categories' => Category::query()->count(),
            'products' => Product::query()->count(),
        ];

        return inertia('tenant/Dashboard', [
            'totals' => $totals,
            'status_chart' => [
                'planograms' => $this->statusDistribution(Planogram::query(), ['draft', 'published', 'archived']),
                'categories' => $this->statusDistribution(Category::query(), ['draft', 'published', 'archived']),
                'products' => $this->statusDistribution(Product::query(), ['draft', 'published', 'archived']),
            ],
            'recent' => [
                'planograms' => Planogram::query()
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'name', 'slug', 'status', 'created_at'])
                    ->map(fn (Planogram $planogram): array => [
                        'id' => $planogram->id,
                        'name' => $planogram->name,
                        'slug' => $planogram->slug,
                        'status' => (string) ($planogram->status ?: 'draft'),
                        'created_at' => $planogram->created_at?->toDateTimeString(),
                    ]),
                'categories' => Category::query()
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'name', 'slug', 'status', 'created_at'])
                    ->map(fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'status' => (string) ($category->status ?: 'draft'),
                        'created_at' => $category->created_at?->toDateTimeString(),
                    ]),
                'products' => Product::query()
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'name', 'slug', 'status', 'created_at'])
                    ->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'status' => (string) ($product->status ?: 'draft'),
                        'created_at' => $product->created_at?->toDateTimeString(),
                    ]),
            ],
        ]);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $orderedStatuses
     * @return Collection<int, array{status: string, total: int}>
     */
    private function statusDistribution(Builder $query, array $orderedStatuses): Collection
    {
        $countsByStatus = $query
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) ($row->status ?: 'draft') => (int) $row->total]);

        return collect($orderedStatuses)->map(fn (string $status): array => [
            'status' => $status,
            'total' => (int) ($countsByStatus[$status] ?? 0),
        ]);
    }
}
