<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyCategoriesCommand extends Command
{
    protected $signature = 'import:source-categories
        {client? : Client ID or slug}
        {--dry-run : Show what would be imported}
        {--limit= : Limit products to process per client}';

    protected $aliases = ['import:legacy-categories'];

    protected $description = 'Importa categorias da base de origem e relaciona aos produtos locais por EAN (via products.category_id)';

    private $legacy;

    private $client;

    private $clientDb;

    private array $categoryCache = []; // Cache de categorias por nome

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            $this->error('❌ No clients found');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $this->importClientCategories($client);
        }

        $this->newLine();
        $this->info('✅ All category imports completed!');

        return self::SUCCESS;
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('✅ Connected to source database');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Connection failed: '.$e->getMessage());

            return false;
        }
    }

    private function getClients()
    {
        $query = DB::table('clients')->where('status', 'published');

        if ($filter = $this->argument('client')) {
            $query->where(fn ($q) => $q->where('id', $filter)->orWhere('slug', $filter));
        }

        return $query->get();
    }

    private function importClientCategories(object $client): void
    {
        $this->newLine();
        $this->info("🔄 Client: {$client->name}");

        if (! $this->setupClientDatabase($client)) {
            return;
        }

        $this->client = $client;
        $this->categoryCache = []; // Reset cache for each client

        // Buscar categorias de primeiro nivel (sem parent) na base de origem
        $query = $this->legacy->table('categories')
            ->whereNull('category_id')
            ->orderBy('id'); // Necessário para chunk

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $totalRelations = (clone $query)->count();
        $this->info("  📊 Found {$totalRelations} products with categories in source database");

        if ($this->option('dry-run')) {
            $this->showDryRunStats($query);

            return;
        }

        $processed = 0;
        $updated = 0;
        $notFound = 0;
        $categoryCreated = 0;

        $query->chunk(100, function ($categories) use (&$processed, &$updated, &$notFound, &$categoryCreated) {
            foreach ($categories as $category) {
                $processed++;

                $this->info("  📂 Processing root category [{$processed}]: {$category->name} (Nível: {$category->nivel})");

                // 1. Cria/atualiza a categoria de primeiro nível no banco local
                $localCategory = $this->findOrCreateCategory($category, $categoryCreated);

                if (! $localCategory) {
                    continue;
                }

                // 2. Atualiza os produtos que pertencem a essa categoria
                $this->updateProductCategory($category, $updated, $notFound);

                // 3. Processa categorias filhas recursivamente
                $this->processChildrenCategories($category->id, $categoryCreated, $updated, $notFound, 1);
            }

            $this->line("  ✨ Progress: {$processed} root categories | {$categoryCreated} total created | {$updated} products updated | {$notFound} not found");
        });

        $this->newLine();
        $this->info("  ✅ Completed for {$client->name}:");
        $this->info("     • Products processed: {$processed}");
        $this->info("     • Products updated: {$updated}");
        $this->info("     • Products not found locally: {$notFound}");
        $this->info("     • Categories created: {$categoryCreated}");
    }

    private function showDryRunStats($query): void
    {
        $categories = $query->get();

        $this->line('  📊 Dry run stats:');
        $this->line("     • Root categories (level 1): {$categories->count()}");

        // Contar todas as categorias filhas recursivamente
        $totalChildren = 0;
        foreach ($categories as $category) {
            $totalChildren += $this->legacy->table('categories')
                ->where('category_id', $category->id)
                ->count();
        }

        $this->line("     • Child categories: {$totalChildren}");
        $this->line('     • Total categories: '.($categories->count() + $totalChildren));

        // Mostrar algumas categorias de exemplo
        $this->newLine();
        $this->line('  📋 Sample root categories:');
        $categories->take(5)->each(function ($category) {
            $this->line("     - {$category->name} (ID: {$category->id}, Nível: {$category->nivel})");
        });
    }

    private function findOrCreateCategory(object $categoryData, int &$categoryCreated, bool $showMessage = true): ?object
    {
        try {
            // 1. Verifica se ja existe pelo ID original da base de origem
            $existing = $this->clientDb->table('categories')
                ->where('id', $categoryData->id)
                ->first();

            if ($existing) {
                // Atualiza categoria existente
                $this->clientDb->table('categories')
                    ->where('id', $categoryData->id)
                    ->update([
                        'category_id' => $categoryData->category_id ?? null,
                        'name' => $categoryData->name,
                        'nivel' => $categoryData->nivel,
                        'hierarchy_position' => $categoryData->hierarchy_position,
                        'level_name' => $categoryData->level_name,
                        'full_path' => $categoryData->full_path,
                        'hierarchy_path' => $categoryData->hierarchy_path,
                        'is_placeholder' => $categoryData->is_placeholder ?? 0,
                        'description' => $categoryData->description,
                        'codigo' => $categoryData->codigo,
                        'updated_at' => now(),
                    ]);

                if ($showMessage) {
                    $this->line("     ↻ Updated: {$categoryData->name}");
                }

                return $existing;
            }

            // 2. Gera slug único baseado na hierarquia
            $slug = $this->generateUniqueSlug($categoryData);

            // 3. Insere nova categoria
            $data = [
                'id' => $categoryData->id, // Mantem o mesmo ID da base de origem
                'tenant_id' => $this->client->tenant_id ?? null,
                'category_id' => $categoryData->category_id ?? null, // Parent ID
                'name' => $categoryData->name,
                'slug' => $slug,
                'nivel' => $categoryData->nivel,
                'hierarchy_position' => $categoryData->hierarchy_position,
                'level_name' => $categoryData->level_name,
                'full_path' => $categoryData->full_path,
                'hierarchy_path' => $categoryData->hierarchy_path,
                'is_placeholder' => $categoryData->is_placeholder ?? 0,
                'description' => $categoryData->description,
                'codigo' => $categoryData->codigo,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->clientDb->table('categories')->insert($data);
            $categoryCreated++;

            if ($showMessage) {
                $this->line("     ✓ Created: {$categoryData->name} (slug: {$slug})");
            }

            // Retorna a categoria recém criada
            return $this->clientDb->table('categories')
                ->where('id', $categoryData->id)
                ->first();

        } catch (\Exception $e) {
            $this->warn("  ⚠️  Failed to create/update category '{$categoryData->name}': ".$e->getMessage());

            return null;
        }
    }

    /**
     * Gera um slug único considerando a hierarquia da categoria
     */
    private function generateUniqueSlug(object $categoryData): string
    {
        $baseSlug = Str::slug($categoryData->name);

        // Verifica se o slug já existe para este tenant
        $exists = $this->clientDb->table('categories')
            ->where('tenant_id', $this->client->tenant_id ?? null)
            ->where('slug', $baseSlug)
            ->exists();

        if (! $exists) {
            return $baseSlug;
        }

        // Se já existe, tenta incluir o parent na slug
        if ($categoryData->category_id) {
            $parent = $this->clientDb->table('categories')
                ->where('id', $categoryData->category_id)
                ->first();

            if ($parent) {
                $slugWithParent = Str::slug($parent->name.'-'.$categoryData->name);

                // Verifica se esse slug já existe
                $existsWithParent = $this->clientDb->table('categories')
                    ->where('tenant_id', $this->client->tenant_id ?? null)
                    ->where('slug', $slugWithParent)
                    ->exists();

                if (! $existsWithParent) {
                    return $slugWithParent;
                }
            }
        }

        // Se ainda não conseguiu slug único, adiciona o nível e um sufixo incremental
        $counter = 1;
        do {
            $uniqueSlug = $baseSlug.'-'.$categoryData->nivel.'-'.$counter;
            $exists = $this->clientDb->table('categories')
                ->where('tenant_id', $this->client->tenant_id ?? null)
                ->where('slug', $uniqueSlug)
                ->exists();
            $counter++;
        } while ($exists);

        return $uniqueSlug;
    }

    private function processChildrenCategories(string $parentCategoryId, int &$categoryCreated, int &$updated, int &$notFound, int $level = 1): void
    {
        // Busca categorias filhas na base de origem
        $children = $this->legacy->table('categories')
            ->where('category_id', $parentCategoryId)
            ->get();

        if ($children->isEmpty()) {
            return;
        }

        $indent = str_repeat('  ', $level);
        $this->line("{$indent}└─ Processing {$children->count()} child categories (level ".($level + 1).')');

        foreach ($children as $child) {
            // Cria/atualiza categoria filha
            $localChild = $this->findOrCreateCategory($child, $categoryCreated, false);

            if (! $localChild) {
                continue;
            }

            // Atualiza produtos dessa categoria
            $productsBefore = $updated;
            $this->updateProductCategory($child, $updated, $notFound);
            $productsUpdated = $updated - $productsBefore;

            if ($productsUpdated > 0) {
                $this->line("{$indent}   ├─ {$child->name}: {$productsUpdated} products linked");
            }

            // Processa filhos recursivamente
            $this->processChildrenCategories($child->id, $categoryCreated, $updated, $notFound, $level + 1);
        }
    }

    private function updateProductCategory(object $category, int &$updated, int &$notFound): void
    {
        try {
            // Busca produtos na base de origem que tem essa categoria
            $products = $this->legacy->table('products')
                ->where('category_id', $category->id)
                ->whereNotNull('ean')
                ->get();

            if ($products->isEmpty()) {
                return;
            }

            $linked = 0;
            foreach ($products as $productRelation) {
                // Busca o produto local pelo EAN
                $product = $this->clientDb->table('products')
                    ->where('ean', $productRelation->ean)
                    ->first();

                if (! $product) {
                    $notFound++;

                    continue;
                }

                // Atualiza o category_id do produto (usa o mesmo ID da categoria de origem)
                if ($product->category_id !== $category->id) {
                    $this->clientDb->table('products')
                        ->where('id', $product->id)
                        ->update(['category_id' => $category->id]);
                    $updated++;
                    $linked++;
                }
            }

            if ($linked > 0) {
                $this->line("     → {$linked} products linked to '{$category->name}'");
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Failed to update products for category '{$category->name}': ".$e->getMessage());
        }
    }

    private function setupClientDatabase(object $client): bool
    {
        if (empty($client->database)) {
            $this->warn("  ⚠️  No database configured for client {$client->name}.");

            return false;
        }

        $dbName = $client->database;

        $this->info("  📍 Using database: {$dbName}");

        // Setup connection
        $defaultConfig = config('database.connections.'.config('database.default'));
        Config::set('database.connections.client_db', array_merge($defaultConfig, [
            'database' => $dbName,
        ]));

        DB::purge('client_db');

        try {
            $this->clientDb = DB::connection('client_db');
            $this->clientDb->getPdo();

            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Cannot connect to {$dbName}: ".$e->getMessage());

            return false;
        }
    }
}
