import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

export function useGenerationRun() {
    const latestRun = ref<GenerationRun | null>(null);
    const isPolling = ref(false);

    let pollTimer: ReturnType<typeof setTimeout> | null = null;

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
            latestRun.value = (json.data as GenerationRun | null) ?? null;

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

        isPolling.value = true;
        scheduleNextPoll();
    }

    function scheduleNextPoll(): void {
        stopTimer();

        pollTimer = setTimeout(async () => {
            const gondolaId = currentGondola.value?.id;

            if (!gondolaId) {
                stopPolling();

                return;
            }

            try {
                const res = await fetch(runsApiUrl(gondolaId, 'latest'));
                const json = await res.json();
                const run = (json.data as GenerationRun | null) ?? null;

                latestRun.value = run;

                if (run && run.is_pending) {
                    scheduleNextPoll();

                    return;
                }

                stopPolling();

                // Concluída: recarrega os dados da página para o editor exibir os
                // segmentos recém-posicionados (falha não recarrega — nada mudou).
                if (run && run.status === 'completed') {
                    router.reload();
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

    // Trocar de gôndola no editor deve trocar o relatório exibido.
    watch(
        () => currentGondola.value?.id,
        (gondolaId) => {
            stopPolling();
            latestRun.value = null;

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
        isPolling,
        fetchLatest,
        startPolling,
        stopPolling,
    };
}
