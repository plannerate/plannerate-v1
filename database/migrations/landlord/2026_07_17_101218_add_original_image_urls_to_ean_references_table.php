<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda a URL do arquivo ORIGINAL de cada ângulo, arquivado no disco S3 (do)
 * no upload. As colunas image_{angle}_url continuam apontando para a cópia
 * padronizada (WebP dentro do teto de config('plannerate.image.max_side')) que
 * a UI/gôndola consome; as _original_url permitem re-derivar a cópia em
 * qualquer tamanho depois, sem re-baixar do S3/web.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::table('ean_references', function (Blueprint $table): void {
            if (! Schema::hasColumn('ean_references', 'image_front_original_url')) {
                $table->string('image_front_original_url')->nullable();
            }

            if (! Schema::hasColumn('ean_references', 'image_side_original_url')) {
                $table->string('image_side_original_url')->nullable();
            }

            if (! Schema::hasColumn('ean_references', 'image_top_original_url')) {
                $table->string('image_top_original_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ean_references', function (Blueprint $table): void {
            $table->dropColumn([
                'image_front_original_url',
                'image_side_original_url',
                'image_top_original_url',
            ]);
        });
    }
};
