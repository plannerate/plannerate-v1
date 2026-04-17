<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Import\Person;

use Callcocam\LaravelRaptor\Enums\RoleStatus;
use Callcocam\LaravelRaptor\Support\Import\Columns\Sheet;
use Callcocam\LaravelRaptor\Support\Import\Contracts\ImportServiceInterface;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkflowStepTemplateImportService implements ImportServiceInterface
{
    protected int $successfulRows = 0;

    protected int $failedRows = 0;

    protected array $errors = [];

    protected array $failedRowsData = [];

    protected array $completedRows = [];

    protected array $context = [];

    protected ?Sheet $sheet = null;

    protected ?string $connectionForImport = null;

    protected ?string $flowStepTemplatesTable = null;

    protected ?string $flowsTable = null;

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

        $worksheet = $spreadsheet->getSheetByName('Tabela de workflowsteptemplates');
        if (! $worksheet) {
            return $this->notify('Erro na Importação', 'Aba "Tabela de workflowsteptemplates" não encontrada.', 'error');
        }

        $rows = $this->readSheetRows($worksheet);
        if (empty($rows)) {
            return $this->notify('Importação concluída', 'Nenhum registro encontrado na planilha.', 'success');
        }

        $flowId = data_get($this->context, 'flow_id')
            ?? data_get($options, 'flow_id')
            ?? $this->resolveFlowId();

        $roleMap = $this->resolveRoles($rows, $tenantId);
        $templates = $this->buildTemplates($rows, $roleMap, $tenantId, $userId, $flowId);

        $flowConnection = $this->flowConnectionName();

        return $flowConnection
            ? DB::connection($flowConnection)->transaction(fn () => $this->persistTemplates($templates, $tenantId))
            : DB::transaction(fn () => $this->persistTemplates($templates, $tenantId));
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

    protected function flowConnectionName(): ?string
    {
        return $this->connectionForImport ?? app(FlowStepTemplate::class)->getConnectionName();
    }

    protected function flowTable(string $tableName): Builder
    {
        $flowConnection = $this->flowConnectionName();

        return $flowConnection
            ? DB::connection($flowConnection)->table($tableName)
            : DB::table($tableName);
    }

    protected function tenantScope(Builder $query, ?string $tenantId): Builder
    {
        return $tenantId !== null
            ? $query->where('tenant_id', $tenantId)
            : $query->whereNull('tenant_id');
    }

    protected function readSheetRows(Worksheet $ws): array
    {
        $rows = [];
        for ($r = 2; $r <= $ws->getHighestRow(); $r++) {
            $name = $this->cell($ws, $r, 1);
            if (! $name || trim($name) === '') {
                continue;
            }
            $rows[] = [
                'name' => trim($name),
                'description' => $this->cell($ws, $r, 2),
                'instructions' => $this->cell($ws, $r, 3),
                'category' => $this->cell($ws, $r, 4),
                'role_name' => $this->cell($ws, $r, 5),
                'prev_name' => $this->cell($ws, $r, 6),
                'next_name' => $this->cell($ws, $r, 7),
                'order' => (int) $this->cell($ws, $r, 8),
                'duration' => (int) $this->cell($ws, $r, 9),
                'color' => $this->cell($ws, $r, 10) ?: 'blue',
                'required' => $this->toBool($this->cell($ws, $r, 11)),
                'active' => $this->toBool($this->cell($ws, $r, 12)),
                '_row' => $r,
            ];
        }

        return $rows;
    }

    protected function cell(Worksheet $ws, int $row, int $col): ?string
    {
        $val = $ws->getCell(Coordinate::stringFromColumnIndex($col).$row)->getValue();

        return ($val !== null && $val !== '') ? (string) $val : null;
    }

    protected function toBool(mixed $v): bool
    {
        if ($v === null || is_bool($v)) {
            return $v ?? true;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    protected function resolveRoles(array $rows, ?string $tenantId): array
    {
        $names = collect($rows)
            ->pluck('role_name')
            ->filter(fn ($n) => $n && trim($n) !== '')
            ->map(fn ($n) => trim($n))
            ->unique()
            ->values()
            ->all();

        if (empty($names)) {
            return [];
        }

        $rolesTable = config('raptor.tables.roles', 'roles');
        $existingRoles = collect(
            $this->tenantScope(
                $this->table($rolesTable)->select(['id', 'name', 'slug']),
                $tenantId
            )->get()->map(fn ($role) => (array) $role)->all()
        );

        $map = [];
        foreach ($names as $name) {
            $matchedRoleId = $this->findBestRoleIdByNameOrSlug($name, $existingRoles);

            if ($matchedRoleId) {
                $map[$name] = $matchedRoleId;
                continue;
            }

            $id = (string) Str::ulid();
            $slug = Str::slug($name).'-'.Str::limit($tenantId ?? 'global', 8);

            $this->table($rolesTable)->insert([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'tenant_id' => $tenantId,
                'status' => RoleStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $map[$name] = $id;
            $existingRoles->push([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
            ]);
        }

        return $map;
    }

    /**
     * Constrói templates em memória com relacionamentos previous/next resolvidos.
     */
    protected function buildTemplates(array $rows, array $roleMap, ?string $tenantId, ?string $userId, ?string $flowId): Collection
    {
        $templates = collect($rows)->map(fn ($row) => [
            'id' => (string) Str::ulid(),
            'flow_id' => $flowId,
            'name' => $row['name'],
            'slug' => Str::slug($row['name']).($tenantId ? '-'.Str::limit($tenantId, 8) : ''),
            'description' => $row['description'],
            'instructions' => $row['instructions'],
            'category' => $row['category'],
            'default_role_id' => $roleMap[trim($row['role_name'] ?? '')] ?? null,
            'suggested_order' => $row['order'],
            'estimated_duration_days' => $row['duration'] ?: 1,
            'color' => $row['color'],
            'is_required_by_default' => $row['required'],
            'is_active' => $row['active'],
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            '_prev' => trim($row['prev_name'] ?? ''),
            '_next' => trim($row['next_name'] ?? ''),
            '_row' => $row['_row'],
        ]);

        $nameToId = $templates->pluck('id', 'name');

        return $templates->map(function ($t) use ($nameToId) {
            $t['template_previous_step_id'] = $t['_prev'] ? ($nameToId[$t['_prev']] ?? null) : null;
            $t['template_next_step_id'] = $t['_next'] ? ($nameToId[$t['_next']] ?? null) : null;

            return $t;
        });
    }

    protected function persistTemplates(Collection $templates, ?string $tenantId): array
    {
        $names = $templates->pluck('name')->all();
        $existing = $this->tenantScope(
            $this->flowTable($this->flowStepTemplatesTable())->whereIn('name', $names),
            $tenantId
        )->pluck('id', 'name')->all();

        $nameToIds = $templates->pluck('id', 'name')->all();
        $now = now();

        // Primeira fase: garante todos os registros sem relacionamentos previous/next.
        foreach ($templates as $t) {
            $existingId = $existing[$t['name']] ?? null;
            $resolvedId = $existingId ?: $t['id'];
            $nameToIds[$t['name']] = $resolvedId;

            $payload = $this->payload($t, null, null, $now);

            try {
                if ($existingId) {
                    $this->flowTable($this->flowStepTemplatesTable())->where('id', $existingId)->update($payload);
                } else {
                    $payload['id'] = $resolvedId;
                    $payload['created_at'] = $now;
                    $this->flowTable($this->flowStepTemplatesTable())->insert($payload);
                }

                $this->successfulRows++;
                $this->completedRows[] = ['row' => $t['_row'], 'data' => $t];
            } catch (\Throwable $e) {
                $this->failedRows++;
                $this->errors[] = ['row' => $t['_row'], 'message' => $e->getMessage()];
                $this->failedRowsData[] = ['row' => $t['_row'], 'data' => $t, 'message' => $e->getMessage()];
            }
        }

        // Segunda fase: atualiza relacionamentos após todos os IDs existirem.
        foreach ($templates as $t) {
            if (! isset($nameToIds[$t['name']])) {
                continue;
            }

            $currentId = $nameToIds[$t['name']];
            $prevId = $this->resolveStepId($t['_prev'], $existing, $nameToIds);
            $nextId = $this->resolveStepId($t['_next'], $existing, $nameToIds);

            try {
                $this->flowTable($this->flowStepTemplatesTable())
                    ->where('id', $currentId)
                    ->update([
                        'template_previous_step_id' => $prevId,
                        'template_next_step_id' => $nextId,
                        'updated_at' => $now,
                    ]);
            } catch (\Throwable $e) {
                $this->failedRows++;
                $this->errors[] = ['row' => $t['_row'], 'message' => $e->getMessage()];
                $this->failedRowsData[] = ['row' => $t['_row'], 'data' => $t, 'message' => $e->getMessage()];
            }
        }

        $total = $this->successfulRows + $this->failedRows;
        $msg = "Importação: {$total} registro(s) ({$this->successfulRows} ok, {$this->failedRows} erro).";

        return $this->notify('Importação concluída', $msg, $this->failedRows ? 'warning' : 'success');
    }

    protected function resolveStepId(string $name, array $existing, array $newIds): ?string
    {
        if (! $name) {
            return null;
        }

        return $existing[$name] ?? $newIds[$name] ?? null;
    }

    protected function payload(array $t, ?string $prevId, ?string $nextId, $now): array
    {
        return [
            'user_id' => $t['user_id'],
            'tenant_id' => $t['tenant_id'],
            'flow_id' => $t['flow_id'] ?? null,
            'template_previous_step_id' => $prevId,
            'template_next_step_id' => $nextId,
            'name' => $t['name'],
            'slug' => $t['slug'],
            'default_role_id' => $t['default_role_id'],
            'description' => $t['description'],
            'instructions' => $t['instructions'],
            'category' => $t['category'],
            'suggested_order' => $t['suggested_order'],
            'estimated_duration_days' => $t['estimated_duration_days'],
            'is_required_by_default' => $t['is_required_by_default'],
            'is_active' => $t['is_active'],
            'color' => $t['color'],
            'updated_at' => $now,
        ];
    }

    public function processRow(array $row, int $rowNumber): void {}

    protected function flowStepTemplatesTable(): string
    {
        if ($this->flowStepTemplatesTable === null) {
            $this->flowStepTemplatesTable = app(FlowStepTemplate::class)->getTable();
        }

        return $this->flowStepTemplatesTable;
    }

    protected function flowTableName(): string
    {
        if ($this->flowsTable === null) {
            $this->flowsTable = app(Flow::class)->getTable();
        }

        return $this->flowsTable;
    }

    protected function resolveFlowId(): ?string
    {
        return $this->flowTable($this->flowTableName())
            ->where('slug', 'planogramas')
            ->value('id')
            ?? $this->flowTable($this->flowTableName())
                ->orderByDesc('created_at')
                ->value('id');
    }

    protected function findBestRoleIdByNameOrSlug(string $name, Collection $existingRoles): ?string
    {
        $normalizedName = Str::lower(trim($name));
        $targetSlug = $this->normalizeRoleSlug($name);

        $exactByName = $existingRoles->first(function (array $role) use ($normalizedName) {
            return Str::lower(trim((string) ($role['name'] ?? ''))) === $normalizedName;
        });
        if ($exactByName) {
            return data_get($exactByName, 'id');
        }

        $exactBySlug = $existingRoles->first(function (array $role) use ($targetSlug) {
            return $this->normalizeRoleSlug((string) ($role['slug'] ?? '')) === $targetSlug;
        });
        if ($exactBySlug) {
            return data_get($exactBySlug, 'id');
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestSimilarity = 0.0;

        foreach ($existingRoles as $role) {
            $candidateSlug = $this->normalizeRoleSlug((string) ($role['slug'] ?? $role['name'] ?? ''));
            if ($candidateSlug === '' || $targetSlug === '') {
                continue;
            }

            $distance = levenshtein($targetSlug, $candidateSlug);
            similar_text($targetSlug, $candidateSlug, $similarity);

            $isCandidate = $distance <= 2 || $similarity >= 85;
            if (! $isCandidate) {
                continue;
            }

            if ($distance < $bestDistance || ($distance === $bestDistance && $similarity > $bestSimilarity)) {
                $best = $role;
                $bestDistance = $distance;
                $bestSimilarity = $similarity;
            }
        }

        return data_get($best, 'id');
    }

    protected function normalizeRoleSlug(string $value): string
    {
        $slug = Str::slug(trim($value));

        if ($slug === '') {
            return '';
        }

        // Remove sufixos técnicos comuns (ex: "-01k3abcd") para comparar similaridade.
        return (string) preg_replace('/-[a-z0-9]{6,12}$/', '', $slug);
    }

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
