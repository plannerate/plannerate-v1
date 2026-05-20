<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;

#[Signature('categories:sync-sortiment-attributes {--tenant= : ID do tenant específico} {--preview : Apenas mostra o que seria feito}')]
#[Description('Lê as categorias e atualiza sortiment_attribute e sortiment_attribute_levels nos produtos com base nos níveis departamento → subcategoria')]
class SyncCategorySortimentAttributesCommand extends Command
{
    /**
     * Posições de hierarquia a incluir no sortimento (departamento, subdepartamento, categoria, subcategoria).
     *
     * @var array<int, int>
     */
    private const SORTIMENT_POSITIONS = [2, 3, 4, 5];

    /**
     * Nome de nível padrão por posição (fallback quando category.level_name está vazio).
     *
     * @var array<int, string>
     */
    private const LEVEL_NAME_BY_POSITION = [
        2 => 'departamento',
        3 => 'subdepartamento',
        4 => 'categoria',
        5 => 'subcategoria',
    ];

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $preview = (bool) $this->option('preview');

        if ($preview) {
            $this->info('MODO PREVIEW — Nenhuma alteração será gravada.');
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $preview);
        }

        $this->info('Sincronização de sortiment_attribute concluída.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        $tenantId = $this->option('tenant');

        if (is_string($tenantId) && $tenantId !== '') {
            return Tenant::query()
                ->where('status', 'active')
                ->whereKey($tenantId)
                ->get(['id', 'name', 'database']);
        }

        $allTenants = Tenant::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'database']);

        if ($allTenants->isEmpty()) {
            return $allTenants;
        }

        $options = $allTenants->mapWithKeys(
            fn (Tenant $tenant): array => [(string) $tenant->id => $tenant->name]
        )->all();

        $selectedIds = multiselect(
            label: 'Selecione os tenants para processar:',
            options: $options,
            default: array_keys($options),
            hint: 'Pressione espaço para selecionar/deselecionar. Enter para confirmar.',
        );

        return $allTenants->whereIn('id', $selectedIds)->values();
    }

    private function processTenant(Tenant $tenant, bool $preview): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database'))
            ? trim((string) $tenant->getAttribute('database'))
            : '';

        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->warn(sprintf('Tenant %s sem database configurado; ignorado.', $tenant->id));

            return;
        }

        $process = function () use ($connection, $preview, $tenant): void {
            $this->syncTenantProducts($connection, (string) $tenant->id, $tenant->name ?? $tenant->id, $preview);
        };

        if ($shouldSwitchTenantContext) {
            $tenant->execute($process);

            return;
        }

        $process();
    }

    private function syncTenantProducts(string $connection, string $tenantId, mixed $tenantName, bool $preview): void
    {
        $updated = 0;
        $skipped = 0;

        Product::on($connection)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('category_id')
            ->with(['category'])
            ->chunkById(200, function (Collection $products) use ($preview, &$updated, &$skipped): void {
                foreach ($products as $product) {
                    $result = $this->buildProductSyncData($product->category);

                    if ($result === null) {
                        $skipped++;

                        continue;
                    }

                    $sortimentAttribute = $result['sortiment_attribute'];
                    $sortimentAttributeLevels = $result['sortiment_attribute_levels'];

                    if (
                        $product->sortiment_attribute === $sortimentAttribute
                        && $product->sortiment_attribute_levels === $sortimentAttributeLevels
                    ) {
                        $skipped++;

                        continue;
                    }

                    if (! $preview) {
                        $product->updateQuietly([
                            'sortiment_attribute' => $sortimentAttribute,
                            'sortiment_attribute_levels' => $sortimentAttributeLevels,
                        ]);
                    }

                    $updated++;
                }
            });

        $this->line(sprintf(
            '%s: %d produto(s) atualizado(s), %d ignorado(s).',
            $tenantName,
            $updated,
            $skipped,
        ));
    }

    /**
     * Calcula sortiment_attribute e sortiment_attribute_levels a partir da hierarquia da categoria.
     *
     * Inclui os nós nas posições 2 (departamento), 3 (subdepartamento), 4 (categoria) e 5 (subcategoria)
     * quando presentes na hierarquia.
     *
     * @return array{sortiment_attribute: string, sortiment_attribute_levels: string}|null
     */
    public function buildProductSyncData(?Category $category): ?array
    {
        if (! $category instanceof Category) {
            return null;
        }

        $hierarchy = $category->getFullHierarchy();

        if ($hierarchy->isEmpty()) {
            return null;
        }

        $names = [];
        $levelKeys = [];

        foreach ($hierarchy as $node) {
            $position = (int) $node->hierarchy_position;

            if (! in_array($position, self::SORTIMENT_POSITIONS, true)) {
                continue;
            }

            $rawLevelName = (string) ($node->level_name ?? self::LEVEL_NAME_BY_POSITION[$position] ?? '');
            $levelKey = $this->normalizeLevelKey($rawLevelName);

            if ($levelKey === '') {
                continue;
            }

            $names[] = (string) $node->name;
            $levelKeys[] = $levelKey;
        }

        if ($names === []) {
            return null;
        }

        $sortimentAttribute = implode(' | ', $names);

        return [
            'sortiment_attribute' => $sortimentAttribute,
            'sortiment_attribute_levels' => implode(',', $levelKeys),
        ];
    }

    /**
     * Normaliza um nome de nível para chave de sortimento (mesmo algoritmo do frontend).
     */
    private function normalizeLevelKey(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
