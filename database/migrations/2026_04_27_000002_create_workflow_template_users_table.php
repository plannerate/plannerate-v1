<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_template_users', function (Blueprint $table) { 
            $table->foreignUlid('workflow_template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->ulid('user_id');
            $table->timestamps();

            $table->unique(['workflow_template_id', 'user_id'], 'wf_template_users_unique');
            $table->index('workflow_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_template_users');
    }
};
