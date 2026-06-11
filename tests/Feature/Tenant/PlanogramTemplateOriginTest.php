<?php

use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('tenant')->dropAllTables();

    Schema::connection('tenant')->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->string('code');
        $table->string('name');
        $table->string('department')->default('');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->char('created_by', 26)->nullable();
        $table->string('origin')->nullable();
        $table->char('source_gondola_id', 26)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * Cria um registro direto na tabela tenant sem passar pelo model (evita BelongsToTenant).
 */
function makeTemplate(array $attrs = []): string
{
    $id = Str::ulid()->toBase32();
    $defaults = [
        'id' => $id,
        'tenant_id' => null,
        'code' => 'T-'.$id,
        'name' => 'Template '.$id,
        'department' => 'Bebidas',
        'is_active' => true,
        'origin' => null,
        'source_gondola_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::connection('tenant')->table('planogram_templates')
        ->insert(array_merge($defaults, $attrs));

    return $id;
}

test('template auto com is_active false não aparece no scopeVisible', function (): void {
    makeTemplate(['origin' => 'auto', 'is_active' => false]);

    $visible = PlanogramTemplate::visible()->get();

    expect($visible)->toHaveCount(0);
});

test('template manual aparece no scopeVisible', function (): void {
    $id = makeTemplate(['origin' => null, 'is_active' => true]);

    $visible = PlanogramTemplate::visible()->get();

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->id)->toBe($id);
});

test('template auto ativo não aparece no scopeVisible', function (): void {
    makeTemplate(['origin' => 'auto', 'is_active' => true]);

    $visible = PlanogramTemplate::visible()->get();

    expect($visible)->toHaveCount(0);
});

test('scopeAuto retorna apenas templates de origem automatica', function (): void {
    $autoId = makeTemplate(['origin' => 'auto', 'is_active' => false]);
    makeTemplate(['origin' => null, 'is_active' => true]);

    $auto = PlanogramTemplate::auto()->get();

    expect($auto)->toHaveCount(1)
        ->and($auto->first()->id)->toBe($autoId);
});

test('source_gondola_id é persistido e legível', function (): void {
    $gondolaId = Str::ulid()->toBase32();
    $id = makeTemplate(['origin' => 'auto', 'source_gondola_id' => $gondolaId]);

    $template = PlanogramTemplate::find($id);

    expect($template->source_gondola_id)->toBe($gondolaId)
        ->and($template->origin)->toBe('auto');
});
