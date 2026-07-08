<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Categories\CategoryHierarchyService;
use App\Services\Categories\CategoryTreeService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    // Cada teste recebe um banco :memory: limpo na conexão do tenant.
    DB::purge('tenant');

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/**
 * Cria uma categoria diretamente na conexão do tenant (sem tenant atual: o
 * TenantScope é no-op e o tenant_id fica nulo, o que basta para os testes).
 */
function makeCategory(string $name, ?string $parentId = null): Category
{
    return Category::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        'category_id' => $parentId,
        'status' => 'published',
    ]);
}

function treeService(): CategoryTreeService
{
    return new CategoryTreeService(new CategoryHierarchyService);
}

test('move reparents a category and recomputes denormalized fields for the whole subtree', function (): void {
    $mercearia = makeCategory('Mercearia');
    $biscoitos = makeCategory('Biscoitos', $mercearia->id);
    $recheados = makeCategory('Recheados', $biscoitos->id);
    $chocolate = makeCategory('Chocolate', $recheados->id);
    $bebidas = makeCategory('Bebidas');

    treeService()->move($biscoitos->id, $bebidas->id);

    $biscoitos->refresh();
    expect($biscoitos->category_id)->toBe($bebidas->id);
    expect($biscoitos->nivel)->toBe('2');
    expect($biscoitos->hierarchy_position)->toBe(2);
    expect($biscoitos->level_name)->toBe('Departamento');
    expect($biscoitos->full_path)->toBe('Bebidas > Biscoitos');
    expect($biscoitos->hierarchy_path)->toBe(['Bebidas', 'Biscoitos']);

    $recheados->refresh();
    expect($recheados->hierarchy_position)->toBe(3);
    expect($recheados->full_path)->toBe('Bebidas > Biscoitos > Recheados');

    $chocolate->refresh();
    expect($chocolate->hierarchy_position)->toBe(4);
    expect($chocolate->full_path)->toBe('Bebidas > Biscoitos > Recheados > Chocolate');
    expect($chocolate->hierarchy_path)->toBe(['Bebidas', 'Biscoitos', 'Recheados', 'Chocolate']);
});

test('move keeps products and planogram FKs intact and busts the hierarchy cache', function (): void {
    $mercearia = makeCategory('Mercearia');
    $biscoitos = makeCategory('Biscoitos', $mercearia->id);
    $bebidas = makeCategory('Bebidas');

    $product = Product::query()->create([
        'name' => 'Produto X',
        'slug' => 'produto-x-'.Str::lower(Str::random(5)),
        'ean' => '7890000000012',
        'status' => 'published',
        'category_id' => $biscoitos->id,
    ]);

    // Aquece o cache do caminho com o valor ANTIGO.
    expect($product->hierarchy_path)->toBe('Mercearia > Biscoitos');

    treeService()->move($biscoitos->id, $bebidas->id);

    // FK do produto permanece na mesma categoria.
    $product->refresh();
    expect($product->category_id)->toBe($biscoitos->id);

    // Cache invalidado: o caminho recomputa com o novo pai.
    $fresh = Product::query()->findOrFail($product->id);
    expect($fresh->hierarchy_path)->toBe('Bebidas > Biscoitos');
});

test('move to root sets the node at level 1', function (): void {
    $mercearia = makeCategory('Mercearia');
    $biscoitos = makeCategory('Biscoitos', $mercearia->id);

    treeService()->move($biscoitos->id, null);

    $biscoitos->refresh();
    expect($biscoitos->category_id)->toBeNull();
    expect($biscoitos->hierarchy_position)->toBe(1);
    expect($biscoitos->full_path)->toBe('Biscoitos');
});

test('move into itself is rejected', function (): void {
    $node = makeCategory('Mercearia');

    treeService()->move($node->id, $node->id);
})->throws(ValidationException::class);

test('move into a descendant is rejected (cycle guard)', function (): void {
    $mercearia = makeCategory('Mercearia');
    $biscoitos = makeCategory('Biscoitos', $mercearia->id);

    // Tentar mover a raiz para dentro do próprio filho.
    treeService()->move($mercearia->id, $biscoitos->id);
})->throws(ValidationException::class);

test('move that would exceed max depth is rejected', function (): void {
    // Cadeia de 5 níveis: n1 > n2 > n3 > n4 > n5 (subárvore de altura 4 a partir de n2).
    $n1 = makeCategory('N1');
    $n2 = makeCategory('N2', $n1->id);
    $n3 = makeCategory('N3', $n2->id);
    $n4 = makeCategory('N4', $n3->id);
    $n5 = makeCategory('N5', $n4->id);

    // Destino já em profundidade 5; mover n2 (altura 4) levaria n5 ao nível 9 > 7.
    $deep = makeCategory('Deep1');
    $deep2 = makeCategory('Deep2', $deep->id);
    $deep3 = makeCategory('Deep3', $deep2->id);
    $deep4 = makeCategory('Deep4', $deep3->id);
    $deep5 = makeCategory('Deep5', $deep4->id);

    treeService()->move($n2->id, $deep5->id);
})->throws(ValidationException::class);

test('moveProducts reassigns products to the target category', function (): void {
    $origem = makeCategory('Origem');
    $destino = makeCategory('Destino');

    $p1 = Product::query()->create(['name' => 'P1', 'slug' => 'p1-'.Str::lower(Str::random(5)), 'ean' => '7890000000029', 'status' => 'published', 'category_id' => $origem->id]);
    $p2 = Product::query()->create(['name' => 'P2', 'slug' => 'p2-'.Str::lower(Str::random(5)), 'ean' => '7890000000036', 'status' => 'published', 'category_id' => $origem->id]);

    $moved = treeService()->moveProducts([$p1->id, $p2->id], $destino->id);

    expect($moved)->toBe(2);
    expect($p1->refresh()->category_id)->toBe($destino->id);
    expect($p2->refresh()->category_id)->toBe($destino->id);
});

test('nodesForParent returns roots with children and product counts', function (): void {
    $root = makeCategory('Raiz');
    makeCategory('Filho A', $root->id);
    makeCategory('Filho B', $root->id);
    Product::query()->create(['name' => 'PP', 'slug' => 'pp-'.Str::lower(Str::random(5)), 'ean' => '7890000000043', 'status' => 'published', 'category_id' => $root->id]);

    $roots = treeService()->nodesForParent(null);

    expect($roots)->toHaveCount(1);
    expect($roots[0]['id'])->toBe($root->id);
    expect($roots[0]['children_count'])->toBe(2);
    expect($roots[0]['products_count'])->toBe(1);

    $children = treeService()->nodesForParent($root->id);
    expect($children)->toHaveCount(2);
});
