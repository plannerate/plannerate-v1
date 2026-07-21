<?php

namespace App\Jobs;

use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\EanReferenceImageSyncService;
use App\Services\ProductRepositoryImageResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessEanReferenceImageJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /**
     * @param  list<string>  $tenantIds
     */
    public function __construct(
        public string $eanReferenceId,
        public bool $force = false,
        public array $tenantIds = [],
        public bool $notify = false,
        public ?string $notifyUserId = null,
    ) {
        $this->onQueue('critical');
    }

    public function __wakeup(): void
    {
        if (! isset($this->force)) {
            $this->force = false;
        }
        if (! isset($this->tenantIds)) {
            $this->tenantIds = [];
        }
        if (! isset($this->notify)) {
            $this->notify = false;
        }
        if (! isset($this->notifyUserId)) {
            $this->notifyUserId = null;
        }
    }

    public function handle(ProductRepositoryImageResolver $imageResolver, EanReferenceImageSyncService $syncService): void
    {
        $reference = EanReference::query()->find($this->eanReferenceId);

        if (! $reference) {
            return;
        }

        $normalizedEan = EanReference::normalizeEan((string) $reference->ean);
        if ($normalizedEan === '') {
            return;
        }

        $result = $imageResolver->resolveByEan(
            $normalizedEan,
            force: $this->force,
            description: $this->resolveProductDescription($normalizedEan),
        );
        $path = is_array($result) ? ($result['path'] ?? null) : null;

        if (! is_string($path) || $path === '') {
            return;
        }

        $reference->image_front_url = $path;
        $reference->save();

        if ($this->notify && $this->notifyUserId !== null) {
            $user = User::on('landlord')->find($this->notifyUserId);
            $user?->notify(new AppNotification(
                title: 'Imagem encontrada',
                message: "Imagem do EAN {$normalizedEan} salva com sucesso.",
                type: 'success',
            ));
        }

        $syncService->syncOne($normalizedEan, $path, $this->tenantIds);
    }

    /**
     * Nome do produto para alimentar a geração por IA (último recurso do resolver).
     *
     * O job é NotTenantAware e o ean_references não guarda descrição, então o único lugar
     * com um nome legível é a tabela de produtos do tenant que pediu o download.
     */
    private function resolveProductDescription(string $normalizedEan): ?string
    {
        foreach ($this->tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant instanceof Tenant) {
                continue;
            }

            $name = $tenant->execute(
                fn (): ?string => Product::query()->where('ean', $normalizedEan)->value('name')
            );

            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }

        return null;
    }
}
