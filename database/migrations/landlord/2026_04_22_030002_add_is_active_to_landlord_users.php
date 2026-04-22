<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('users')
            || Schema::connection($this->connection)->hasColumn('users', 'is_active')) {
            return;
        }

        Schema::connection($this->connection)->table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('users')
            || ! Schema::connection($this->connection)->hasColumn('users', 'is_active')) {
            return;
        }

        Schema::connection($this->connection)->table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
