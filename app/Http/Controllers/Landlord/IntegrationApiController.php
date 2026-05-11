<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreIntegrationApiRequest;
use App\Http\Requests\Landlord\UpdateIntegrationApiRequest;
use App\Models\IntegrationApi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationApiController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IntegrationApi::class);

        $search = $this->requestString($request, 'search');
        $isActive = $this->requestEnum($request, 'is_active', ['0', '1']);

        return $this->renderDeferredIndex('landlord/integration-apis/Index', 'integrationApis', fn (): LengthAwarePaginator => $this->integrationApisPaginator(
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

    public function create(): Response
    {
        $this->authorize('create', IntegrationApi::class);

        return Inertia::render('landlord/integration-apis/Form', [
            'integrationApi' => null,
            'defaults' => $this->defaultPayload(),
            'fieldMapTables' => $this->fieldMapTables(),
        ]);
    }

    public function store(StoreIntegrationApiRequest $request): RedirectResponse
    {
        $this->authorize('create', IntegrationApi::class);

        IntegrationApi::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.created'),
        ]);

        return to_route('landlord.integration-apis.index');
    }

    public function edit(IntegrationApi $integrationApi): Response
    {
        $this->authorize('update', $integrationApi);

        return Inertia::render('landlord/integration-apis/Form', [
            'integrationApi' => $this->formPayload($integrationApi),
            'defaults' => $this->defaultPayload(),
            'fieldMapTables' => $this->fieldMapTables(),
        ]);
    }

    public function update(UpdateIntegrationApiRequest $request, IntegrationApi $integrationApi): RedirectResponse
    {
        $this->authorize('update', $integrationApi);

        $integrationApi->update($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.updated'),
        ]);

        return to_route('landlord.integration-apis.index');
    }

    public function destroy(IntegrationApi $integrationApi): RedirectResponse
    {
        $this->authorize('delete', $integrationApi);

        $integrationApi->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.deleted'),
        ]);

        return to_route('landlord.integration-apis.index');
    }

    private function integrationApisPaginator(string $search, string $isActive, int $perPage): LengthAwarePaginator
    {
        return IntegrationApi::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($isActive !== '', fn ($query) => $query->where('is_active', $isActive === '1'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (IntegrationApi $integrationApi): array => [
                'id' => $integrationApi->id,
                'name' => $integrationApi->name,
                'slug' => $integrationApi->slug,
                'description' => $integrationApi->description,
                'is_active' => $integrationApi->is_active,
                'created_at' => $integrationApi->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(IntegrationApi $integrationApi): array
    {
        return [
            'id' => $integrationApi->id,
            'name' => $integrationApi->name,
            'slug' => $integrationApi->slug,
            'description' => $integrationApi->description,
            'requests_json' => $this->prettyJson($integrationApi->requests),
            'response_json' => $this->prettyJson($integrationApi->response),
            'is_active' => $integrationApi->is_active,
        ];
    }

    /**
     * @return array{requests_json: string, response_json: string}
     */
    private function defaultPayload(): array
    {
        return [
            'requests_json' => $this->prettyJson([
                'method' => 'GET',
                'payload' => 'query',
                'products' => [
                    'fallback_path' => '/products',
                ],
                'sales' => [
                    'fallback_path' => '/sales',
                ],
            ]),
            'response_json' => $this->prettyJson([
                'items_path' => 'data',
                'pagination' => [
                    'last_page_path' => 'pagination.last_page',
                ],
            ]),
        ];
    }

    /**
     * @return array<string, array{label: string, columns: list<string>}>
     */
    private function fieldMapTables(): array
    {
        $tables = config('integrations.field_map_tables', []);

        if (! is_array($tables)) {
            return [];
        }

        return collect($tables)
            ->filter(fn (mixed $table): bool => is_array($table))
            ->map(function (array $table, string $key): array {
                return [
                    'label' => is_string($table['label'] ?? null) ? $table['label'] : $key,
                    'columns' => collect($table['columns'] ?? [])
                        ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    private function prettyJson(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
