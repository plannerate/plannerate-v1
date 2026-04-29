<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\ImportCategorySpreadsheetRequest;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Jobs\Imports\ImportCategoriesFromSpreadsheetJob;
use App\Models\Category;
use App\Services\Files\Exports\Categories\CategoryExportService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoryController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    private const MERCADOLOGICO_UI_LEVELS = 7;

    public function cascadeChildren(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $parentId = $request->query('parent_id');

        if ($parentId !== null && $parentId !== '') {
            $parent = Category::query()
                ->whereKey($parentId)
                ->first();

            if ($parent === null) {
                abort(404);
            }

            if ($parent->getMercadologicoDepth() >= self::MERCADOLOGICO_UI_LEVELS) {
                return response()->json([]);
            }
        }

        $query = Category::query()->orderBy('name');

        if ($parentId !== null && $parentId !== '') {
            $query->where('category_id', $parentId);
        } else {
            $query->whereNull('category_id');
        }

        return response()->json(
            $query->get(['id', 'name'])->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values()->all()
        );
    }

    public function cascadePath(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $id = $request->query('id');
        if (! is_string($id) || $id === '') {
            return response()->json(['path' => []]);
        }

        $category = Category::query()->whereKey($id)->first();

        if ($category === null) {
            return response()->json(['path' => []]);
        }

        $path = $category->getFullHierarchy()->map(fn (Category $node): array => [
            'id' => $node->id,
            'name' => $node->name,
        ])->values()->all();

        return response()->json(['path' => $path]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published', 'importer']);

        return $this->renderDeferredIndex('tenant/categories/Index', 'categories', fn (): LengthAwarePaginator => $this->categoriesPaginator(
            $search,
            $status,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    private function categoriesPaginator(string $search, string $status, int $perPage): LengthAwarePaginator
    {
        return Category::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'codigo' => $category->codigo,
                'full_path' => $category->full_path,
                'is_placeholder' => $category->is_placeholder,
                'level_name' => $category->level_name,
                'created_at' => $category->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('tenant/categories/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'category' => null,
            'parent_categories' => $this->parentCategoriesForSelect(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $validated = $request->validated();

        Category::query()->create([
            ...$validated,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'is_placeholder' => $request->boolean('is_placeholder'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.created'),
        ]);

        return to_route('tenant.categories.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Category $category): Response
    {
        unset($subdomain);
        $this->authorize('update', $category);

        return Inertia::render('tenant/categories/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'category' => [
                'id' => $category->id,
                'category_id' => $category->category_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'level_name' => $category->level_name,
                'codigo' => $category->codigo,
                'status' => $category->status,
                'description' => $category->description,
                'nivel' => $category->nivel,
                'hierarchy_position' => $category->hierarchy_position,
                'full_path' => $category->full_path,
                'hierarchy_path' => $category->hierarchy_path,
                'is_placeholder' => (bool) $category->is_placeholder,
            ],
            'parent_categories' => $this->parentCategoriesForSelect($category->id),
        ]);
    }

    public function update(UpdateCategoryRequest $request, string $subdomain, Category $category): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $category);

        $validated = $request->validated();

        $category->update([
            ...$validated,
            'is_placeholder' => $request->boolean('is_placeholder'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.updated'),
        ]);

        return to_route('tenant.categories.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Category $category): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.deleted'),
        ]);

        return to_route('tenant.categories.index', $this->tenantRouteParameters());
    }

    public function import(ImportCategorySpreadsheetRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $uploadedFile = $request->file('spreadsheet');
        if ($uploadedFile === null) {
            return to_route('tenant.categories.index', $this->tenantRouteParameters());
        }

        $disk = 'local';
        $path = $uploadedFile->store('imports/categories', $disk);

        ImportCategoriesFromSpreadsheetJob::dispatch(
            tenantId: (string) $this->tenantId(),
            userId: $request->user()?->getAuthIdentifier(),
            disk: $disk,
            path: $path,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.import_queued'),
        ]);

        return to_route('tenant.categories.index', $this->tenantRouteParameters());
    }

    public function exportTemplate(CategoryExportService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', Category::class);

        return $service->downloadTemplate();
    }

    public function exportData(CategoryExportService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', Category::class);

        return $service->downloadData((string) $this->tenantId());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function parentCategoriesForSelect(?string $ignoreId = null): array
    {
        return Category::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }
}
