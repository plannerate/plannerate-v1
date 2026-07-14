<?php

/**
 * Rotas de Templates de Planograma (CRUD, slots, subtemplates, import/export, review)
 * e de Regras de Produto (mandatory/blocked). Movidas de routes/tenant.php do app
 * para o pacote na Etapa 6 da refatoração — URIs, nomes e middlewares preservados.
 *
 * Passam pelo middleware tenant.client.redirect (área restrita, mesmo contrato original).
 */

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramProductRuleController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\PlanogramTemplateController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController;
use Illuminate\Support\Facades\Route;

// ── Planogram Templates ───────────────────────────────────────
Route::get('planogram-templates', [PlanogramTemplateController::class, 'index'])
    ->name('planogram-templates.index');
Route::get('planogram-templates/options', [PlanogramTemplateController::class, 'options'])
    ->name('planogram-templates.options');
Route::get('planogram-templates/import', [PlanogramTemplateController::class, 'importPage'])
    ->name('planogram-templates.import-page');
Route::post('planogram-templates/import', [PlanogramTemplateController::class, 'import'])
    ->name('planogram-templates.import');
Route::get('planogram-templates/export', [PlanogramTemplateController::class, 'exportAll'])
    ->name('planogram-templates.export-all');
Route::get('planogram-templates/create', [PlanogramTemplateController::class, 'create'])
    ->name('planogram-templates.create');
Route::post('planogram-templates', [PlanogramTemplateController::class, 'store'])
    ->name('planogram-templates.store');
Route::get('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'show'])
    ->name('planogram-templates.show');
Route::get('planogram-templates/{planogramTemplate}/edit', [PlanogramTemplateController::class, 'edit'])
    ->name('planogram-templates.edit');
Route::put('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'update'])
    ->name('planogram-templates.update');
Route::delete('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'destroy'])
    ->name('planogram-templates.destroy')
    ->withTrashed();
Route::post('planogram-templates/{planogramTemplate}/restore', [PlanogramTemplateController::class, 'restore'])
    ->name('planogram-templates.restore')
    ->withTrashed();
Route::get('planogram-templates/{planogramTemplate}/export', [PlanogramTemplateController::class, 'export'])
    ->name('planogram-templates.export');
Route::post('planogram-templates/{planogramTemplate}/promote', [PlanogramTemplateController::class, 'promote'])
    ->name('planogram-templates.promote');

// ── Template Slots (wizard etapa 2) ───────────────────────────
Route::get('planogram-templates/{planogramTemplate}/slots', [TemplateSlotController::class, 'index'])
    ->name('planogram-templates.slots.index');
Route::get('planogram-templates/{planogramTemplate}/review', [TemplateSlotController::class, 'review'])
    ->name('planogram-templates.slots.review');
Route::get('planogram-templates/{planogramTemplate}/slots/products', [TemplateSlotController::class, 'slotProducts'])
    ->name('planogram-templates.slots.products');
Route::get('planogram-templates/{planogramTemplate}/slots/analysis', [TemplateSlotController::class, 'slotAnalysis'])
    ->name('planogram-templates.slots.analysis');
Route::post('planogram-templates/{planogramTemplate}/slots/reorder', [TemplateSlotController::class, 'reorder'])
    ->name('planogram-templates.slots.reorder');
Route::post('planogram-templates/{planogramTemplate}/slots/sync-images', [TemplateSlotController::class, 'syncImages'])
    ->name('planogram-templates.slots.sync-images');
Route::put('planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}', [TemplateSlotController::class, 'updateSlot'])
    ->name('planogram-templates.slots.update');
Route::delete('planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}', [TemplateSlotController::class, 'destroySlot'])
    ->name('planogram-templates.slots.destroy');

// ── Template Subtemplates ─────────────────────────────────────
Route::post('planogram-templates/{planogramTemplate}/subtemplates', [TemplateSlotController::class, 'createSubtemplate'])
    ->name('planogram-templates.subtemplates.store');
Route::post('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone', [TemplateSlotController::class, 'cloneSubtemplate'])
    ->name('planogram-templates.subtemplates.clone');
Route::post('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots', [TemplateSlotController::class, 'storeSlot'])
    ->name('planogram-templates.slots.store');
Route::post('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots/bulk', [TemplateSlotController::class, 'bulkStoreSlots'])
    ->name('planogram-templates.slots.bulk');
Route::put('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults', [TemplateSlotController::class, 'updateSubtemplateSlotDefaults'])
    ->name('planogram-templates.subtemplates.slot-defaults.update');
Route::put('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings', [TemplateSlotController::class, 'updateSubtemplateSettings'])
    ->name('planogram-templates.subtemplates.settings.update');
Route::delete('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}', [TemplateSlotController::class, 'destroySubtemplate'])
    ->name('planogram-templates.subtemplates.destroy');

// ── Planogram Product Rules ───────────────────────────────────
Route::get('planogram-product-rules', [PlanogramProductRuleController::class, 'index'])
    ->name('planogram-product-rules.index');
Route::post('planogram-product-rules', [PlanogramProductRuleController::class, 'store'])
    ->name('planogram-product-rules.store');
Route::delete('planogram-product-rules/{planogramProductRule}', [PlanogramProductRuleController::class, 'destroy'])
    ->name('planogram-product-rules.destroy');
