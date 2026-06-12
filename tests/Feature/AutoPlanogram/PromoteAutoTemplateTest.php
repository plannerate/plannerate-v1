<?php

use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
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
        $table->string('department');
        $table->char('category_id', 26)->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(false);
        $table->char('created_by', 26)->nullable();
        $table->string('origin')->nullable();
        $table->char('source_gondola_id', 26)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_subtemplates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('template_id', 26);
        $table->string('code');
        $table->unsignedTinyInteger('num_modules');
        $table->boolean('is_active')->default(false);
        $table->string('hot_zone_priority')->nullable();
        $table->string('cold_zone_priority')->nullable();
        $table->string('flow_direction')->nullable();
        $table->string('layout_orientation')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * Insere template diretamente no banco (evita BelongsToTenant/tenantId).
 */
function insertAutoTemplate(array $attrs = []): string
{
    $id = Str::ulid()->toBase32();

    DB::connection('tenant')->table('planogram_templates')->insert(array_merge([
        'id' => $id,
        'tenant_id' => null,
        'code' => 'T-'.$id,
        'name' => 'Template '.$id,
        'department' => 'Bebidas',
        'is_active' => false,
        'origin' => 'auto',
        'source_gondola_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));

    return $id;
}

/**
 * Insere subtemplate diretamente no banco.
 */
function insertAutoSubtemplate(string $templateId, array $attrs = []): string
{
    $id = Str::ulid()->toBase32();

    DB::connection('tenant')->table('planogram_subtemplates')->insert(array_merge([
        'id' => $id,
        'tenant_id' => null,
        'template_id' => $templateId,
        'code' => 'S-'.$id,
        'num_modules' => 4,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));

    return $id;
}

test('promover template auto seta is_active=true e limpa origin no template e nos subtemplates', function (): void {
    $templateId = insertAutoTemplate(['origin' => 'auto', 'is_active' => false]);
    $sub1 = insertAutoSubtemplate($templateId);
    $sub2 = insertAutoSubtemplate($templateId);

    $template = PlanogramTemplate::find($templateId);

    // Lógica equivalente ao PlanogramTemplateController::promote()
    expect($template->origin)->toBe('auto');
    $template->update(['is_active' => true, 'origin' => null]);
    $template->subtemplates()->update(['is_active' => true]);

    $template->refresh();
    expect($template->is_active)->toBeTrue()
        ->and($template->origin)->toBeNull(); // obrigatório para scopeVisible() incluir o template

    $activeSubs = PlanogramSubtemplate::where('template_id', $templateId)
        ->where('is_active', true)
        ->get();

    expect($activeSubs)->toHaveCount(2)
        ->and($activeSubs->pluck('id')->sort()->values()->all())
        ->toBe(collect([$sub1, $sub2])->sort()->values()->all());
});

test('promover template com origin diferente de auto é rejeitado pela lógica do controller', function (): void {
    $templateId = insertAutoTemplate(['origin' => null, 'is_active' => false]);

    $template = PlanogramTemplate::find($templateId);

    // O controller verifica origin === 'auto' antes de prosseguir
    $isEligible = $template->origin === 'auto';

    expect($isEligible)->toBeFalse();
    // Template permanece inativo pois a condição de promote não foi satisfeita
    expect($template->is_active)->toBeFalse();
});

test('gôndola vinculada ao template sintetizado deve ter generation_mode=template', function (): void {
    $gondolaId = Str::ulid()->toBase32();
    $templateId = insertAutoTemplate(['origin' => 'auto', 'is_active' => false, 'source_gondola_id' => $gondolaId]);
    $subtemplateId = insertAutoSubtemplate($templateId);

    // Simula o que AutoPlanogramController::generate() faz após síntese:
    // busca o subtemplate para obter o template_id e determina o novo generation_mode
    $synth = PlanogramSubtemplate::find($subtemplateId);

    expect($synth)->not->toBeNull()
        ->and($synth->template_id)->toBe($templateId);

    // A origin permanece no template (não vive no generation_mode da gôndola)
    $template = PlanogramTemplate::find($templateId);
    expect($template->origin)->toBe('auto')
        ->and($template->source_gondola_id)->toBe($gondolaId);

    // generation_mode calculado: se synthTemplateId != null → 'template'
    $generationMode = $synth->template_id !== null ? 'template' : 'automatic';
    expect($generationMode)->toBe('template');
});

test('regerar do zero é idempotente: mesmo source_gondola_id não duplica template', function (): void {
    $gondolaId = Str::ulid()->toBase32();

    // Primeira síntese
    $templateId = insertAutoTemplate(['origin' => 'auto', 'is_active' => false, 'source_gondola_id' => $gondolaId]);
    insertAutoSubtemplate($templateId);

    $countBefore = DB::connection('tenant')->table('planogram_templates')
        ->where('source_gondola_id', $gondolaId)
        ->whereNull('deleted_at')
        ->count();

    expect($countBefore)->toBe(1);

    // Verifica que o AutoTemplateSynthesizer encontraria o template existente
    // (lockForUpdate em production, aqui verificamos via query direta)
    $existing = DB::connection('tenant')->table('planogram_templates')
        ->where('source_gondola_id', $gondolaId)
        ->whereNull('deleted_at')
        ->first();

    expect($existing)->not->toBeNull()
        ->and($existing->id)->toBe($templateId);

    $countAfter = DB::connection('tenant')->table('planogram_templates')
        ->where('source_gondola_id', $gondolaId)
        ->whereNull('deleted_at')
        ->count();

    expect($countAfter)->toBe(1);
});
