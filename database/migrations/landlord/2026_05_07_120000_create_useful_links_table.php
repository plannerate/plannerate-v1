<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->create('useful_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('url');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->boolean('show_on_tenant_dashboard')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('useful_links');
    }
};
