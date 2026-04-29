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
 * Repository para operações de acesso a dados de Layers (Camadas de produtos)
 */
class LayerRepository
{
    use UsesPlannerateTenantDatabase;

    private const REPO = 'LayerRepository';

    public function findByProductId(string $productId): ?object
    {
        try {
            return $this->plannerateTenantTable('layers')->where('product_id', $productId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'findByProductId',
                'product_id' => $productId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function find(string $layerId): ?object
    {
        try {
            return $this->plannerateTenantTable('layers')->where('id', $layerId)->first();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'find',
                'layer_id' => $layerId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function create(array $data): bool
    {
        try {
            return $this->plannerateTenantTable('layers')->insert($data);
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

    public function update(string $layerId, array $data): int
    {
        try {
            return $this->plannerateTenantTable('layers')
                ->where('id', $layerId)
                ->update($data);
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'update',
                'layer_id' => $layerId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function delete(string $layerId): int
    {
        try {
            return $this->plannerateTenantTable('layers')->where('id', $layerId)->delete();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'delete',
                'layer_id' => $layerId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function countBySegmentId(string $segmentId): int
    {
        try {
            return $this->plannerateTenantTable('layers')->where('segment_id', $segmentId)->count();
        } catch (\Throwable $e) {
            Log::error('Plannerate repository failed', [
                'repository' => self::REPO,
                'method' => 'countBySegmentId',
                'segment_id' => $segmentId,
                'connection' => $this->plannerateTenantConnectionName(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
