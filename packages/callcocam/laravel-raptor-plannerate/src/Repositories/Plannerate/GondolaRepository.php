<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Repositories\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository para operações de acesso a dados de Gondolas
 */
class GondolaRepository
{
    private const REPO = 'GondolaRepository';

    /**
     * Busca uma gôndola por ID
     */
    public function find(string $gondolaId): ?Gondola
    {
        try {
            return Gondola::find($gondolaId);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'gondola_id' => $gondolaId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Busca uma gôndola por ID (lança exceção se não encontrar)
     */
    public function findOrFail(string $gondolaId): Gondola
    {
        try {
            return Gondola::findOrFail($gondolaId);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'findOrFail',
                'gondola_id' => $gondolaId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza uma gôndola
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $gondolaId, array $data): int
    {
        try {
            return DB::connection(config('database.default'))->table('gondolas')
                ->where('id', $gondolaId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'gondola_id' => $gondolaId,
                'connection' => config('database.default'),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
