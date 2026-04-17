<?php

use Callcocam\LaravelRaptorFlow\Support\Display\DisplayCardItem;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayColumn;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayComponents;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayField;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplayRow;
use Callcocam\LaravelRaptorFlow\Support\Display\DisplaySection;
use Callcocam\LaravelRaptorFlow\Support\Display\NotesBlock;

it('serializes display sections with rows and fields', function () {
    $section = DisplaySection::make('responsible')
        ->label('Responsabilidade')
        ->columnSpan(8)
        ->addRow(
            DisplayRow::make()->addFields([
                DisplayField::label('currentResponsible.name')->labelText('Responsável atual'),
                DisplayField::date('sla_date')->labelText('SLA'),
            ]),
        );

    expect($section->toArray())
        ->toMatchArray([
            'id' => 'responsible',
            'label' => 'Responsabilidade',
            'columnSpan' => 8,
        ])
        ->and($section->toArray()['rows'])->toHaveCount(1)
        ->and($section->toArray()['rows'][0]['fields'])->toHaveCount(2);
});

it('serializes cards fields and card columns', function () {
    $column = DisplayColumn::make('summary')
        ->addField(
            DisplayField::cards('summary', [
                DisplayCardItem::make('status')->label('Status')->format('badge'),
                DisplayCardItem::make('sla_date')->label('SLA')->format('date'),
            ]),
        );

    expect($column->toArray()['fields'][0])
        ->toMatchArray([
            'key' => 'summary',
            'type' => 'cards',
        ])
        ->and($column->toArray()['fields'][0]['cards'])->toHaveCount(2);
});

it('serializes display column showWhenEmpty flag', function () {
    $column = DisplayColumn::make('owner')
        ->showWhenEmpty(true)
        ->addField(DisplayField::label('currentResponsible.name')->labelText('Responsavel'));

    expect($column->toArray())->toMatchArray([
        'id' => 'owner',
        'showWhenEmpty' => true,
    ]);
});

it('serializes notes blocks', function () {
    $note = NotesBlock::make('workflow-notes')
        ->label('Notas internas')
        ->url('/workflow/gondola/{workable.id}/notes')
        ->placeholder('Adicionar notas');

    expect($note->toArray())
        ->toMatchArray([
            'id' => 'workflow-notes',
            'label' => 'Notas internas',
            'url' => '/workflow/gondola/{workable.id}/notes',
            'placeholder' => 'Adicionar notas',
        ]);
});

it('evaluates callback url in notes block', function () {
    $target = (object) ['id' => 'abc-123'];

    $note = NotesBlock::make('workflow-notes')
        ->url(fn ($target) => "/workflow/gondola/{$target->id}/notes");

    expect($note->toArray($target)['url'])
        ->toBe('/workflow/gondola/abc-123/notes');
});

it('evaluates callbacks in display schema properties with target context', function () {
    $target = (object) [
        'name' => 'Gondola A',
        'status' => 'in_progress',
    ];

    $section = DisplaySection::make('dynamic')
        ->label(fn ($target) => "Detalhes de {$target->name}")
        ->addField(
            DisplayField::cards('summary', [
                DisplayCardItem::make('status')
                    ->label(fn ($target) => strtoupper($target->status))
                    ->format(fn () => 'badge'),
            ])->placeholder(fn ($target) => "Status de {$target->name}"),
        );

    $serialized = $section->toArray($target);

    expect($serialized['label'])->toBe('Detalhes de Gondola A')
        ->and($serialized['rows'][0]['fields'][0]['placeholder'])->toBe('Status de Gondola A')
        ->and($serialized['rows'][0]['fields'][0]['cards'][0]['label'])->toBe('IN_PROGRESS')
        ->and($serialized['rows'][0]['fields'][0]['cards'][0]['format'])->toBe('badge');
});

it('exposes canonical component names for display types', function () {
    $timeline = DisplayField::timeline('history')->defaultComponent()->toArray();
    $unknown = DisplayComponents::forType('unknown');

    expect(DisplayComponents::forType('text'))->toBe(DisplayComponents::TEXT)
        ->and(DisplayComponents::forType('selectUsers'))->toBe(DisplayComponents::SELECT_USERS)
        ->and($timeline['component'])->toBe(DisplayComponents::TIMELINE)
        ->and($unknown)->toBe(DisplayComponents::CUSTOM);
});
