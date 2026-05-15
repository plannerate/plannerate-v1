<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('gondola_id')->nullable()->index();
            $table->foreignUlid('segment_id')->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_notes');
    }
};
