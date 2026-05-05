<?php

namespace App\Services\Files\Imports\Categories;

use App\Models\Category;
use App\Models\EanReference;
use App\Services\Files\Imports\Connections\CategoryImportConnection;
use App\Services\Files\Imports\Connections\EanReferenceByEanConnection;
use App\Services\Files\Imports\Connections\PlanogramCategoryLeafConnection;
use App\Services\Files\Imports\Connections\ProductCategoryByEanConnection;
use App\Services\Files\Imports\ImportExecutionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryHierarchyImportService
{
    /**
     * @var array<int, string>
     */
    private const LEVEL_COLUMNS = [
        1 => 'segmento_varejista',
        2 => 'departamento',
        3 => 'subdepartamento',
        4 => 'categoria',
        5 => 'subcategoria',
        6 => 'segmento',
        7 => 'subsegmento',
        8 => 'atributo',
    ];

    /**
     * @var array<int, CategoryImportConnection>
     */
    private array $connections;

    /**
     * @param  array<int, CategoryImportConnection>|null  $connections
     */
    public function __construct(?array $connections = null)
    {
        $this->connections = $connections ?? [
            new EanReferenceByEanConnection,
            new ProductCategoryByEanConnection,
            new PlanogramCategoryLeafConnection,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importRows(string $tenantId, ?string $userId, array $rows): ImportExecutionResult
    {
        $result = new ImportExecutionResult;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $normalized = $this->normalizeRow($row);
            $result->rowsProcessed++;

            if (($normalized['ean'] ?? '') === '') {
                $result->addError("Linha {$rowNumber}: coluna ean obrigatoria.");

                continue;
            }

            if (($normalized['segmento_varejista'] ?? '') === '') {
                $result->addError("Linha {$rowNumber}: coluna segmento_varejista obrigatoria.");

                continue;
            }

            if (! $this->isHierarchyContinuous($normalized)) {
                $result->addError("Linha {$rowNumber}: niveis intermediarios vazios nao sao permitidos.");

                continue;
            }

            $leafCategory = $this->resolveHierarchy($tenantId, $userId, $normalized, $result);
            if (! $leafCategory instanceof Category) {
                continue;
            }

            foreach ($this->connections as $connection) {
                $connection->connect($tenantId, $userId, $leafCategory, $normalized, $result);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = Str::of((string) $key)
                ->trim()
                ->lower()
                ->ascii()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();
            $normalizedValue = trim((string) ($value ?? ''));
            $normalized[$normalizedKey] = $normalizedValue;
        }

        if (isset($normalized['ean'])) {
            $normalized['ean'] = preg_replace('/\D+/', '', $normalized['ean']) ?? '';
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function isHierarchyContinuous(array $row): bool
    {
        $foundEmpty = false;

        foreach (self::LEVEL_COLUMNS as $column) {
            $hasValue = ($row[$column] ?? '') !== '';

            if (! $hasValue) {
                $foundEmpty = true;

                continue;
            }

            if ($foundEmpty) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveHierarchy(
        string $tenantId,
        ?string $userId,
        array $row,
        ImportExecutionResult $result
    ): ?Category {
        $referenceCategoryId = $this->resolveReferenceCategoryId((string) ($row['ean'] ?? ''));
        $parentId = null;
        $pathNames = [];
        $leafCategory = null;
        $leafPosition = $this->resolveLeafPosition($row);

        foreach (self::LEVEL_COLUMNS as $position => $column) {
            $name = $row[$column] ?? '';
            if ($name === '') {
                break;
            }

            $pathNames[] = $name;

            $category = Category::query()
                ->where('tenant_id', $tenantId)
                ->where('category_id', $parentId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();

            if (! $category instanceof Category) {
                $category = $this->createCategory(
                    tenantId: $tenantId,
                    userId: $userId,
                    parentId: $parentId,
                    name: $name,
                    levelName: $column,
                    position: $position,
                    pathNames: $pathNames,
                    forcedCategoryId: $position === $leafPosition ? $referenceCategoryId : null,
                );

                $result->categoriesCreated++;
            } else {
                $updates = [];

                $expectedPath = implode(' > ', $pathNames);
                if ((string) $category->full_path !== $expectedPath) {
                    $updates['full_path'] = $expectedPath;
                }

                if ((int) $category->hierarchy_position !== $position) {
                    $updates['hierarchy_position'] = $position;
                }

                if ((string) $category->nivel !== (string) $position) {
                    $updates['nivel'] = (string) $position;
                }

                if (! empty($updates)) {
                    $category->update($updates);
                    $result->categoriesUpdated++;
                }
            }

            $parentId = $category->id;
            $leafCategory = $category;
        }

        return $leafCategory;
    }

    private function resolveReferenceCategoryId(string $ean): ?string
    {
        $normalizedEan = EanReference::normalizeEan($ean);
        if ($normalizedEan === '') {
            return null;
        }

        $reference = EanReference::query()
            ->select(['category_id'])
            ->where('ean', $normalizedEan)
            ->first();

        if (! $reference instanceof EanReference || ! is_string($reference->category_id)) {
            return null;
        }

        $categoryId = trim($reference->category_id);

        return $categoryId !== '' ? $categoryId : null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveLeafPosition(array $row): int
    {
        $leafPosition = 1;

        foreach (self::LEVEL_COLUMNS as $position => $column) {
            if (($row[$column] ?? '') !== '') {
                $leafPosition = $position;
            }
        }

        return $leafPosition;
    }

    /**
     * @param  array<int, string>  $pathNames
     */
    private function createCategory(
        string $tenantId,
        ?string $userId,
        ?string $parentId,
        string $name,
        string $levelName,
        int $position,
        array $pathNames,
        ?string $forcedCategoryId = null,
    ): Category {
        $payload = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'category_id' => $parentId,
            'name' => $name,
            'level_name' => $levelName,
            'status' => 'importer',
            'nivel' => (string) $position,
            'hierarchy_position' => $position,
            'full_path' => implode(' > ', $pathNames),
            'hierarchy_path' => $pathNames,
            'is_placeholder' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (is_string($forcedCategoryId) && $forcedCategoryId !== '') {
            $existingWithForcedId = Category::query()->whereKey($forcedCategoryId)->first();
            if ($existingWithForcedId instanceof Category) {
                return $existingWithForcedId;
            }

            $connectionName = Category::query()->getModel()->getConnectionName();
            $table = Category::query()->getModel()->getTable();

            DB::connection($connectionName)->table($table)->insert([
                ...$payload,
                'id' => $forcedCategoryId,
            ]);

            return Category::query()->whereKey($forcedCategoryId)->firstOrFail();
        }

        return Category::query()->create($payload);
    }
}
