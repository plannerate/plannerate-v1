<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GenerationOverlay from '@/components/plannerate/generation/GenerationOverlay.vue';
import Planogram from '@/components/plannerate/Planogram.vue';
import PlanogramAuto from '@/components/plannerate/PlanogramAuto.vue';
import ReoptimizationBanner from '@/components/plannerate/reoptimization/ReoptimizationBanner.vue';
import PlanogramGenerationSummary from '@/components/PlanogramGenerationSummary.vue';
import { useGenerationRun } from '@/composables/plannerate/generation/useGenerationRun';
// @ts-expect-error - BackendBreadcrumb type definition may not be available
import SimpleLayout from '@/layouts/SimpleLayout.vue';
import type {BackendBreadcrumb} from '@/composables/useBreadcrumbs';

interface Product {
    id: string;
    name: string;
    sku: string;
    ean?: string;
    image_url?: string;
    width?: number;
    height?: number;
    depth?: number;
}

interface Props {
    message?: string;
    resourceName?: string;
    resourcePluralName?: string;
    resourceLabel?: string;
    resourcePluralLabel?: string;
    maxWidth?: string;
    breadcrumbs?: BackendBreadcrumb[];
    record: any;
    products?: Product[];
    availableUsers?: Array<{ id: string; name: string }>;
    saveChangesRoute?: string;
    backRoute?: string;
    analysis?: {
        abc?: any;
        stock?: any;
    };
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
        can_remove_gondola: boolean;
        can_autogenate_gondola: boolean;
        can_autogenate_gondola_ia: boolean;
    };
}

const props = withDefaults(defineProps<Props>(), {
    resourceName: 'planogram',
    resourcePluralName: 'planograms',
    resourceLabel: 'Planograma',
    resourcePluralLabel: 'Planogramas',
    maxWidth: 'full',
    saveChangesRoute: '',
    products: () => [],
    availableUsers: () => [],
    analysis: () => ({}),
    permissions: () => ({
        can_create_gondola: false,
        can_update_gondola: false,
        can_remove_gondola: false,
        can_autogenate_gondola: true,
        can_autogenate_gondola_ia: true,
    }),
});

// Precisam ser computed, NÃO desestruturados: `const { record } = props` congela a
// referência do setup. Depois de um `router.reload()` (o que a geração faz ao
// concluir) o Inertia entrega um `record` novo em props, mas a const continuava
// apontando para o objeto antigo — o PlanogramEditor nunca via a mudança, o watch
// de `record` não disparava e a gôndola recém-gerada não aparecia na tela.
const record = computed(() => props.record);
const products = computed(() => props.products);
const analysis = computed(() => props.analysis);

const page = usePage();

// A geração roda em fila: os relatórios não vêm mais no flash do Inertia, e sim
// persistidos na última execução (PlanogramGenerationRun). O flash é mantido como
// fallback para qualquer fluxo legado que ainda o preencha.
const {
    latestRun,
    capacityReport: runCapacityReport,
    validationReport: runValidationReport,
    isGenerating,
    hasFailed,
    isStuck,
    elapsedMs,
    dismissed,
    justCompleted,
    reloadCountdown,
    dismiss,
    retry,
} = useGenerationRun();

const validationReport = computed(
    () => runValidationReport.value ?? (page.props.flash as any)?.validation_report ?? null,
);
const capacityReport = computed(
    () => runCapacityReport.value ?? (page.props.flash as any)?.capacity_report ?? null,
);

const editorComponent = computed(() =>
    props.record?.generation_mode && props.record.generation_mode !== 'manual'
        ? PlanogramAuto
        : Planogram,
);

// Overlay: aparece durante a geração, na falha e no flash de sucesso; some ao
// concluir de fato (router.reload) ou quando o usuário o fecha explicitamente.
const showOverlay = computed(
    () => (isGenerating.value || hasFailed.value || justCompleted.value) && !dismissed.value,
);

// A trava só se justifica enquanto os segmentos ainda serão sobrescritos: run
// pendente (queued/running) ou no flash de sucesso, antes do reload. Falha não
// trava — nada será sobrescrito, e o usuário pode seguir editando ou tentar de novo.
const lockEditor = computed(() => isGenerating.value || justCompleted.value);
</script>

<template>
    <SimpleLayout :maxWidth="props.maxWidth">
        <div class="relative w-full">
            <div :inert="lockEditor" :aria-hidden="lockEditor" :class="lockEditor ? 'pointer-events-none' : ''">
                <component
                    :is="editorComponent"
                    :record="record"
                    :products="products"
                    :available-users="availableUsers"
                    :analysis="analysis"
                    :saveChangesRoute="saveChangesRoute"
                    :backRoute="backRoute"
                    :permissions="permissions"
                />
            </div>

            <GenerationOverlay
                v-if="showOverlay"
                :run="latestRun"
                :elapsed-ms="elapsedMs"
                :is-stuck="isStuck"
                :reload-countdown="reloadCountdown"
                :back-route="backRoute ?? ''"
                class="absolute inset-0"
                @dismiss="dismiss"
                @retry="retry"
            />
        </div>
        <!--
            O relatório completo (capacidade, alocados, sugestões, validação) mora em
            página própria: aqui fica só a linha-resumo com o link, para não empurrar
            o planograma para fora da tela.
        -->
        <ReoptimizationBanner v-if="record?.id" :gondola-id="record.id" class="mx-4 mb-3" />

        <PlanogramGenerationSummary
            v-if="record?.id"
            :report="capacityReport"
            :validation-report="validationReport"
            :gondola-id="record.id"
            class="mx-4 mb-4"
        />
    </SimpleLayout>
</template>