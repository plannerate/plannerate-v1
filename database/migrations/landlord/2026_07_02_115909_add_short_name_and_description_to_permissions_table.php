<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        Schema::connection($this->connection)->table($table, function (Blueprint $table): void {
            $table->string('short_name', 150)->nullable()->after('name');
            $table->text('description')->nullable()->after('short_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        Schema::connection($this->connection)->table($table, function (Blueprint $table): void {
            $table->dropColumn(['short_name', 'description']);
        });
    }
};
