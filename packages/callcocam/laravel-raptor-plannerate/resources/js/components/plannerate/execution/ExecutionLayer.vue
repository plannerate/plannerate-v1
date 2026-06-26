<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ExecutionBar from './ExecutionBar.vue';
import AddDivergenceModal from './modals/AddDivergenceModal.vue';
import AddEvidenceModal from './modals/AddEvidenceModal.vue';
import CompleteExecutionModal from './modals/CompleteExecutionModal.vue';
import type { ExecutionPayload } from './types';

/**
 * Camada de Execução em Loja acoplada à tela de print read-only.
 *
 * O payload pesado (`execution`) chega via Inertia::optional: na montagem
 * dispara um partial reload que também inicia automaticamente a execução
 * pendente (export §12). Após cada mutação, refaz o partial reload para
 * atualizar a barra-resumo e os modais.
 */
const props = defineProps<{
    execution: ExecutionPayload | null;
}>();

const loadingPayload = ref(false);

const showEvidence = ref(false);
const showDivergence = ref(false);
const showComplete = ref(false);

const execution = computed(() => props.execution);

/** Recarrega apenas o payload de execução (partial reload). */
function refresh(): void {
    loadingPayload.value = true;
    router.reload({
        only: ['execution'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            loadingPayload.value = false;
        },
    });
}

onMounted(() => {
    // Carrega o payload sob demanda (e dispara o início automático no backend).
    if (!props.execution) {
        refresh();
    }
});

/** Conclusão bem-sucedida: navega para o board (o card sai da listagem). */
function onCompleted(): void {
    showComplete.value = false;
    router.visit('/kanban');
}
</script>

<template>
    <div>
        <ExecutionBar
            :execution="execution"
            :loading="loadingPayload && !execution"
            @add-evidence="showEvidence = true"
            @add-divergence="showDivergence = true"
            @complete="showComplete = true"
        />

        <AddEvidenceModal
            v-if="execution"
            v-model:open="showEvidence"
            :execution-id="execution.id"
            :summary="execution.evidence_summary"
            @saved="refresh"
        />

        <AddDivergenceModal
            v-if="execution"
            v-model:open="showDivergence"
            :execution-id="execution.id"
            :divergences="execution.divergences"
            @saved="refresh"
        />

        <CompleteExecutionModal
            v-if="execution"
            v-model:open="showComplete"
            :execution="execution"
            @completed="onCompleted"
            @go-evidence="showComplete = false; showEvidence = true"
            @go-divergence="showComplete = false; showDivergence = true"
        />
    </div>
</template>
