<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\WorkflowTemplateStoreRequest;
use App\Http\Requests\Landlord\WorkflowTemplateUpdateRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

class WorkflowTemplateController extends Controller
{
    public function index(Request $request, Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $hasStatusFilter = in_array($status, ['draft', 'published'], true);

        /** @var array{
         *     templates: LengthAwarePaginator<array<string, mixed>>,
         * } $data
         */
        $data = $this->runInTenantContext($tenant, function () use ($search, $hasStatusFilter, $status): array {
            $templates = WorkflowTemplate::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->when($hasStatusFilter, fn ($q) => $q->where('status', $status))
                ->with('suggestedUsers:id,name')
                ->orderBy('suggested_order')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (WorkflowTemplate $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'description' => $t->description,
                    'suggested_order' => $t->suggested_order,
                    'estimated_duration_days' => $t->estimated_duration_days,
                    'default_role_id' => $t->default_role_id,
                    'color' => $t->color,
                    'icon' => $t->icon,
                    'is_required_by_default' => $t->is_required_by_default,
                    'template_next_step_id' => $t->template_next_step_id,
                    'template_previous_step_id' => $t->template_previous_step_id,
                    'status' => $t->status,
                    'user_ids' => $t->suggestedUsers->pluck('id')->all(),
                    'created_at' => $t->created_at?->toDateTimeString(),
                ]);

            return [
                'templates' => $templates,
                'users' => $this->usersForSelect(),
                'existing_templates' => $this->templatesForSelect(),
            ];
        });

        return Inertia::render('landlord/tenants/templates/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'templates' => $data['templates'],
            'users' => $data['users'],
            'existing_templates' => $data['existing_templates'],
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
            ],
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $data = $this->runInTenantContext($tenant, function (): array {
            return [
                'users' => $this->usersForSelect(),
                'existing_templates' => $this->templatesForSelect(),
            ];
        });

        return Inertia::render('landlord/tenants/templates/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'template' => null,
            'users' => $data['users'],
            'existing_templates' => $data['existing_templates'],
        ]);
    }

    public function store(WorkflowTemplateStoreRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();
        $userIds = $validated['user_ids'] ?? [];

        $this->runInTenantContext($tenant, function () use ($validated, $userIds, $request): void {
            $template = WorkflowTemplate::create([
                ...Arr::except($validated, ['user_ids']),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            if ($userIds !== []) {
                $template->suggestedUsers()->sync($userIds);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.kanban.templates.messages.created'),
        ]);

        return to_route('landlord.tenants.kanban.templates.index', $tenant);
    }

    public function edit(Tenant $tenant, string $template): Response
    {
        $this->authorize('update', $tenant);

        $data = $this->runInTenantContext($tenant, function () use ($template): array {
            $tpl = WorkflowTemplate::with('suggestedUsers')->findOrFail($template);

            return [
                'template' => [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'slug' => $tpl->slug,
                    'description' => $tpl->description,
                    'suggested_order' => $tpl->suggested_order,
                    'estimated_duration_days' => $tpl->estimated_duration_days,
                    'default_role_id' => $tpl->default_role_id,
                    'color' => $tpl->color,
                    'icon' => $tpl->icon,
                    'is_required_by_default' => $tpl->is_required_by_default,
                    'template_next_step_id' => $tpl->template_next_step_id,
                    'template_previous_step_id' => $tpl->template_previous_step_id,
                    'status' => $tpl->status,
                    'user_ids' => $tpl->suggestedUsers->pluck('id')->all(),
                ],
                'users' => $this->usersForSelect(),
                'existing_templates' => $this->templatesForSelect($tpl->id),
            ];
        });

        return Inertia::render('landlord/tenants/templates/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'template' => $data['template'],
            'users' => $data['users'],
            'existing_templates' => $data['existing_templates'],
        ]);
    }

    public function update(WorkflowTemplateUpdateRequest $request, Tenant $tenant, string $template): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();
        $userIds = $validated['user_ids'] ?? [];

        $this->runInTenantContext($tenant, function () use ($validated, $userIds, $template): void {
            $tpl = WorkflowTemplate::findOrFail($template);
            $tpl->update(Arr::except($validated, ['user_ids']));
            $tpl->suggestedUsers()->sync($userIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.kanban.templates.messages.updated'),
        ]);

        return to_route('landlord.tenants.kanban.templates.index', $tenant);
    }

    public function destroy(Tenant $tenant, string $template): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function () use ($template): void {
            WorkflowTemplate::findOrFail($template)->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.kanban.templates.messages.deleted'),
        ]);

        return to_route('landlord.tenants.kanban.templates.index', $tenant);
    }

    public function seedDefaultTemplates(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $this->runInTenantContext($tenant, function (): void {
            $defaults = WorkflowTemplate::getDefaultTemplates();
            $createdTemplates = [];

            // Create all templates first
            foreach ($defaults as $default) {
                $template = WorkflowTemplate::create([
                    'name' => $default['name'],
                    'slug' => Str::slug($default['name']),
                    'description' => $default['description'],
                    'suggested_order' => $default['suggested_order'],
                    'estimated_duration_days' => $default['estimated_duration_days'],
                    'is_required_by_default' => $default['is_required_by_default'],
                    'color' => $default['color'],
                    'icon' => $default['icon'],
                    'status' => 'published',
                ]);
                $createdTemplates[] = $template;
            }

            // Link templates in sequence
            foreach ($createdTemplates as $index => $template) {
                $previousTemplate = $index > 0 ? $createdTemplates[$index - 1] : null;
                $nextTemplate = $index < count($createdTemplates) - 1 ? $createdTemplates[$index + 1] : null;

                $template->update([
                    'template_previous_step_id' => $previousTemplate?->id,
                    'template_next_step_id' => $nextTemplate?->id,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.kanban.templates.messages.seeded'),
        ]);

        return to_route('landlord.tenants.kanban.templates.index', $tenant);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function usersForSelect(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function templatesForSelect(?string $excludeId = null): array
    {
        return WorkflowTemplate::query()
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('suggested_order')
            ->get(['id', 'name'])
            ->map(fn (WorkflowTemplate $t): array => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $tenantConnectionName = $this->resolveTenantConnectionName();
        $originalTenantDatabase = config("database.connections.{$tenantConnectionName}.database");
        $originalTenant = CurrentTenantModel::current();
        $tenant->makeCurrent();

        try {
            return $callback();
        } finally {
            if ($originalTenant !== null) {
                $originalTenant->makeCurrent();
            } else {
                CurrentTenantModel::forgetCurrent();
                config([
                    "database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase,
                ]);
                DB::purge($tenantConnectionName);
            }
        }
    }

    private function resolveTenantConnectionName(): string
    {
        return (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
    }
}
