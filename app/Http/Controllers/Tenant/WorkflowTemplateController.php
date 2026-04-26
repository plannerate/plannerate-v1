<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\WorkflowTemplateStoreRequest;
use App\Http\Requests\Tenant\WorkflowTemplateUpdateRequest;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowTemplateController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkflowTemplate::class);

        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $hasStatusFilter = in_array($status, ['draft', 'published'], true);

        $templates = WorkflowTemplate::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($hasStatusFilter, fn ($q) => $q->where('status', $status))
            ->orderBy('suggested_order')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WorkflowTemplate $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'color' => $t->color,
                'icon' => $t->icon,
                'suggested_order' => $t->suggested_order,
                'status' => $t->status,
                'created_at' => $t->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/kanban/templates/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', WorkflowTemplate::class);

        return Inertia::render('tenant/kanban/templates/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => null,
            'users' => $this->usersForSelect(),
            'existing_templates' => $this->templatesForSelect(),
        ]);
    }

    public function store(WorkflowTemplateStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkflowTemplate::class);

        $validated = $request->validated();
        $userIds = $validated['user_ids'] ?? [];

        $template = WorkflowTemplate::create([
            ...Arr::except($validated, ['user_ids']),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        if ($userIds !== []) {
            $template->suggestedUsers()->sync($userIds);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.kanban.templates.messages.created'),
        ]);

        return to_route('tenant.kanban.templates.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, WorkflowTemplate $template): Response
    {
        unset($subdomain);
        $this->authorize('update', $template);

        $template->load('suggestedUsers');

        return Inertia::render('tenant/kanban/templates/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'suggested_order' => $template->suggested_order,
                'estimated_duration_days' => $template->estimated_duration_days,
                'default_role_id' => $template->default_role_id,
                'color' => $template->color,
                'icon' => $template->icon,
                'is_required_by_default' => $template->is_required_by_default,
                'template_next_step_id' => $template->template_next_step_id,
                'template_previous_step_id' => $template->template_previous_step_id,
                'status' => $template->status,
                'user_ids' => $template->suggestedUsers->pluck('id')->all(),
            ],
            'users' => $this->usersForSelect(),
            'existing_templates' => $this->templatesForSelect($template->id),
        ]);
    }

    public function update(WorkflowTemplateUpdateRequest $request, string $subdomain, WorkflowTemplate $template): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $template);

        $validated = $request->validated();
        $userIds = $validated['user_ids'] ?? [];

        $template->update(Arr::except($validated, ['user_ids']));
        $template->suggestedUsers()->sync($userIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.kanban.templates.messages.updated'),
        ]);

        return to_route('tenant.kanban.templates.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, WorkflowTemplate $template): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $template);

        $template->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.kanban.templates.messages.deleted'),
        ]);

        return to_route('tenant.kanban.templates.index', $this->tenantRouteParameters());
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
}
