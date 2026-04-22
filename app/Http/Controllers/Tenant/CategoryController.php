<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $tenantId = $this->tenantId();
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $hasStatusFilter = in_array($status, ['draft', 'published', 'importer'], true);

        $categories = Category::query()
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'status' => $category->status,
                'codigo' => $category->codigo,
                'is_placeholder' => $category->is_placeholder,
                'created_at' => $category->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/categories/Index', [
            'subdomain' => (string) $request->route('subdomain'),
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('tenant/categories/Form', [
            'subdomain' => (string) request()->route('subdomain'),
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
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'is_placeholder' => $request->boolean('is_placeholder'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.created'),
        ]);

        return to_route('tenant.categories.index');
    }

    public function edit(Category $category): Response
    {
        $this->ensureTenantOwnership($category);
        $this->authorize('update', $category);

        return Inertia::render('tenant/categories/Form', [
            'subdomain' => (string) request()->route('subdomain'),
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

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->ensureTenantOwnership($category);
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

        return to_route('tenant.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->ensureTenantOwnership($category);
        $this->authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.categories.messages.deleted'),
        ]);

        return to_route('tenant.categories.index');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function parentCategoriesForSelect(?string $ignoreId = null): array
    {
        return Category::query()
            ->where('tenant_id', $this->tenantId())
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    private function ensureTenantOwnership(Category $category): void
    {
        abort_if($category->tenant_id !== $this->tenantId(), 404);
    }

    private function tenantId(): ?string
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        return app($containerKey)?->getKey();
    }
}
