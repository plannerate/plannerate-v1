<?php

use App\Services\Workflow\KanbanService;
use Illuminate\Support\Collection;

it('builds previous and next step payload by template id', function () {
    $service = new class extends KanbanService
    {
        public function buildNeighbors(Collection $stepTemplates): array
        {
            return $this->buildStepNeighborsByTemplateId($stepTemplates);
        }

        public function resolveNeighbors(?string $templateId): array
        {
            return $this->resolveStepNeighbors($templateId);
        }

        public function setNeighbors(array $neighbors): void
        {
            $this->stepNeighborsByTemplateId = $neighbors;
        }
    };

    $templates = collect([
        (object) ['id' => 'step-1', 'name' => 'Etapa 1'],
        (object) ['id' => 'step-2', 'name' => 'Etapa 2'],
        (object) ['id' => 'step-3', 'name' => 'Etapa 3'],
    ]);

    $neighbors = $service->buildNeighbors($templates);
    $service->setNeighbors($neighbors);

    expect($neighbors['step-1'])->toBe([
        'templatePreviousStep' => null,
        'templateNextStep' => ['id' => 'step-2', 'name' => 'Etapa 2'],
    ])->and($neighbors['step-2'])->toBe([
        'templatePreviousStep' => ['id' => 'step-1', 'name' => 'Etapa 1'],
        'templateNextStep' => ['id' => 'step-3', 'name' => 'Etapa 3'],
    ])->and($neighbors['step-3'])->toBe([
        'templatePreviousStep' => ['id' => 'step-2', 'name' => 'Etapa 2'],
        'templateNextStep' => null,
    ])->and($service->resolveNeighbors('step-2'))->toBe([
        'templatePreviousStep' => ['id' => 'step-1', 'name' => 'Etapa 1'],
        'templateNextStep' => ['id' => 'step-3', 'name' => 'Etapa 3'],
    ])->and($service->resolveNeighbors('unknown-step'))->toBe([
        'templatePreviousStep' => null,
        'templateNextStep' => null,
    ]);
});
