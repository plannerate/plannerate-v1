<script setup lang="ts">
import { CalendarClock, CheckCircle2, GripVertical, Pause, Play, User } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Execution } from '@/components/kanban/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    execution: Execution;
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
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
}>();

const canPause = computed(() => props.execution.status === 'active');
const canResume = computed(() => props.execution.status === 'paused');
const canComplete = computed(() => !['completed', 'cancelled'].includes(props.execution.status));
</script>

<template>
    <article
        draggable="true"
        class="group rounded-lg border border-border bg-background p-3 text-sm shadow-sm transition hover:border-primary/40 hover:shadow-md"
        :class="{ 'opacity-50 ring-2 ring-primary/30': isDragging }"
        @dragstart="emit('dragstart', execution)"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <GripVertical class="size-4 shrink-0 text-muted-foreground opacity-50" />
                    <p class="truncate font-medium text-foreground">
                        {{ execution.gondola_name ?? 'Gondola sem nome' }}
                    </p>
                </div>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ execution.gondola_location ?? 'Sem local informado' }}
                </p>
            </div>

            <Badge :class="statusClass">
                {{ statusLabel }}
            </Badge>
        </div>

        <div class="mt-3 space-y-1.5 text-xs text-muted-foreground">
            <p class="truncate">
                Planograma:
                <span class="font-medium text-foreground">{{ execution.planogram_name ?? '-' }}</span>
            </p>
            <p class="truncate">
                Etapa:
                <span class="font-medium text-foreground">{{ execution.step_name ?? '-' }}</span>
            </p>
            <p class="flex items-center gap-1.5" :class="{ 'text-destructive': isOverdue }">
                <CalendarClock class="size-3.5" />
                SLA: {{ formattedSlaDate }}
            </p>
            <p class="flex items-center gap-1.5">
                <User class="size-3.5" />
                {{ execution.assigned_to_user?.name ?? 'Sem responsavel' }}
            </p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="emit('details', execution)">
                Detalhes
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
                Pausar
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
                Retomar
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
                Concluir
            </Button>
        </div>
    </article>
</template>
