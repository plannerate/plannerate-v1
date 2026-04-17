<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige índices unique sem tenant_id em tabelas multi-tenant.
 * Substitui unique simples por unique composto ['tenant_id', 'campo']
 * para evitar colisões de dados entre tenants diferentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // users: email e slug únicos por tenant
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_email_unique')) {
                $table->dropUnique('users_email_unique');
            }
            if (Schema::hasIndex('users', 'users_slug_unique')) {
                $table->dropUnique('users_slug_unique');
            }
            if (! Schema::hasIndex('users', 'users_tenant_email_unique')) {
                $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            }
            if (! Schema::hasIndex('users', 'users_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'users_tenant_slug_unique');
            }
        });

        // stores: slug único por tenant; code já tem ['client_id', 'code'] — remove único simples
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasIndex('stores', 'stores_slug_unique')) {
                $table->dropUnique('stores_slug_unique');
            }
            if (Schema::hasIndex('stores', 'stores_code_unique')) {
                $table->dropUnique('stores_code_unique');
            }
            if (! Schema::hasIndex('stores', 'stores_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'stores_tenant_slug_unique');
            }
        });

        // clients: slug único por tenant
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasIndex('clients', 'clients_slug_unique')) {
                $table->dropUnique('clients_slug_unique');
            }
            if (! Schema::hasIndex('clients', 'clients_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'clients_tenant_slug_unique');
            }
        });

        // clusters: slug único por tenant
        Schema::table('clusters', function (Blueprint $table) {
            if (Schema::hasIndex('clusters', 'clusters_slug_unique')) {
                $table->dropUnique('clusters_slug_unique');
            }
            if (! Schema::hasIndex('clusters', 'clusters_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'clusters_tenant_slug_unique');
            }
        });

        // images: slug único por tenant
        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasIndex('images', 'images_slug_unique')) {
                $table->dropUnique('images_slug_unique');
            }
            if (! Schema::hasIndex('images', 'images_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'images_tenant_slug_unique');
            }
        });

        // permissions: slug único por tenant (null = global)
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasIndex('permissions', 'permissions_slug_unique')) {
                $table->dropUnique('permissions_slug_unique');
            }
            if (! Schema::hasIndex('permissions', 'permissions_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'permissions_tenant_slug_unique');
            }
        });

        // roles: slug único por tenant (null = global)
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasIndex('roles', 'roles_slug_unique')) {
                $table->dropUnique('roles_slug_unique');
            }
            if (! Schema::hasIndex('roles', 'roles_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'roles_tenant_slug_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasIndex('roles', 'roles_tenant_slug_unique')) {
                $table->dropUnique('roles_tenant_slug_unique');
            }
            if (! Schema::hasIndex('roles', 'roles_slug_unique')) {
                $table->unique('slug', 'roles_slug_unique');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasIndex('permissions', 'permissions_tenant_slug_unique')) {
                $table->dropUnique('permissions_tenant_slug_unique');
            }
            if (! Schema::hasIndex('permissions', 'permissions_slug_unique')) {
                $table->unique('slug', 'permissions_slug_unique');
            }
        });

        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasIndex('images', 'images_tenant_slug_unique')) {
                $table->dropUnique('images_tenant_slug_unique');
            }
            if (! Schema::hasIndex('images', 'images_slug_unique')) {
                $table->unique('slug', 'images_slug_unique');
            }
        });

        Schema::table('clusters', function (Blueprint $table) {
            if (Schema::hasIndex('clusters', 'clusters_tenant_slug_unique')) {
                $table->dropUnique('clusters_tenant_slug_unique');
            }
            if (! Schema::hasIndex('clusters', 'clusters_slug_unique')) {
                $table->unique('slug', 'clusters_slug_unique');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasIndex('clients', 'clients_tenant_slug_unique')) {
                $table->dropUnique('clients_tenant_slug_unique');
            }
            if (! Schema::hasIndex('clients', 'clients_slug_unique')) {
                $table->unique('slug', 'clients_slug_unique');
            }
        });

        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasIndex('stores', 'stores_tenant_slug_unique')) {
                $table->dropUnique('stores_tenant_slug_unique');
            }
            if (! Schema::hasIndex('stores', 'stores_slug_unique')) {
                $table->unique('slug', 'stores_slug_unique');
            }
            if (! Schema::hasIndex('stores', 'stores_code_unique')) {
                $table->unique('code', 'stores_code_unique');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_tenant_email_unique')) {
                $table->dropUnique('users_tenant_email_unique');
            }
            if (Schema::hasIndex('users', 'users_tenant_slug_unique')) {
                $table->dropUnique('users_tenant_slug_unique');
            }
            if (! Schema::hasIndex('users', 'users_email_unique')) {
                $table->unique('email', 'users_email_unique');
            }
            if (! Schema::hasIndex('users', 'users_slug_unique')) {
                $table->unique('slug', 'users_slug_unique');
            }
        });
    }
};
