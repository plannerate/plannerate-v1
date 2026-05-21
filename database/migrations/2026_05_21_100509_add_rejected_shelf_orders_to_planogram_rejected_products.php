<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_rejected_products', function (Blueprint $table) {
            $table->json('rejected_shelf_orders')->nullable()->after('shelf_order')
                ->comment('Lista de shelf_orders onde o produto foi rejeitado (pode ser >1 quando a categoria aparece em múltiplos slots)');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::connection($this->connection)->table('planogram_rejected_products', function (Blueprint $table) {
            $table->dropColumn('rejected_shelf_orders');
        });
    }
};
