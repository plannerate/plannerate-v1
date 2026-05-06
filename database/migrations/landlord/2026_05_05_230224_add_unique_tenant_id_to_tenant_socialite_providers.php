<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->table('tenant_socialite_providers', function (Blueprint $table): void {
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenant_socialite_providers', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id']);
        });
    }
};
