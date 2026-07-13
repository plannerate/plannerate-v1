<script setup lang="ts">
/**
 * Overlay de "geração em andamento" sobre o editor da gôndola.
 *
 * Sem lógica de negócio: recebe o estado já decidido pelo composable
 * (useGenerationRun) e apenas desenha. Cobre os cinco estados do fluxo
 * assíncrono — na fila, montando, concluída, falhou e travada — e garante que
 * o usuário sempre tenha uma saída (Esc, minimizar ou "Voltar aos planogramas").
 */
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, ChevronDown, Loader2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { wayfinderPath } from '../../../libs/wayfinderPath';
import type { GenerationRun } from '@/composables/plannerate/generation/useGenerationRun';

interface Props {
    run: GenerationRun | null;
    elapsedMs: number;
    isStuck: boolean;
    /** Segundos restantes até o `router.reload()` — só > 0 no estado concluído. */
    reloadCountdown: number;
    backRoute: string;
}

interface Emits {
    (e: 'dismiss'): void;
    (e: 'retry'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useT();

/** Card colapsado num pill flutuante — permite olhar a gôndola atual sem perder a trava. */
const minimized = ref(false);

const status = computed(() => props.run?.status ?? 'queued');
const isFailed = computed(() => status.value === 'failed');
const isCompleted = computed(() => status.value === 'completed');

/** A varredura de luz só faz sentido enquanto algo está de fato sendo montado. */
const showSweep = computed(() => !isFailed.value && !isCompleted.value && !props.isStuck);

const statusIcon = computed(() => {
    if (isFailed.value || props.isStuck) {
        return AlertTriangle;
    }

    if (isCompleted.value) {
        return CheckCircle2;
    }

    return Loader2;
});

const statusIconClass = computed(() => {
    if (isFailed.value) {
        return 'text-destructive';
    }

    if (props.isStuck) {
        return 'text-amber-500';
    }

    if (isCompleted.value) {
        return 'text-emerald-500';
    }

    return 'animate-spin text-primary';
});

const title = computed(() => {
    if (isFailed.value) {
        return t('plannerate.generation.overlay.failed_title');
    }

    if (props.isStuck) {
        return t('plannerate.generation.overlay.stuck_title');
    }

    if (isCompleted.value) {
        return t('plannerate.generation.overlay.completed');
    }

    return status.value === 'running'
        ? t('plannerate.generation.overlay.running')
        : t('plannerate.generation.overlay.queued');
});

const subtitle = computed(() => {
    if (props.isStuck) {
        return t('plannerate.generation.overlay.stuck_hint');
    }

    if (isCompleted.value) {
        return t('plannerate.generation.overlay.reloading_in', { seconds: String(props.reloadCountdown) });
    }

    if (!isFailed.value) {
        return elapsedLabel.value;
    }

    return null;
});

/** "há 14s" / "há 2min 05s" — sem chave por unidade, o número já carrega o sentido. */
function formatElapsed(ms: number): string {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    const time = minutes > 0 ? `${minutes}min ${String(seconds).padStart(2, '0')}s` : `${seconds}s`;

    return t('plannerate.generation.overlay.elapsed', { time });
}

const elapsedLabel = computed(() => formatElapsed(props.elapsedMs));

const pillLabel = computed(() => (isFailed.value ? title.value : elapsedLabel.value));

/**
 * Esc sempre dá uma saída: nos estados travado/falho, fecha de vez (a proteção
 * já não faz sentido); nos demais, apenas minimiza — a trava de edição continua.
 */
function handleKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') {
        return;
    }

    if (isFailed.value || props.isStuck) {
        emit('dismiss');
    } else {
        minimized.value = true;
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div class="pointer-events-auto absolute inset-0 z-40 flex items-center justify-center" role="status" aria-live="polite">
        <div
            class="absolute inset-0 bg-background/10 backdrop-grayscale-[.6] backdrop-brightness-90 dark:bg-background/20 dark:backdrop-brightness-75"
        />

        <div
            v-if="showSweep"
            class="generation-sweep pointer-events-none absolute inset-y-0 left-0 w-1/3 bg-linear-to-r from-transparent via-white/50 to-transparent motion-reduce:hidden dark:via-white/15"
        />

        <button
            v-if="minimized"
            type="button"
            class="absolute right-4 bottom-4 flex items-center gap-2 rounded-full border bg-background px-4 py-2 text-sm font-medium text-foreground shadow-lg hover:bg-accent"
            @click="minimized = false"
        >
            <component :is="statusIcon" class="size-4" :class="statusIconClass" />
            {{ pillLabel }}
        </button>

        <div
            v-else
            class="relative w-full max-w-sm rounded-lg border bg-background p-5 shadow-xl"
            :class="isFailed ? 'border-destructive/50' : 'border-border'"
        >
            <div class="flex items-start gap-3">
                <component :is="statusIcon" class="mt-0.5 size-5 shrink-0" :class="statusIconClass" />

                <div class="min-w-0 flex-1">
                    <p class="font-medium text-foreground">{{ title }}</p>
                    <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
                    <p v-if="isFailed && run?.error_message" class="mt-2 rounded bg-destructive/10 p-2 text-xs text-destructive">
                        {{ run.error_message }}
                    </p>
                </div>

                <button
                    v-if="!isFailed && !isStuck && !isCompleted"
                    type="button"
                    class="text-muted-foreground hover:text-foreground"
                    :title="t('plannerate.generation.overlay.minimize')"
                    @click="minimized = true"
                >
                    <ChevronDown class="size-4" />
                </button>
            </div>

            <p v-if="!isFailed && !isCompleted" class="mt-3 text-xs text-muted-foreground">
                {{ t('plannerate.generation.overlay.wait_here') }}
            </p>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                <Link
                    v-if="backRoute"
                    :href="wayfinderPath(backRoute)"
                    class="mr-auto flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    {{ t('plannerate.generation.overlay.back_to_planograms') }}
                </Link>

                <Button v-if="isFailed || isStuck" variant="outline" size="sm" @click="emit('dismiss')">
                    {{ t('plannerate.generation.overlay.dismiss') }}
                </Button>

                <Button v-if="isFailed" variant="default" size="sm" @click="emit('retry')">
                    {{ t('plannerate.generation.overlay.retry') }}
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes generation-sweep {
    from {
        transform: translateX(-120%);
    }
    to {
        transform: translateX(320%);
    }
}

.generation-sweep {
    animation: generation-sweep 2.6s ease-in-out infinite;
}
</style>
