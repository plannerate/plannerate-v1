<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEanReferenceImageJob;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProcessProductImages extends Command
{
    protected $signature = 'process-product-images
        {--ean= : EAN específico para processar}
        {--tenant=* : ID(s) do tenant (todos com módulo image-bank se omitido)}
        {--set-url-null : Define url como null antes de sincronizar}
        {--force : Tenta baixar novamente ignorando cache/existência local}';

    protected $description = 'Despacha jobs para baixar/converter imagens por EAN e sincronizar produtos';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant com módulo image-bank ativo encontrado.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $references = $this->resolveEanReferencesForProcessing();

        if ($references->isEmpty()) {
            $this->warn('Nenhum EAN encontrado para processar no catálogo global.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('Despachando %d job(s) por EAN...', $references->count()));

        if ((bool) $this->option('set-url-null')) {
            $this->resetTenantProductUrls($tenants);
        }

        $tenantIds = $tenants->pluck('id')->map(fn ($id): string => (string) $id)->all();
        foreach ($references as $reference) {
            ProcessEanReferenceImageJob::dispatch(
                eanReferenceId: (string) $reference->id,
                force: $force,
                tenantIds: $tenantIds,
            );
        }

        $this->newLine();
        $this->info('Jobs despachados. Rode o worker da fila para processar rapidamente em paralelo.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        $tenantIds = $this->option('tenant');

        if (! empty($tenantIds)) {
            $ids = collect($tenantIds)
                ->flatMap(fn (string $value): array => explode(',', $value))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->values()
                ->all();

            return Tenant::query()
                ->whereIn('id', $ids)
                ->whereNotNull('database')
                ->where('database', '!=', '')
                ->get();
        }

        return Tenant::query()
            ->where('status', 'active')
            ->whereHasActiveModule(ModuleSlug::IMAGE_BANK)
            ->whereNotNull('database')
            ->where('database', '!=', '')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:string,ean:string,image_front_url:?string}>
     */
    private function resolveEanReferencesForProcessing()
    {
        $ean = trim((string) $this->option('ean'));
        $normalizedEan = EanReference::normalizeEan($ean);
        $force = (bool) $this->option('force');

        return DB::connection('landlord')
            ->table('ean_references')
            ->whereNull('deleted_at')
            ->when($normalizedEan !== '', fn ($query) => $query->where('ean', $normalizedEan))
            ->when(! $force, fn ($query) => $query->whereNull('image_front_url'))
            ->select(['id', 'ean', 'image_front_url'])
            ->get();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function resetTenantProductUrls(Collection $tenants): void
    {
        $ean = trim((string) $this->option('ean'));

        foreach ($tenants as $tenant) {
            $tenantName = $tenant->name;

            $tenant->execute(function () use ($ean, $tenantName): void {
                $affected = Product::query()
                    ->whereNotNull('ean')
                    ->when($ean !== '', fn ($query) => $query->where('ean', $ean))
                    ->whereNotNull('url')
                    ->update(['url' => null, 'updated_at' => now()]);

                $this->line(sprintf('  [%s] URL resetada para null em %d produto(s).', $tenantName, $affected));
            });
        }
    }
}
