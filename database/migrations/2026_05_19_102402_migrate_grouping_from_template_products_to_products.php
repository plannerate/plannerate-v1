<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $templateProducts = DB::connection('tenant')
            ->table('planogram_template_products')
            ->whereNotNull('product_id')
            ->whereNotNull('grouping')
            ->where('grouping', '!=', '')
            ->get(['product_id', 'grouping', 'grouping_normalized']);

        $updated = 0;
        foreach ($templateProducts as $tp) {
            $rows = DB::connection('tenant')
                ->table('products')
                ->where('id', $tp->product_id)
                ->whereNull('grouping')
                ->update([
                    'grouping' => $tp->grouping,
                    'grouping_normalized' => $tp->grouping_normalized,
                ]);
            $updated += $rows;
        }

        Log::info('Migração grouping concluída', [
            'registros_processados' => $templateProducts->count(),
            'produtos_atualizados' => $updated,
        ]);
    }

    public function down(): void
    {
        // Irreversível: não apaga grouping de produtos que possam ter sido editados manualmente
    }
};
