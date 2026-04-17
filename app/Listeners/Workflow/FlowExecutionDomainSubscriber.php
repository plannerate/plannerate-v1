<?php

namespace App\Listeners\Workflow;

use Callcocam\LaravelRaptorFlow\Events\FlowExecutionActionOccurred;
use Illuminate\Events\Dispatcher;

class FlowExecutionDomainSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            FlowExecutionActionOccurred::class,
            [self::class, 'onActionOccurred']
        );
    }

    public function onActionOccurred(FlowExecutionActionOccurred $event): void
    {
        match ($event->action) {
            'start' => $this->onStarted($event),
            'move' => $this->onMoved($event),
            'pause' => $this->onPaused($event),
            'resume' => $this->onResumed($event),
            'reassign' => $this->onReassigned($event),
            'abandon' => $this->onAbandoned($event),
            'complete' => $this->onCompleted($event),
            'notes' => $this->onNotesUpdated($event),
            default => null,
        };
    }

    protected function onStarted(FlowExecutionActionOccurred $event): void {}

    protected function onMoved(FlowExecutionActionOccurred $event): void {}

    protected function onPaused(FlowExecutionActionOccurred $event): void {}

    protected function onResumed(FlowExecutionActionOccurred $event): void {}

    protected function onReassigned(FlowExecutionActionOccurred $event): void {}

    protected function onAbandoned(FlowExecutionActionOccurred $event): void {}

    protected function onCompleted(FlowExecutionActionOccurred $event): void {}

    protected function onNotesUpdated(FlowExecutionActionOccurred $event): void {}
}
