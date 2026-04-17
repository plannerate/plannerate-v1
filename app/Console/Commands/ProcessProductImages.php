<?php

namespace App\Console\Commands;

use App\Concerns\BelongsToConnection;
use App\Jobs\DOProcessProductImageJob;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Console\Command;

class ProcessProductImages extends Command
{
    use BelongsToConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-product-images {--ean= : EAN code of the product to process} {--client= : Client slug or ID to filter products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process product images (optionally filtered by client)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clients = [];

        // Se client não for especificado, busca todos com status published
        if ($clientSlugOrId = $this->option('client')) {
            $client = Client::where('slug', $clientSlugOrId)
                ->orWhere('id', $clientSlugOrId)
                ->first();

            if (!$client) {
                $this->error("Client '{$clientSlugOrId}' not found");
                return 1;
            }

            $clients = [$client];
        } else {
            $clients = Client::query()->where('status', 'published')->get();
            $this->info("Processing images for " . count($clients) . " published clients");
        }

        $totalProcessed = 0;

        foreach ($clients as $client) {
            $this->info("Processing products for client: {$client->name} ({$client->slug})");
            $this->setupClientConnection($client);
            $query = Product::on($this->getClientConnection());

            if ($ean = $this->option('ean')) {
                $query->where('ean', $ean);
                $this->info("  - Filtering by EAN: {$ean}");
            } else {
                $query->whereNull('url');
            }

            $products = $query->get();
            $this->info("  - Found {$products->count()} products to process");

            foreach ($products as $product) {
                DOProcessProductImageJob::dispatch(
                    $product->id,
                    $client->id,
                    $client->database
                );
            }

            $totalProcessed += $products->count();
            $this->info("  ✓ Dispatched {$products->count()} jobs for {$client->name}");
        }

        $this->info("Total: Dispatched {$totalProcessed} image processing jobs across " . count($clients) . " client(s)");

        return 0;
    }
}
