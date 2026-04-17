<?php

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Support\Actions\CustomAction;
use Callcocam\LaravelRaptorFlow\Support\Builders\ConfigureKanbanCard;
use Callcocam\LaravelRaptorFlow\Support\Builders\ConfigureKanbanModal;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayColumn;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayField;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplaySection;
use Callcocam\LaravelRaptorFlow\Support\Display\NotesBlock;

it('serializes modal builder payload and resolves actions per execution', function () {
    $execution = new FlowExecution;
    $execution->id = 'exec-100';

    $modal = ConfigureKanbanModal::make()
        ->addAction(
            CustomAction::make('start')
                ->label('Iniciar')
                ->method('post')
                ->url(fn (?FlowExecution $execution) => '/flow/executions/'.($execution?->id ?? '{id}').'/start'),
        )
        ->addSection(
            DisplaySection::make('summary')
                ->label('Resumo')
                ->addField(DisplayField::label('status')->labelText('Status')),
        )
        ->addNote(
            NotesBlock::make('workflow-notes')
                ->url('/flow/executions/{id}/notes')
                ->placeholder('Adicionar notas...'),
        )
        ->addLink([
            'key' => 'open',
            'label' => 'Abrir',
            'url' => '/flow/executions/{id}',
            'external' => false,
        ]);

    $serialized = $modal->toArray();
    $resolvedActions = $modal->resolveActionsForExecution($execution);

    expect($serialized)->toHaveKeys(['sections', 'actions', 'links', 'notes'])
        ->and($serialized['sections'])->toHaveCount(1)
        ->and($serialized['actions'])->toHaveCount(1)
        ->and($serialized['links'])->toHaveCount(1)
        ->and($serialized['notes'])->toHaveCount(1)
        ->and($resolvedActions)->toHaveCount(1)
        ->and($resolvedActions[0]['url'])->toBe('/flow/executions/exec-100/start');
});

it('serializes card builder columns and resolves card actions per execution', function () {
    $execution = new FlowExecution;
    $execution->id = 'exec-200';

    $card = ConfigureKanbanCard::make()
        ->addColumn(
            DisplayColumn::make('identity')
                ->addFields([
                    DisplayField::label('workable.name')->labelText('Nome'),
                ]),
        )
        ->addAction(
            CustomAction::make('open')
                ->label('Abrir')
                ->method('get')
                ->url(fn (?FlowExecution $execution) => '/flow/executions/'.($execution?->id ?? '{id}')),
        )
        ->addAction(
            CustomAction::make('hidden-action')
                ->label('Oculta')
                ->method('get')
                ->visible(fn (FlowExecution $execution) => $execution->id === 'exec-visible')
                ->url(fn (?FlowExecution $execution) => '/flow/executions/'.($execution?->id ?? '{id}').'/hidden'),
        )
        ->addLink([
            'key' => 'timeline',
            'label' => 'Linha do tempo',
            'url' => fn (?FlowExecution $execution) => '/flow/executions/'.($execution?->id ?? '{id}').'/timeline',
            'position' => 'secondary',
            'priority' => 20,
        ])
        ->addLink([
            'key' => 'open-external',
            'label' => 'Abrir externo',
            'url' => fn (?FlowExecution $execution) => '/flow/executions/'.($execution?->id ?? '{id}').'/external',
            'position' => 'primary',
            'priority' => 10,
            'external' => true,
        ]);

    $serialized = $card->toArray();
    $resolvedActions = $card->resolveActionsForExecution($execution);
    $resolvedLinks = $card->resolveLinksForExecution($execution);

    expect($serialized)->toHaveKeys(['columns', 'links'])
        ->and($serialized['columns'])->toHaveCount(1)
        ->and($serialized['links'])->toHaveCount(2)
        ->and($serialized['links'][0]['key'])->toBe('open-external')
        ->and($resolvedActions)->toHaveCount(1)
        ->and($resolvedActions[0]['id'])->toBe('open')
        ->and($resolvedActions[0]['url'])->toBe('/flow/executions/exec-200')
        ->and($resolvedLinks)->toHaveCount(2)
        ->and($resolvedLinks[0])->toMatchArray([
            'key' => 'open-external',
            'position' => 'primary',
            'priority' => 10,
            'external' => true,
            'url' => '/flow/executions/exec-200/external',
        ])
        ->and($resolvedLinks[1])->toMatchArray([
            'key' => 'timeline',
            'position' => 'secondary',
            'priority' => 20,
            'url' => '/flow/executions/exec-200/timeline',
        ]);
});
