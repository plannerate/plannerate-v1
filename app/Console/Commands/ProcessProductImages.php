<?php

namespace App\Console\Commands;

use App\Jobs\DOProcessProductImageJob;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

class ProcessProductImages extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-product-images {--ean= : EAN code of the product to process} {--tenant=* : Tenant ID(s) to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process product images for products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Tenant|null $tenant */
        $tenant = CurrentTenantModel::current();
        if (! $tenant instanceof Tenant) {
            $this->warn('Nenhum tenant atual encontrado para este ciclo.');

            return self::FAILURE;
        }

        $ean = trim((string) $this->option('ean'));
        if ($ean !== '') {
            $this->info("Filtering by EAN: {$ean}");
        }

        $this->info("Tenant atual: {$tenant->name}");

        $query = Product::query()
            ->when($ean !== '', fn ($q) => $q->where('ean', $ean))
            ->when($ean === '', fn ($q) => $q->whereNull('url'))
            ->whereNotNull('ean');

        $totalToDispatch = (clone $query)->count();

        if ($totalToDispatch === 0) {
            $this->warn('Nenhum produto elegivel para enfileirar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Total elegivel: {$totalToDispatch} produto(s)");
        $progressBar = $this->output->createProgressBar($totalToDispatch);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->setMessage('Iniciando enfileiramento...');
        $progressBar->start();

        $query
            ->select(['id'])
            ->chunkById(1000, function ($products) use ($progressBar, &$dispatchedJobs): void {
                foreach ($products as $product) {
                    DOProcessProductImageJob::dispatch($product->id);
                    $dispatchedJobs++;
                    $progressBar->setMessage("Enfileirados: {$dispatchedJobs}");
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Tenant {$tenant->name}: {$dispatchedJobs} job(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
