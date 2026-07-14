<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Product;

/**
 * Metadados para traduzir um diff de layout em algo legível: shelf_id → (módulo, prateleira)
 * e product_id → (nome, EAN, imagem).
 *
 * Existe para o serviço de diff não fazer query nenhuma: ele compara estruturas puras e o
 * contexto é montado uma vez, fora dele. Isso mantém o diff testável sem banco.
 */
final class LayoutDiffContext
{
    /**
     * @param  array<string, array{module: int, shelf: int}>  $shelfPositions  shelf_id => posição
     * @param  array<string, array{name: string, ean: ?string, image_url: ?string}>  $products
     */
    public function __construct(
        private readonly array $shelfPositions = [],
        private readonly array $products = [],
    ) {}

    /**
     * Monta o contexto a partir da gôndola (posições) e dos produtos envolvidos no diff.
     *
     * `module` é 1-based (o que o usuário vê); `shelf` usa o ordering físico da prateleira.
     *
     * @param  iterable<Product>  $products
     */
    public static function fromGondola(Gondola $gondola, iterable $products): self
    {
        $gondola->loadMissing('sections.shelves');

        $shelfPositions = [];

        foreach ($gondola->sections->sortBy('ordering')->values() as $moduleIndex => $section) {
            foreach ($section->shelves as $shelf) {
                $shelfPositions[(string) $shelf->id] = [
                    'module' => $moduleIndex + 1,
                    'shelf' => (int) ($shelf->ordering ?? 0),
                ];
            }
        }

        $productMap = [];

        foreach ($products as $product) {
            $productMap[(string) $product->id] = [
                'name' => (string) ($product->name ?? ''),
                'ean' => $product->ean !== null ? (string) $product->ean : null,
                'image_url' => $product->image_url !== null ? (string) $product->image_url : null,
            ];
        }

        return new self($shelfPositions, $productMap);
    }

    /** @return array{module: int, shelf: int} */
    public function positionOf(string $shelfId): array
    {
        return $this->shelfPositions[$shelfId] ?? ['module' => 0, 'shelf' => 0];
    }

    public function productName(string $productId): string
    {
        return $this->products[$productId]['name'] ?? $productId;
    }

    public function productEan(string $productId): ?string
    {
        return $this->products[$productId]['ean'] ?? null;
    }

    public function productImageUrl(string $productId): ?string
    {
        return $this->products[$productId]['image_url'] ?? null;
    }
}
