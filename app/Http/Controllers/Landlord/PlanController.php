<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePlanRequest;
use App\Http\Requests\Landlord\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Services\Tenancy\AdministrativeUserLimitService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    use InteractsWithDeferredIndex;

    /**
     * Prefixo das chaves de plan_item que guardam o limite por perfil administrativo.
     */
    private const ROLE_LIMIT_KEY_PREFIX = 'user_limit:';

    public function __construct(
        private readonly AdministrativeUserLimitService $administrativeUserLimit,
    ) {}

    /**
     * Display a listing of plans.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plan::class);

        $search = $this->requestString($request, 'search');
        $isActive = $this->requestEnum($request, 'is_active', ['0', '1']);

        return $this->renderDeferredIndex('landlord/plans/Index', 'plans', fn (): LengthAwarePaginator => $this->plansPaginator(
            $search,
            $isActive,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
            ],
        ]);
    }

    private function plansPaginator(string $search, string $isActive, int $perPage): LengthAwarePaginator
    {
        return Plan::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($isActive !== '', fn ($query) => $query->where('is_active', $isActive === '1'))
            ->withCount('tenants')
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'user_limit' => $plan->user_limit,
                'is_active' => $plan->is_active,
                'tenants_count' => $plan->tenants_count,
                'created_at' => $plan->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Show the form for creating a plan.
     */
    public function create(): Response
    {
        $this->authorize('create', Plan::class);

        return Inertia::render('landlord/plans/Form', [
            'plan' => null,
            'administrative_roles' => $this->administrativeRoleLimits(null),
        ]);
    }

    /**
     * Store a newly created plan.
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $items = $validated['items'] ?? [];
        $roleLimits = $validated['role_limits'] ?? [];
        unset($validated['items'], $validated['role_limits']);

        DB::connection('landlord')->transaction(function () use ($validated, $items, $roleLimits): void {
            $plan = Plan::query()->create($validated);
            $this->syncPlanItems($plan, $items);
            $this->syncRoleLimits($plan, $roleLimits);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.plans.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.plans.index');
    }

    /**
     * Show the form for editing the specified plan.
     */
    public function edit(Plan $plan): Response
    {
        $this->authorize('update', $plan);

        $plan->load('items');

        return Inertia::render('landlord/plans/Form', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'user_limit' => $plan->user_limit,
                'is_active' => $plan->is_active,
                'items' => $plan->items
                    ->reject(fn (PlanItem $item): bool => $this->isRoleLimitKey((string) $item->key))
                    ->map(fn (PlanItem $item): array => [
                        'id' => $item->id,
                        'key' => $item->key,
                        'label' => $item->label,
                        'value' => $item->value,
                        'type' => $item->type,
                        'sort_order' => $item->sort_order,
                        'is_active' => $item->is_active,
                        'limit_message' => $item->limit_message,
                        'upgrade_url' => $item->upgrade_url,
                    ])->values()->all(),
            ],
            'administrative_roles' => $this->administrativeRoleLimits($plan),
        ]);
    }

    /**
     * Update the specified plan.
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');
        $items = $validated['items'] ?? [];
        $roleLimits = $validated['role_limits'] ?? [];
        unset($validated['items'], $validated['role_limits']);

        DB::connection('landlord')->transaction(function () use ($plan, $validated, $items, $roleLimits): void {
            $plan->update($validated);
            $this->syncPlanItems($plan, $items);
            $this->syncRoleLimits($plan, $roleLimits);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.plans.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.plans.index');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncPlanItems(Plan $plan, array $items): void
    {
        $submittedIds = collect($items)->pluck('id')->filter()->values()->all();

        // Não remove os itens de limite por perfil (user_limit:*) — estes são
        // gerenciados exclusivamente por syncRoleLimits().
        $plan->items()
            ->whereNotIn('id', $submittedIds)
            ->where('key', 'not like', self::ROLE_LIMIT_KEY_PREFIX.'%')
            ->delete();

        foreach ($items as $index => $data) {
            $plan->items()->updateOrCreate(
                ['id' => $data['id'] ?? ''],
                [
                    'key' => $data['key'],
                    'label' => $data['label'],
                    'value' => $data['value'] !== '' ? $data['value'] : null,
                    'type' => $data['type'] ?? 'string',
                    'sort_order' => $index,
                    'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                    'limit_message' => $data['limit_message'] !== '' ? ($data['limit_message'] ?? null) : null,
                    'upgrade_url' => $data['upgrade_url'] !== '' ? ($data['upgrade_url'] ?? null) : null,
                ],
            );
        }
    }

    /**
     * Persiste os limites por perfil administrativo como plan_items
     * (key = "user_limit:{system_name}", type integer). Valor vazio/nulo
     * remove o item (= perfil sem limite naquele plano).
     *
     * @param  array<string, mixed>  $roleLimits  Mapa system_name => limite
     */
    private function syncRoleLimits(Plan $plan, array $roleLimits): void
    {
        foreach ($this->limitableAdministrativeRoles() as $role) {
            $key = self::ROLE_LIMIT_KEY_PREFIX.$role->system_name;
            $value = $roleLimits[$role->system_name] ?? null;

            // Valor vazio/nulo/inválido = perfil sem limite neste plano → remove o item.
            if ($value === null || $value === '' || (int) $value < 1) {
                $plan->items()->where('key', $key)->delete();

                continue;
            }

            $plan->items()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => __('app.landlord.plans.role_limits.item_label', ['role' => $role->name]),
                    'value' => (string) (int) $value,
                    'type' => 'integer',
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Lista dos perfis administrativos (exceto tenant-admin, cujo limite usa o
     * campo user_limit do plano) com o limite atual configurado no plano.
     *
     * @return list<array{system_name:string,name:string,limit:int|null}>
     */
    private function administrativeRoleLimits(?Plan $plan): array
    {
        $existing = $plan instanceof Plan
            ? $plan->items()->where('key', 'like', self::ROLE_LIMIT_KEY_PREFIX.'%')->get()->keyBy('key')
            : collect();

        return $this->limitableAdministrativeRoles()
            ->map(function (Role $role) use ($existing): array {
                $value = $existing->get(self::ROLE_LIMIT_KEY_PREFIX.$role->system_name)?->typedValue();

                return [
                    'system_name' => (string) $role->system_name,
                    'name' => $role->name,
                    'limit' => is_int($value) ? $value : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Perfis administrativos elegíveis a limite por plano (exclui tenant-admin).
     *
     * @return Collection<int, Role>
     */
    private function limitableAdministrativeRoles(): Collection
    {
        return $this->administrativeUserLimit->administrativeRoles()
            ->reject(fn (Role $role): bool => $role->system_name === 'tenant-admin')
            ->values();
    }

    private function isRoleLimitKey(string $key): bool
    {
        return str_starts_with($key, self::ROLE_LIMIT_KEY_PREFIX);
    }

    /**
     * Remove the specified plan.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        if ($plan->tenants()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.plans.messages.in_use'),
            ]);

            return back();
        }

        $plan->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.plans.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.plans.index');
    }
}
