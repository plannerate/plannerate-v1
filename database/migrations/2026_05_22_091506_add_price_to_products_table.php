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

        if (Schema::connection($this->connection)->hasColumn('products', 'price')) {
            return;
        }

        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable()->after('current_stock')
                ->comment('Preço de venda — usado para ordenação por preço no planograma');
        });
    }

    public function down(): void
    {
        if (DB::connection($this->connection)->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn('products', 'price')) {
            return;
        }

        Schema::connection($this->connection)->table('products', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
