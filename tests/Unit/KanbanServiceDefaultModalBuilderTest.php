<?php

use App\Services\Workflow\KanbanService;
use Tests\TestCase;

uses(TestCase::class);

it('builds default modal actions with canonical flow execution urls', function () {
    $service = new KanbanService;

    $urls = [
        'start' => '/flow/executions/{id}/start',
        'pause' => '/flow/executions/{id}/pause',
        'resume' => '/flow/executions/{id}/resume',
        'abandon' => '/flow/executions/{id}/abandon',
        'notes' => '/flow/executions/{id}/notes',
    ];

    $modal = $service->buildDefaultModalBuilder($urls)->toArray();

    $actionsById = collect($modal['actions'])->keyBy('id');

    expect($actionsById['start']['url'])->toBe('/flow/executions/{id}/start')
        ->and($actionsById['pause']['url'])->toBe('/flow/executions/{id}/pause')
        ->and($actionsById['resume']['url'])->toBe('/flow/executions/{id}/resume')
        ->and($actionsById['abandon']['url'])->toBe('/flow/executions/{id}/abandon')
        ->and($modal['notes'][0]['url'])->toBe('/flow/executions/{id}/notes');
});

it('renders notifications section with textarea field for recent titles', function () {
    $service = new KanbanService;

    $modal = $service->buildDefaultModalBuilder([
        'start' => '/flow/executions/{id}/start',
    ])->toArray();

    $notificationsSection = collect($modal['sections'])->firstWhere('id', 'notifications');

    expect($notificationsSection)->not->toBeNull();

    $fields = collect($notificationsSection['rows'] ?? [])->flatMap(
        fn (array $row): array => $row['fields'] ?? [],
    );
    $textareaField = $fields->firstWhere('key', 'notifications_summary.latest_titles');

    expect($textareaField)->not->toBeNull()
        ->and($textareaField['type'])->toBe('textarea')
        ->and($textareaField['label'])->toBe('Recentes');
});
