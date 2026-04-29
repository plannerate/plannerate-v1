<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePermissionRequest;
use App\Http\Requests\Landlord\UpdatePermissionRequest;
use App\Models\Permission;
use App\Support\Authorization\RbacType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
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

        $search = trim((string) $request->string('search'));
        $type = (string) $request->string('type');
        $hasTypeFilter = in_array($type, RbacType::all(), true);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($hasTypeFilter, fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate($this->resolvePerPage($request, 15))
            ->withQueryString()
            ->through(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'type' => $permission->type,
                'is_protected' => in_array($permission->name, self::PROTECTED_PERMISSIONS, true),
                'created_at' => $permission->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('landlord/permissions/Index', [
            'permissions' => $permissions,
            'filters' => [
                'search' => $search,
                'type' => $hasTypeFilter ? $type : '',
            ],
            'filter_options' => [
                'types' => $this->typesForSelect(),
            ],
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

        Permission::query()->create([
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'guard_name' => 'web',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.permissions.messages.created'),
        ]);

        return to_route('landlord.permissions.index');
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

        $permission->update([
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.permissions.messages.updated'),
        ]);

        return to_route('landlord.permissions.index');
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

        return to_route('landlord.permissions.index');
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
