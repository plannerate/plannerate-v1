<?php

namespace App\Console\Commands;

use App\Jobs\DOProcessProductImageJob;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessProductImages extends Command
{
    protected $signature = 'process-product-images
        {--ean=      : EAN específico para processar}
        {--tenant=*  : ID(s) do tenant (todos com módulo image-bank se omitido)}';

    protected $description = 'Processa imagens de produtos para tenants com o módulo image-bank ativo';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant com módulo image-bank ativo encontrado.');

            return self::SUCCESS;
        }

        $doAvailable = $this->probeDoStorage();
        if (! $doAvailable) {
            $this->warn('Disco DO não acessível — apenas fast path (EanReference cache) será executado. Jobs não serão despachados.');
        }

        $eanRefMap = $this->loadEanReferenceMap();
        $this->line(sprintf('%d EAN(s) em cache no EanReference.', count($eanRefMap)));

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("Tenant: {$tenant->name}");
            $tenant->execute(fn () => $this->processTenant($eanRefMap, $doAvailable));
        }

        $this->newLine();
        $this->info('Concluído.');

        return self::SUCCESS;
    }

    private function probeDoStorage(): bool
    {
        try {
            Storage::disk('do')->exists('__probe__');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        $tenantIds = $this->option('tenant');

        if (! empty($tenantIds)) {
            $ids = collect($tenantIds)
                ->flatMap(fn (string $v) => explode(',', $v))
                ->map(fn (string $v) => trim($v))
                ->filter()
                ->values()
                ->toArray();

            return Tenant::query()->whereIn('id', $ids)->get();
        }

        return Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->get();
    }

    /**
     * Mapa normalizedEan → image_front_url do landlord (uma query por execução).
     *
     * @return array<string, string>
     */
    private function loadEanReferenceMap(): array
    {
        return DB::connection('landlord')
            ->table('ean_references')
            ->whereNull('deleted_at')
            ->whereNotNull('image_front_url')
            ->pluck('image_front_url', 'ean')
            ->all();
    }

    /**
     * @param  array<string, string>  $eanRefMap
     */
    private function processTenant(array $eanRefMap, bool $doAvailable = true): void
    {
        $ean = trim((string) $this->option('ean'));
        $stats = ['total' => 0, 'fast' => 0, 'dispatched' => 0, 'skipped' => 0];

        $query = Product::query()
            ->whereNotNull('ean')
            ->when($ean !== '', fn ($q) => $q->where('ean', $ean))
            ->select(['id', 'ean', 'url']);

        $progressBar = $this->output->createProgressBar($query->count());
        $progressBar->start();

        $query->chunkById(500, function ($products) use ($eanRefMap, $doAvailable, &$stats, $progressBar): void {
            $eligible = $products->filter(function ($product): bool {
                if ($product->url === null || $product->url === '') {
                    return true;
                }

                return ! Storage::disk('public')->exists($product->url);
            });

            $stats['total'] += $products->count();
            $stats['skipped'] += $products->count() - $eligible->count();

            if ($eligible->isEmpty()) {
                $progressBar->advance($products->count());

                return;
            }

            // Fast path: EAN já resolvido → UPDATE em lote via SQL
            $fastPath = $eligible->filter(
                fn ($p) => isset($eanRefMap[EanReference::normalizeEan((string) $p->ean)])
            );

            if ($fastPath->isNotEmpty()) {
                $grouped = $fastPath->groupBy(
                    fn ($p) => $eanRefMap[EanReference::normalizeEan((string) $p->ean)]
                );

                foreach ($grouped as $imageUrl => $group) {
                    DB::table('products')
                        ->whereIn('id', $group->pluck('id'))
                        ->update(['url' => $imageUrl, 'updated_at' => now()]);
                }

                $stats['fast'] += $fastPath->count();
            }

            // Slow path: EAN desconhecido → despacha job (só se DO estiver disponível)
            if ($doAvailable) {
                $slowPath = $eligible->reject(
                    fn ($p) => isset($eanRefMap[EanReference::normalizeEan((string) $p->ean)])
                );

                foreach ($slowPath as $product) {
                    DOProcessProductImageJob::dispatch($product->id);
                    $stats['dispatched']++;
                }
            }

            $progressBar->advance($products->count());
        });

        $progressBar->finish();
        $this->newLine();
        $this->line(sprintf(
            '  Total: %d | Ignorados: %d | Fast path: %d | Jobs: %d',
            $stats['total'],
            $stats['skipped'],
            $stats['fast'],
            $stats['dispatched'],
        ));
    }
}
