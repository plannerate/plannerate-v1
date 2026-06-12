<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    $landlordUsesSqlite = (string) config('database.connections.landlord.driver') === 'sqlite';

    if ($landlordUsesSqlite) {
        $this->markTestSkipped(
            'PlanogramTemplate HTTP tests: landlord em SQLite (:memory:) não suporta multi-migrate neste ciclo.'
        );
    }

    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--path' => 'database/migrations',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant admin can view planogram templates index', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-index');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-01',
        'name' => 'LIMPEZA-01',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-index.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.index', ['subdomain' => 'tpl-index'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Index')
            ->has('planogramTemplates.data', 1)
            ->where('planogramTemplates.data.0.code', 'LIMPEZA-01'));
});

test('tenant admin can view import page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-create');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-create.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.create', ['subdomain' => 'tpl-create'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Import'));
});

test('import with valid xlsx creates template and slots', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-import');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $xlsxPath = createMinimalTemplateSpreadsheet();

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-import.'.config('app.landlord_domain')])
        ->post(route('tenant.planogram-templates.import', ['subdomain' => 'tpl-import'], false), [
            'file' => new UploadedFile($xlsxPath, 'templates.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

    $response->assertRedirect(route('tenant.planogram-templates.index', ['subdomain' => 'tpl-import'], false));

    $template = PlanogramTemplate::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'LIMPEZA - 01')
        ->first();

    expect($template)->not->toBeNull();
    expect($template->name)->toBe('LIMPEZA - 01');
    expect($template->department)->toBe('LIMPEZA');

    $subtemplate = $template->subtemplates()->first();
    expect($subtemplate)->not->toBeNull();
    expect($subtemplate->num_modules)->toBe(1);

    $slots = $subtemplate->slots()->get();
    expect($slots)->toHaveCount(2);
    expect($slots->first()->ordering)->toBe(1);
    // category_id será null quando não há categorias correspondentes no banco de teste
    expect($slots->first()->category_id)->toBeNull();
});

test('import without file fails validation', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-no-file');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-no-file.'.config('app.landlord_domain')])
        ->from(route('tenant.planogram-templates.create', ['subdomain' => 'tpl-no-file'], false))
        ->post(route('tenant.planogram-templates.import', ['subdomain' => 'tpl-no-file'], false), []);

    $response->assertSessionHasErrors(['file']);
});

test('tenant admin can view template show page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-show');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-SHOW',
        'name' => 'LIMPEZA-SHOW',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-show.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.show', ['subdomain' => 'tpl-show', 'planogramTemplate' => $template->id], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Show')
            ->where('planogramTemplate.code', 'LIMPEZA-SHOW'));
});

test('tenant admin can view template review page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-review');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-REV',
        'name' => 'LIMPEZA-REV',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-review.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.slots.review', ['subdomain' => 'tpl-review', 'planogramTemplate' => $template->id], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Review')
            ->where('template.code', 'LIMPEZA-REV'));
});

test('review page keeps selected slot from query param', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-review-slot');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-REV-SLOT',
        'name' => 'LIMPEZA-REV-SLOT',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'LIMPEZA-REV-SLOT-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    $slot = PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'min_facings' => 1,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'skip',
        'use_target_stock' => false,
        'ordering' => 1,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-review-slot.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.slots.review', [
            'subdomain' => 'tpl-review-slot',
            'planogramTemplate' => $template->id,
            'slot_id' => $slot->id,
        ], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Review')
            ->where('selected_slot_id', $slot->id));
});

test('tenant admin can soft-delete a template', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-delete');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-DEL',
        'name' => 'LIMPEZA-DEL',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-delete.'.config('app.landlord_domain')])
        ->delete(route('tenant.planogram-templates.destroy', ['subdomain' => 'tpl-delete', 'planogramTemplate' => $template->id], false));

    $response->assertRedirect(route('tenant.planogram-templates.index', ['subdomain' => 'tpl-delete'], false));

    expect(PlanogramTemplate::withoutGlobalScopes()->withTrashed()->find($template->id)?->deleted_at)->not->toBeNull();
    expect(PlanogramTemplate::find($template->id))->toBeNull();
});

test('planogram templates index is isolated by tenant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenantA = makeTenantForTemplates('tpl-iso-a');
    $tenantB = makeTenantForTemplates('tpl-iso-b');
    assignTenantAdminRoleForTemplates($user, $tenantA->id);

    PlanogramTemplate::query()->create([
        'tenant_id' => $tenantA->id,
        'code' => 'TPL-A',
        'name' => 'TPL-A',
        'department' => 'DEPT-A',
        'is_active' => true,
    ]);

    PlanogramTemplate::query()->create([
        'tenant_id' => $tenantB->id,
        'code' => 'TPL-B',
        'name' => 'TPL-B',
        'department' => 'DEPT-B',
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-iso-a.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.index', ['subdomain' => 'tpl-iso-a'], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('planogramTemplates.data', 1)
            ->where('planogramTemplates.data.0.code', 'TPL-A'));
});

test('planogram template routes are forbidden without permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    makeTenantForTemplates('tpl-no-perm');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-no-perm.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.index', ['subdomain' => 'tpl-no-perm'], false));

    $response->assertForbidden();
});

test('slot products endpoint returns only products from slot category_id', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-slot-products');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'TPL-SLOT',
        'name' => 'TPL-SLOT',
        'department' => 'MERCEARIA',
        'is_active' => true,
    ]);

    $cerealCategoryId = (string) Str::ulid();
    $biscoitoCategoryId = (string) Str::ulid();

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Match',
        'category_id' => $cerealCategoryId,
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Outro',
        'category_id' => $biscoitoCategoryId,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-slot-products.'.config('app.landlord_domain')])
        ->getJson(route('tenant.planogram-templates.slots.products', [
            'subdomain' => 'tpl-slot-products',
            'planogramTemplate' => $template->id,
            'category_id' => $cerealCategoryId,
        ], false));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Produto Match')
        ->assertJsonPath('data.0.category_id', $cerealCategoryId);
});

test('slot analysis endpoint returns placement summary and row reasons', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-slot-analysis');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'TPL-ANALYSIS',
        'name' => 'TPL-ANALYSIS',
        'department' => 'MERCEARIA',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'TPL-ANALYSIS-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    $cerealCategoryId = (string) Str::ulid();

    $slot = PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'category_id' => $cerealCategoryId,
        'module_number' => 1,
        'shelf_order' => 1,
        'min_facings' => 2,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'skip',
        'use_target_stock' => false,
        'ordering' => 1,
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Cabe',
        'category_id' => $cerealCategoryId,
        'width' => 20,
        'status' => 'active',
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Fora',
        'category_id' => $cerealCategoryId,
        'width' => 80,
        'status' => 'active',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-slot-analysis.'.config('app.landlord_domain')])
        ->getJson(route('tenant.planogram-templates.slots.analysis', [
            'subdomain' => 'tpl-slot-analysis',
            'planogramTemplate' => $template->id,
            'slot_id' => $slot->id,
            'shelf_width_cm' => 100,
        ], false));

    $response
        ->assertOk()
        ->assertJsonPath('data.summary.total_products', 2)
        ->assertJsonPath('data.summary.placed_products', 1)
        ->assertJsonPath('data.summary.rejected_products', 1);
});

test('saving slot persists subtemplate slot defaults and exposes them in slots payload', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-slot-defaults');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-DEFAULTS',
        'name' => 'LIMPEZA-DEFAULTS',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'LIMPEZA-DEFAULTS-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    $payload = [
        'module_number' => 1,
        'shelf_order' => 1,
        'category_id' => null,
        'min_facings' => 3,
        'priority' => 2,
        'price_order' => 'desc',
        'size_order' => 'asc',
        'brand_exposure' => 'vertical',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'reduce_facings',
        'use_target_stock' => true,
    ];

    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-slot-defaults.'.config('app.landlord_domain')])
        ->post(
            route('tenant.planogram-templates.slots.store', [
                'subdomain' => 'tpl-slot-defaults',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $subtemplate->id,
            ], false),
            $payload,
        )
        ->assertRedirect(route('tenant.planogram-templates.slots.index', [
            'subdomain' => 'tpl-slot-defaults',
            'planogramTemplate' => $template->id,
        ], false));

    $subtemplate->refresh();

    expect($subtemplate->slot_defaults)->toMatchArray([
        'min_facings' => 3,
        'priority' => 2,
        'price_order' => 'desc',
        'size_order' => 'asc',
        'brand_exposure' => 'vertical',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'reduce_facings',
        'use_target_stock' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-slot-defaults.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.slots.index', [
            'subdomain' => 'tpl-slot-defaults',
            'planogramTemplate' => $template->id,
        ], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Slots')
            ->where('subtemplates.0.slot_defaults.min_facings', 3)
            ->where('subtemplates.0.slot_defaults.priority', 2)
            ->where('subtemplates.0.slot_defaults.price_order', 'desc')
            ->where('subtemplates.0.slot_defaults.space_fallback', 'reduce_facings')
            ->where('subtemplates.0.slot_defaults.use_target_stock', true));
});

test('updating subtemplate settings persists the four global fields and exposes them in the slots payload', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-sub-settings');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-SETTINGS',
        'name' => 'LIMPEZA-SETTINGS',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'LIMPEZA-SETTINGS-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-sub-settings.'.config('app.landlord_domain')])
        ->put(
            route('tenant.planogram-templates.subtemplates.settings.update', [
                'subdomain' => 'tpl-sub-settings',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $subtemplate->id,
            ], false),
            [
                'hot_zone_priority' => 'maior_margem',
                'cold_zone_priority' => 'maior_volume',
                'flow_direction' => 'right_to_left',
                'layout_orientation' => 'vertical',
            ],
        )
        ->assertRedirect();

    $subtemplate->refresh();

    expect($subtemplate->hot_zone_priority?->value)->toBe('maior_margem')
        ->and($subtemplate->cold_zone_priority?->value)->toBe('maior_volume')
        ->and($subtemplate->flow_direction?->value)->toBe('right_to_left')
        ->and($subtemplate->layout_orientation?->value)->toBe('vertical');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-sub-settings.'.config('app.landlord_domain')])
        ->get(route('tenant.planogram-templates.slots.index', [
            'subdomain' => 'tpl-sub-settings',
            'planogramTemplate' => $template->id,
        ], false));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/planogram-templates/Slots')
            ->where('subtemplates.0.hot_zone_priority', 'maior_margem')
            ->where('subtemplates.0.cold_zone_priority', 'maior_volume')
            ->where('subtemplates.0.flow_direction', 'right_to_left')
            ->where('subtemplates.0.layout_orientation', 'vertical'));
});

test('subtemplate settings rejects invalid enum values and accepts nulls', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-sub-settings-val');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'LIMPEZA-SETTINGS-VAL',
        'name' => 'LIMPEZA-SETTINGS-VAL',
        'department' => 'LIMPEZA',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'LIMPEZA-SETTINGS-VAL-1M',
        'num_modules' => 1,
        'is_active' => true,
        'layout_orientation' => 'vertical',
    ]);

    $settingsUrl = route('tenant.planogram-templates.subtemplates.settings.update', [
        'subdomain' => 'tpl-sub-settings-val',
        'planogramTemplate' => $template->id,
        'planogramSubtemplate' => $subtemplate->id,
    ], false);

    // Enum inválido → erro de validação no campo
    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-sub-settings-val.'.config('app.landlord_domain')])
        ->put($settingsUrl, ['layout_orientation' => 'diagonal'])
        ->assertSessionHasErrors('layout_orientation');

    // Nulls explícitos limpam os valores salvos
    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-sub-settings-val.'.config('app.landlord_domain')])
        ->put($settingsUrl, [
            'hot_zone_priority' => null,
            'cold_zone_priority' => null,
            'flow_direction' => null,
            'layout_orientation' => null,
        ])
        ->assertRedirect();

    $subtemplate->refresh();

    expect($subtemplate->layout_orientation)->toBeNull()
        ->and($subtemplate->hot_zone_priority)->toBeNull();
});

test('reimport do mesmo Excel faz upsert — não apaga slots existentes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-reimport');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $xlsxPath = createMinimalTemplateSpreadsheet();

    $uploadFile = fn () => new UploadedFile($xlsxPath, 'templates.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->withServerVariables(['HTTP_HOST' => 'tpl-reimport.'.config('app.landlord_domain')])
        ->post(route('tenant.planogram-templates.import', ['subdomain' => 'tpl-reimport'], false), ['file' => $uploadFile()]);

    $subtemplate = PlanogramSubtemplate::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    $slotIdBefore = $subtemplate->slots()->first()->id;

    $this->withServerVariables(['HTTP_HOST' => 'tpl-reimport.'.config('app.landlord_domain')])
        ->post(route('tenant.planogram-templates.import', ['subdomain' => 'tpl-reimport'], false), ['file' => $uploadFile()]);

    $slotIdAfter = PlanogramTemplateSlot::withoutGlobalScopes()
        ->where('subtemplate_id', $subtemplate->id)
        ->orderBy('ordering')
        ->first()->id;

    expect($slotIdAfter)->toBe($slotIdBefore);
    expect(PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $subtemplate->id)->count())->toBe(2);
});

test('tenant admin pode clonar subtemplate para mais módulos', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-clone');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'CLONE-01',
        'name' => 'CLONE-01',
        'department' => 'TEST',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'CLONE-01-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'min_facings' => 2,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'skip',
        'use_target_stock' => false,
        'ordering' => 1,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-clone.'.config('app.landlord_domain')])
        ->post(
            route('tenant.planogram-templates.subtemplates.clone', [
                'subdomain' => 'tpl-clone',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $subtemplate->id,
            ], false),
            ['target_modules' => 3],
        );

    $response->assertRedirect(route('tenant.planogram-templates.slots.index', ['subdomain' => 'tpl-clone', 'planogramTemplate' => $template->id], false));

    $clone = PlanogramSubtemplate::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('num_modules', 3)
        ->firstOrFail();

    expect($clone->code)->toBe('CLONE-01-2M-3M');
    expect(PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $clone->id)->count())->toBe(1);
});

test('clonar subtemplate para módulos já existente retorna erro de validação', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-clone-dup');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'CLONE-DUP',
        'name' => 'CLONE-DUP',
        'department' => 'TEST',
        'is_active' => true,
    ]);

    $sub2 = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'DUP-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);

    PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'DUP-3M',
        'num_modules' => 3,
        'is_active' => true,
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-clone-dup.'.config('app.landlord_domain')])
        ->from(route('tenant.planogram-templates.slots.index', ['subdomain' => 'tpl-clone-dup', 'planogramTemplate' => $template->id], false))
        ->post(
            route('tenant.planogram-templates.subtemplates.clone', [
                'subdomain' => 'tpl-clone-dup',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $sub2->id,
            ], false),
            ['target_modules' => 3],
        );

    $response->assertSessionHasErrors(['target_modules']);
});

test('bulk endpoint copia configuração de um slot para várias prateleiras', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-bulk-shelves');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'BULK-SHELVES',
        'name' => 'BULK-SHELVES',
        'department' => 'TEST',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'BULK-SHELVES-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    $base = bulkSlotPayload(1, 1);
    $slots = [
        $base,
        [...$base, 'shelf_order' => 2],
        [...$base, 'shelf_order' => 3],
    ];

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-bulk-shelves.'.config('app.landlord_domain')])
        ->post(
            route('tenant.planogram-templates.slots.bulk', [
                'subdomain' => 'tpl-bulk-shelves',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $subtemplate->id,
            ], false),
            ['slots' => $slots],
        );

    $response->assertRedirect(route('tenant.planogram-templates.slots.index', [
        'subdomain' => 'tpl-bulk-shelves',
        'planogramTemplate' => $template->id,
    ], false));

    expect(PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $subtemplate->id)->count())->toBe(3);
});

test('bulk endpoint faz upsert — sobrescreve slot já existente na posição', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-bulk-upsert');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'BULK-UPSERT',
        'name' => 'BULK-UPSERT',
        'department' => 'TEST',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'BULK-UPSERT-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'min_facings' => 1,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'mixed',
        'flavor_exposure' => 'mixed',
        'space_fallback' => 'skip',
        'use_target_stock' => false,
        'ordering' => 1,
    ]);

    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-bulk-upsert.'.config('app.landlord_domain')])
        ->post(
            route('tenant.planogram-templates.slots.bulk', [
                'subdomain' => 'tpl-bulk-upsert',
                'planogramTemplate' => $template->id,
                'planogramSubtemplate' => $subtemplate->id,
            ], false),
            ['slots' => [[...bulkSlotPayload(1, 1), 'min_facings' => 4, 'max_facings' => 8]]],
        );

    $slots = PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $subtemplate->id)->get();

    expect($slots)->toHaveCount(1);
    expect($slots->first()->min_facings)->toBe(4);
});

test('bulk endpoint replica todos os slots de um módulo para outro módulo', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenantForTemplates('tpl-bulk-module');
    assignTenantAdminRoleForTemplates($user, $tenant->id);

    $template = PlanogramTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'BULK-MODULE',
        'name' => 'BULK-MODULE',
        'department' => 'TEST',
        'is_active' => true,
    ]);

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'BULK-MODULE-2M',
        'num_modules' => 2,
        'is_active' => true,
    ]);

    // Módulo 1 com 2 prateleiras
    $slots = [
        bulkSlotPayload(1, 1),
        [...bulkSlotPayload(1, 2)],
    ];
    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-bulk-module.'.config('app.landlord_domain')])
        ->post(route('tenant.planogram-templates.slots.bulk', [
            'subdomain' => 'tpl-bulk-module',
            'planogramTemplate' => $template->id,
            'planogramSubtemplate' => $subtemplate->id,
        ], false), ['slots' => $slots]);

    // Replica para o módulo 2
    $moduleCopy = [
        [...bulkSlotPayload(2, 1)],
        [...bulkSlotPayload(2, 2)],
    ];
    $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-bulk-module.'.config('app.landlord_domain')])
        ->post(route('tenant.planogram-templates.slots.bulk', [
            'subdomain' => 'tpl-bulk-module',
            'planogramTemplate' => $template->id,
            'planogramSubtemplate' => $subtemplate->id,
        ], false), ['slots' => $moduleCopy]);

    expect(PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $subtemplate->id)->where('module_number', 2)->count())->toBe(2);
    expect(PlanogramTemplateSlot::withoutGlobalScopes()->where('subtemplate_id', $subtemplate->id)->count())->toBe(4);
});

// ── helpers ──────────────────────────────────────────────────────────────────

function bulkSlotPayload(int $module, int $shelf): array
{
    return [
        'module_number' => $module,
        'shelf_order' => $shelf,
        'category_id' => null,
        'min_facings' => 1,
        'max_facings' => 5,
        'priority' => 1,
        'price_order' => 'none',
        'size_order' => 'none',
        'brand_exposure' => 'horizontal',
        'flavor_exposure' => 'horizontal',
        'space_fallback' => 'reduce_c',
        'use_target_stock' => false,
        'facing_expansion' => 'none',
    ];
}

function makeTenantForTemplates(string $subdomain): Tenant
{
    $conn = (string) config('database.default');
    $db = (array) config("database.connections.{$conn}");

    $tenant = Tenant::query()->create([
        'name' => strtoupper($subdomain),
        'slug' => $subdomain,
        'database' => (string) ($db['database'] ?? 'database.sqlite'),
        'status' => 'active',
    ]);

    $tenant->domains()->create([
        'host' => $subdomain.'.'.config('app.landlord_domain'),
        'type' => 'subdomain',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $tenant;
}

function assignTenantAdminRoleForTemplates(User $user, string $tenantId): void
{
    $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();

    setPermissionsTeamId($tenantId);
    $user->assignRole($role);
}

function createMinimalTemplateSpreadsheet(): string
{
    $spreadsheet = new Spreadsheet;

    // A=templateCode, B=department, C=subtemplateCode, D=numModules,
    // E=moduleNumber, F=shelfOrder, G=category, H=subcategory,
    // I=grouping, J=minFacings, K=priceOrder, L=sizeOrder,
    // M=brandExposure, N=flavorExposure, O=spaceFallback, P=useTargetStock
    $tplSheet = $spreadsheet->getActiveSheet();
    $tplSheet->setTitle('Templates');
    $tplSheet->fromArray([
        ['Código Template', 'Departamento', 'Código Subtemplate', 'Módulos', 'Módulo', 'Nível', 'Categoria', 'Subcategoria', 'Agrupamento', 'Faces Min', 'Ordem Preço', 'Ordem Tamanho', 'Exposição Marca', 'Exposição Sabor', 'Fallback Espaço', 'Usar Estoque'],
        ['LIMPEZA - 01', 'LIMPEZA', 'LAVA ROUPAS - 01', 1, 1, 1, 'LIMPEZA', 'LAVA ROUPAS', 'AMACIANTE', 2, 'Do mais barato', 'Do maior', 'Vertical', 'Horizontal', 'Reduzir facing', 'Sim'],
        ['LIMPEZA - 01', 'LIMPEZA', 'LAVA ROUPAS - 01', 1, 1, 2, 'LIMPEZA', 'LAVA ROUPAS', 'DETERGENTE', 1, 'Do mais caro', 'Do menor', 'Horizontal', 'Vertical', 'Curva C', 'Não'],
    ], null, 'A1');

    // A=ean, B=description, C=department, D=category, E=subcategory,
    // F=grouping, G=brand, H=packageType, I=packageContent
    $prodSheet = $spreadsheet->createSheet();
    $prodSheet->setTitle('Produtos');
    $prodSheet->fromArray([
        ['EAN', 'Descrição', 'Departamento', 'Categoria', 'Subcategoria', 'Agrupamento', 'Marca', 'Embalagem', 'Conteúdo'],
        ['7891000000001', 'Amaciante Marca X 2L', 'LIMPEZA', 'LIMPEZA', 'LAVA ROUPAS', 'AMACIANTE', 'MARCA X', 'Galão', '2L'],
    ], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'tpl_test_').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
