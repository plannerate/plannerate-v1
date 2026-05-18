<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::create('global_planogram_subtemplates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('template_id')->comment('FK → global_planogram_templates');
            $table->string('code')->comment('Código subtemplate — col C do Excel');
            $table->unsignedTinyInteger('num_modules')->comment('Qtd módulos — col D');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['template_id', 'num_modules']);
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_planogram_subtemplates');
    }
};
