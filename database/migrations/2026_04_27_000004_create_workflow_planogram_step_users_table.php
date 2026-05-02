<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('workflow_planogram_step_users', function (Blueprint $table) {
            $table->foreignUlid('workflow_planogram_step_id')->constrained('workflow_planogram_steps')->cascadeOnDelete();
            $table->ulid('user_id');
            $table->timestamps();

            $table->unique(['workflow_planogram_step_id', 'user_id'], 'wf_planogram_step_users_unique');
            $table->index('workflow_planogram_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_planogram_step_users');
    }
};
