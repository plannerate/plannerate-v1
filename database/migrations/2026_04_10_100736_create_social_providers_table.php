<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('raptor.tables.social_providers', 'social_providers');

        Schema::connection(config('raptor.database.landlord_connection_name', 'landlord'))
            ->create($tableName, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('tenant_id')->constrained(config('raptor.tables.tenants', 'tenants'))->cascadeOnDelete()->comment('Tenant ao qual o provedor pertence');
                $table->string('name')->comment('Nome/label exibido no botão de login');
                $table->string('slug')->comment('Identificador único do provedor por tenant');
                $table->string('provider')->comment('Driver Socialite: google, facebook, github, twitter, linkedin');
                $table->text('client_id')->comment('OAuth Client ID');
                $table->text('client_secret')->comment('OAuth Client Secret — armazenado criptografado');
                $table->string('redirect_uri')->nullable()->comment('URI de callback OAuth');
                $table->json('scopes')->nullable()->comment('Escopos adicionais');
                $table->string('status')->default('draft')->comment('draft = inativo, published = ativo');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'provider'], 'social_providers_tenant_provider_unique');
                $table->unique(['tenant_id', 'slug'], 'social_providers_tenant_slug_unique');
            });
    }

    public function down(): void
    {
        Schema::connection(config('raptor.database.landlord_connection_name', 'landlord'))
            ->dropIfExists(config('raptor.tables.social_providers', 'social_providers'));
    }
};
