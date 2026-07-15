<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Concerns\InteractsWithResourceAbilities;
use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\ImportIntegrationApiConfigRequest;
use App\Http\Requests\Landlord\StoreIntegrationApiRequest;
use App\Http\Requests\Landlord\UpdateIntegrationApiRequest;
use App\Models\IntegrationApi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IntegrationApiController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithResourceAbilities;
    use InteractsWithTrashedFilter;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IntegrationApi::class);

        $search = $this->requestString($request, 'search');
        $isActive = $this->requestEnum($request, 'is_active', ['0', '1']);
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('landlord/integration-apis/Index', 'integrationApis', fn (): LengthAwarePaginator => $this->integrationApisPaginator(
            $search,
            $isActive,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'is_active' => $isActive,
                'trashed' => $trashed,
            ],
            'can' => $this->resolveResourceAbilities(IntegrationApi::class),
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

        $integrationApi = IntegrationApi::query()->create($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.created'),
        ]);

        return $this->toLandlordRoute('landlord.integration-apis.edit', ['integration_api' => $integrationApi]);
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

        return $this->toLandlordRoute('landlord.integration-apis.edit', ['integration_api' => $integrationApi]);
    }

    public function destroy(IntegrationApi $integrationApi): RedirectResponse
    {
        $this->authorize('delete', $integrationApi);

        if ($integrationApi->trashed()) {
            $integrationApi->forceDelete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.landlord.integration_apis.messages.force_deleted'),
            ]);

            return $this->toLandlordRoute('landlord.integration-apis.index');
        }

        $integrationApi->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.integration-apis.index');
    }

    public function restore(IntegrationApi $integrationApi): RedirectResponse
    {
        $this->authorize('delete', $integrationApi);

        $integrationApi->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.restored'),
        ]);

        return $this->toLandlordRoute('landlord.integration-apis.index');
    }

    public function exportConfigurations(): StreamedResponse
    {
        $this->authorize('viewAny', IntegrationApi::class);

        $payload = [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'integration_apis' => IntegrationApi::query()
                ->orderBy('slug')
                ->get()
                ->map(fn (IntegrationApi $integrationApi): array => [
                    'name' => $integrationApi->name,
                    'slug' => $integrationApi->slug,
                    'description' => $integrationApi->description,
                    'requests' => is_array($integrationApi->requests) ? $integrationApi->requests : [],
                    'response' => is_array($integrationApi->response) ? $integrationApi->response : [],
                    'is_active' => (bool) $integrationApi->is_active,
                ])
                ->values()
                ->all(),
        ];

        $fileName = sprintf('integration-apis-%s.json', now()->format('Ymd-His'));

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            },
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    public function importConfigurations(ImportIntegrationApiConfigRequest $request): RedirectResponse
    {
        $this->authorize('create', IntegrationApi::class);

        $uploadedFile = $request->file('spreadsheet');

        if (! $uploadedFile instanceof UploadedFile) {
            return $this->toLandlordRoute('landlord.integration-apis.index');
        }

        $decoded = json_decode((string) $uploadedFile->get(), true);
        if (! is_array($decoded)) {
            return back()->withErrors([
                'spreadsheet' => __('app.landlord.integration_apis.messages.import_invalid_json'),
            ]);
        }

        $rawItems = $decoded['integration_apis'] ?? $decoded;
        if (! is_array($rawItems)) {
            return back()->withErrors([
                'spreadsheet' => __('app.landlord.integration_apis.messages.import_invalid_structure'),
            ]);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        collect($rawItems)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->each(function (array $item) use (&$created, &$updated, &$skipped): void {
                $slug = Str::of((string) ($item['slug'] ?? ''))->trim()->lower()->toString();

                if ($slug === '') {
                    $skipped++;

                    return;
                }

                $name = Str::of((string) ($item['name'] ?? ''))->trim()->toString();

                $payload = [
                    'name' => $name !== '' ? $name : Str::of($slug)->replace(['-', '_'], ' ')->title()->toString(),
                    'slug' => $slug,
                    'description' => is_string($item['description'] ?? null) ? $item['description'] : null,
                    'requests' => is_array($item['requests'] ?? null) ? $item['requests'] : [],
                    'response' => is_array($item['response'] ?? null) ? $item['response'] : [],
                    'is_active' => (bool) ($item['is_active'] ?? true),
                ];

                $existing = IntegrationApi::withTrashed()->where('slug', $slug)->first();

                if ($existing instanceof IntegrationApi) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->update($payload);
                    $updated++;

                    return;
                }

                IntegrationApi::query()->create($payload);
                $created++;
            });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.integration_apis.messages.imported', [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ]),
        ]);

        return $this->toLandlordRoute('landlord.integration-apis.index');
    }

    private function integrationApisPaginator(string $search, string $isActive, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = IntegrationApi::query();
        $this->applyTrashedToQuery($query, $trashed);

        return $query
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
                'trashed' => $integrationApi->trashed(),
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
                'paths' => [
                    'products' => [
                        'enabled' => true,
                        'target_table' => 'products',
                        'fallback_path' => '/products',
                        'id_prefix' => 'P1',
                        'unique_by' => ['codigo_erp'],
                        'date_fields' => ['changed_since' => 'data_alteracao'],
                    ],
                    'sales' => [
                        'enabled' => true,
                        'target_table' => 'sales',
                        'fallback_path' => '/sales',
                        'id_prefix' => 'S1',
                        'unique_by' => ['codigo_erp', 'sale_date', 'promotion'],
                        'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
                    ],
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
