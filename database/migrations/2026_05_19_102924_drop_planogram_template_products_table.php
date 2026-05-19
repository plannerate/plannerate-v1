<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->dropIfExists('planogram_template_products');
    }

    public function down(): void
    {
        // Recriação omitida — use backup se necessário
    }
};
