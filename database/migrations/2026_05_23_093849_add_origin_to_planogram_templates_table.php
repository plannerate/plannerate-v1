<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('planogram_templates', function (Blueprint $table): void {
            $table->string('origin')->nullable()->after('is_active');
            $table->char('source_gondola_id', 26)->nullable()->after('origin');
            $table->index('origin');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('planogram_templates', function (Blueprint $table): void {
            $table->dropIndex(['origin']);
            $table->dropColumn(['origin', 'source_gondola_id']);
        });
    }
};
