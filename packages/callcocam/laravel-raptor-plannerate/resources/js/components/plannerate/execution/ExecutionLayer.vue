<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, toRef, watch } from 'vue';
import type { Section } from '@/types/planogram';
import ExecutionBar from './ExecutionBar.vue';
import AddDivergenceModal from './modals/AddDivergenceModal.vue';
import AddEvidenceModal from './modals/AddEvidenceModal.vue';
import CompleteExecutionModal from './modals/CompleteExecutionModal.vue';
import type { ExecutionPayload } from './types';
import { useExecutionStructure } from './useExecutionStructure';

/**
 * Camada de Execução em Loja acoplada à tela de print read-only.
 *
 * O `execution` já vem montado no carregamento (prop normal, montado só quando
 * `canExecute`). As mutações usam `back()` recarregando apenas `execution`
 * (`only`), então a barra/modais refletem o estado novo sem reset. A barra-resumo
 * é teleportada para dentro da toolbar de print (ver ExecutionBar), formando uma
 * única linha — por isso não há mais deslocamento manual da toolbar aqui.
 */
const props = defineProps<{
    execution: ExecutionPayload | null;
    sections?: Section[];
}>();

const { modules } = useExecutionStructure(toRef(props, 'sections'));

const showEvidence = ref(false);
const showDivergence = ref(false);
const showComplete = ref(false);
// Quando o usuário sai do modal de Concluir para Adicionar/Resolver, reabrimos
// o Concluir ao fechar o modal intermediário.
const returnToComplete = ref(false);

const execution = computed(() => props.execution);

/** Vai do Concluir para o modal de evidência, marcando para retornar. */
function openEvidenceFromComplete(): void {
    showComplete.value = false;
    returnToComplete.value = true;
    showEvidence.value = true;
}

/** Vai do Concluir para o modal de divergência, marcando para retornar. */
function openDivergenceFromComplete(): void {
    showComplete.value = false;
    returnToComplete.value = true;
    showDivergence.value = true;
}

// Ao fechar o modal intermediário, reabre o Concluir (se viemos de lá).
watch([showEvidence, showDivergence], ([evidenceOpen, divergenceOpen]) => {
    if (!evidenceOpen && !divergenceOpen && returnToComplete.value) {
        returnToComplete.value = false;
        showComplete.value = true;
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
            :loading="!execution"
            @add-evidence="showEvidence = true"
            @add-divergence="showDivergence = true"
            @complete="showComplete = true"
        />

        <AddEvidenceModal
            v-if="execution"
            v-model:open="showEvidence"
            :execution-id="execution.id"
            :summary="execution.evidence_summary"
            :evidences="execution.evidences"
            :modules="modules"
        />

        <AddDivergenceModal
            v-if="execution"
            v-model:open="showDivergence"
            :execution-id="execution.id"
            :divergences="execution.divergences"
            :sections="props.sections ?? []"
        />

        <CompleteExecutionModal
            v-if="execution"
            v-model:open="showComplete"
            :execution="execution"
            @completed="onCompleted"
            @go-evidence="openEvidenceFromComplete"
            @go-divergence="openDivergenceFromComplete"
        />
    </div>
</template>
