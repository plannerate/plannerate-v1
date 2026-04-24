<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Editor;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GondolaAnalysis extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'gondola_id',
        'type',
        'data',
        'summary',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'summary' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento com Gondola
     */
    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para análises recentes (últimas 24h)
     */
    public function scopeRecent($query)
    {
        return $query->where('analyzed_at', '>=', now()->subHours(24));
    }

    /**
     * Obtém a análise ABC mais recente da gôndola
     */
    public static function getLatestAbcAnalysis(string $gondolaId): ?self
    {
        return static::ofType('abc')
            ->where('gondola_id', $gondolaId)
            ->latest('analyzed_at')
            ->first();
    }

    /**
     * Obtém a análise de Target Stock mais recente da gôndola
     */
    public static function getLatestStockAnalysis(string $gondolaId): ?self
    {
        return static::ofType('stock')
            ->where('gondola_id', $gondolaId)
            ->latest('analyzed_at')
            ->first();
    }

    /**
     * Verifica se a análise está desatualizada (mais de 24h)
     */
    public function isOutdated(): bool
    {
        return $this->analyzed_at->lt(now()->subHours(24));
    }

    /**
     * Retorna a classificação de um produto específico (para ABC)
     */
    public function getProductClassification(string $productEan): ?string
    {
        if ($this->type !== 'abc' || ! isset($this->data['results'])) {
            return null;
        }

        $product = collect($this->data['results'])
            ->firstWhere('ean', $productEan);

        return $product['classificacao'] ?? null;
    }

    /**
     * Retorna os dados de um produto específico (para ABC)
     */
    public function getAbcProductData(string $productEan): ?array
    {
        if ($this->type !== 'abc' || ! isset($this->data['results'])) {
            return null;
        }

        return collect($this->data['results'])
            ->firstWhere('ean', $productEan);
    }

    /**
     * Retorna o status de estoque de um produto (para Stock)
     */
    public function getProductStockStatus(string $productEan): ?array
    {
        if ($this->type !== 'stock' || ! isset($this->data['results'])) {
            return null;
        }

        return collect($this->data['results'])
            ->firstWhere('ean', $productEan);
    }

    /**
     * Retorna dados do resumo (summary)
     */
    public function getSummaryData(): array
    {
        return $this->summary ?? [];
    }

    /**
     * Retorna total de produtos na análise
     */
    public function getProductsCount(): int
    {
        return count($this->data['results'] ?? []);
    }

    /**
     * Retorna análise formatada para o frontend
     */
    public function toFormattedArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'summary' => $this->summary,
            'analyzed_at' => $this->analyzed_at,
            'is_outdated' => $this->isOutdated(),
            'products_count' => $this->getProductsCount(),
        ];
    }

    /**
     * Retorna análise ABC formatada com informações específicas
     */
    public function toAbcFormattedArray(): array
    {
        if ($this->type !== 'abc') {
            return $this->toFormattedArray();
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'results' => $this->data['results'] ?? [],
            'filters' => $this->data['filters'] ?? [],
            'weights' => $this->data['weights'] ?? [],
            'cuts' => $this->data['cuts'] ?? [],
            'summary' => [
                'total_products' => $this->summary['total_products'] ?? 0,
                'class_a_count' => $this->summary['class_a_count'] ?? 0,
                'class_b_count' => $this->summary['class_b_count'] ?? 0,
                'class_c_count' => $this->summary['class_c_count'] ?? 0,
            ],
            'analyzed_at' => $this->analyzed_at,
            'is_outdated' => $this->isOutdated(),
        ];
    }

    /**
     * Retorna análise de Target Stock formatada com informações específicas
     */
    public function toStockFormattedArray(): array
    {
        if ($this->type !== 'stock') {
            return $this->toFormattedArray();
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'results' => $this->data['results'] ?? [],
            'filters' => $this->data['filters'] ?? [],
            'parameters' => $this->data['parameters'] ?? [],
            'summary' => [
                'total_products' => $this->summary['total_products'] ?? 0,
                'total_target_stock' => $this->summary['total_target_stock'] ?? 0,
                'total_current_stock' => $this->summary['total_current_stock'] ?? 0,
                'products_above_target' => $this->summary['products_above_target'] ?? 0,
                'products_below_target' => $this->summary['products_below_target'] ?? 0,
            ],
            'analyzed_at' => $this->analyzed_at,
            'is_outdated' => $this->isOutdated(),
        ];
    }

    public function slugTo(): bool|string
    {
        return false; // Não precisa de slug
    }
}
