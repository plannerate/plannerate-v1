import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { generate as generateAutoPlanogram } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Generation/AutoPlanogramController';
import { currentGondola } from '@/composables/plannerate/core/useGondolaState';

/**
 * Execução da geração de planograma (PlanogramGenerationRun).
 *
 * A geração roda em fila: o resultado não vem mais no flash do Inertia, e sim
 * persistido no backend. Este composable busca a última execução da gôndola corrente
 * para hidratar o banner de capacidade/validação, e faz polling enquanto houver uma
 * geração em andamento — recarregando a página quando ela termina, para o editor
 * exibir os segmentos recém-posicionados.
 */
export interface GenerationRun {
    id: string;
    status: 'queued' | 'running' | 'completed' | 'failed';
    status_label: string;
    is_pending: boolean;
    mode: 'automatic' | 'template';
    occupancy_avg: number | null;
    occupancy_min: number | null;
    occupancy_max: number | null;
    iterations_run: number | null;
    converged: boolean | null;
    duration_ms: number | null;
    error_message: string | null;
    created_at: string | null;
    finished_at: string | null;
    config_snapshot?: Record<string, unknown> | null;
    capacity_report?: Record<string, any> | null;
    validation_report?: Record<string, any> | null;
    template_id?: string | null;
    synth_template_id?: string | null;
}

/** Intervalo do polling enquanto a geração está na fila/rodando. */
const POLL_INTERVAL_MS = 3000;

/**
 * Teto de tentativas de polling (~10min, alinhado ao `timeout = 600` do job).
 * Evita um `setTimeout` recursivo rodando para sempre numa aba esquecida aberta
 * caso o run nunca saia do estado pendente.
 */
const MAX_POLL_ATTEMPTS = 200;

/** Tempo em `queued` a partir do qual a fila é considerada parada (estado "travada"). */
const STUCK_THRESHOLD_MS = 60_000;

/** Intervalo do relógio de tempo decorrido exibido no overlay ("há Ns"). */
const ELAPSED_TICK_MS = 1000;

/** Segundos exibidos no contador de "recarregando em..." antes do `router.reload()`. */
const RELOAD_COUNTDOWN_SECONDS = 3;

export function useGenerationRun() {
    const latestRun = ref<GenerationRun | null>(null);
    const isPolling = ref(false);
    /** Tempo decorrido (ms) desde `latestRun.created_at`, atualizado a cada segundo. */
    const elapsedMs = ref(0);
    /**
     * Overlay fechado manualmente pelo usuário nos estados de falha/travamento.
     * Não interrompe o polling — a geração pode se recuperar sozinha.
     */
    const dismissed = ref(false);
    /**
     * Segundos restantes até o `router.reload()` após concluir (0 = nenhum
     * reload agendado). Alimenta o contador visível no overlay ("recarregando em 3…").
     */
    const reloadCountdown = ref(0);

    let pollTimer: ReturnType<typeof setTimeout> | null = null;
    let elapsedTimer: ReturnType<typeof setInterval> | null = null;
    let reloadCountdownTimer: ReturnType<typeof setInterval> | null = null;
    let pollAttempts = 0;

    /**
     * Aplica um run recém-buscado ao estado. Reseta `dismissed` quando o id muda
     * (nova execução) para que o overlay volte a aparecer mesmo se a anterior
     * tiver sido fechada pelo usuário.
     */
    function applyRun(run: GenerationRun | null): void {
        if (run && run.id !== latestRun.value?.id) {
            dismissed.value = false;
        }

        latestRun.value = run;
    }

    /** Fecha o overlay sem parar o acompanhamento da geração. */
    function dismiss(): void {
        dismissed.value = true;
    }

    function updateElapsedMs(): void {
        const createdAt = latestRun.value?.created_at;

        elapsedMs.value = createdAt ? Date.now() - new Date(createdAt).getTime() : 0;
    }

    function startElapsedClock(): void {
        stopElapsedClock();
        updateElapsedMs();
        elapsedTimer = setInterval(updateElapsedMs, ELAPSED_TICK_MS);
    }

    function stopElapsedClock(): void {
        if (elapsedTimer !== null) {
            clearInterval(elapsedTimer);
            elapsedTimer = null;
        }
    }

    /** Conta `RELOAD_COUNTDOWN_SECONDS` e então recarrega a página. */
    function startReloadCountdown(): void {
        stopReloadCountdown();
        reloadCountdown.value = RELOAD_COUNTDOWN_SECONDS;

        reloadCountdownTimer = setInterval(() => {
            reloadCountdown.value -= 1;

            if (reloadCountdown.value <= 0) {
                stopReloadCountdown();
                router.reload();
            }
        }, ELAPSED_TICK_MS);
    }

    function stopReloadCountdown(): void {
        if (reloadCountdownTimer !== null) {
            clearInterval(reloadCountdownTimer);
            reloadCountdownTimer = null;
        }

        reloadCountdown.value = 0;
    }

    /**
     * Repete a última execução com a MESMA configuração (`config_snapshot` é o
     * snapshot exato do request original, gravado pelo GenerationQueueDispatcher).
     * Reusa o endpoint de geração já existente — nenhuma rota nova.
     */
    async function retry(): Promise<void> {
        const gondolaId = currentGondola.value?.id;
        const run = latestRun.value;

        if (!gondolaId || !run) {
            return;
        }

        dismissed.value = false;

        router.post(
            generateAutoPlanogram.url(gondolaId),
            {
                ...(run.config_snapshot ?? {}),
                template_id: run.template_id ?? null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    void fetchLatest();
                },
            },
        );
    }

    /**
     * Monta a URL da API de execuções. Helper local (não Wayfinder): mesmo padrão dos
     * demais endpoints `api/gondolas/*` do pacote (ver useRejectedProductsModule).
     */
    function runsApiUrl(gondolaId: string, path: string): string {
        return `/api/gondolas/${gondolaId}/generation-runs/${path}`;
    }

    /** Última execução da gôndola — fonte do relatório exibido no editor. */
    async function fetchLatest(): Promise<void> {
        const gondolaId = currentGondola.value?.id;

        if (!gondolaId) {
            return;
        }

        try {
            const res = await fetch(runsApiUrl(gondolaId, 'latest'));

            if (!res.ok) {
                return;
            }

            const json = await res.json();
            applyRun((json.data as GenerationRun | null) ?? null);

            // Geração ainda em andamento (usuário reabriu o editor antes de terminar):
            // continua acompanhando até concluir.
            if (latestRun.value?.is_pending) {
                startPolling();
            }
        } catch {
            // Falha de rede não deve quebrar o editor — o banner apenas não aparece.
        }
    }

    /**
     * Acompanha a execução pendente até concluir. Ao concluir, recarrega os dados da
     * página para o editor refletir os segmentos gerados.
     */
    function startPolling(): void {
        if (isPolling.value) {
            return;
        }

        pollAttempts = 0;
        isPolling.value = true;
        scheduleNextPoll();
    }

    function scheduleNextPoll(): void {
        stopTimer();

        pollTimer = setTimeout(async () => {
            pollAttempts += 1;

            if (pollAttempts > MAX_POLL_ATTEMPTS) {
                stopPolling();

                return;
            }

            const gondolaId = currentGondola.value?.id;

            if (!gondolaId) {
                stopPolling();

                return;
            }

            try {
                const res = await fetch(runsApiUrl(gondolaId, 'latest'));
                const json = await res.json();
                const run = (json.data as GenerationRun | null) ?? null;

                applyRun(run);

                if (run && run.is_pending) {
                    scheduleNextPoll();

                    return;
                }

                stopPolling();

                // Concluída: mostra o contador de "recarregando em..." e então recarrega
                // os dados da página, para o editor exibir os segmentos recém-posicionados
                // (falha não recarrega — nada mudou).
                if (run && run.status === 'completed') {
                    startReloadCountdown();
                }
            } catch {
                stopPolling();
            }
        }, POLL_INTERVAL_MS);
    }

    function stopPolling(): void {
        isPolling.value = false;
        stopTimer();
    }

    function stopTimer(): void {
        if (pollTimer !== null) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    /** Relatório de capacidade da última execução (alimenta o banner do editor). */
    const capacityReport = computed(() => latestRun.value?.capacity_report ?? null);

    /** Relatório de validação da última execução. */
    const validationReport = computed(() => latestRun.value?.validation_report ?? null);

    /** Há geração em andamento para esta gôndola? */
    const isGenerating = computed(() => latestRun.value?.is_pending === true);

    /** A última execução falhou (erro técnico ou cancelamento de negócio). */
    const hasFailed = computed(() => latestRun.value?.status === 'failed');

    /** Run parado em `queued` além do limiar — sinal de que o Horizon não está consumindo a fila. */
    const isStuck = computed(
        () => latestRun.value?.status === 'queued' && elapsedMs.value > STUCK_THRESHOLD_MS,
    );

    /** Contador de reload em andamento — a geração acabou de concluir. */
    const justCompleted = computed(() => reloadCountdown.value > 0);

    // O relógio de "há Ns" só roda enquanto há geração em andamento.
    watch(
        isGenerating,
        (pending) => {
            if (pending) {
                startElapsedClock();
            } else {
                stopElapsedClock();
            }
        },
        { immediate: true },
    );

    // Trocar de gôndola no editor deve trocar o relatório exibido.
    watch(
        () => currentGondola.value?.id,
        (gondolaId) => {
            stopPolling();
            stopElapsedClock();
            stopReloadCountdown();
            latestRun.value = null;
            dismissed.value = false;
            elapsedMs.value = 0;

            if (gondolaId) {
                void fetchLatest();
            }
        },
        { immediate: true },
    );

    return {
        latestRun,
        capacityReport,
        validationReport,
        isGenerating,
        hasFailed,
        isStuck,
        isPolling,
        elapsedMs,
        dismissed,
        justCompleted,
        reloadCountdown,
        dismiss,
        retry,
        fetchLatest,
        startPolling,
        stopPolling,
    };
}
