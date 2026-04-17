<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ImportLegacyProductCategoryCommand extends Command
{
    protected $signature = 'import:source-product-category
        {ean? : EAN do produto (opcional)}
        {client? : Client ID ou slug (opcional)}
        {--limit= : Limite de produtos quando EAN não for informado}
        {--dry-run : Apenas mostra o que seria atualizado}';

    protected $aliases = ['import:legacy-product-category'];

    protected $description = 'Sincroniza categorias, products.category_id e planograms.category_id via base de origem';

    private $legacy;

    private $clientDb;

    /** @var array<string, bool> */
    private array $localCategoryIds = [];

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $ean = $this->argument('ean') ? (string) $this->argument('ean') : null;

        if ($ean) {
            $legacy = $this->getLegacyProductWithCategory($ean);
            if ($legacy) {
                $this->info("✅ Origem encontrada: EAN {$ean} -> Categoria {$legacy->category_name} ({$legacy->category_id})");
            } else {
                $this->warn("⚠️ EAN {$ean} nao encontrado na base de origem. O comando ainda pode soft-delete no client se o produto nao tiver uso.");
            }
        } else {
            $this->info('🔎 EAN não informado: sincronizando categorias e reconciliando products/planograms em todos os clients filtrados.');
        }

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            $this->error('❌ Nenhum client encontrado para processar.');

            return self::FAILURE;
        }

        $totals = [
            'updated' => 0,
            'not_found_in_legacy' => 0,
            'missing_category_in_client' => 0,
            'no_local_product_for_ean' => 0,
            'invalid_categories_nullified' => 0,
            'products_scanned' => 0,
            'categories_created' => 0,
            'planograms_scanned' => 0,
            'planograms_updated' => 0,
            'soft_deleted' => 0,
            'kept_in_use' => 0,
            'errors' => 0,
        ];

        foreach ($clients as $client) {
            $this->processClient($client, $ean, $totals);
        }

        $this->newLine();
        $this->info('✅ Processamento concluído.');
        $this->line("  • Atualizados: {$totals['updated']}");
        $this->line("  • EAN nao encontrado na base de origem: {$totals['not_found_in_legacy']}");
        $this->line("  • Categoria inexistente no client: {$totals['missing_category_in_client']}");
        $this->line("  • Produto local não encontrado (modo EAN): {$totals['no_local_product_for_ean']}");
        $this->line("  • Categorias criadas: {$totals['categories_created']}");
        $this->line("  • category_id inválido limpo para null: {$totals['invalid_categories_nullified']}");
        $this->line("  • Produtos escaneados: {$totals['products_scanned']}");
        $this->line("  • Planogramas escaneados: {$totals['planograms_scanned']}");
        $this->line("  • Planogramas atualizados: {$totals['planograms_updated']}");
        $this->line("  • Soft deleted: {$totals['soft_deleted']}");
        $this->line("  • Mantidos por uso em layers/sales: {$totals['kept_in_use']}");
        $this->line("  • Erros de conexão/processamento: {$totals['errors']}");

        return self::SUCCESS;
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('✅ Conectado a base de origem.');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Falha ao conectar na base de origem: '.$e->getMessage());

            return false;
        }
    }

    private function getLegacyProductWithCategory(string $ean): ?object
    {
        return $this->legacy->table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select([
                'products.id as product_id',
                'products.ean',
                'products.category_id',
                'categories.name as category_name',
            ])
            ->where('products.ean', $ean)
            ->first();
    }

    private function getLegacyProductsByEans(array $eans): Collection
    {
        if (empty($eans)) {
            return collect();
        }

        return $this->legacy->table('products')
            ->select([
                'products.ean',
                'products.category_id',
            ])
            ->whereIn('products.ean', $eans)
            ->get()
            ->keyBy('ean');
    }

    private function getClients()
    {
        $query = DB::table('clients')->where('status', 'published');

        if ($filter = $this->argument('client')) {
            $query->where(fn ($q) => $q->where('id', $filter)->orWhere('slug', $filter));
        }

        return $query->get();
    }

    private function processClient(object $client, ?string $ean, array &$totals): void
    {
        $this->newLine();
        $this->info("🔄 Client: {$client->name}");

        if (! $this->setupClientDatabase($client)) {
            $totals['errors']++;

            return;
        }

        $createdCategories = $this->syncMissingCategoriesFromLegacy($client);
        $totals['categories_created'] += $createdCategories;

        if ($createdCategories > 0) {
            $this->line("  🏷️ Categorias criadas no client: {$createdCategories}");
        }

        $invalidCleaned = $this->sanitizeInvalidCategoryIds();
        $totals['invalid_categories_nullified'] += $invalidCleaned;

        if ($invalidCleaned > 0) {
            $this->line("  🧹 category_id inválido -> null: {$invalidCleaned}");
        }

        if ($ean) {
            $this->processSingleEanForClient($ean, $totals);
            $this->reconcilePlanogramsFromLegacy($client, $totals);

            return;
        }

        $this->reconcileProductsFromLegacy($totals);
        $this->reconcilePlanogramsFromLegacy($client, $totals);
    }

    private function processSingleEanForClient(string $ean, array &$totals): void
    {
        $localProduct = $this->clientDb->table('products')
            ->where('ean', $ean)
            ->whereNull('deleted_at')
            ->first();

        if (! $localProduct) {
            $totals['no_local_product_for_ean']++;
            $this->warn('  ⚠️ Produto não encontrado no banco do client para este EAN.');

            return;
        }

        $legacyProduct = $this->getLegacyProductWithCategory($ean);

        if (! $legacyProduct) {
            $totals['not_found_in_legacy']++;

            if ($this->canSoftDeleteProduct($localProduct->id)) {
                $this->softDeleteProduct($localProduct->id, $totals);
                $this->warn('  🗑️ EAN ausente na base de origem e sem uso em layers/sales: soft delete aplicado.');
            } else {
                $totals['kept_in_use']++;
                $this->warn('  ⚠️ EAN ausente na base de origem, mas produto esta em uso (layers/sales). Mantido.');
            }

            return;
        }

        $legacyCategoryId = $this->normalizeNullableId($legacyProduct->category_id ?? null);

        if ($legacyCategoryId !== null && ! $this->hasLocalCategory($legacyCategoryId)) {
            $totals['missing_category_in_client']++;
            $this->warn("  ⚠️ Categoria {$legacyProduct->category_id} nao existe no client. Rode import:source-categories primeiro.");

            return;
        }

        if ($this->normalizeNullableId($localProduct->category_id ?? null) === $legacyCategoryId) {
            $this->line('  ℹ️ Produto já está com a categoria correta.');

            return;
        }

        if ($this->option('dry-run')) {
            $totals['updated']++;
            $this->line("  🧪 Dry-run: atualizaria category_id de {$localProduct->category_id} para {$legacyCategoryId}");

            return;
        }

        $this->clientDb->table('products')
            ->where('id', $localProduct->id)
            ->update([
                'category_id' => $legacyCategoryId,
                'updated_at' => now(),
            ]);

        $totals['updated']++;
        $this->line("  ✅ Produto atualizado: category_id {$localProduct->category_id} -> {$legacyCategoryId}");
    }

    private function reconcileProductsFromLegacy(array &$totals): void
    {
        $query = $this->clientDb->table('products')
            ->select('id', 'ean', 'category_id')
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $query->chunk(200, function (Collection $products) use (&$totals) {
            $totals['products_scanned'] += $products->count();

            $eans = $products
                ->pluck('ean')
                ->filter(fn ($ean) => ! empty($ean))
                ->unique()
                ->values()
                ->all();

            $legacyByEan = $this->getLegacyProductsByEans($eans);

            foreach ($products as $product) {
                $currentCategoryId = $this->normalizeNullableId($product->category_id ?? null);

                if (empty($product->ean)) {
                    if ($currentCategoryId === null && $this->canSoftDeleteProduct($product->id)) {
                        $this->softDeleteProduct($product->id, $totals);
                    } elseif ($currentCategoryId === null) {
                        $totals['kept_in_use']++;
                    }

                    continue;
                }

                $legacyProduct = $legacyByEan->get($product->ean);

                if (! $legacyProduct) {
                    if ($currentCategoryId === null) {
                        $totals['not_found_in_legacy']++;

                        if ($this->canSoftDeleteProduct($product->id)) {
                            $this->softDeleteProduct($product->id, $totals);
                        } else {
                            $totals['kept_in_use']++;
                        }
                    }

                    continue;
                }

                $legacyCategoryId = $this->normalizeNullableId($legacyProduct->category_id ?? null);

                if ($legacyCategoryId !== null && ! $this->hasLocalCategory($legacyCategoryId)) {
                    $totals['missing_category_in_client']++;

                    continue;
                }

                if ($currentCategoryId === $legacyCategoryId) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $totals['updated']++;

                    continue;
                }

                $this->clientDb->table('products')
                    ->where('id', $product->id)
                    ->update([
                        'category_id' => $legacyCategoryId,
                        'updated_at' => now(),
                    ]);

                $totals['updated']++;
            }
        });
    }

    private function reconcilePlanogramsFromLegacy(object $client, array &$totals): void
    {
        $query = $this->clientDb->table('planograms')
            ->select('id', 'category_id')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->orderBy('id');

        $query->chunk(200, function (Collection $planograms) use ($client, &$totals) {
            $totals['planograms_scanned'] += $planograms->count();

            $legacyPlanograms = $this->legacy->table('planograms')
                ->select('id', 'category_id')
                ->where('client_id', $client->id)
                ->whereIn('id', $planograms->pluck('id')->values()->all())
                ->get()
                ->keyBy('id');

            foreach ($planograms as $planogram) {
                $legacyPlanogram = $legacyPlanograms->get($planogram->id);

                if (! $legacyPlanogram) {
                    continue;
                }

                $legacyCategoryId = $this->normalizeNullableId($legacyPlanogram->category_id ?? null);
                $currentCategoryId = $this->normalizeNullableId($planogram->category_id ?? null);

                if ($legacyCategoryId !== null && ! $this->hasLocalCategory($legacyCategoryId)) {
                    $totals['missing_category_in_client']++;

                    continue;
                }

                if ($legacyCategoryId === $currentCategoryId) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $totals['planograms_updated']++;

                    continue;
                }

                $this->clientDb->table('planograms')
                    ->where('id', $planogram->id)
                    ->update([
                        'category_id' => $legacyCategoryId,
                        'updated_at' => now(),
                    ]);

                $totals['planograms_updated']++;
            }
        });
    }

    private function syncMissingCategoriesFromLegacy(object $client): int
    {
        $this->localCategoryIds = $this->clientDb->table('categories')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->toArray();

        $created = 0;

        $this->legacy->table('categories')
            ->select([
                'id',
                'category_id',
                'name',
                'slug',
                'level_name',
                'codigo',
                'status',
                'description',
                'nivel',
                'hierarchy_position',
                'full_path',
                'hierarchy_path',
                'is_placeholder',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunk(500, function (Collection $categories) use ($client, &$created) {
                foreach ($categories as $category) {
                    $categoryId = (string) $category->id;

                    if ($categoryId === '' || isset($this->localCategoryIds[$categoryId])) {
                        continue;
                    }

                    $created++;

                    if ($this->option('dry-run')) {
                        $this->localCategoryIds[$categoryId] = true;

                        continue;
                    }

                    $this->clientDb->table('categories')->insert([
                        'id' => $categoryId,
                        'tenant_id' => $client->tenant_id,
                        'category_id' => $this->normalizeNullableId($category->category_id ?? null),
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'level_name' => $category->level_name,
                        'codigo' => $category->codigo,
                        'status' => $this->normalizeCategoryStatus($category->status ?? null),
                        'description' => $category->description,
                        'nivel' => $category->nivel,
                        'hierarchy_position' => $category->hierarchy_position,
                        'full_path' => $category->full_path,
                        'hierarchy_path' => $category->hierarchy_path,
                        'is_placeholder' => (bool) ($category->is_placeholder ?? false),
                        'created_at' => $category->created_at ?? now(),
                        'updated_at' => $category->updated_at ?? now(),
                    ]);

                    $this->localCategoryIds[$categoryId] = true;
                }
            });

        return $created;
    }

    private function hasLocalCategory(string $categoryId): bool
    {
        return isset($this->localCategoryIds[$categoryId]);
    }

    private function normalizeNullableId(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeCategoryStatus(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['draft', 'published', 'importer'], true)
            ? $normalized
            : 'published';
    }

    private function sanitizeInvalidCategoryIds(): int
    {
        $validCategoryIds = $this->clientDb->table('categories')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $invalidProductsQuery = $this->clientDb->table('products')
            ->whereNull('deleted_at')
            ->whereNotNull('category_id');

        if (! empty($validCategoryIds)) {
            $invalidProductsQuery->whereNotIn('category_id', $validCategoryIds);
        }

        $count = (clone $invalidProductsQuery)->count();

        if ($count === 0 || $this->option('dry-run')) {
            return $count;
        }

        $invalidProductsQuery->update([
            'category_id' => null,
            'updated_at' => now(),
        ]);

        return $count;
    }

    private function canSoftDeleteProduct(string $productId): bool
    {
        $inLayer = $this->clientDb->table('layers')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->exists();

        if ($inLayer) {
            return false;
        }

        $inSales = $this->clientDb->table('sales')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->exists();

        return ! $inSales;
    }

    private function softDeleteProduct(string $productId, array &$totals): void
    {
        if ($this->option('dry-run')) {
            $totals['soft_deleted']++;

            return;
        }

        $this->clientDb->table('products')
            ->where('id', $productId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        $totals['soft_deleted']++;
    }

    private function setupClientDatabase(object $client): bool
    {
        if (empty($client->database)) {
            $this->warn("  ⚠️ Banco não configurado para client {$client->name}.");

            return false;
        }

        $dbName = $client->database;

        $this->line("  📍 Banco: {$dbName}");

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
            $this->error("  ❌ Falha ao conectar no banco {$dbName}: {$e->getMessage()}");

            return false;
        }
    }
}
