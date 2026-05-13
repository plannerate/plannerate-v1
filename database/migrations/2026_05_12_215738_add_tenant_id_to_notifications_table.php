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
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasColumn('notifications', 'tenant_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->char('tenant_id', 26)->nullable()->after('notifiable_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'tenant_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_tenant_id_index');
            $table->dropColumn('tenant_id');
        });
    }
};
