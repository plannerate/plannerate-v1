<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository para operações de acesso a dados de Shelves (Prateleiras)
 */
class ShelfRepository
{
    private const REPO = 'ShelfRepository';

    public function exists(string $shelfId): bool
    {
        try {
            return DB::connection(config('database.default'))->table('shelves')->where('id', $shelfId)->exists();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'exists',
                'shelf_id' => $shelfId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function find(string $shelfId): ?object
    {
        try {
            return DB::connection(config('database.default'))->table('shelves')->where('id', $shelfId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'shelf_id' => $shelfId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function create(array $data): bool
    {
        try {
            return DB::connection(config('database.default'))->table('shelves')->insert($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'create',
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function update(string $shelfId, array $data): int
    {
        try {
            return DB::connection(config('database.default'))->table('shelves')
                ->where('id', $shelfId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'shelf_id' => $shelfId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function findBySectionId(string $sectionId): array
    {
        try {
            return DB::connection(config('database.default'))->table('shelves')
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
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function updateBatch(array $shelves): void
    {
        try {
            foreach ($shelves as $shelf) {
                DB::connection(config('database.default'))->table('shelves')
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
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
