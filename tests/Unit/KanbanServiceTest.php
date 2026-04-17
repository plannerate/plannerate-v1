<?php

use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Services\KanbanService;
use Callcocam\LaravelRaptorFlow\Support\Actions\CustomAction;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayColumn;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayField;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplaySection;
use Callcocam\LaravelRaptorFlow\Support\Display\NotesBlock;
use Callcocam\LaravelRaptorFlow\Support\Kanban\Columns\Types\WorkableColumn;
use Callcocam\LaravelRaptorFlow\Support\Kanban\KanbanBoard;

// ── addColumn ────────────────────────────────────────────────────────────────

it('accumulates columns via addColumn', function () {
    $service = new KanbanService;

    $col1 = WorkableColumn::make('workable');
    $col2 = WorkableColumn::make('permissions');

    $service->addColumn($col1)->addColumn($col2);

    $reflection = new ReflectionProperty(KanbanService::class, 'columns');
    $columns = $reflection->getValue($service);

    expect($columns)->toHaveCount(2)
        ->and($columns[0])->toBe($col1)
        ->and($columns[1])->toBe($col2);
});

it('returns the same instance from addColumn for fluent chaining', function () {
    $service = new KanbanService;
    $result = $service->addColumn(WorkableColumn::make('workable'));

    expect($result)->toBe($service);
});

// ── addAction + getDetailModalConfig ─────────────────────────────────────────

it('serializes registered actions in getDetailModalConfig', function () {
    $service = new KanbanService;

    $service
        ->addAction(CustomAction::make('start')->label('Iniciar')->url('/start'))
        ->addAction(CustomAction::make('pause')->label('Pausar')->url('/pause'));

    $config = $service->getDetailModalConfig();

    expect($config)->toHaveKeys(['sections', 'actions', 'links', 'notes'])
        ->and($config['actions'])->toHaveCount(2)
        ->and($config['actions'][0])->toHaveKey('id')
        ->and($config['actions'][1])->toHaveKey('id')
        ->and($config['actions'][0])->not->toHaveKey('visibleStatuses');
});

it('evaluates callback properties in actions when serializing detail modal config', function () {
    $service = new KanbanService;

    $dynamicLabel = fn () => 'Ação dinâmica';
    $dynamicVariant = fn () => 'default';
    $dynamicComponent = fn () => 'dynamic-action-button';
    $dynamicUrl = fn () => '/dynamic';

    /** @var mixed $dynamicLabel */
    /** @var mixed $dynamicVariant */
    /** @var mixed $dynamicComponent */
    /** @var mixed $dynamicUrl */
    $service->addAction(
        CustomAction::make('dynamic')
            ->label($dynamicLabel)
            ->variant($dynamicVariant)
            ->component($dynamicComponent)
            ->url($dynamicUrl),
    );

    $config = $service->getDetailModalConfig();

    expect($config['actions'][0])
        ->toMatchArray([
            'id' => 'dynamic',
            'label' => 'Ação dinâmica',
            'variant' => 'default',
            'component' => 'dynamic-action-button',
            'url' => '/dynamic',
        ]);
});

it('returns empty sections and links by default in getDetailModalConfig', function () {
    $service = new KanbanService;

    $config = $service->getDetailModalConfig();

    expect($config['sections'])->toBe([])
        ->and($config['links'])->toBe([])
        ->and($config['notes'])->toBe([]);
});

it('includes modal sections added via addModalSection', function () {
    $service = new KanbanService;

    $service->addModalSection([
        'id' => 'info',
        'label' => 'Informações',
        'fields' => [
            ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notas'],
        ],
    ]);

    $config = $service->getDetailModalConfig();

    expect($config['sections'])->toHaveCount(1)
        ->and($config['sections'][0]['id'])->toBe('info');
});

it('includes typed display sections and notes blocks', function () {
    $service = new KanbanService;

    $service
        ->addSection(
            DisplaySection::make('summary')
                ->label('Resumo')
                ->addField(DisplayField::label('workable.name')),
        )
        ->addNote(
            NotesBlock::make('notes-primary')
                ->url('/workflow/gondola/{workable.id}/notes')
                ->placeholder('Notas...'),
        );

    $config = $service->getDetailModalConfig();

    expect($config['sections'])->toHaveCount(1)
        ->and($config['sections'][0]['rows'])->toHaveCount(1)
        ->and($config['notes'])->toHaveCount(1)
        ->and($config['notes'][0]['id'])->toBe('notes-primary');
});

it('includes modal links added via addModalLink', function () {
    $service = new KanbanService;

    $service->addModalLink([
        'key' => 'edit',
        'label' => 'Editar',
        'url' => '/planograms/{workable.id}/edit',
    ]);

    $config = $service->getDetailModalConfig();

    expect($config['links'])->toHaveCount(1)
        ->and($config['links'][0]['key'])->toBe('edit');
});

// ── Fluent setters ────────────────────────────────────────────────────────────

it('returns the same instance from setFlow for fluent chaining', function () {
    $service = new KanbanService;
    $flow = new Flow;

    expect($service->setFlow($flow))->toBe($service);
});

it('returns the same instance from setFilters for fluent chaining', function () {
    $service = new KanbanService;

    expect($service->setFilters(['status' => 'pending']))->toBe($service);
});

it('returns the same instance from withDetailModal for fluent chaining', function () {
    $service = new KanbanService;

    expect($service->withDetailModal())->toBe($service);
});

// ── getFilterOptionsData stub ──────────────────────────────────────────────────

it('returns empty array from getFilterOptionsData by default', function () {
    $service = new KanbanService;

    expect($service->getFilterOptionsData())->toBe([]);
});

it('returns canonical board payload without remapping steps', function () {
    $rawBoard = [
        'board' => [
            'steps' => [
                ['id' => 'step-1', 'name' => 'Step 1'],
            ],
            'executions' => [
                'step-1' => [
                    ['id' => 'exec-1', 'workflow_step_template_id' => 'step-1'],
                ],
            ],
        ],
        'groupConfigs' => [['id' => 'g-1', 'name' => 'Grupo 1', 'stepIds' => ['step-1']]],
        'userRoles' => ['admin'],
        'filters' => ['values' => ['status' => 'pending']],
    ];

    $board = \Mockery::mock(KanbanBoard::class);
    $board->shouldReceive('getBoardData')->once()->andReturn($rawBoard);

    $service = new class($board) extends KanbanService
    {
        public function __construct(private KanbanBoard $board) {}

        protected function buildBoard(): KanbanBoard
        {
            return $this->board;
        }
    };

    $service->addCardColumn(
        DisplayColumn::make('meta')->addField(DisplayField::badge('status')),
    );

    $result = $service->getBoardData();

    expect($result['board'])->toBe($rawBoard['board'])
        ->and($result['groupConfigs'])->toBe($rawBoard['groupConfigs'])
        ->and($result['userRoles'])->toBe($rawBoard['userRoles'])
        ->and($result['filters'])->toBe($rawBoard['filters'])
        ->and($result['cardConfig']['columns'])->toHaveCount(1)
        ->and($result['cardConfig']['links'])->toBe([]);
});

it('includes card links ordered by priority in card config', function () {
    $service = new KanbanService;

    $service->addCardLink([
        'key' => 'secondary-link',
        'label' => 'Secundario',
        'url' => '/flow/executions/{id}/history',
        'position' => 'secondary',
        'priority' => 20,
    ])->addCardLink([
        'key' => 'primary-link',
        'label' => 'Primario',
        'url' => '/flow/executions/{id}',
        'position' => 'primary',
        'priority' => 10,
        'external' => true,
    ]);

    $cardConfig = $service->getCardConfig();

    expect($cardConfig['links'])->toHaveCount(2)
        ->and($cardConfig['links'][0])->toMatchArray([
            'key' => 'primary-link',
            'position' => 'primary',
            'priority' => 10,
            'external' => true,
        ])
        ->and($cardConfig['links'][1])->toMatchArray([
            'key' => 'secondary-link',
            'position' => 'secondary',
            'priority' => 20,
        ]);
});
