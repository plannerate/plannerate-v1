<script setup lang="ts">
/**
 * Barra-resumo da última geração exibida no editor.
 *
 * Antes o editor despejava os relatórios inteiros (capacidade, produtos alocados,
 * sugestões, validação) logo abaixo do canvas — dezenas de linhas empurrando o
 * planograma para fora da tela. Aqui fica só a linha de números, com link para a
 * página do relatório, onde o detalhe completo é exibido.
 */
import { ExternalLink, FileBarChart } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';

interface Props {
    /** capacity_report da última execução (null quando a gôndola nunca foi gerada) */
    report: Record<string, any> | null;
    /** validation_report da última execução — só a contagem de erros aparece aqui */
    validationReport: Record<string, any> | null;
    gondolaId: string;
}

const props = defineProps<Props>();

const { t } = useT();

/** Página do relatório desta gôndola (helper local: rota não rastreada pelo Wayfinder) */
const reportUrl = computed(() => `/editor/gondolas/${props.gondolaId}/generation-report`);

const placed = computed<number>(() => props.report?.posicionados ?? 0);
const total = computed<number>(() => props.report?.total_produtos ?? 0);
const noSpace = computed<number>(() => props.report?.rejeitados_espaco ?? 0);
const noDimensions = computed<number>(() => props.report?.rejeitados_sem_dimensao ?? 0);
const heightExceeds = computed<number>(() => props.report?.rejeitados_altura ?? 0);
const suggestions = computed<number>(() => props.report?.suggestions?.length ?? 0);
const validationErrors = computed<number>(() => props.validationReport?.error_count ?? 0);

/** Há algo que o usuário precise resolver? Muda a cor da barra de neutra para âmbar. */
const hasIssues = computed(
    () => noSpace.value > 0 || noDimensions.value > 0 || heightExceeds.value > 0 || validationErrors.value > 0,
);
</script>

<template>
    <div
        v-if="report"
        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-4 py-2 text-sm"
        :class="hasIssues
            ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40'
            : 'border-border bg-muted/40'"
    >
        <FileBarChart
            class="size-4 shrink-0"
            :class="hasIssues ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
        />

        <span class="font-medium">{{ t('plannerate.generation.report.summary.title') }}</span>

        <span :class="hasIssues ? 'text-amber-800 dark:text-amber-200' : 'text-muted-foreground'">
            {{ t('plannerate.generation.report.summary.positioned', { placed: String(placed), total: String(total) }) }}
        </span>

        <!-- Pendências: só aparecem quando existem -->
        <span v-if="noSpace > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
            {{ t('plannerate.generation.report.summary.no_space', { count: String(noSpace) }) }}
        </span>
        <span v-if="noDimensions > 0" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
            {{ t('plannerate.generation.report.summary.no_dimensions', { count: String(noDimensions) }) }}
        </span>
        <span v-if="heightExceeds > 0" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
            {{ t('plannerate.generation.report.summary.height_exceeds', { count: String(heightExceeds) }) }}
        </span>
        <span v-if="validationErrors > 0" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
            {{ t('plannerate.generation.report.summary.validation_errors', { count: String(validationErrors) }) }}
        </span>
        <span v-if="suggestions > 0" class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
            {{ t('plannerate.generation.report.summary.suggestions', { count: String(suggestions) }) }}
        </span>

        <!--
            Âncora nativa (não o <Link> do Inertia): o Link intercepta o clique e
            navegaria na mesma aba, tirando o usuário do editor. Aqui o relatório
            abre em aba separada, com o editor intacto por baixo.
        -->
        <a
            :href="reportUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="ml-auto inline-flex items-center gap-1 font-medium text-primary hover:underline"
        >
            {{ t('plannerate.generation.report.link') }}
            <ExternalLink class="size-3.5" />
        </a>
    </div>
</template>
