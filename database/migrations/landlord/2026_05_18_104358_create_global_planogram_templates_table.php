<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::create('global_planogram_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique()->comment('Código único — col A do Excel');
            $table->string('name');
            $table->string('department')->comment('Departamento mercadológico — col B');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable()->comment('FK → users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_planogram_templates');
    }
};
