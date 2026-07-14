<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ProposalPruner;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ReoptimizationScheduler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

/**
 * Expirar proposta abandonada e descartar snapshot já inútil.
 *
 * A expiração NÃO é faxina: o agendador não analisa gôndola com proposta pendente, então uma
 * proposta que ninguém decide trava a reotimização daquela gôndola para sempre. Sem expirar, o
 * usuário que ignora uma proposta desliga a feature na gôndola sem perceber.
 */
function makePrunableProposal(array $attributes = []): PlanogramReoptimizationProposal
{
    $proposal = PlanogramReoptimizationProposal::create(array_merge([
        'planogram_id' => (string) Str::ulid(),
        'gondola_id' => (string) Str::ulid(),
        'status' => ProposalStatus::Pending,
        'baseline_layout' => ['version' => 1, 'segments' => []],
        'proposed_layout' => ['version' => 1, 'segments' => []],
        'proposed_rejected' => [],
        'diff_summary' => ['entries' => [], 'summary' => [], 'has_changes' => true],
    ], $attributes));

    // created_at/updated_at são preenchidos automaticamente; envelhecer exige forceFill.
    if (isset($attributes['created_at']) || isset($attributes['updated_at'])) {
        $proposal->forceFill(array_intersect_key($attributes, array_flip(['created_at', 'updated_at'])))->save();
    }

    return $proposal->fresh();
}

beforeEach(function (): void {
    fakeReoptimizationTenant();
    buildProposalSchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('proposta pendente e abandonada expira', function (): void {
    $old = makePrunableProposal(['created_at' => now()->subDays(ProposalPruner::EXPIRE_AFTER_DAYS + 1)]);
    $recent = makePrunableProposal(['created_at' => now()->subDay()]);

    $result = app(ProposalPruner::class)->prune();

    expect($result['expired'])->toBe(1)
        ->and($old->fresh()->status)->toBe(ProposalStatus::Expired)
        ->and($recent->fresh()->status)->toBe(ProposalStatus::Pending);
});

/**
 * O ponto todo da expiração. Sem ela, esta gôndola nunca mais seria analisada.
 */
test('expirar a proposta abandonada destrava o agendador para aquela gôndola', function (): void {
    Queue::fake();

    $gondola = makeReoptGondola();
    makeCompletedRun($gondola);

    makePrunableProposal([
        'gondola_id' => $gondola->id,
        'planogram_id' => $gondola->planogram_id,
        'created_at' => now()->subDays(ProposalPruner::EXPIRE_AFTER_DAYS + 1),
    ]);

    $scheduler = app(ReoptimizationScheduler::class);

    // Presa: a proposta pendente bloqueia a análise.
    expect($scheduler->eligibleGondolas())->toBeEmpty();

    app(ProposalPruner::class)->prune();

    // Destravada: volta a ser elegível e a análise pode rodar.
    expect($scheduler->eligibleGondolas()->pluck('id')->all())->toBe([$gondola->id]);

    expect($scheduler->enqueue($gondola, GenerationRunTrigger::Scheduled))->not->toBeNull();
});

test('snapshots de proposta decidida há tempo suficiente são descartados, mas a linha fica', function (): void {
    $decided = makePrunableProposal([
        'status' => ProposalStatus::Rejected,
        'rejection_reason' => 'Marca própria precisa ficar na altura dos olhos.',
        'updated_at' => now()->subDays(ProposalPruner::DISCARD_SNAPSHOTS_AFTER_DAYS + 1),
    ]);

    $result = app(ProposalPruner::class)->prune();

    expect($result['snapshots_discarded'])->toBe(1);

    $fresh = $decided->fresh();

    // Os blobs (~150 KB) vão embora...
    expect($fresh->proposed_layout)->toBeNull()
        ->and($fresh->baseline_layout)->toBeNull();

    // ...mas o histórico fica: é ele que ensina a ajustar o template.
    expect($fresh->status)->toBe(ProposalStatus::Rejected)
        ->and($fresh->rejection_reason)->toBe('Marca própria precisa ficar na altura dos olhos.')
        ->and($fresh->diff_summary)->not->toBeNull();
});

test('proposta pendente NUNCA perde o snapshot — é o que a aprovação aplica', function (): void {
    // Mesmo velha: enquanto for pendente, o layout proposto precisa existir. Descartá-lo deixaria
    // uma proposta aprovável que não sabe o que aplicar.
    $pending = makePrunableProposal([
        'created_at' => now()->subDays(ProposalPruner::EXPIRE_AFTER_DAYS - 1),
        'updated_at' => now()->subDays(ProposalPruner::EXPIRE_AFTER_DAYS - 1),
    ]);

    app(ProposalPruner::class)->prune();

    expect($pending->fresh()->proposed_layout)->not->toBeNull();
});

test('proposta decidida recentemente mantém o snapshot para investigação', function (): void {
    $recent = makePrunableProposal([
        'status' => ProposalStatus::Applied,
        'updated_at' => now()->subDay(),
    ]);

    $result = app(ProposalPruner::class)->prune();

    expect($result['snapshots_discarded'])->toBe(0)
        ->and($recent->fresh()->proposed_layout)->not->toBeNull();
});
