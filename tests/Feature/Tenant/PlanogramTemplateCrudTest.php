<?php

use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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
    expect($slots->first()->grouping)->toBe('AMACIANTE');
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
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
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

test('slot products endpoint returns only products from slot grouping_normalized', function (): void {
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

    $subtemplate = PlanogramSubtemplate::query()->create([
        'tenant_id' => $tenant->id,
        'template_id' => $template->id,
        'code' => 'TPL-SLOT-1M',
        'num_modules' => 1,
        'is_active' => true,
    ]);

    PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
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

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Match',
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Outro',
        'grouping' => 'BISCOITO',
        'grouping_normalized' => 'biscoito',
    ]);

    $response = $this
        ->withServerVariables(['HTTP_HOST' => 'tpl-slot-products.'.config('app.landlord_domain')])
        ->getJson(route('tenant.planogram-templates.slots.products', [
            'subdomain' => 'tpl-slot-products',
            'planogramTemplate' => $template->id,
            'grouping_normalized' => 'cereal-matinal',
        ], false));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Produto Match')
        ->assertJsonPath('data.0.grouping_normalized', 'cereal-matinal');
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

    $slot = PlanogramTemplateSlot::query()->create([
        'tenant_id' => $tenant->id,
        'subtemplate_id' => $subtemplate->id,
        'module_number' => 1,
        'shelf_order' => 1,
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
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
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
        'width' => 20,
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'name' => 'Produto Fora',
        'grouping' => 'CEREAL MATINAL',
        'grouping_normalized' => 'cereal-matinal',
        'width' => 80,
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
        'grouping' => 'AMACIANTE',
        'grouping_normalized' => 'amaciante',
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

// ── helpers ──────────────────────────────────────────────────────────────────

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
