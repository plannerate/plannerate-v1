<?php

namespace App\Jobs\Imports;

use App\Events\Tenant\CategoriesImportFinished;
use App\Models\Tenant;
use App\Services\Files\Imports\Categories\CategorySpreadsheetImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Jobs\TenantAware;

class ImportCategoriesFromSpreadsheetJob implements ShouldQueue, TenantAware
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $tenantId,
        public ?string $userId,
        public string $disk,
        public string $path
    ) {}

    public function handle(CategorySpreadsheetImportService $service): void
    {
        $absolutePath = Storage::disk($this->disk)->path($this->path);
        $result = $service->importFile($absolutePath, $this->tenantId, $this->userId);

        Log::info('Importacao de categorias finalizada.', [
            'tenant_id' => $this->tenantId,
            'rows_processed' => $result->rowsProcessed,
            'categories_created' => $result->categoriesCreated,
            'categories_updated' => $result->categoriesUpdated,
            'products_linked' => $result->productsLinked,
            'warnings' => $result->warnings,
            'errors' => $result->errors,
        ]);

        $userId = $this->userId;
        if ($userId !== null && $userId !== '') {
            $tenant = Tenant::current();
            $tenantSlug = $tenant !== null ? (string) $tenant->slug : '';

            broadcast(new CategoriesImportFinished(
                userId: $userId,
                tenantId: $this->tenantId,
                tenantSlug: $tenantSlug,
                rowsProcessed: $result->rowsProcessed,
                categoriesCreated: $result->categoriesCreated,
                categoriesUpdated: $result->categoriesUpdated,
                productsLinked: $result->productsLinked,
                warningsCount: count($result->warnings),
                errorsCount: count($result->errors),
            ));
        }

        Storage::disk($this->disk)->delete($this->path);
    }
}
