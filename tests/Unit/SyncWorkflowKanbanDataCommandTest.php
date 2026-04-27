<?php

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

it('registra o comando de sincronizacao de dados do kanban', function (): void {
    expect(array_keys(Artisan::all()))->toContain('workflow:sync-kanban-data');
});
