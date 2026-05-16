<?php

namespace App\Services\AutoPlanogram;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fonte única de verdade para largura de produto no pipeline de auto-planograma.
 *
 * Centraliza a sanitização: width=0, negativo ou acima do threshold recebem o
 * fallback e geram warning para que o admin corrija o cadastro.
 */
final class ProductWidthResolver
{
    private const DEFAULT_WIDTH_CM = 10.0;

    private const MAX_PLAUSIBLE_WIDTH = 60.0;

    public function __construct(
        private float $defaultWidth = self::DEFAULT_WIDTH_CM,
        private float $maxPlausible = self::MAX_PLAUSIBLE_WIDTH,
    ) {}

    /**
     * Retorna a largura válida do produto em cm.
     * Registra warning se o valor original for suspeito.
     */
    public function resolve(mixed $product): float
    {
        $raw = isset($product->width) ? (float) $product->width : null;

        if ($raw === null) {
            return $this->defaultWidth;
        }

        if ($raw <= 0) {
            Log::warning('ProductWidthResolver: width inválido (zero ou negativo)', [
                'product_id' => $product->id,
                'product_name' => $product->name ?? '?',
                'width_raw' => $raw,
                'usando' => $this->defaultWidth,
            ]);

            return $this->defaultWidth;
        }

        if ($raw > $this->maxPlausible) {
            Log::warning('ProductWidthResolver: width suspeito (acima do threshold)', [
                'product_id' => $product->id,
                'product_name' => $product->name ?? '?',
                'width_raw' => $raw,
                'threshold' => $this->maxPlausible,
                'usando' => $this->defaultWidth,
            ]);

            return $this->defaultWidth;
        }

        return $raw;
    }

    /**
     * Versão em lote — retorna array<product_id, float> para uso no escalonamento.
     *
     * @param  Collection<int, mixed>  $products
     * @return array<string, float>
     */
    public function resolveAll(Collection $products): array
    {
        return $products->mapWithKeys(fn ($p) => [
            $p->id => $this->resolve($p),
        ])->all();
    }
}
