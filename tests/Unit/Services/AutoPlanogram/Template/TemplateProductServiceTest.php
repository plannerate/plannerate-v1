<?php

use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateProduct;
use App\Models\Product;
use App\Services\AutoPlanogram\Template\TemplateProductService;

test('products data includes linked product dimensions', function (): void {
    $linkedProduct = new Product([
        'width' => '12.50',
        'height' => '18.00',
        'depth' => '7.25',
        'unit' => 'cm',
        'has_dimensions' => true,
    ]);

    $templateProduct = new PlanogramTemplateProduct([
        'ean' => '7890000000000',
        'product_id' => '01HX0000000000000000000000',
        'description' => 'Produto teste',
        'brand' => 'Marca',
        'grouping' => 'Categoria | Subcategoria',
        'category' => 'Categoria',
        'subcategory' => 'Subcategoria',
        'package_type' => 'Caixa',
        'package_content' => '12 un',
    ]);
    $templateProduct->id = '01HY0000000000000000000000';
    $templateProduct->setRelation('product', $linkedProduct);

    $template = new PlanogramTemplate;
    $template->setRelation('templateProducts', collect([$templateProduct]));

    $products = (new TemplateProductService)->productsData($template);

    expect($products[0])
        ->toMatchArray([
            'width' => '12.50',
            'height' => '18.00',
            'depth' => '7.25',
            'unit' => 'cm',
            'has_dimensions' => true,
        ]);
});
