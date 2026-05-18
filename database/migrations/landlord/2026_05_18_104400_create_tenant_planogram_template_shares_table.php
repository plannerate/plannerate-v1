<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::create('tenant_planogram_template_shares', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('global_template_id')->comment('FK → global_planogram_templates');
            $table->ulid('tenant_id')->comment('FK → tenants');
            $table->timestamp('shared_at')->useCurrent();
            $table->ulid('shared_by')->nullable()->comment('FK → users');
            $table->timestamps();
            $table->unique(['global_template_id', 'tenant_id']);
            $table->index('global_template_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_planogram_template_shares');
    }
};
