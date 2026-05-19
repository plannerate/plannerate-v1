<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection('tenant')
            ->table('planogram_template_slots')
            ->select(['id', 'grouping'])
            ->orderBy('id')
            ->get()
            ->each(function (object $slot): void {
                $grouping = trim((string) $slot->grouping);

                DB::connection('tenant')
                    ->table('planogram_template_slots')
                    ->where('id', $slot->id)
                    ->update([
                        'grouping_normalized' => $grouping === '' ? null : Str::slug($grouping),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planogram_template_slots', function () {
            // Irreversível: apenas recalcula grouping_normalized a partir de grouping.
        });
    }
};
