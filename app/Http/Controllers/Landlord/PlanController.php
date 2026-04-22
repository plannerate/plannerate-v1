<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePlanRequest;
use App\Http\Requests\Landlord\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Display a listing of plans.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plan::class);

        $search = trim((string) $request->string('search'));
        $isActive = $request->query('is_active');
        $hasIsActiveFilter = in_array($isActive, ['0', '1'], true);

        $plans = Plan::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($hasIsActiveFilter, fn ($query) => $query->where('is_active', $isActive === '1'))
            ->withCount('tenants')
            ->latest()
            ->paginate(10)
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

        return Inertia::render('landlord/plans/Index', [
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'is_active' => $hasIsActiveFilter ? $isActive : '',
            ],
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

        Plan::query()->create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.plans.messages.created'),
        ]);

        return to_route('landlord.plans.index');
    }

    /**
     * Show the form for editing the specified plan.
     */
    public function edit(Plan $plan): Response
    {
        $this->authorize('update', $plan);

        return Inertia::render('landlord/plans/Form', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'user_limit' => $plan->user_limit,
                'is_active' => $plan->is_active,
            ],
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

        $plan->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.plans.messages.updated'),
        ]);

        return to_route('landlord.plans.index');
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

        return to_route('landlord.plans.index');
    }
}
