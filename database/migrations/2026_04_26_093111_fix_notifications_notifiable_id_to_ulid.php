<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
            $table->string('notifiable_id', 26)->change();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
            $table->unsignedBigInteger('notifiable_id')->change();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }
};
