<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_templates', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->string('code')->comment('Código único — col A do Excel');
            $table->string('name');
            $table->string('department')->comment('Departamento mercadológico — col B');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable()->comment('FK → users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_templates');
    }
};
