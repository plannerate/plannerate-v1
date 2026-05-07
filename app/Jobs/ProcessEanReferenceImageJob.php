<?php

namespace App\Jobs;

use App\Models\EanReference;
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

    /**
     * @param  list<string>  $tenantIds
     */
    public function __construct(
        public string $eanReferenceId,
        public bool $force = false,
        public array $tenantIds = [],
        public bool $notify = false,
        public ?string $notifyUserId = null,
    ) {}

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

        $result = $imageResolver->resolveByEan($normalizedEan, force: $this->force);
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
}
