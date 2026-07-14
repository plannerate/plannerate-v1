<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\GondolaLayoutReader;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutHasher;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot\LayoutSnapshotSerializer;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Exceptions\StaleProposalException;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\RejectProposalRequest;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ProposalApplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

/**
 * Aprovar uma proposta APLICA o snapshot revisado — não recalcula.
 *
 * Recalcular na aprovação pareceria equivalente, mas as vendas mudam entre a análise e a decisão:
 * o usuário aprovaria um diff e receberia outro layout. É o pior tipo de bug, porque o sistema
 * pareceria estar funcionando.
 *
 * E se a gôndola mudou desde a análise, aplicar sobrescreveria o trabalho de outra pessoa com um
 * layout construído sobre um "antes" que não existe mais. Aqui isso é recusa, não aviso.
 */

/**
 * Gera o planograma de verdade (persistido) e devolve a gôndola com o layout aplicado —
 * o "antes" que a proposta vai propor mudar.
 *
 * @return array{gondola: Gondola, input: mixed}
 */
function makeGeneratedGondola(): array
{
    ['input' => $input, 'products' => $products, 'templateId' => $templateId] = makeTemplateScenario();
    bindDeterministicScorer($products);

    app(AutoPlanogramService::class)->generate($input);

    $gondola = new Gondola;
    $gondola->forceFill([
        'id' => $input->gondolaId,
        'planogram_id' => $input->planogramId,
        'name' => 'Gôndola de teste',
        'template_id' => $templateId,
        'generation_mode' => 'template',
    ])->save();

    return ['gondola' => $gondola->fresh(), 'input' => $input];
}

/**
 * Cria uma proposta pendente cujo layout proposto é o atual com UMA mudança concreta:
 * o primeiro segmento ganha uma frente a mais.
 */
function makePendingProposal(Gondola $gondola, string $planogramId): PlanogramReoptimizationProposal
{
    $baseline = app(GondolaLayoutReader::class)->read($gondola);
    $serializer = app(LayoutSnapshotSerializer::class);

    $baselineSnapshot = $serializer->toArray($baseline);
    $proposedSnapshot = $baselineSnapshot;
    $proposedSnapshot['segments'][0]['layers'][0]['quantity'] += 1;

    return PlanogramReoptimizationProposal::create([
        'planogram_id' => $planogramId,
        'gondola_id' => $gondola->id,
        'status' => ProposalStatus::Pending,
        'baseline_layout' => $baselineSnapshot,
        'baseline_hash' => app(LayoutHasher::class)->hash($baseline),
        'proposed_layout' => $proposedSnapshot,
        'proposed_rejected' => [],
        'diff_summary' => ['entries' => [], 'summary' => [], 'has_changes' => true],
    ]);
}

beforeEach(function (): void {
    fakeReoptimizationTenant();
    buildProposalSchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('aprovar aplica EXATAMENTE o layout revisado', function (): void {
    ['gondola' => $gondola, 'input' => $input] = makeGeneratedGondola();

    $proposal = makePendingProposal($gondola, $input->planogramId);
    $expected = $proposal->proposed_layout;

    app(ProposalApplier::class)->apply($proposal, userId: null);

    // O que está na gôndola tem que bater com o snapshot aprovado — não com um recálculo.
    $applied = app(LayoutSnapshotSerializer::class)->toArray(
        app(GondolaLayoutReader::class)->read($gondola->fresh())
    );

    expect(collect($applied['segments'])->flatMap(fn (array $s) => $s['layers'])->sum('quantity'))
        ->toBe(collect($expected['segments'])->flatMap(fn (array $s) => $s['layers'])->sum('quantity'))
        ->and(count($applied['segments']))->toBe(count($expected['segments']));

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Applied)
        ->and($proposal->fresh()->applied_at)->not->toBeNull();
});

test('aprovar registra um run `apply` — o editor precisa saber que a gôndola mudou', function (): void {
    ['gondola' => $gondola, 'input' => $input] = makeGeneratedGondola();

    $proposal = makePendingProposal($gondola, $input->planogramId);

    app(ProposalApplier::class)->apply($proposal, userId: null);

    $appliedRunId = $proposal->fresh()->applied_run_id;

    expect($appliedRunId)->not->toBeNull();

    $run = DB::connection('tenant')->table('planogram_generation_runs')->where('id', $appliedRunId)->first();

    expect($run->kind)->toBe('apply')
        ->and($run->status)->toBe('completed');
});

test('proposta desatualizada é recusada e não sobrescreve a gôndola', function (): void {
    ['gondola' => $gondola, 'input' => $input] = makeGeneratedGondola();

    $proposal = makePendingProposal($gondola, $input->planogramId);

    // Alguém edita a gôndola depois da análise: o diff revisado passa a descrever um "antes"
    // que não existe mais.
    Layer::query()->first()->forceFill(['quantity' => 99])->save();

    $segmentsBefore = Segment::count();
    $layersBefore = Layer::pluck('quantity')->sort()->values()->all();

    expect(fn () => app(ProposalApplier::class)->apply($proposal, userId: null))
        ->toThrow(StaleProposalException::class);

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Superseded)
        ->and(Segment::count())->toBe($segmentsBefore)
        ->and(Layer::pluck('quantity')->sort()->values()->all())->toBe($layersBefore);
});

test('proposta já decidida não é aplicada duas vezes', function (): void {
    ['gondola' => $gondola, 'input' => $input] = makeGeneratedGondola();

    $proposal = makePendingProposal($gondola, $input->planogramId);
    $proposal->forceFill(['status' => ProposalStatus::Rejected])->save();

    expect(fn () => app(ProposalApplier::class)->apply($proposal, userId: null))
        ->toThrow(StaleProposalException::class);
});

test('rejeitar exige motivo', function (): void {
    $rules = (new RejectProposalRequest)->rules();

    expect(Validator::make([], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['reason' => Str::repeat('a', 501)], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['reason' => 'Marca própria precisa ficar na altura dos olhos.'], $rules)->fails())->toBeFalse();
});
