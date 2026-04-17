<?php

namespace App\Console\Commands;

use App\Concerns\BelongsToConnection;
use App\Models\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Console\Command;

class SetProductUrlNull extends Command
{
    use BelongsToConnection;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-product-url-null {--client= : Client slug or ID to filter products} {--ean= : EAN code of the product to process} {--dry-run : Do not update the database, only show the products that would be updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seta o campo url dos products como null.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ean = $this->option('ean');
        $dryRun = $this->option('dry-run');

        // Busca clientes
        if ($clientSlugOrId = $this->option('client')) {
            $client = Client::where('slug', $clientSlugOrId)
                ->orWhere('id', $clientSlugOrId)
                ->first();

            if (! $client) {
                $this->error("Cliente não encontrado: {$clientSlugOrId}");
                return 1;
            }

            $clients = collect([$client]);
        } else {
            $clients = Client::query()->where('status', 'published')->get();
            $this->info("Processando produtos para " . count($clients) . " clientes publicados");
        }

        foreach ($clients as $client) {
            try {
                // Verifica se o client tem database configurado
                if (empty($client->database)) {
                    $this->warn("Cliente {$client->name} ({$client->slug}) não possui database configurado. Pulando...");
                    continue;
                }

                $this->setupClientConnection($client);
                $this->info("Processando client: {$client->name} ({$client->slug})");

                // Monta query base
                $query = Product::on($this->getClientConnection())
                    ->whereNotNull('url'); // Sempre busca produtos com URL não nulo

                // Filtro por EAN se fornecido
                if ($ean) {
                    $query->where('ean', $ean);
                }

                // Conta produtos que seriam atualizados
                $count = $query->count();

                if ($dryRun) {
                    $this->info("  [DRY RUN] {$count} produtos com url não nulo seriam atualizados.");
                    if ($ean) {
                        $products = $query->get();

                        $this->info("    - {$products->count()} produtos com url não nulo seriam atualizados.");
                    }
                    continue;
                }

                // Atualiza produtos
                if ($count > 0) {
                    $updated = $query->update(['url' => null]);
                    $this->info("  ✓ {$updated} produtos atualizados com url = null.");
                } else {
                    $this->info("  Nenhum produto para atualizar.");
                }
            } catch (\Exception $e) {
                $this->error("  Erro ao processar cliente {$client->name}: {$e->getMessage()}");
                // Continua para o próximo cliente
                continue;
            }
        }

        return 0;
    }
}
