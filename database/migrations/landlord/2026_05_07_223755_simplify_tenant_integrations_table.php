<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection($this->connection)->table('tenant_integrations', function (Blueprint $table): void {
            $table->dropColumn([
                'external_name',
                'external_name_ean',
                'external_name_status',
                'external_name_sale_date',
                'http_method',
                'api_url',
                'authentication_headers',
                'authentication_body',
            ]);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tenant_integrations', function (Blueprint $table): void {
            $table->string('external_name')->nullable()->after('identifier');
            $table->string('external_name_ean')->nullable()->after('external_name');
            $table->string('external_name_status')->nullable()->after('external_name_ean');
            $table->string('external_name_sale_date')->nullable()->after('external_name_status');
            $table->string('http_method')->default('POST')->after('external_name_sale_date');
            $table->string('api_url')->nullable()->after('http_method');
            $table->json('authentication_headers')->nullable()->after('api_url');
            $table->json('authentication_body')->nullable()->after('authentication_headers');
        });
    }
};
