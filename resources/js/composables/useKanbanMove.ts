import { ref, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { BoardColumn, BoardStep, Execution } from '@/components/kanban/types';

type MoveAttempt = {
    executionId: string;
    fromStepId: string;
    targetStepId: string;
};

type MoveMessages = {
    mustBeStarted: string;
    skippedStep: string;
};

export function useKanbanMove(
    board: MaybeRefOrGetter<BoardColumn[]>,
    messages: MoveMessages,
    onDenied: (message: string) => void,
) {
    const draggingExecutionId = ref<string | null>(null);
    const draggingFromStepId = ref<string | null>(null);
    const dragOverStepId = ref<string | null>(null);

    function findExecution(executionId: string): Execution | null {
        for (const column of toValue(board)) {
            const execution = column.executions.find((item) => item.id === executionId);

            if (execution) {
                return execution;
            }
        }

        return null;
    }

    function findStep(stepId: string): BoardStep | null {
        return toValue(board).find((column) => column.step.id === stepId)?.step ?? null;
    }

    function resetDrag(): void {
        draggingExecutionId.value = null;
        draggingFromStepId.value = null;
        dragOverStepId.value = null;
    }

    function canMoveExecution(execution: Execution): boolean {
        return execution.can_move && execution.status === 'active';
    }

    function canDropOnStep(stepId: string): boolean {
        const executionId = draggingExecutionId.value;

        if (!executionId) {
            return false;
        }

        const execution = findExecution(executionId);

        if (!execution || !canMoveExecution(execution)) {
            onDenied(messages.mustBeStarted);

            return false;
        }

        const targetStep = findStep(stepId);

        if (targetStep?.is_skipped) {
            onDenied(messages.skippedStep);

            return false;
        }

        return true;
    }

    function onDragStart(execution: Execution, stepId: string): void {
        if (!canMoveExecution(execution)) {
            onDenied(messages.mustBeStarted);

            return;
        }

        draggingExecutionId.value = execution.id;
        draggingFromStepId.value = stepId;
    }

    function onDragOver(stepId: string): void {
        if (!canDropOnStep(stepId)) {
            dragOverStepId.value = null;

            return;
        }

        dragOverStepId.value = stepId;
    }

    function onDragLeave(stepId: string): void {
        if (dragOverStepId.value === stepId) {
            dragOverStepId.value = null;
        }
    }

    function resolveDrop(targetStepId: string): MoveAttempt | null {
        const executionId = draggingExecutionId.value;
        const fromStepId = draggingFromStepId.value;

        if (!executionId || !fromStepId || fromStepId === targetStepId) {
            resetDrag();

            return null;
        }

        if (!canDropOnStep(targetStepId)) {
            resetDrag();

            return null;
        }

        resetDrag();

        return { executionId, fromStepId, targetStepId };
    }

    return {
        draggingExecutionId,
        dragOverStepId,
        onDragStart,
        onDragOver,
        onDragLeave,
        resolveDrop,
    };
}
