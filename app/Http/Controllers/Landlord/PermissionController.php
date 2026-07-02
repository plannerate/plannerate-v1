<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePermissionRequest;
use App\Http\Requests\Landlord\UpdatePermissionRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\PermissionName;
use App\Support\Authorization\RbacType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    use InteractsWithDeferredIndex;

    /**
     * @var list<string>
     */
    private const PROTECTED_PERMISSIONS = [
        'landlord.roles.delete',
        'landlord.permissions.delete',
        'landlord.users.delete',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        $search = $this->requestString($request, 'search');
        $type = $this->requestEnum($request, 'type', RbacType::all());

        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $missingCount = count(array_filter(
            PermissionName::all(),
            fn (string $name): bool => ! in_array($name, $existing, true),
        ));

        return $this->renderDeferredIndex('landlord/permissions/Index', 'permissions', fn (): LengthAwarePaginator => $this->permissionsPaginator(
            $search,
            $type,
            $this->resolvePerPage($request, 15),
        ), [
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'filter_options' => [
                'types' => $this->typesForSelect(),
            ],
            'missing_count' => $missingCount,
        ]);
    }

    private function permissionsPaginator(string $search, string $type, int $perPage): LengthAwarePaginator
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'type' => $permission->type,
                'short_name' => $permission->short_name,
                'description' => $permission->description,
                'is_protected' => in_array($permission->name, self::PROTECTED_PERMISSIONS, true),
                'created_at' => $permission->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('landlord/permissions/Form', [
            'permission' => null,
            'types' => $this->typesForSelect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $name = $request->string('name')->toString();

        Permission::query()->create([
            'name' => $name,
            'type' => $request->string('type')->toString(),
            'short_name' => $this->resolveShortName($request->input('short_name'), $name),
            'description' => $this->resolveDescription($request->input('description'), $name),
            'guard_name' => 'web',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.permissions.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.permissions.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission): Response
    {
        $this->authorize('update', $permission);

        return Inertia::render('landlord/permissions/Form', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'type' => $permission->type,
                'short_name' => $permission->short_name,
                'description' => $permission->description,
                'is_protected' => in_array($permission->name, self::PROTECTED_PERMISSIONS, true),
            ],
            'types' => $this->typesForSelect(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->authorize('update', $permission);

        if (in_array($permission->name, self::PROTECTED_PERMISSIONS, true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.permissions.messages.protected'),
            ]);

            return back();
        }

        // O slug (name) é imutável após a criação; usa-se o valor atual da permissão.
        $name = $permission->name;

        $permission->update([
            'type' => $request->string('type')->toString(),
            'short_name' => $this->resolveShortName($request->input('short_name'), $name),
            'description' => $this->resolveDescription($request->input('description'), $name),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.permissions.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.permissions.index');
    }

    /**
     * Sync permissions from PermissionName constants to the database.
     */
    public function sync(): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        $missing = array_filter(
            PermissionName::all(),
            fn (string $name): bool => ! in_array($name, $existing, true),
        );

        $currentTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        /** @var Collection<int, Permission> $created */
        $created = collect();

        foreach ($missing as $name) {
            $created->push(Permission::query()->create([
                'name' => $name,
                'type' => PermissionName::typeFor($name) ?? RbacType::TENANT,
                'short_name' => PermissionName::shortNameFor($name),
                'description' => PermissionName::descriptionFor($name),
                'guard_name' => 'web',
            ]));
        }

        $superAdmin = Role::query()->where('system_name', 'super-admin')->first();
        $landlordAdmin = Role::query()->where('system_name', 'landlord-admin')->first();
        $tenantAdmin = Role::query()->where('system_name', 'tenant-admin')->first();

        foreach ($created as $permission) {
            $superAdmin?->givePermissionTo($permission);

            if ($permission->type === RbacType::LANDLORD) {
                $landlordAdmin?->givePermissionTo($permission);
            } elseif ($permission->type === RbacType::TENANT) {
                $tenantAdmin?->givePermissionTo($permission);
            }
        }

        $backfilled = $this->backfillPermissionMetadata();

        setPermissionsTeamId($currentTeamId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $count = $created->count();

        if ($count === 0 && $backfilled === 0) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'Todas as permissões já estão registradas e descritas.',
            ]);

            return $this->toLandlordRoute('landlord.permissions.index');
        }

        $parts = [];

        if ($count > 0) {
            $parts[] = "{$count} nova(s) permissão(ões) cadastrada(s) e atribuída(s)";
        }

        if ($backfilled > 0) {
            $parts[] = "{$backfilled} descrição(ões) preenchida(s)";
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => ucfirst(implode(' e ', $parts)).'.',
        ]);

        return $this->toLandlordRoute('landlord.permissions.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        $this->authorize('delete', $permission);

        if (in_array($permission->name, self::PROTECTED_PERMISSIONS, true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.permissions.messages.protected'),
            ]);

            return back();
        }

        $permission->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.permissions.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.permissions.index');
    }

    /**
     * Resolve o nome curto: usa o valor informado ou cai para a descrição base do PermissionName.
     */
    private function resolveShortName(mixed $input, string $name): ?string
    {
        $value = is_string($input) ? trim($input) : '';

        return $value !== '' ? $value : PermissionName::shortNameFor($name);
    }

    /**
     * Resolve a descrição: usa o valor informado ou cai para a descrição base do PermissionName.
     */
    private function resolveDescription(mixed $input, string $name): ?string
    {
        $value = is_string($input) ? trim($input) : '';

        return $value !== '' ? $value : PermissionName::descriptionFor($name);
    }

    /**
     * Preenche nome curto/descrição das permissões existentes que estão vazias,
     * usando os metadados base do PermissionName. Retorna a quantidade atualizada.
     */
    private function backfillPermissionMetadata(): int
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->where(function ($query): void {
                $query->whereNull('short_name')
                    ->orWhere('short_name', '')
                    ->orWhereNull('description')
                    ->orWhere('description', '');
            })
            ->get();

        $updated = 0;

        foreach ($permissions as $permission) {
            $changes = [];

            if ($permission->short_name === null || trim((string) $permission->short_name) === '') {
                $shortName = PermissionName::shortNameFor($permission->name);

                if ($shortName !== null) {
                    $changes['short_name'] = $shortName;
                }
            }

            if ($permission->description === null || trim((string) $permission->description) === '') {
                $description = PermissionName::descriptionFor($permission->name);

                if ($description !== null) {
                    $changes['description'] = $description;
                }
            }

            if ($changes !== []) {
                $permission->update($changes);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typesForSelect(): array
    {
        return [
            [
                'value' => RbacType::LANDLORD,
                'label' => __('app.landlord.roles.types.landlord'),
            ],
            [
                'value' => RbacType::TENANT,
                'label' => __('app.landlord.roles.types.tenant'),
            ],
        ];
    }
}
