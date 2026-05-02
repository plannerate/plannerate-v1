<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->foreignUlid('gondola_id')->index();
            $table->string('name')->nullable();
            $table->string('code', 50)->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('num_shelves')->nullable();
            $table->integer('base_height')->default(17);
            $table->integer('base_depth')->default(40);
            $table->integer('base_width')->default(130);
            $table->decimal('cremalheira_width', 8, 2)->default(4.00);
            $table->decimal('hole_height', 8, 2)->default(2.00);
            $table->decimal('hole_width', 8, 2)->default(2.00);
            $table->decimal('hole_spacing', 8, 2)->default(2.00);
            $table->integer('ordering')->default(0);
            $table->enum('alignment', ['left', 'right', 'center', 'justify'])->nullable();
            $table->longText('settings')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            // gondola_id sem FK constraint para suportar multi-database
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
