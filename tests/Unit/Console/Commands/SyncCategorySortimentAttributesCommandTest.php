<?php

use App\Console\Commands\SyncCategorySortimentAttributesCommand;
use App\Models\Category;

test('buildProductSyncData preenche sortiment e grouping a partir da hierarquia da categoria', function (): void {
    $root = new Category([
        'name' => 'Matriz',
        'level_name' => 'Departamento',
        'hierarchy_position' => 1,
        'category_id' => null,
    ]);

    $departamento = new Category([
        'name' => 'Higiene',
        'level_name' => 'Departamento',
        'hierarchy_position' => 2,
        'category_id' => 'root',
    ]);
    $departamento->setRelation('parent', $root);

    $subdepartamento = new Category([
        'name' => 'Pessoal',
        'level_name' => 'Subdepartamento',
        'hierarchy_position' => 3,
        'category_id' => 'departamento',
    ]);
    $subdepartamento->setRelation('parent', $departamento);

    $categoria = new Category([
        'name' => 'Sabonetes',
        'level_name' => 'Categoria',
        'hierarchy_position' => 4,
        'category_id' => 'subdepartamento',
    ]);
    $categoria->setRelation('parent', $subdepartamento);

    $subcategoria = new Category([
        'name' => 'Líquido',
        'level_name' => 'Subcategoria',
        'hierarchy_position' => 5,
        'category_id' => 'categoria',
    ]);
    $subcategoria->setRelation('parent', $categoria);

    $command = new SyncCategorySortimentAttributesCommand;

    $result = $command->buildProductSyncData($subcategoria);

    expect($result)->not->toBeNull()
        ->and($result)->toMatchArray([
            'sortiment_attribute' => 'Higiene | Pessoal | Sabonetes | Líquido',
            'sortiment_attribute_levels' => 'departamento,subdepartamento,categoria,subcategoria',
            'grouping' => 'Higiene | Pessoal | Sabonetes | Líquido',
            'grouping_normalized' => 'higiene-pessoal-sabonetes-liquido',
        ]);
});
