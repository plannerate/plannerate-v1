<?php

namespace App\Jobs\Cleanup;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class RestoreSoldProductsJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array<int, string>  $productIds
     */
    public function __construct(
        public string $tenantId,
        public array $productIds,
        public string $tenantConnectionName,
        public bool $executeInTenantContext = true,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        if ($this->tenantId === '' || $this->productIds === []) {
            return;
        }

        $tenant = Tenant::query()->whereKey($this->tenantId)->first();
        if (! $tenant) {
            return;
        }

        $run = function (): void {
            $restorableIds = $this->resolveRestorableIds();

            $totalRestored = 0;

            foreach (array_chunk($restorableIds, 500) as $chunk) {
                $totalRestored += DB::connection($this->tenantConnectionName)
                    ->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->whereNotNull('deleted_at')
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            Log::info('Restauração de produtos com vendas concluída', [
                'tenant_id' => $this->tenantId,
                'total_restored' => $totalRestored,
            ]);
        };

        if ($this->executeInTenantContext) {
            $tenant->execute($run);

            return;
        }

        $run();
    }

    /**
     * Filtra os produtos a restaurar removendo os que colidiriam com o índice
     * único parcial `products_tenant_id_ean_unique` (WHERE deleted_at IS NULL):
     *
     *  - EAN que já possui um produto ativo → pular (restaurar criaria duplicado);
     *  - EAN repetido dentro do próprio conjunto → restaurar apenas um.
     *
     * Assim uma colisão de EAN não aborta o lote inteiro; os conflitos são
     * apenas logados para revisão manual, sem tocar em produtos ativos.
     *
     * @return array<int, string>
     */
    private function resolveRestorableIds(): array
    {
        $connection = DB::connection($this->tenantConnectionName);

        /** @var Collection<int, object{id: string, ean: ?string}> $candidates */
        $candidates = collect();

        foreach (array_chunk($this->productIds, 500) as $chunk) {
            $candidates = $candidates->merge(
                $connection->table('products')
                    ->where('tenant_id', $this->tenantId)
                    ->whereIn('id', $chunk)
                    ->whereNotNull('deleted_at')
                    ->get(['id', 'ean'])
            );
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        $eans = $candidates->pluck('ean')->filter(fn (?string $ean): bool => $ean !== null && $ean !== '')->unique();

        $activeEans = [];
        foreach ($eans->chunk(1000) as $eanChunk) {
            $connection->table('products')
                ->where('tenant_id', $this->tenantId)
                ->whereNull('deleted_at')
                ->whereIn('ean', $eanChunk->values()->all())
                ->pluck('ean')
                ->each(function (string $ean) use (&$activeEans): void {
                    $activeEans[$ean] = true;
                });
        }

        $restorableIds = [];
        $seenEan = [];
        $skippedActiveConflict = 0;

        foreach ($candidates as $candidate) {
            $ean = $candidate->ean;

            if ($ean !== null && $ean !== '') {
                if (isset($activeEans[$ean])) {
                    $skippedActiveConflict++;

                    continue;
                }

                if (isset($seenEan[$ean])) {
                    continue;
                }

                $seenEan[$ean] = true;
            }

            $restorableIds[] = (string) $candidate->id;
        }

        if ($skippedActiveConflict > 0) {
            Log::warning('Produtos não restaurados: EAN já ativo em outro produto', [
                'tenant_id' => $this->tenantId,
                'skipped' => $skippedActiveConflict,
                'candidates' => $candidates->count(),
            ]);
        }

        return $restorableIds;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'cleanup',
            'restore-products',
            "tenant:{$this->tenantId}",
        ];
    }
}
