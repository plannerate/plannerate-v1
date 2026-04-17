<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Badge } from '@/components/ui/badge'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '~/components/ui/card'
import { Separator } from '~/components/ui/separator'
import { getWorkflowStatusLabel, getWorkflowStatusVariant } from '@/lib/status'
import {
    ArrowLeft,
    Clock,
    User,
    GitBranch,
    Calendar,
    ArrowRight,
    ArrowLeftRight,
    Play,
    Pause,
    RotateCcw,
    UserPlus,
    FileText,
} from 'lucide-vue-next'

interface FlowExecution {
    id: string
    status: string
    config_step?: { step_template?: { name: string } | null } | null
    step_template?: { name: string } | null
    assigned_user?: { id: string; name: string; email: string } | null
}

interface HistoryEntry {
    id: string
    action: string
    performed_at: string
    notes: string | null
    duration_in_step_minutes: number | null
    was_overdue: boolean
    user: { id: string; name: string } | null
    from_step_id?: string | null
    to_step_id?: string | null
    previous_responsible_id?: string | null
    new_responsible_id?: string | null
}

interface Props {
    gondola: {
        id: string
        name: string
        width?: number
        height?: number
        depth?: number
        planogram: { id: string; name: string } | null
    }
    flowExecution: FlowExecution | null
    history: HistoryEntry[]
}

const props = defineProps<Props>()

const currentExecution = computed(() => props.flowExecution)

const currentStepName = computed(() => {
    const exec = currentExecution.value
    return exec?.config_step?.step_template?.name
        ?? exec?.step_template?.name
        ?? 'N/A'
})

const actionIcon = (action: string) => {
    const map: Record<string, any> = {
        created: Play,
        moved_to: ArrowRight,
        moved_back: ArrowLeftRight,
        paused: Pause,
        resumed: RotateCcw,
        reassigned: UserPlus,
    }
    return map[action] ?? FileText
}

const actionLabel = (action: string) => {
    const map: Record<string, string> = {
        created: 'Workflow Iniciado',
        moved_to: 'Movido para',
        moved_back: 'Retrocedido para',
        paused: 'Pausado',
        resumed: 'Retomado',
        reassigned: 'Reatribuído',
    }
    return map[action] ?? action
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatDuration = (minutes: number | null) => {
    if (!minutes) return null
    if (minutes < 60) return `${minutes}min`
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`
}
</script>

<template>
    <AppLayout>
        <Head :title="`Workflow - ${gondola.name}`" />

        <div class="mx-auto max-w-5xl space-y-6 p-6">
            <!-- Back link -->
            <div>
                <Link
                    href="/kanban"
                    class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="mr-2 size-4" />
                    Voltar ao Kanban
                </Link>
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ gondola.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Planograma: {{ gondola.planogram?.name ?? 'N/A' }}
                    </p>
                </div>
                <Badge v-if="currentExecution" :variant="getWorkflowStatusVariant(currentExecution.status)">
                    {{ getWorkflowStatusLabel(currentExecution.status) }}
                </Badge>
            </div>

            <!-- Current Status Card -->
            <Card v-if="currentExecution">
                <CardHeader>
                    <CardTitle>Etapa Atual</CardTitle>
                    <CardDescription>Status atual do workflow desta gôndola</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                <GitBranch class="size-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Etapa</p>
                                <p class="font-medium">{{ currentStepName }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                <User class="size-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Responsável</p>
                                <p class="font-medium">
                                    {{ currentExecution.assigned_user?.name ?? 'Não atribuído' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                <Clock class="size-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Status</p>
                                <p class="font-medium">{{ getWorkflowStatusLabel(currentExecution.status) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Gondola Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Dimensões</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Largura</p>
                            <p class="text-lg font-semibold">{{ gondola.width }} cm</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Altura</p>
                            <p class="text-lg font-semibold">{{ gondola.height }} cm</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Profundidade</p>
                            <p class="text-lg font-semibold">{{ gondola.depth }} cm</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Timeline -->
            <Card>
                <CardHeader>
                    <CardTitle>Histórico do Workflow</CardTitle>
                    <CardDescription>
                        {{ history.length }} {{ history.length === 1 ? 'evento' : 'eventos' }} registrados
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="history.length === 0" class="py-8 text-center text-muted-foreground">
                        Nenhum histórico registrado para esta gôndola.
                    </div>
                    <div v-else class="relative space-y-0">
                        <div
                            v-for="(entry, index) in history"
                            :key="entry.id"
                            class="relative flex gap-4 pb-6"
                        >
                            <!-- Timeline line -->
                            <div class="flex flex-col items-center">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full border bg-card"
                                    :class="{ 'border-destructive': entry.was_overdue }"
                                >
                                    <component :is="actionIcon(entry.action)" class="size-4 text-muted-foreground" />
                                </div>
                                <div
                                    v-if="index < history.length - 1"
                                    class="mt-1 w-px flex-1 bg-border"
                                />
                            </div>

                            <!-- Content -->
                            <div class="flex-1 pt-0.5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="font-medium">
                                            {{ actionLabel(entry.action) }}
                                            <template v-if="entry.to_step?.workflow_step_template">
                                                "{{ entry.to_step.workflow_step_template.name }}"
                                            </template>
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            por {{ entry.user?.name ?? 'Sistema' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Calendar class="size-3" />
                                        {{ formatDate(entry.performed_at) }}
                                    </div>
                                </div>

                                <!-- Extra info -->
                                <div v-if="entry.notes" class="mt-1 text-sm text-muted-foreground">
                                    {{ entry.notes }}
                                </div>
                                <div v-if="entry.duration_in_step_minutes" class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock class="size-3" />
                                    Tempo na etapa: {{ formatDuration(entry.duration_in_step_minutes) }}
                                </div>
                                <Badge v-if="entry.was_overdue" variant="destructive" class="mt-1">
                                    Atrasado
                                </Badge>
                                <div v-if="entry.new_assigned_user" class="mt-1 text-sm text-muted-foreground">
                                    {{ entry.previous_assigned_user?.name ?? 'N/A' }} &rarr; {{ entry.new_assigned_user.name }}
                                </div>

                                <Separator v-if="index < history.length - 1" class="mt-4" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
