<?php

use App\Models\Gondola;
use App\Models\Tenant;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Services\Editor\GondolaPayloadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cobre a resolução do template_slot por gôndola no payload do editor.
 *
 * Regressão (foco): num planograma multi-gôndola, gerar uma gôndola sobrescreve o
 * planogram.subtemplate_id (compartilhado). As demais gôndolas devem continuar
 * resolvendo seus slots pelo próprio template_id + nº de módulos, não pelo ponteiro.
 *
 * O schema tenant é criado explicitamente no beforeEach (padrão do GondolaSlotOverrideTest),
 * pois as migrations de planograma rodam na conexão 'tenant' e, em sqlite :memory:, não são
 * garantidas entre testes.
 */
beforeEach(function (): void {
    $this->tenantId = (string) Str::ulid();

    $tenant = new Tenant([
        'name' => 'Tenant Slot Map',
        'slug' => 'tenant-slot-map',
        'database' => (string) config('database.connections.'.config('database.default').'.database'),
        'status' => 'active',
    ]);
    $tenant->id = $this->tenantId;

    app()->instance('tenant', $tenant);
    app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);
    // Desliga os módulos para evitar a consulta a tenant_domains/landlord via TenantModuleService.
    app()->instance(TenantModuleService::class, new class extends TenantModuleService
    {
        public function tenantHasActiveModule(Tenant $tenant, string $slug): bool
        {
            unset($tenant, $slug);

            return false;
        }
    });

    // Evita o acesso a Tenant::current()->domain->host disparado por getRouteGondolasAttribute.
    Cache::forever("tenants_{$this->tenantId}_subdomain", 'teste');

    if (! Schema::connection('landlord')->hasTable('tenant_domains')) {
        Schema::connection('landlord')->create('tenant_domains', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('domain')->nullable();
            $table->timestamps();
        });
    }

    $conn = Schema::connection('tenant');

    $conn->dropIfExists('planogram_gondola_slot_overrides');
    $conn->dropIfExists('planogram_template_slots');
    $conn->dropIfExists('planogram_subtemplates');
    $conn->dropIfExists('planogram_templates');
    $conn->dropIfExists('shelves');
    $conn->dropIfExists('sections');
    $conn->dropIfExists('gondolas');
    $conn->dropIfExists('planograms');

    $conn->create('planograms', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('subtemplate_id', 26)->nullable();
        $table->char('store_id', 26)->nullable();
        $table->char('category_id', 26)->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('type')->default('planograma');
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('gondolas', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('planogram_id', 26)->nullable();
        $table->char('template_id', 26)->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->unsignedTinyInteger('num_modulos')->default(1);
        $table->string('flow')->default('left_to_right');
        $table->string('alignment')->default('justify');
        $table->float('scale_factor')->default(1);
        $table->string('generation_mode')->nullable();
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('sections', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26);
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->float('width')->default(90);
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('shelves', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('section_id', 26);
        $table->unsignedSmallInteger('ordering')->default(1);
        $table->float('shelf_position')->default(0);
        $table->float('shelf_width')->default(90);
        $table->float('shelf_height')->default(4);
        $table->float('shelf_depth')->default(40);
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('planogram_templates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->string('code');
        $table->string('name');
        $table->string('department')->default('geral');
        $table->string('origin')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('planogram_subtemplates', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->char('template_id', 26);
        $table->string('code');
        $table->unsignedTinyInteger('num_modules');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('planogram_template_slots', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26);
        $table->char('subtemplate_id', 26);
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('module_number');
        $table->unsignedTinyInteger('shelf_order');
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedTinyInteger('max_facings')->nullable();
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->string('brand_exposure')->nullable();
        $table->string('flavor_exposure')->nullable();
        $table->string('space_fallback')->nullable();
        $table->string('facing_expansion')->nullable();
        $table->boolean('use_target_stock')->nullable();
        $table->unsignedTinyInteger('priority')->nullable();
        $table->unsignedSmallInteger('ordering')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    $conn->create('planogram_gondola_slot_overrides', function (Blueprint $table): void {
        $table->char('id', 26)->primary();
        $table->char('tenant_id', 26)->nullable();
        $table->char('gondola_id', 26)->index();
        $table->char('category_id', 26)->nullable();
        $table->unsignedTinyInteger('min_facings')->nullable();
        $table->unsignedTinyInteger('max_facings')->nullable();
        $table->string('price_order')->nullable();
        $table->string('size_order')->nullable();
        $table->string('brand_exposure')->nullable();
        $table->string('flavor_exposure')->nullable();
        $table->string('space_fallback')->nullable();
        $table->string('facing_expansion')->nullable();
        $table->boolean('use_target_stock')->nullable();
        $table->string('role_override')->nullable();
        $table->unsignedSmallInteger('max_share_per_sku')->nullable();
        $table->unsignedSmallInteger('max_share_per_brand')->nullable();
        $table->unsignedSmallInteger('max_share_per_subcategory')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

/**
 * Cria um cenário de gôndola com 1 módulo, 1 prateleira e 1 slot de template.
 *
 * @return array{gondolaId: string, shelfId: string, slotId: string}
 */
function seedGondolaWithSlot(string $tenantId, ?string $planogramSubtemplatePointer): array
{
    $now = now();
    $planogramId = (string) Str::ulid();
    $gondolaId = (string) Str::ulid();
    $sectionId = (string) Str::ulid();
    $shelfId = (string) Str::ulid();
    $templateId = (string) Str::ulid();
    $subtemplateId = (string) Str::ulid();
    $slotId = (string) Str::ulid();

    DB::connection('tenant')->table('planograms')->insert([
        'id' => $planogramId,
        'tenant_id' => $tenantId,
        'subtemplate_id' => $planogramSubtemplatePointer,
        'name' => 'Planograma',
        'slug' => 'planograma-'.Str::lower(Str::random(6)),
        'type' => 'planograma',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('gondolas')->insert([
        'id' => $gondolaId,
        'tenant_id' => $tenantId,
        'planogram_id' => $planogramId,
        'template_id' => $templateId,
        'name' => 'Gondola',
        'slug' => 'gondola-'.Str::lower(Str::random(6)),
        'num_modulos' => 1,
        'flow' => 'left_to_right',
        'alignment' => 'justify',
        'scale_factor' => 1,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('sections')->insert([
        'id' => $sectionId,
        'tenant_id' => $tenantId,
        'gondola_id' => $gondolaId,
        'name' => 'Modulo 1',
        'code' => 'M1',
        'ordering' => 1,
        'width' => 90,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('shelves')->insert([
        'id' => $shelfId,
        'tenant_id' => $tenantId,
        'section_id' => $sectionId,
        'ordering' => 1,
        'shelf_position' => 10,
        'shelf_width' => 90,
        'shelf_height' => 4,
        'shelf_depth' => 40,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('planogram_templates')->insert([
        'id' => $templateId,
        'tenant_id' => $tenantId,
        'code' => 'TPL-'.Str::upper(Str::random(4)),
        'name' => 'Template',
        'department' => 'Cereais',
        'origin' => 'auto',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('planogram_subtemplates')->insert([
        'id' => $subtemplateId,
        'tenant_id' => $tenantId,
        'template_id' => $templateId,
        'code' => 'SUB-'.Str::upper(Str::random(4)),
        'num_modules' => 1,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('tenant')->table('planogram_template_slots')->insert([
        'id' => $slotId,
        'tenant_id' => $tenantId,
        'subtemplate_id' => $subtemplateId,
        'category_id' => null,
        'module_number' => 1,
        'shelf_order' => 1,
        'min_facings' => 2,
        'max_facings' => 5,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'reduce_c',
        'use_target_stock' => false,
        'facing_expansion' => 'none',
        'priority' => 1,
        'ordering' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return ['gondolaId' => $gondolaId, 'shelfId' => $shelfId, 'slotId' => $slotId];
}

test('editor payload resolves the template slot for a shelf via the gondola own template', function (): void {
    $seed = seedGondolaWithSlot($this->tenantId, planogramSubtemplatePointer: null);

    $gondola = Gondola::query()
        ->with(['planogram', 'sections.shelves'])
        ->findOrFail($seed['gondolaId']);

    $payload = app(GondolaPayloadService::class)->buildEditorPayload($gondola);
    $shelf = collect($payload['sections'][0]['shelves'] ?? [])->firstWhere('id', $seed['shelfId']);

    expect($shelf)
        ->not->toBeNull()
        ->and($shelf['template_slot']['id'] ?? null)->toBe($seed['slotId'])
        ->and($shelf['template_slot']['shelf_order'] ?? null)->toBe(1)
        ->and($shelf['template_slot']['min_facings'] ?? null)->toBe(2);
});

test('editor payload resolves slots even when planogram subtemplate_id points to a foreign subtemplate', function (): void {
    // Ponteiro do planograma aponta para um subtemplate inexistente/de outra gôndola — o que
    // acontece quando outra gôndola do mesmo planograma é gerada. A gôndola atual deve
    // resolver seus slots pelo próprio template_id, ignorando o ponteiro compartilhado.
    $seed = seedGondolaWithSlot($this->tenantId, planogramSubtemplatePointer: (string) Str::ulid());

    $gondola = Gondola::query()
        ->with(['planogram', 'sections.shelves'])
        ->findOrFail($seed['gondolaId']);

    $payload = app(GondolaPayloadService::class)->buildEditorPayload($gondola);
    $shelf = collect($payload['sections'][0]['shelves'] ?? [])->firstWhere('id', $seed['shelfId']);

    expect($shelf)
        ->not->toBeNull()
        ->and($shelf['template_slot']['id'] ?? null)->toBe($seed['slotId']);
});
