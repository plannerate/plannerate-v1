<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Import\Person;

use Callcocam\LaravelRaptor\Support\Import\Columns\Sheet;
use Callcocam\LaravelRaptor\Support\Import\Contracts\ImportServiceInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CategoryImportService implements ImportServiceInterface
{
    protected int $successfulRows = 0;

    protected int $failedRows = 0;

    protected array $errors = [];

    protected array $failedRowsData = [];

    protected array $completedRows = [];

    protected array $context = [];

    protected ?Sheet $sheet = null;

    protected ?string $connectionForImport = null;

    /**
     * Cache em memória para categorias já processadas.
     * Formato: ['nivel_name|parent_id' => category_id]
     */
    protected array $categoryCache = [];

    /**
     * Hierarquia de níveis de categoria (ordem é importante)
     */
    protected const HIERARCHY_LEVELS = [
        1 => 'Segmento varejista',
        2 => 'Departamento',
        3 => 'Subdepartamento',
        4 => 'Categoria',
        5 => 'Subcategoria',
        6 => 'Segmento',
        7 => 'Subsegmento',
        8 => 'Atributo',
    ];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array{notification: array{title: string, text: string, type: string}}
     */
    public function import(string|UploadedFile|null $file, array $options = []): array
    {
        if (! $file) {
            return $this->notify('Erro na Importação', 'Nenhum arquivo foi enviado.', 'error');
        }

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! $path || ! is_readable($path)) {
            return $this->notify('Erro na Importação', 'Arquivo não encontrado ou não legível.', 'error');
        }

        $tenantId = $this->context['tenant_id'] ?? $options['tenant_id'] ?? config('app.current_tenant_id');
        $userId = $this->context['user_id'] ?? $options['user_id'] ?? null;
        $clientId = $this->context['client_id'] ?? $options['client_id'] ?? null;
        $database = $options['database'] ?? $this->context['database'] ?? null;

        if ($database) {
            $this->setupTenantDatabase($database);
            $this->connectionForImport = 'tenant';
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return $this->notify('Erro na Importação', 'Erro ao carregar Excel: '.$e->getMessage(), 'error');
        }

        $worksheet = $spreadsheet->getSheetByName('Tabela mercadológico');
        if (! $worksheet) {
            return $this->notify('Erro na Importação', 'Aba "Tabela mercadológico" não encontrada.', 'error');
        }

        $rows = $this->readSheetRows($worksheet);
        if (empty($rows)) {
            return $this->notify('Importação concluída', 'Nenhum registro encontrado na planilha.', 'success');
        }

        return DB::transaction(fn () => $this->processCategories($rows, $tenantId, $userId, $clientId));
    }

    protected function notify(string $title, string $text, string $type = 'success'): array
    {
        return ['notification' => compact('title', 'text', 'type')];
    }

    protected function setupTenantDatabase(string $database): void
    {
        $config = config('database.connections.tenant');
        if (is_array($config)) {
            Config::set('database.connections.tenant.database', $database);
            DB::purge('tenant');
        }
    }

    protected function table(string $tableName): Builder
    {
        return $this->connectionForImport
            ? DB::connection($this->connectionForImport)->table($tableName)
            : DB::table($tableName);
    }

    protected function tenantScope(Builder $query, ?string $tenantId): Builder
    {
        return $tenantId !== null
            ? $query->where('tenant_id', $tenantId)
            : $query->whereNull('tenant_id');
    }

    /**
     * Lê todas as linhas da planilha
     */
    protected function readSheetRows(Worksheet $ws): array
    {
        $rows = [];
        for ($r = 2; $r <= $ws->getHighestRow(); $r++) {
            $ean = $this->cell($ws, $r, 1);
            if (! $ean || trim($ean) === '') {
                continue;
            }

            $rows[] = [
                'ean' => trim($ean),
                'segmento_varejista' => $this->cell($ws, $r, 2),
                'departamento' => $this->cell($ws, $r, 3),
                'subdepartamento' => $this->cell($ws, $r, 4),
                'categoria' => $this->cell($ws, $r, 5),
                'subcategoria' => $this->cell($ws, $r, 6),
                'segmento' => $this->cell($ws, $r, 7),
                'subsegmento' => $this->cell($ws, $r, 8),
                'atributo' => $this->cell($ws, $r, 9),
                '_row' => $r,
            ];
        }
        

        return $rows;
    }

    protected function cell(Worksheet $ws, int $row, int $col): ?string
    {
        $val = $ws->getCell(Coordinate::stringFromColumnIndex($col).$row)->getValue();

        return ($val !== null && $val !== '') ? trim((string) $val) : null;
    }

    /**
     * Processa todas as categorias e vincula produtos
     */
    protected function processCategories(array $rows, ?string $tenantId, ?string $userId, ?string $clientId): array
    {
        $this->categoryCache = [];
        $now = now();

        foreach ($rows as $row) {
            try {
                // Construir hierarquia de categorias progressivamente
                $hierarchyData = [
                    1 => $row['segmento_varejista'],
                    2 => $row['departamento'],
                    3 => $row['subdepartamento'],
                    4 => $row['categoria'],
                    5 => $row['subcategoria'],
                    6 => $row['segmento'],
                    7 => $row['subsegmento'],
                    8 => $row['atributo'],
                ];

                $parentId = null;
                $lastCategoryId = null;
                $hierarchyPath = [];

                // Processar cada nível da hierarquia
                foreach (self::HIERARCHY_LEVELS as $position => $levelName) {
                    $categoryName = $hierarchyData[$position];

                    // Parar se não houver valor para este nível
                    if (! $categoryName) {
                        break;
                    }

                    // Buscar ou criar categoria neste nível
                    $categoryId = $this->findOrCreateCategory(
                        $categoryName,
                        $levelName,
                        $position,
                        $parentId,
                        $hierarchyPath,
                        $tenantId,
                        $userId,
                        $now
                    );

                    $hierarchyPath[] = [
                        'id' => $categoryId,
                        'name' => $categoryName,
                        'level' => $levelName,
                        'position' => $position,
                    ];

                    $parentId = $categoryId;
                    $lastCategoryId = $categoryId;
                }

                // Atualizar produto com a categoria mais específica
                if ($lastCategoryId) {
                    $updated = $this->table('products')
                        ->where('ean', $row['ean']);

                    if ($clientId) {
                        $updated->where('client_id', $clientId);
                    }

                    if ($tenantId) {
                        $updated->where('tenant_id', $tenantId);
                    }

                    $updated->update([
                        'category_id' => $lastCategoryId,
                        'updated_at' => $now,
                    ]);
                }

                $this->successfulRows++;
                $this->completedRows[] = ['row' => $row['_row'], 'data' => $row];
            } catch (\Throwable $e) {
                $this->failedRows++;
                $this->errors[] = ['row' => $row['_row'], 'message' => $e->getMessage()];
                $this->failedRowsData[] = ['row' => $row['_row'], 'data' => $row, 'message' => $e->getMessage()];
            }
        }

        $total = $this->successfulRows + $this->failedRows;
        $msg = "Importação: {$total} produto(s) processado(s) ({$this->successfulRows} ok, {$this->failedRows} erro).";

        return $this->notify('Importação concluída', $msg, $this->failedRows ? 'warning' : 'success');
    }

    /**
     * Busca ou cria uma categoria em um nível específico da hierarquia
     */
    protected function findOrCreateCategory(
        string $name,
        string $levelName,
        int $position,
        ?string $parentId,
        array $hierarchyPath,
        ?string $tenantId,
        ?string $userId,
        $now
    ): string {
        // Criar chave de cache
        $cacheKey = $name.'|'.($parentId ?? 'root').'|'.$position;

        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        // Buscar categoria existente
        $query = $this->table('categories')
            ->where('name', $name)
            ->where('hierarchy_position', $position);

        if ($parentId) {
            $query->where('category_id', $parentId);
        } else {
            $query->whereNull('category_id');
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $existing = $query->first();

        if ($existing) {
            $this->categoryCache[$cacheKey] = $existing->id;

            return $existing->id;
        }

        // Criar nova categoria
        $categoryId = (string) Str::ulid();
        $slug = Str::slug($name).'-'.Str::slug($levelName).'-'.Str::random(6);
        $fullPath = collect($hierarchyPath)
            ->pluck('name')
            ->push($name)
            ->implode(' > ');

        $this->table('categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'category_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'level_name' => $levelName,
            'nivel' => $levelName,
            'hierarchy_position' => $position,
            'full_path' => $fullPath,
            'hierarchy_path' => json_encode(array_merge($hierarchyPath, [[
                'id' => $categoryId,
                'name' => $name,
                'level' => $levelName,
                'position' => $position,
            ]])),
            'status' => 'importer',
            'is_placeholder' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->categoryCache[$cacheKey] = $categoryId;

        return $categoryId;
    }

    public function processRow(array $row, int $rowNumber): void {}

    public function getSuccessfulRows(): int
    {
        return $this->successfulRows;
    }

    public function getFailedRows(): int
    {
        return $this->failedRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFailedRowsData(): array
    {
        return $this->failedRowsData;
    }

    public function getCompletedRows(): array
    {
        return $this->completedRows;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getSheet(): Sheet
    {
        return $this->sheet ?? throw new \RuntimeException('Sheet não configurada.');
    }

    public function setSheet(Sheet $sheet): static
    {
        $this->sheet = $sheet;

        return $this;
    }
}
