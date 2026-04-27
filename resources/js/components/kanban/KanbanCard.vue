<script setup lang="ts">
import { CalendarClock, CheckCircle2, ExternalLink, GripVertical, Pause, Play, User, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import type { Execution } from '@/components/kanban/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { editor as tenantEditorPlanogramGondolas } from '@/routes/tenant/planograms/gondolas';

const props = defineProps<{
    execution: Execution;
    subdomain: string;
    currentUserId: string | null;
    isDragging: boolean;
    statusClass: string;
    statusLabel: string;
    formattedSlaDate: string;
    isOverdue: boolean;
    isBusy: boolean;
}>();

const emit = defineEmits<{
    dragstart: [execution: Execution];
    details: [execution: Execution];
    start: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
    abandon: [execution: Execution];
}>();

const { t } = useT();

const canStart = computed(() => props.execution.can_start);
const canPause = computed(() => props.execution.can_pause);
const canResume = computed(() => props.execution.can_resume);
const canComplete = computed(() => props.execution.can_complete);
const canAbandon = computed(() => props.execution.can_abandon);
const canMove = computed(() => props.execution.can_move && props.execution.status === 'active');
const isActive = computed(() => props.execution.status === 'active');
const wasStartedByCurrentUser = computed(
    () => isActive.value && props.execution.started_by?.id === props.currentUserId,
);
const executionLinkHref = computed(() => {
    if (!isActive.value || !props.execution.started_by?.id || !props.currentUserId) {
        return null;
    }

    if (wasStartedByCurrentUser.value) {
        return tenantEditorPlanogramGondolas.url({
            subdomain: props.subdomain,
            record: props.execution.gondola_id,
        });
    }

    return gondolaView.url(props.execution.gondola_id);
});
const executionLinkLabel = computed(() => (
    wasStartedByCurrentUser.value
        ? t('app.kanban.links.open_editor')
        : t('app.kanban.links.view_pdf')
));
</script>

<template>
    <article
        :draggable="canMove && !isBusy"
        class="group rounded-lg border border-border bg-background p-3 text-sm shadow-sm transition hover:border-primary/40 hover:shadow-md"
        :class="{
            'cursor-grab active:cursor-grabbing': canMove,
            'cursor-not-allowed': !canMove,
            'opacity-50 ring-2 ring-primary/30': isDragging,
        }"
        :title="canMove ? t('app.kanban.move.card_title') : t('app.kanban.move.must_be_started')"
        @dragstart="emit('dragstart', execution)"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <GripVertical
                        class="size-4 shrink-0 text-muted-foreground"
                        :class="{ 'opacity-50': canMove, 'opacity-20': !canMove }"
                    />
                    <p class="truncate font-medium text-foreground">
                        {{ execution.gondola_name ?? t('app.kanban.card.unnamed_gondola') }}
                    </p>
                </div>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ execution.gondola_location ?? t('app.kanban.card.no_location') }}
                </p>
            </div>

            <Badge :class="statusClass">
                {{ statusLabel }}
            </Badge>
        </div>

        <div class="mt-3 space-y-1.5 text-xs text-muted-foreground">
            <p class="truncate">
                {{ t('app.kanban.card.planogram') }}:
                <span class="font-medium text-foreground">{{ execution.planogram_name ?? '-' }}</span>
            </p>
            <p class="truncate">
                {{ t('app.kanban.card.step') }}:
                <span class="font-medium text-foreground">{{ execution.step_name ?? '-' }}</span>
            </p>
            <p class="flex items-center gap-1.5" :class="{ 'text-destructive': isOverdue }">
                <CalendarClock class="size-3.5" />
                {{ t('app.kanban.card.sla') }}: {{ formattedSlaDate }}
            </p>
            <p class="flex items-center gap-1.5">
                <User class="size-3.5" />
                {{ execution.assigned_to_user?.name ?? t('app.kanban.card.no_responsible') }}
            </p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="emit('details', execution)">
                {{ t('app.kanban.actions.details') }}
            </Button>

            <Button v-if="executionLinkHref" size="sm" variant="outline" class="h-7 px-2 text-xs" as-child>
                <a :href="executionLinkHref" target="_blank" rel="noopener noreferrer">
                    <ExternalLink class="mr-1 size-3.5" />
                    {{ executionLinkLabel }}
                </a>
            </Button>

            <Button
                v-if="canStart"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('start', execution)"
            >
                <Play class="mr-1 size-3.5" />
                {{ t('app.kanban.actions.start') }}
            </Button>

            <Button
                v-if="canPause"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('pause', execution)"
            >
                <Pause class="mr-1 size-3.5" />
                {{ t('app.kanban.actions.pause') }}
            </Button>

            <Button
                v-if="canResume"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('resume', execution)"
            >
                <Play class="mr-1 size-3.5" />
                {{ t('app.kanban.actions.resume') }}
            </Button>

            <Button
                v-if="canComplete"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('complete', execution)"
            >
                <CheckCircle2 class="mr-1 size-3.5" />
                {{ t('app.kanban.actions.complete') }}
            </Button>

            <Button
                v-if="canAbandon"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs text-destructive hover:text-destructive"
                :disabled="isBusy"
                @click="emit('abandon', execution)"
            >
                <XCircle class="mr-1 size-3.5" />
                {{ t('app.kanban.actions.abandon') }}
            </Button>
        </div>
    </article>
</template>
