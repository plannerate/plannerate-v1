<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->table('plan_items', function (Blueprint $table): void {
            $table->string('limit_message')->nullable()->after('value');
            $table->string('upgrade_url')->nullable()->after('limit_message');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_items', function (Blueprint $table): void {
            $table->dropColumn(['limit_message', 'upgrade_url']);
        });
    }
};
