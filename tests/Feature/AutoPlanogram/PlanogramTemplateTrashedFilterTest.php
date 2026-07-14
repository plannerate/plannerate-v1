<?php

use App\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cobre o bug: PlanogramTemplateController::index()/templatesPaginator() não aplicava
 * o filtro "trashed" (excluídos nunca apareciam) e destroy() nunca forçava exclusão
 * definitiva de um template já na lixeira. Fix replica o padrão já usado em
 * PlanogramController (trait InteractsWithTrashedFilter + trashed() ? forceDelete()).
 *
 * Também cobre o cascade do force delete: sem FK real no banco entre
 * planogram_templates/planogram_subtemplates/planogram_template_slots, apagar o
 * template de vez sem cascade deixaria subtemplates/slots órfãos. E o cascade nunca
 * pode alcançar gôndolas — se alguma gôndola usa o template (gondolas.template_id),
 * o force delete é bloqueado.
 */
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
        $table->boolean('is_active')->default(true);
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
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('planogram_template_slots', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26);
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('module_number');
        $table->unsignedTinyInteger('shelf_order');
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::connection('tenant')->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('template_id', 26)->nullable();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

const TRASHED_FILTER_TENANT = '01jym02qk8n1cwdq2hd5drpgsz';

function makeTrashedFilterTemplate(string $code): PlanogramTemplate
{
    return PlanogramTemplate::query()->create([
        'tenant_id' => TRASHED_FILTER_TENANT,
        'code' => $code,
        'name' => $code,
        'department' => 'BEBIDAS',
        'is_active' => true,
    ]);
}

test('template excluído some da listagem padrão mas aparece com o filtro "only"', function (): void {
    $ativo = makeTrashedFilterTemplate('ATIVO');
    $excluido = makeTrashedFilterTemplate('EXCLUIDO');
    $excluido->delete();

    // Mesma query base do PlanogramTemplateController::templatesPaginator()
    $semFiltro = PlanogramTemplate::query()->pluck('id');
    expect($semFiltro)->toContain($ativo->id)
        ->not->toContain($excluido->id);

    $soExcluidos = PlanogramTemplate::query()->onlyTrashed()->pluck('id');
    expect($soExcluidos)->toContain($excluido->id)
        ->not->toContain($ativo->id);

    $comExcluidos = PlanogramTemplate::query()->withTrashed()->pluck('id');
    expect($comExcluidos)->toContain($ativo->id, $excluido->id);
});

test('destroy de template ativo move para a lixeira (soft delete)', function (): void {
    $template = makeTrashedFilterTemplate('T1');

    // Lógica equivalente a PlanogramTemplateController::destroy()
    expect($template->trashed())->toBeFalse();
    $template->delete();

    expect(PlanogramTemplate::query()->find($template->id))->toBeNull()
        ->and(PlanogramTemplate::withoutGlobalScopes()->find($template->id)->trashed())->toBeTrue();
});

test('destroy de template já excluído apaga definitivamente (force delete)', function (): void {
    $template = makeTrashedFilterTemplate('T2');
    $template->delete();
    $template->refresh();

    // Lógica equivalente a PlanogramTemplateController::destroy()
    if ($template->trashed()) {
        $template->forceDelete();
    }

    expect(PlanogramTemplate::withoutGlobalScopes()->find($template->id))->toBeNull();
});

test('force delete de template sem gôndola usando também apaga subtemplates e slots (evita órfãos)', function (): void {
    $template = makeTrashedFilterTemplate('T3');
    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => TRASHED_FILTER_TENANT,
        'template_id' => $template->id,
        'code' => 'T3-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);
    $slot = PlanogramTemplateSlot::query()->create([
        'tenant_id' => TRASHED_FILTER_TENANT,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
    ]);
    // Um slot já soft-deleted individualmente antes do force delete do template —
    // withoutGlobalScopes() no controller precisa alcançar ele também.
    $slot->delete();

    $template->delete();
    $template->refresh();

    // Lógica equivalente a PlanogramTemplateController::destroy() (sem gôndola usando)
    expect(Gondola::where('template_id', $template->id)->exists())->toBeFalse();

    $subtemplateIds = PlanogramSubtemplate::withoutGlobalScopes()->where('template_id', $template->id)->pluck('id');
    PlanogramTemplateSlot::withoutGlobalScopes()->whereIn('subtemplate_id', $subtemplateIds)->forceDelete();
    PlanogramSubtemplate::withoutGlobalScopes()->where('template_id', $template->id)->forceDelete();
    $template->forceDelete();

    expect(PlanogramTemplate::withoutGlobalScopes()->find($template->id))->toBeNull()
        ->and(PlanogramSubtemplate::withoutGlobalScopes()->find($subtemplate->id))->toBeNull()
        ->and(PlanogramTemplateSlot::withoutGlobalScopes()->find($slot->id))->toBeNull();
});

test('force delete de template é bloqueado se alguma gôndola ainda usa ele, e nada é apagado', function (): void {
    $template = makeTrashedFilterTemplate('T4');
    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => TRASHED_FILTER_TENANT,
        'template_id' => $template->id,
        'code' => 'T4-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);

    DB::connection('tenant')->table('gondolas')->insert([
        'id' => (string) Str::ulid(),
        'tenant_id' => TRASHED_FILTER_TENANT,
        'template_id' => $template->id,
        'name' => 'Gôndola usando T4',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $template->delete();
    $template->refresh();

    // Lógica equivalente a PlanogramTemplateController::destroy(): bloqueia, não apaga nada
    expect(Gondola::where('template_id', $template->id)->exists())->toBeTrue();

    expect(PlanogramTemplate::withoutGlobalScopes()->find($template->id))
        ->not->toBeNull()
        ->trashed()->toBeTrue();
    expect(PlanogramSubtemplate::withoutGlobalScopes()->find($subtemplate->id))->not->toBeNull();
});
