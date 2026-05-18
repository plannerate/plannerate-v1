<?php

namespace App\Services\AutoPlanogram\Template;

use App\Models\GlobalPlanogramTemplate;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateProduct;
use App\Models\PlanogramTemplateSlot;
use App\Models\Tenant;
use App\Models\TenantPlanogramTemplateShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

final class CopyGlobalTemplateToTenantService
{
    public function copy(GlobalPlanogramTemplate $globalTemplate, Tenant $tenant, User $sharedBy): void
    {
        $alreadyShared = TenantPlanogramTemplateShare::where('global_template_id', $globalTemplate->getKey())
            ->where('tenant_id', $tenant->getKey())
            ->exists();

        if ($alreadyShared) {
            return;
        }

        $globalTemplate->load(['subtemplates.slots', 'templateProducts']);

        $this->runInTenantContext($tenant, function () use ($globalTemplate, $tenant, $sharedBy): void {
            DB::transaction(function () use ($globalTemplate, $tenant, $sharedBy): void {
                $tenantId = $tenant->getKey();

                $tenantTemplate = PlanogramTemplate::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'global_template_id' => $globalTemplate->getKey(),
                    'code' => $globalTemplate->code,
                    'name' => $globalTemplate->name,
                    'department' => $globalTemplate->department,
                    'description' => $globalTemplate->description,
                    'is_active' => $globalTemplate->is_active,
                    'created_by' => $sharedBy->getKey(),
                ]);

                foreach ($globalTemplate->subtemplates as $globalSub) {
                    $tenantSub = PlanogramSubtemplate::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'template_id' => $tenantTemplate->getKey(),
                        'code' => $globalSub->code,
                        'num_modules' => $globalSub->num_modules,
                        'description' => $globalSub->description,
                        'is_active' => $globalSub->is_active,
                    ]);

                    foreach ($globalSub->slots as $slot) {
                        PlanogramTemplateSlot::create([
                            'tenant_id' => $tenantId,
                            'subtemplate_id' => $tenantSub->getKey(),
                            'module_number' => $slot->module_number,
                            'shelf_order' => $slot->shelf_order,
                            'category' => $slot->category,
                            'subcategory' => $slot->subcategory,
                            'grouping' => $slot->grouping,
                            'grouping_normalized' => $slot->grouping_normalized,
                            'min_facings' => $slot->min_facings,
                            'priority' => $slot->priority,
                            'price_order' => $slot->price_order,
                            'size_order' => $slot->size_order,
                            'brand_exposure' => $slot->brand_exposure,
                            'flavor_exposure' => $slot->flavor_exposure,
                            'space_fallback' => $slot->space_fallback,
                            'use_target_stock' => $slot->use_target_stock,
                            'ordering' => $slot->ordering,
                        ]);
                    }
                }

                foreach ($globalTemplate->templateProducts as $product) {
                    PlanogramTemplateProduct::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'template_id' => $tenantTemplate->getKey(),
                        'ean' => $product->ean,
                        'description' => $product->description,
                        'department' => $product->department,
                        'category' => $product->category,
                        'subcategory' => $product->subcategory,
                        'grouping' => $product->grouping,
                        'grouping_normalized' => $product->grouping_normalized,
                        'brand' => $product->brand,
                        'package_type' => $product->package_type,
                        'package_content' => $product->package_content,
                    ]);
                }

                TenantPlanogramTemplateShare::create([
                    'global_template_id' => $globalTemplate->getKey(),
                    'tenant_id' => $tenantId,
                    'shared_at' => now(),
                    'shared_by' => $sharedBy->getKey(),
                ]);
            });
        });
    }

    private function runInTenantContext(Tenant $tenant, callable $callback): void
    {
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
        $originalTenantDatabase = config("database.connections.{$tenantConnectionName}.database");
        $originalTenant = CurrentTenantModel::current();
        $tenant->makeCurrent();

        try {
            $callback();
        } finally {
            if ($originalTenant !== null) {
                $originalTenant->makeCurrent();
            } else {
                CurrentTenantModel::forgetCurrent();
                config(["database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase]);
                DB::purge($tenantConnectionName);
            }
        }
    }
}
