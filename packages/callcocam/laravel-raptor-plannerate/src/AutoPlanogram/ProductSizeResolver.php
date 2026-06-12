<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram;

final class ProductSizeResolver
{
    public function resolve(mixed $product): float
    {
        $content = trim((string) ($product->packaging_content ?? ''));

        if ($content !== '' && $content !== '0') {
            return $this->parseSize($content);
        }

        return (float) ($product->weight ?? 0.0);
    }

    public function parseSize(string $content): float
    {
        preg_match('/[\d.]+/', $content, $matches);
        $value = (float) ($matches[0] ?? 0);
        $unit = strtolower((string) preg_replace('/[\d. ]+/', '', $content));

        return match ($unit) {
            'ml', 'g' => $value / 1000,
            'l', 'kg' => $value,
            default => $value,
        };
    }
}
