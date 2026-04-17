<?php

// TESTES UNITÁRIOS PUROS - SEM DATABASE
// Usa objetos simples ao invés de Eloquent models
use App\DTOs\Plannerate\AutoGenerate\ShelfLayoutDTO;
use App\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO;

describe('SectionAllocationItemDTO dimensions', function () {
    it('creates allocation item with dimensions', function () {
        $item = new SectionAllocationItemDTO(
            productId: '01HQXYZ123',
            facings: 3,
            shelfId: '01HQABC456',
            productWidth: 10.5,
            productDepth: 15.2,
            productHeight: 25.0
        );

        expect($item->productWidth)->toBe(10.5);
        expect($item->productDepth)->toBe(15.2);
        expect($item->productHeight)->toBe(25.0);
        expect($item->facings)->toBe(3);
    });

    it('defaults to zero when dimensions not provided', function () {
        $item = new SectionAllocationItemDTO(
            productId: '01HQXYZ123',
            facings: 2,
            shelfId: '01HQABC456'
            // productWidth, productDepth, productHeight usam defaults 0.0
        );

        expect($item->productWidth)->toBe(0.0);
        expect($item->productDepth)->toBe(0.0);
        expect($item->productHeight)->toBe(0.0);
    });

    it('handles zero dimensions', function () {
        $item = new SectionAllocationItemDTO(
            productId: '01HQXYZ123',
            facings: 1,
            shelfId: '01HQABC456',
            productWidth: 0.0,
            productDepth: 0.0,
            productHeight: 0.0
        );

        expect($item->productWidth)->toBe(0.0);
        expect($item->productDepth)->toBe(0.0);
        expect($item->productHeight)->toBe(0.0);
    });
});

describe('ShelfLayoutDTO depth validation', function () {
    it('rejects product deeper than shelf', function () {
        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 40.0,
            availableWidth: 100.0,
            depth: 30.0, // prateleira tem 30cm de profundidade
        );

        // Criar objeto que simula RankedProductDTO com produto aninhado
        $mockProduct = (object) [
            'id' => '01HQ123',
            'name' => 'Produto Fundo',
            'width' => 10.0,
            'depth' => 35.0, // produto tem 35cm - NÃO CABE!
            'height' => 20.0,
        ];
        
        $rankedProduct = (object) [
            'product' => $mockProduct,
            'facings' => 2,
        ];

        $result = $shelf->addProduct($rankedProduct);

        expect($result)->toBeFalse();
        expect($shelf->products)->toBeEmpty();
    });

    it('accepts product with valid depth', function () {
        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 40.0,
            availableWidth: 100.0,
            depth: 40.0, // prateleira tem 40cm de profundidade
        );

        // Criar objeto que simula RankedProductDTO
        $mockProduct = (object) [
            'id' => '01HQ456',
            'name' => 'Produto OK',
            'width' => 10.0,
            'depth' => 25.0, // produto tem 25cm - CABE!
            'height' => 20.0,
        ];
        
        $rankedProduct = (object) [
            'product' => $mockProduct,
            'facings' => 2,
        ];

        $result = $shelf->addProduct($rankedProduct);

        expect($result)->toBeTrue();
        expect($shelf->products)->toHaveCount(1);
    });

    it('handles products without depth specified', function () {
        $shelf = new ShelfLayoutDTO(
            id: '01shelf00000000000000000001',
            shelfIndex: 0,
            height: 40.0,
            availableWidth: 100.0,
            depth: 40.0,
        );

        // Produto sem profundidade definida (depth = 0)
        $mockProduct = (object) [
            'id' => '01HQ789',
            'name' => 'Produto Sem Depth',
            'width' => 10.0,
            'depth' => 0, // sem profundidade - deve aceitar
            'height' => 20.0,
        ];
        
        $rankedProduct = (object) [
            'product' => $mockProduct,
            'facings' => 2,
        ];

        $result = $shelf->addProduct($rankedProduct);

        expect($result)->toBeTrue();
        expect($shelf->products)->toHaveCount(1);
    });
});
