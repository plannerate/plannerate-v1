<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('planogram_subtemplates', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('template_id', 26);
            $table->string('code')->comment('Código subtemplate — col C do Excel');
            $table->unsignedTinyInteger('num_modules')->comment('Qtd módulos — col D');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'template_id', 'num_modules']);
            $table->index(['tenant_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planogram_subtemplates');
    }
};
