<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Repository para operações de acesso a dados de Shelves (Prateleiras)
 */
class ShelfRepository
{
    use UsesPlannerateTenantDatabase;

    private const REPO = 'ShelfRepository';

    public function exists(string $shelfId): bool
    {
        try {
            return $this->plannerateTenantTable('shelves')->where('id', $shelfId)->exists();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'exists',
                'shelf_id' => $shelfId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function find(string $shelfId): ?object
    {
        try {
            return $this->plannerateTenantTable('shelves')->where('id', $shelfId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'shelf_id' => $shelfId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function create(array $data): bool
    {
        try {
            return $this->plannerateTenantTable('shelves')->insert($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'create',
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function update(string $shelfId, array $data): int
    {
        try {
            return $this->plannerateTenantTable('shelves')
                ->where('id', $shelfId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'shelf_id' => $shelfId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function findBySectionId(string $sectionId): array
    {
        try {
            return $this->plannerateTenantTable('shelves')
                ->where('section_id', $sectionId)
                ->whereNull('deleted_at')
                ->orderBy('shelf_position', 'asc')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'findBySectionId',
                'section_id' => $sectionId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function updateBatch(array $shelves): void
    {
        try {
            foreach ($shelves as $shelf) {
                $this->plannerateTenantTable('shelves')
                    ->where('id', $shelf['id'])
                    ->update([
                        'ordering' => $shelf['ordering'],
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'updateBatch',
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
