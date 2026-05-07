<?php

namespace App\Console\Commands;

use App\Models\EanReference;
use App\Models\Tenant;
use App\Services\EanReferenceImageSyncService;
use App\Support\Modules\ModuleSlug;
use Illuminate\Console\Command;

class SyncProductImages extends Command
{
    protected $signature = 'sync-product-images
        {--ean= : EAN específico para sincronizar}
        {--tenant=* : ID(s) do tenant (todos com módulo image-bank se omitido)}';

    protected $description = 'Propaga image_front_url do catálogo EAN para produtos com url vazia nos tenants com image-bank ativo';

    public function handle(EanReferenceImageSyncService $syncService): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === null) {
            $this->warn('Nenhum tenant com módulo image-bank ativo encontrado.');

            return self::SUCCESS;
        }

        $ean = trim((string) $this->option('ean'));
        $normalizedEan = EanReference::normalizeEan($ean);

        if ($normalizedEan !== '') {
            return $this->syncSingleEan($syncService, $normalizedEan, $tenantIds);
        }

        return $this->syncAll($syncService, $tenantIds);
    }

    /**
     * @param  list<string>  $tenantIds
     */
    private function syncSingleEan(EanReferenceImageSyncService $syncService, string $normalizedEan, array $tenantIds): int
    {
        $reference = EanReference::query()
            ->whereNull('deleted_at')
            ->whereNotNull('image_front_url')
            ->where('image_front_url', '!=', '')
            ->forNormalizedEan($normalizedEan)
            ->first(['ean', 'image_front_url']);

        if (! $reference || ! is_string($reference->image_front_url)) {
            $this->warn("EAN {$normalizedEan} não encontrado ou sem imagem no catálogo.");

            return self::SUCCESS;
        }

        $updated = $syncService->syncOne($normalizedEan, $reference->image_front_url, $tenantIds, onlyEmpty: true);

        $this->info(sprintf('EAN %s: %d produto(s) atualizado(s).', $normalizedEan, $updated));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $tenantIds
     */
    private function syncAll(EanReferenceImageSyncService $syncService, array $tenantIds): int
    {
        $total = EanReference::query()
            ->whereNotNull('image_front_url')
            ->where('image_front_url', '!=', '')
            ->count();

        if ($total === 0) {
            $this->warn('Nenhum EAN com imagem encontrado no catálogo.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Sincronizando imagens de %d EAN(s)...', $total));
        $this->newLine();

        $totalUpdated = 0;

        $syncService->syncAll(
            tenantIds: $tenantIds,
            onProgress: function (string $ean, int $updated) use (&$totalUpdated): void {
                $totalUpdated += $updated;
                $this->line(sprintf('  EAN %s → %d produto(s) atualizado(s)', $ean, $updated));
            },
        );

        $this->newLine();
        $this->info(sprintf('Concluído. Total de produtos atualizados: %d.', $totalUpdated));

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null null se nenhum tenant encontrado
     */
    private function resolveTenantIds(): ?array
    {
        $rawIds = $this->option('tenant');

        if (! empty($rawIds)) {
            return collect($rawIds)
                ->flatMap(fn (string $v): array => explode(',', $v))
                ->map(fn (string $v): string => trim($v))
                ->filter()
                ->values()
                ->all();
        }

        $ids = Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->whereNotNull('database')
            ->where('database', '!=', '')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $ids !== [] ? $ids : null;
    }
}
