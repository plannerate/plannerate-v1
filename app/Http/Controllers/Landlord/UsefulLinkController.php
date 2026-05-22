<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreUsefulLinkRequest;
use App\Http\Requests\Landlord\UpdateUsefulLinkRequest;
use App\Models\UsefulLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsefulLinkController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UsefulLink::class);

        $search = $this->requestString($request, 'search');
        $showOnTenantDashboard = $this->requestEnum($request, 'show_on_tenant_dashboard', ['0', '1']);

        return $this->renderDeferredIndex('landlord/useful-links/Index', 'useful_links', fn (): LengthAwarePaginator => $this->usefulLinksPaginator(
            $search,
            $showOnTenantDashboard,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'show_on_tenant_dashboard' => $showOnTenantDashboard,
            ],
        ]);
    }

    private function usefulLinksPaginator(string $search, string $showOnTenantDashboard, int $perPage): LengthAwarePaginator
    {
        return UsefulLink::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('url', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($showOnTenantDashboard !== '', fn ($query) => $query->where('show_on_tenant_dashboard', $showOnTenantDashboard === '1'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (UsefulLink $usefulLink): array => [
                'id' => $usefulLink->id,
                'name' => $usefulLink->name,
                'url' => $usefulLink->url,
                'logo' => $usefulLink->logo,
                'description' => $usefulLink->description,
                'show_on_tenant_dashboard' => $usefulLink->show_on_tenant_dashboard,
                'created_at' => $usefulLink->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', UsefulLink::class);

        return Inertia::render('landlord/useful-links/Form', [
            'useful_link' => null,
        ]);
    }

    public function store(StoreUsefulLinkRequest $request): RedirectResponse
    {
        $this->authorize('create', UsefulLink::class);

        UsefulLink::query()->create([
            ...$request->validated(),
            'show_on_tenant_dashboard' => $request->boolean('show_on_tenant_dashboard'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.useful_links.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.useful-links.index');
    }

    public function edit(UsefulLink $usefulLink): Response
    {
        $this->authorize('update', $usefulLink);

        return Inertia::render('landlord/useful-links/Form', [
            'useful_link' => [
                'id' => $usefulLink->id,
                'name' => $usefulLink->name,
                'url' => $usefulLink->url,
                'logo' => $usefulLink->logo,
                'description' => $usefulLink->description,
                'show_on_tenant_dashboard' => $usefulLink->show_on_tenant_dashboard,
            ],
        ]);
    }

    public function update(UpdateUsefulLinkRequest $request, UsefulLink $usefulLink): RedirectResponse
    {
        $this->authorize('update', $usefulLink);

        $usefulLink->update([
            ...$request->validated(),
            'show_on_tenant_dashboard' => $request->boolean('show_on_tenant_dashboard'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.useful_links.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.useful-links.index');
    }

    public function destroy(UsefulLink $usefulLink): RedirectResponse
    {
        $this->authorize('delete', $usefulLink);

        $usefulLink->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.useful_links.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.useful-links.index');
    }
}
