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
 * Repository para operações de acesso a dados de Segments (Segmentos)
 */
class SegmentRepository
{
    private const REPO = 'SegmentRepository';

    /**
     * Busca um segment por ID
     */
    public function find(string $segmentId): ?object
    {
        try {
            return DB::connection(config('database.default'))->table('segments')->where('id', $segmentId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'segment_id' => $segmentId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cria um novo segment
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): bool
    {
        try {
            return DB::connection(config('database.default'))->table('segments')->insert($data);
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

    /**
     * Atualiza um segment
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $segmentId, array $data): int
    {
        try {
            return DB::connection(config('database.default'))->table('segments')
                ->where('id', $segmentId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'segment_id' => $segmentId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Remove um segment
     */
    public function delete(string $segmentId): int
    {
        try {
            return DB::connection(config('database.default'))->table('segments')->where('id', $segmentId)->delete();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'delete',
                'segment_id' => $segmentId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
