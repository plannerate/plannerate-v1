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
 * Repository para operações de acesso a dados de Sections (Seções/Módulos)
 */
class SectionRepository
{
    private const REPO = 'SectionRepository';

    /**
     * Verifica se uma section existe
     */
    public function exists(string $sectionId): bool
    {
        try {
            return DB::connection(config('database.default'))->table('sections')->where('id', $sectionId)->exists();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'exists',
                'section_id' => $sectionId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Busca uma section por ID
     */
    public function find(string $sectionId): ?object
    {
        try {
            return DB::connection(config('database.default'))->table('sections')->where('id', $sectionId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'section_id' => $sectionId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cria uma nova section
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): bool
    {
        try {
            DB::connection(config('database.default'))->table('sections')->insert($data);

            return true;
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'create',
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Atualiza uma section
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $sectionId, array $data): int
    {
        try {
            return DB::connection(config('database.default'))->table('sections')
                ->where('id', $sectionId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'section_id' => $sectionId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Busca todas as sections de uma gôndola (não deletadas)
     *
     * @return array<int, object>
     */
    public function findByGondolaId(string $gondolaId): array
    {
        try {
            return DB::connection(config('database.default'))->table('sections')
                ->where('gondola_id', $gondolaId)
                ->whereNull('deleted_at')
                ->orderBy('ordering', 'asc')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'findByGondolaId',
                'gondola_id' => $gondolaId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza múltiplas sections em lote
     *
     * @param  array<int, array{id: string, ordering: int}>  $sections  Array com id e ordering
     */
    public function updateBatch(array $sections): void
    {
        try {
            foreach ($sections as $section) {
                DB::connection(config('database.default'))->table('sections')
                    ->where('id', $section['id'])
                    ->update([
                        'ordering' => $section['ordering'],
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
