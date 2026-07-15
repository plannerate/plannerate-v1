<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\FastRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Unit', 'Feature')
    ->use(FastRefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Tabela de propostas de reotimização no schema SQLite dos testes.
 *
 * Vive aqui, e não copiada dentro de cada builder de schema, porque QUALQUER teste que dispare
 * uma geração passa pelo GenerationQueueDispatcher — que invalida as propostas pendentes da
 * gôndola. Sem a tabela, a geração morre com "no such table" num teste que nem fala de
 * reotimização.
 */
function buildReoptimizationProposalsTable(): void
{
    Schema::connection('tenant')->create('planogram_reoptimization_proposals', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->string('tenant_id')->nullable();
        $table->string('planogram_id');
        $table->string('gondola_id');
        $table->string('generation_run_id')->nullable();
        $table->string('applied_run_id')->nullable();
        $table->string('status', 20)->default('pending');
        $table->string('trigger', 20)->default('scheduled');
        $table->json('config_snapshot')->nullable();
        $table->json('baseline_layout')->nullable();
        $table->string('baseline_hash', 64)->nullable();
        $table->json('proposed_layout')->nullable();
        $table->json('proposed_rejected')->nullable();
        $table->json('diff_summary')->nullable();
        $table->date('sales_period_start')->nullable();
        $table->date('sales_period_end')->nullable();
        $table->decimal('occupancy_before', 5, 4)->nullable();
        $table->decimal('occupancy_after', 5, 4)->nullable();
        $table->string('requested_by')->nullable();
        $table->string('reviewed_by')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamp('applied_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}
