<?php

use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunKind;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunStatus;
use Callcocam\LaravelRaptorPlannerate\Enums\GenerationRunTrigger;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Jobs\GenerateReoptimizationProposalJob;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Callcocam\LaravelRaptorPlannerate\Services\Reoptimization\ReoptimizationScheduler;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

/**
 * Quem o agendador escolhe reprocessar — e, principalmente, quem ele NÃO escolhe.
 *
 * Cada exclusão aqui evita um estrago concreto: reprocessar uma gôndola sem template deixaria
 * rastro no banco (o modo automático sintetiza o template), empilhar uma segunda proposta sobre
 * uma pendente faria as duas partirem do mesmo baseline, e disparar durante uma geração em curso
 * produziria um diff contra um layout que está sendo reescrito naquele instante.
 */
beforeEach(function (): void {
    fakeReoptimizationTenant();
    buildProposalSchema();
    Queue::fake();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('gôndola habilitada e vencida é elegível', function (): void {
    $gondola = makeReoptGondola();
    makeCompletedRun($gondola);

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas()->pluck('id')->all())
        ->toBe([$gondola->id]);
});

test('gôndola desabilitada não é elegível', function (): void {
    makeCompletedRun(makeReoptGondola(['reoptimization_enabled' => false]));

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas())->toBeEmpty();
});

test('gôndola com cadência futura não é elegível', function (): void {
    makeCompletedRun(makeReoptGondola(['reoptimization_next_run_at' => now()->addWeek()]));

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas())->toBeEmpty();
});

test('gôndola sem template não é elegível — o dry-run só é seguro em modo template', function (): void {
    makeCompletedRun(makeReoptGondola(['template_id' => null, 'generation_mode' => 'manual']));

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas())->toBeEmpty();
});

test('gôndola com proposta pendente não é elegível', function (): void {
    $gondola = makeReoptGondola();
    makeCompletedRun($gondola);

    PlanogramReoptimizationProposal::create([
        'planogram_id' => $gondola->planogram_id,
        'gondola_id' => $gondola->id,
        'status' => ProposalStatus::Pending,
    ]);

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas())->toBeEmpty();
});

test('gôndola com geração em curso não é elegível', function (): void {
    $gondola = makeReoptGondola();
    makeCompletedRun($gondola);
    makeCompletedRun($gondola, ['status' => GenerationRunStatus::Running]);

    expect(app(ReoptimizationScheduler::class)->eligibleGondolas())->toBeEmpty();
});

test('gôndola nunca gerada não é enfileirada — não há configuração para reusar', function (): void {
    $gondola = makeReoptGondola();

    expect(app(ReoptimizationScheduler::class)->enqueue($gondola))->toBeNull();

    Queue::assertNothingPushed();
});

test('enfileirar cria um run de proposta, avança a cadência e desloca a janela de vendas', function (): void {
    $gondola = makeReoptGondola();

    // A geração original é de 3 meses atrás. A janela desloca-se preservando a DEFASAGEM em
    // relação ao dia da configuração — reprocessar no mesmo dia devolveria a mesma janela (e o
    // mesmo planograma), que é exatamente o que a cadência existe para evitar.
    makeCompletedRun($gondola)->forceFill(['created_at' => now()->subMonths(3)])->save();

    $run = app(ReoptimizationScheduler::class)->enqueue($gondola);

    expect($run)->not->toBeNull()
        ->and($run->kind)->toBe(GenerationRunKind::Proposal)
        ->and($run->trigger)->toBe(GenerationRunTrigger::Scheduled);

    // A janela andou: reusar as datas congeladas devolveria o mesmo planograma.
    expect($run->config_snapshot['end_date'])->not->toBe('2025-03-31');

    // Cadência avançada no ENFILEIRAMENTO: se o job falhar, a gôndola não fica presa
    // disparando uma análise nova a cada rodada do agendador.
    $fresh = $gondola->fresh();
    expect($fresh->reoptimization_next_run_at->isFuture())->toBeTrue()
        ->and($fresh->reoptimization_last_run_at)->not->toBeNull();

    Queue::assertPushed(GenerateReoptimizationProposalJob::class, 1);
});

test('rodar o agendador duas vezes não enfileira a mesma gôndola duas vezes', function (): void {
    $gondola = makeReoptGondola();
    makeCompletedRun($gondola);

    $scheduler = app(ReoptimizationScheduler::class);

    foreach ($scheduler->eligibleGondolas() as $eligible) {
        $scheduler->enqueue($eligible);
    }

    // Segunda passada do comando, no mesmo dia: a cadência já foi avançada.
    expect($scheduler->eligibleGondolas())->toBeEmpty();

    Queue::assertPushed(GenerateReoptimizationProposalJob::class, 1);
});

test('"analisar agora" ignora a cadência, mas não os bloqueios', function (): void {
    $gondola = makeReoptGondola(['reoptimization_next_run_at' => now()->addMonth()]);
    makeCompletedRun($gondola);

    $scheduler = app(ReoptimizationScheduler::class);

    // Fora da cadência, mas pedido explicitamente → elegível.
    expect($scheduler->eligibleGondolas($gondola->id)->pluck('id')->all())->toBe([$gondola->id]);

    PlanogramReoptimizationProposal::create([
        'planogram_id' => $gondola->planogram_id,
        'gondola_id' => $gondola->id,
        'status' => ProposalStatus::Pending,
    ]);

    // Com proposta pendente → bloqueado mesmo sendo pedido explicitamente.
    expect($scheduler->eligibleGondolas($gondola->id))->toBeEmpty();
});
