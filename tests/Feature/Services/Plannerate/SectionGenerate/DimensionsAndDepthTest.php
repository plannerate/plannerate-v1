<?php

use App\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use App\DTOs\Plannerate\AutoGenerate\ShelfLayoutDTO;
use App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ShelfLayoutDTO depth validation', function () {
    
    it('validates product depth against shelf depth', function () {
        $product = Product::factory()->create([
            'width' => 10,
            'height' => 20,
            'depth' => 50, // Profundidade maior que a shelf
        ]);

        $rankedProduct = new RankedProductDTO(
            product: $product,
            abcClass: 'A',
            score: 100,
            salesTotal: 1000,
            margin: 500,
            subcategoryId: '01test000000000000000000001',
        );
        $rankedProduct->setFacings(2);

        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 30,
            availableWidth: 100,
            depth: 40, // Profundidade da shelf
        );

        // Produto não deve caber (depth 50 > 40)
        $result = $shelf->addProduct($rankedProduct);
        
        expect($result)->toBeFalse()
            ->and($shelf->products)->toBeEmpty();
    });

    it('allows product that fits in depth', function () {
        $product = Product::factory()->create([
            'width' => 10,
            'height' => 20,
            'depth' => 30, // Profundidade menor que a shelf
        ]);

        $rankedProduct = new RankedProductDTO(
            product: $product,
            abcClass: 'A',
            score: 100,
            salesTotal: 1000,
            margin: 500,
            subcategoryId: '01test000000000000000000001',
        );
        $rankedProduct->setFacings(2);

        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 30,
            availableWidth: 100,
            depth: 40,
        );

        // Produto deve caber (depth 30 <= 40)
        $result = $shelf->addProduct($rankedProduct);
        
        expect($result)->toBeTrue()
            ->and($shelf->products)->toHaveCount(1);
    });

    it('ignores depth validation when product depth is zero', function () {
        $product = Product::factory()->create([
            'width' => 10,
            'height' => 20,
            'depth' => 0, // Sem informação de profundidade
        ]);

        $rankedProduct = new RankedProductDTO(
            product: $product,
            abcClass: 'A',
            score: 100,
            salesTotal: 1000,
            margin: 500,
            subcategoryId: '01test000000000000000000001',
        );
        $rankedProduct->setFacings(2);

        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 30,
            availableWidth: 100,
            depth: 40,
        );

        // Produto deve caber (depth = 0 ignora validação)
        $result = $shelf->addProduct($rankedProduct);
        
        expect($result)->toBeTrue()
            ->and($shelf->products)->toHaveCount(1);
    });
});

describe('SectionAllocationItemDTO dimensions', function () {
    
    it('stores product dimensions correctly', function () {
        $item = new SectionAllocationItemDTO(
            shelfId: '01shelf00000000000000000001',
            productId: '01prod000000000000000000001',
            facings: 3,
            productWidth: 15.5,
            productDepth: 25.0,
            productHeight: 30.0,
        );

        expect($item->productWidth)->toBe(15.5)
            ->and($item->productDepth)->toBe(25.0)
            ->and($item->productHeight)->toBe(30.0)
            ->and($item->facings)->toBe(3);
    });

    it('creates from array with dimensions', function () {
        $data = [
            'shelf_id' => '01shelf00000000000000000001',
            'product_id' => '01prod000000000000000000001',
            'facings' => 2,
            'product_width' => 10.0,
            'product_depth' => 20.0,
            'product_height' => 25.0,
        ];

        $item = SectionAllocationItemDTO::fromArray($data);

        expect($item->shelfId)->toBe('01shelf00000000000000000001')
            ->and($item->productId)->toBe('01prod000000000000000001')
            ->and($item->facings)->toBe(2)
            ->and($item->productWidth)->toBe(10.0)
            ->and($item->productDepth)->toBe(20.0)
            ->and($item->productHeight)->toBe(25.0);
    });

    it('defaults dimensions to zero when not provided', function () {
        $data = [
            'shelf_id' => '01shelf00000000000000000001',
            'product_id' => '01prod000000000000000000001',
            'facings' => 1,
        ];

        $item = SectionAllocationItemDTO::fromArray($data);

        expect($item->productWidth)->toBe(0.0)
            ->and($item->productDepth)->toBe(0.0)
            ->and($item->productHeight)->toBe(0.0);
    });
});
