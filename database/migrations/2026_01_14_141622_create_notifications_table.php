<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            // Usa ULID para notifiable_id (compatível com User que usa ULID)
            $table->ulidMorphs('notifiable');
            $table->ulid('tenant_id')->nullable()->index();
            $table->ulid('client_id')->nullable()->index();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'notifiable_type', 'notifiable_id'], 'notifications_tenant_notifiable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
