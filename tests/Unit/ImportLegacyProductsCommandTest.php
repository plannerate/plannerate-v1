<?php

use Illuminate\Support\Facades\Artisan;

it('registra o comando import legacy products', function () {
    expect(array_keys(Artisan::all()))->toContain('import:legacy-products');
});
