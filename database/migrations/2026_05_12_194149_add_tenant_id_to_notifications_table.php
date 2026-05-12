<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create table if it doesn't exist
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->string('notifiable_id', 26);
                $table->string('tenant_id', 26)->nullable();
                $table->index(['notifiable_type', 'notifiable_id']);
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        } else {
            // Add column if table exists
            Schema::table('notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('notifications', 'tenant_id')) {
                    $table->string('tenant_id', 26)->nullable()->after('notifiable_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
