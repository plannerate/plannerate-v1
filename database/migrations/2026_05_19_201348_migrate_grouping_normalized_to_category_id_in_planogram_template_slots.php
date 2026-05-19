<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $slots = DB::connection('tenant')
            ->table('planogram_template_slots')
            ->whereNull('category_id')
            ->whereNotNull('grouping_normalized')
            ->where('grouping_normalized', '!=', '')
            ->get(['id', 'tenant_id', 'grouping_normalized', 'grouping']);

        foreach ($slots as $slot) {
            $parts = explode('|', (string) $slot->grouping_normalized);
            $categoryName = mb_strtolower(trim((string) end($parts)), 'UTF-8');

            $category = DB::connection('tenant')
                ->table('categories')
                ->whereNull('deleted_at')
                ->where('tenant_id', $slot->tenant_id)
                ->whereRaw('LOWER(name) = ?', [$categoryName])
                ->first(['id', 'name']);

            if ($category) {
                DB::connection('tenant')
                    ->table('planogram_template_slots')
                    ->where('id', $slot->id)
                    ->update(['category_id' => $category->id]);

                Log::info("Slot migrado: {$slot->grouping} → {$category->name} ({$category->id})");
            } else {
                Log::warning("Slot sem categoria encontrada: {$slot->grouping_normalized}");
            }
        }
    }

    public function down(): void
    {
        DB::connection('tenant')
            ->table('planogram_template_slots')
            ->update(['category_id' => null]);
    }
};
