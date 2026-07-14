<?php

use App\Models\Tenant;
use App\Support\Navigation\Menu\MenuItem;
use Callcocam\LaravelRaptorPlannerate\Enums\ProposalStatus;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\ReoptimizationInboxController;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramReoptimizationProposal;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

/**
 * A fila de propostas e o contador do menu.
 *
 * Contar propostas já decididas seria pior do que não ter badge: um "3" permanente no menu que
 * nunca zera treina o usuário a ignorá-lo, e aí a próxima proposta de verdade passa batida.
 */
function makeProposal(ProposalStatus $status): PlanogramReoptimizationProposal
{
    return PlanogramReoptimizationProposal::create([
        'planogram_id' => (string) Str::ulid(),
        'gondola_id' => (string) Str::ulid(),
        'status' => $status,
        'diff_summary' => ['entries' => [['product_id' => 'x']], 'summary' => [], 'has_changes' => true],
    ]);
}

beforeEach(function (): void {
    fakeReoptimizationTenant();
    buildProposalSchema();
});

afterEach(function (): void {
    Tenant::forgetCurrent();
});

test('o contador do menu conta só as propostas pendentes', function (): void {
    makeProposal(ProposalStatus::Pending);
    makeProposal(ProposalStatus::Pending);
    makeProposal(ProposalStatus::Applied);
    makeProposal(ProposalStatus::Rejected);
    makeProposal(ProposalStatus::Superseded);
    makeProposal(ProposalStatus::NoChanges);

    expect(ReoptimizationInboxController::pendingCount())->toBe(2);
});

test('sem propostas pendentes o contador é zero — e o badge some', function (): void {
    makeProposal(ProposalStatus::Applied);

    expect(ReoptimizationInboxController::pendingCount())->toBe(0);
});

test('badge zero não é exibido', function (): void {
    $item = MenuItem::make('x')->badge(fn (): int => 0);

    // Um badge "0" é ruído: a ausência do contador já diz que não há nada pendente.
    expect($item->resolveBadge())->toBeNull();

    $item = MenuItem::make('x')->badge(fn (): int => 3);

    expect($item->resolveBadge())->toBe(3);
});
