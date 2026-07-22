<?php
$tenants = \App\Models\Tenant::query()->get(['id','name']);
foreach ($tenants as $t) {
    try {
        $t->makeCurrent();
        $c = \Illuminate\Support\Facades\DB::connection('tenant');
        $ps = $c->table('product_store');
        $prod = $c->table('products');
        printf("%-24s stores=%-3d products=%-7d prod.stock=%-7d pivot=%-8d pivot.stock=%-8d pivot.date=%-8d\n",
            substr($t->name,0,24),
            $c->table('stores')->whereNull('deleted_at')->count(),
            (clone $prod)->whereNull('deleted_at')->count(),
            (clone $prod)->whereNull('deleted_at')->whereNotNull('current_stock')->count(),
            (clone $ps)->count(),
            (clone $ps)->whereNotNull('current_stock')->count(),
            (clone $ps)->whereNotNull('last_purchase_date')->count(),
        );
    } catch (\Throwable $e) { echo substr($t->name,0,24).' ERRO: '.substr($e->getMessage(),0,90)."\n"; }
}
