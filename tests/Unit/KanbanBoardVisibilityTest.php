<?php

use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowNotification;
use Callcocam\LaravelRaptorFlow\Support\Kanban\KanbanBoard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

it('always includes action_visibility map even without authenticated user', function () {
    auth()->logout();

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->flow_step_template_id = 'step-1';
    $execution->status = FlowStatus::Pending->value;

    $board = new class(collect([$execution])) extends KanbanBoard
    {
        public function __construct(private Collection $executions) {}

        protected function resolveWorkableIds(): array
        {
            return ['workable-1'];
        }

        protected function getWorkflowSteps(): Collection
        {
            return collect([(object) [
                'id' => 'step-1',
                'name' => 'Step 1',
                'slug' => 'step-1',
                'color' => null,
                'description' => null,
                'suggested_order' => 1,
            ]]);
        }

        protected function getExecutions(array $workableIds): Collection
        {
            return $this->executions;
        }
    };

    $payload = $board->getBoardData();
    $executionPayload = $payload['board']['executions']['step-1'][0];

    expect($executionPayload)
        ->toHaveKey('status')
        ->toHaveKey('status_presentation')
        ->toHaveKey('abilities')
        ->and($executionPayload)
        ->toHaveKey('action_visibility')
        ->and($executionPayload)
        ->toHaveKey('modal_actions')
        ->and($executionPayload)
        ->toHaveKey('card_actions')
        ->and($executionPayload)
        ->toHaveKey('card_links')
        ->and($executionPayload['status'])->toBe(FlowStatus::Pending->value)
        ->and($executionPayload['status_presentation'])->toBe([
            'label' => 'Pendente',
            'icon' => 'AlertCircle',
            'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        ])
        ->and($executionPayload['abilities'])->toBeNull()
        ->and($executionPayload['action_visibility'])->toBe([
            'start' => false,
            'move' => false,
            'pause' => false,
            'resume' => false,
            'assign' => false,
            'abandon' => false,
            'notes' => false,
        ])
        ->and($executionPayload['modal_actions'])->toBe([])
        ->and($executionPayload['card_actions'])->toBe([])
        ->and($executionPayload['card_links'])->toBe([]);
});

it('derives action_visibility from policy abilities for authenticated responsible user', function () {
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return 'user-1';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return 'secret';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function can($ability, $arguments = []): bool
        {
            return false;
        }
    };

    auth()->setUser($user);

    $execution = new FlowExecution;
    $execution->id = 'exec-2';
    $execution->flow_step_template_id = 'step-1';
    $execution->status = FlowStatus::InProgress->value;
    $execution->current_responsible_id = 'user-1';

    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = null;
    $configStep->suggested_responsible_id = null;
    $configStep->setRelation('participants', collect([(object) ['user_id' => 'user-1']]));
    $execution->setRelation('configStep', $configStep);

    $board = new class(collect([$execution])) extends KanbanBoard
    {
        public function __construct(private Collection $executions) {}

        protected function resolveWorkableIds(): array
        {
            return ['workable-1'];
        }

        protected function getWorkflowSteps(): Collection
        {
            return collect([(object) [
                'id' => 'step-1',
                'name' => 'Step 1',
                'slug' => 'step-1',
                'color' => null,
                'description' => null,
                'suggested_order' => 1,
            ]]);
        }

        protected function getExecutions(array $workableIds): Collection
        {
            return $this->executions;
        }
    };

    $payload = $board->getBoardData();
    $visibility = $payload['board']['executions']['step-1'][0]['action_visibility'];

    expect($visibility['move'])->toBeTrue()
        ->and($visibility['pause'])->toBeTrue()
        ->and($visibility['resume'])->toBeFalse()
        ->and($visibility['assign'])->toBeTrue()
        ->and($visibility['abandon'])->toBeTrue()
        ->and($visibility['notes'])->toBeTrue()
        ->and($visibility['start'])->toBeFalse();
});

it('includes execution-level modal and card actions when resolvers are configured', function () {
    auth()->logout();

    $execution = new FlowExecution;
    $execution->id = 'exec-3';
    $execution->flow_step_template_id = 'step-1';
    $execution->status = FlowStatus::Pending->value;

    $board = new class(collect([$execution])) extends KanbanBoard
    {
        public function __construct(private Collection $executions)
        {
            $this->modalActions(fn (FlowExecution $execution) => [
                [
                    'id' => 'start',
                    'type' => 'action',
                    'label' => 'Iniciar',
                    'method' => 'post',
                    'url' => "/flow/executions/{$execution->id}/start",
                ],
            ]);

            $this->cardActions(fn (FlowExecution $execution) => [
                [
                    'id' => 'open',
                    'type' => 'link',
                    'label' => 'Abrir',
                    'method' => 'get',
                    'url' => "/flow/executions/{$execution->id}",
                ],
            ]);

            $this->cardLinks(fn (FlowExecution $execution) => [
                [
                    'key' => 'open-primary',
                    'label' => 'Abrir execução',
                    'url' => "/flow/executions/{$execution->id}",
                    'position' => 'primary',
                    'priority' => 10,
                    'external' => false,
                ],
            ]);
        }

        protected function resolveWorkableIds(): array
        {
            return ['workable-1'];
        }

        protected function getWorkflowSteps(): Collection
        {
            return collect([(object) [
                'id' => 'step-1',
                'name' => 'Step 1',
                'slug' => 'step-1',
                'color' => null,
                'description' => null,
                'suggested_order' => 1,
            ]]);
        }

        protected function getExecutions(array $workableIds): Collection
        {
            return $this->executions;
        }
    };

    $payload = $board->getBoardData();
    $executionPayload = $payload['board']['executions']['step-1'][0];

    expect($executionPayload['modal_actions'])->toHaveCount(1)
        ->and($executionPayload['modal_actions'][0])->toMatchArray([
            'id' => 'start',
            'type' => 'action',
            'method' => 'post',
        ])
        ->and($executionPayload['modal_actions'][0]['url'])->toBe('/flow/executions/exec-3/start')
        ->and($executionPayload['card_actions'])->toHaveCount(1)
        ->and($executionPayload['card_actions'][0])->toMatchArray([
            'id' => 'open',
            'type' => 'link',
            'method' => 'get',
        ])
        ->and($executionPayload['card_actions'][0]['url'])->toBe('/flow/executions/exec-3')
        ->and($executionPayload['card_links'])->toHaveCount(1)
        ->and($executionPayload['status_presentation'])->toBe([
            'label' => 'Pendente',
            'icon' => 'AlertCircle',
            'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        ])
        ->and($executionPayload['card_links'][0])->toMatchArray([
            'key' => 'open-primary',
            'position' => 'primary',
            'priority' => 10,
            'url' => '/flow/executions/exec-3',
        ]);
});

it('includes metrics and notifications summaries in execution payload', function () {
    auth()->logout();

    $execution = new FlowExecution;
    $execution->id = 'exec-4';
    $execution->workable_type = 'App\\Models\\Workflow\\GondolaWorkflow';
    $execution->workable_id = 'workable-4';
    $execution->flow_config_step_id = 'config-step-4';
    $execution->flow_step_template_id = 'step-1';
    $execution->status = FlowStatus::InProgress->value;

    $metric = new FlowMetric;
    $metric->id = 'metric-1';
    $metric->total_duration_minutes = 120;
    $metric->effective_work_minutes = 90;
    $metric->estimated_duration_minutes = 100;
    $metric->deviation_minutes = -10;
    $metric->is_on_time = true;
    $metric->is_rework = false;
    $metric->rework_count = 0;
    $metric->calculated_at = Carbon::parse('2026-03-16 12:00:00');

    $notification = new FlowNotification;
    $notification->id = 'notification-1';
    $notification->title = 'Nova responsabilidade';
    $notification->message = 'Voce recebeu uma nova etapa';
    $notification->is_read = false;
    $notification->created_at = Carbon::parse('2026-03-16 12:05:00');

    $execution->setRelation('metrics', collect([$metric]));
    $execution->setRelation('notifications', collect([$notification]));

    $board = new class(collect([$execution])) extends KanbanBoard
    {
        public function __construct(private Collection $executions)
        {
            $this->withDetailModal();
        }

        protected function resolveWorkableIds(): array
        {
            return ['workable-4'];
        }

        protected function getWorkflowSteps(): Collection
        {
            return collect([(object) [
                'id' => 'step-1',
                'name' => 'Step 1',
                'slug' => 'step-1',
                'color' => null,
                'description' => null,
                'suggested_order' => 1,
            ]]);
        }

        protected function getExecutions(array $workableIds): Collection
        {
            return $this->executions;
        }
    };

    $payload = $board->getBoardData();
    $executionPayload = $payload['board']['executions']['step-1'][0];

    expect($executionPayload)
        ->toHaveKey('metrics_summary')
        ->and($executionPayload['metrics_summary'])->toMatchArray([
            'count' => 1,
        ])
        ->and($executionPayload['metrics_summary']['latest'])->toMatchArray([
            'id' => 'metric-1',
            'total_duration_minutes' => 120,
            'effective_work_minutes' => 90,
        ])
        ->and($executionPayload)
        ->toHaveKey('notifications_summary')
        ->and($executionPayload['notifications_summary'])->toMatchArray([
            'count' => 1,
            'unread_count' => 1,
        ])
        ->and($executionPayload['notifications_summary']['latest'])->toHaveCount(1)
        ->and($executionPayload['notifications_summary']['latest'][0])->toMatchArray([
            'id' => 'notification-1',
            'title' => 'Nova responsabilidade',
            'is_read' => false,
        ]);
});
