<?php

namespace App\Console\Commands;

use App\Models\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ImportProductDimensionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'import:product-dimensions
        {--client= : Import only specific client}
        {--dry-run : Show what would be imported without executing}
        {--chunk=1000 : Number of rows per batch}
        {--server=mysql_legacy : Source server connection name}
    ';

    /**
     * The console command description.
     */
    protected $description = 'Busca dimensoes de produtos no servidor de origem e salva em todos os produtos dos clientes';

    /**
     * Mapeamento fixo de client_id => nome do banco de dados
     */
    protected const TENANT_DATABASES = [
        '01jym02qk8n1cwdq2hd5drpgsz' => 'plannerate_albert',
        '01k1xx8evcx6pygzkwax3wrpfm' => 'plannerate_franciosi',
        '01k7ez88kzrywyejk3xgvskyhj' => 'plannerate_bruda',
        '01k8vf7dsq3mxm15jxecf3r99s' => 'plannerate_michelon',
        '01kbr0jmjzk0n5vdjyxd3q3nd0' => 'plannerate_cooasgo',
        '01kh72a47xwdsnc228kbyvfveq' => 'plannerate_crespi',
    ];

    public function handle(): int
    {
        $this->info('🚀 Import Product Dimensions');
        $this->line('');

        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $serverName = $this->option('server');
        $clientFilter = $this->option('client');

        try {
            // Conectar ao servidor de origem
            if (! DB::connection($serverName)->getPdo()) {
                $this->error("❌ Nao conseguiu conectar ao servidor de origem ({$serverName})");

                return self::FAILURE;
            }

            $this->info('✅ Conectado ao servidor de origem');
        } catch (\Exception $e) {
            $this->error("❌ Erro ao conectar ao servidor de origem: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Buscar dimensoes do servidor de origem
        try {
            $legacyDimensions = $this->fetchLegacyDimensions($serverName);
            $this->info('📦 Encontradas '.count($legacyDimensions).' dimensoes no servidor de origem');
        } catch (\Exception $e) {
            $this->error("❌ Erro ao buscar dimensões: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (empty($legacyDimensions)) {
            $this->warn('⚠️  Nenhuma dimensao encontrada no servidor de origem');

            return self::FAILURE;
        }

        // Obter clientes
        $clients = $clientFilter
            ? Client::query()->where(fn ($q) => $q->where('id', $clientFilter)->orWhere('slug', $clientFilter))->get()
            : Client::all();

        $this->info('👥 Processando '.count($clients)." cliente(s)\n");

        $globalTotalPublished = 0;
        $globalTotalDraft = 0;
        $globalTotalSkipped = 0;
        $globalTotalProtected = 0;

        foreach ($clients as $client) {
            $this->line("→ Cliente: {$client->name} (ID: {$client->id})");

            // Resolver nome do banco: primeiro do cliente, depois do mapeamento
            $clientDbName = $client->database ?? self::TENANT_DATABASES[$client->id] ?? null;

            if (empty($clientDbName)) {
                $this->warn('  ⚠️  Cliente sem banco configurado e não está no mapeamento');

                continue;
            }

            try {
                // Configurar conexão tenant com o banco do cliente (mesmo padrão dos outros comandos)
                $defaultConfig = config('database.connections.'.config('database.default'));
                Config::set('database.connections.tenant', array_merge($defaultConfig, [
                    'database' => $clientDbName,
                ]));

                // Limpa cache de conexão e reconecta
                DB::purge('tenant');
                DB::connection('tenant')->getPdo();

                $this->line("  ✅ Banco de dados conectado: {$clientDbName}");
            } catch (\Exception $e) {
                $this->warn("  ⚠️  Banco não existe ou inacessível: {$e->getMessage()}");

                continue;
            }

            // Contadores por cliente
            $clientPublished = 0;
            $clientDraft = 0;
            $clientSkipped = 0;
            $clientProtected = 0;

            // Processar produtos em chunks (todos os produtos, não apenas published)
            Product::on('tenant')
                ->chunk($chunkSize, function ($products) use ($legacyDimensions, $dryRun, &$clientPublished, &$clientDraft, &$clientSkipped, &$clientProtected) {
                    $productsInActiveLayers = Layer::on('tenant')
                        ->whereIn('product_id', $products->pluck('id')->all())
                        ->pluck('product_id')
                        ->flip();

                    foreach ($products as $product) {
                        $dimensionKey = $product->ean ?? $product->id;
                        $isInActiveLayer = $productsInActiveLayers->has($product->id);

                        if (isset($legacyDimensions[$dimensionKey])) {
                            $legacy = $legacyDimensions[$dimensionKey];

                            // Pegar as dimensoes (da origem ou manter as atuais)
                            $width = $legacy['width'] ?? $product->width;
                            $height = $legacy['height'] ?? $product->height;
                            $depth = $legacy['depth'] ?? $product->depth;

                            // Validar: todas as três dimensões > 0 = Com dimensão; senão = Sem dimensão
                            $hasDimensions = $width > 0 && $height > 0 && $depth > 0;

                            if (! $hasDimensions && $isInActiveLayer) {
                                $clientProtected++;

                                continue;
                            }

                            if (! $dryRun) {
                                $product->update([
                                    'width' => $width,
                                    'height' => $height,
                                    'depth' => $depth,
                                    'weight' => $legacy['weight'] ?? $product->weight,
                                    'unit' => $legacy['unit'] ?? 'cm',
                                    'has_dimensions' => $hasDimensions,
                                ]);
                            }

                            if ($hasDimensions) {
                                $clientPublished++;
                            } else {
                                $clientDraft++;
                            }
                        } else {
                            // Produto sem dimensoes na origem - avaliar dimensoes atuais
                            $hasDimensions = $product->width > 0 && $product->height > 0 && $product->depth > 0;

                            if (! $hasDimensions && $isInActiveLayer) {
                                $clientProtected++;
                                $clientSkipped++;

                                continue;
                            }

                            if (! $dryRun) {
                                $product->update(['has_dimensions' => $hasDimensions]);
                            }

                            if ($hasDimensions) {
                                $clientPublished++;
                            } else {
                                $clientDraft++;
                            }

                            $clientSkipped++;
                        }
                    }
                });

            $this->info("  📊 Published: {$clientPublished} | Draft: {$clientDraft} | Sem dimensoes na origem: {$clientSkipped} | Protegidos por layer ativa: {$clientProtected}\n");

            $globalTotalPublished += $clientPublished;
            $globalTotalDraft += $clientDraft;
            $globalTotalSkipped += $clientSkipped;
            $globalTotalProtected += $clientProtected;
        }

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN ativado - nenhum dado foi alterado');
        }

        $this->info('✅ Importação concluída!');
        $this->line("   Total Com dimensão (has_dimensions=true): {$globalTotalPublished}");
        $this->line("   Total Sem dimensão (has_dimensions=false): {$globalTotalDraft}");
        $this->line("   Total sem dimensoes na origem: {$globalTotalSkipped}");
        $this->line("   Total protegidos por layer ativa: {$globalTotalProtected}");

        return self::SUCCESS;
    }

    /**
     * Busca dimensoes do servidor de origem usando EAN como chave
     * Procura na tabela 'dimensions' usando o campo 'ean'
     */
    private function fetchLegacyDimensions(string $serverName): array
    {
        $dimensions = [];

        // Buscar de tabela 'dimensions' usando EAN como chave
        try {
            $legacyDimensions = DB::connection($serverName)
                ->table('dimensions')
                ->select('ean', 'width', 'height', 'depth', 'weight', 'unit')
                ->where('ean', '!=', null)
                ->get();

            foreach ($legacyDimensions as $dim) {
                $key = $dim->ean;
                $dimensions[$key] = [
                    'width' => (float) ($dim->width ?? 0),
                    'height' => (float) ($dim->height ?? 0),
                    'depth' => (float) ($dim->depth ?? 0),
                    'weight' => (float) ($dim->weight ?? 0),
                    'unit' => $dim->unit ?? 'cm',
                ];
            }

            $this->line("  📋 Dimensões da tabela 'dimensions' (por EAN): ".count($legacyDimensions));
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Tabela 'dimensions' não encontrada ou erro: {$e->getMessage()}");
        }

        return $dimensions;
    }
}
