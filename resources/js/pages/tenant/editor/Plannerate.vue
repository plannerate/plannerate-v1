<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlanogramGenerationSummary from '@/components/PlanogramGenerationSummary.vue';
import Planogram from '@/components/plannerate/Planogram.vue';
import PlanogramAuto from '@/components/plannerate/PlanogramAuto.vue';
import { useGenerationRun } from '@/composables/plannerate/generation/useGenerationRun';
import { useT } from '@/composables/useT';
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

// Separar props para ResourceLayout (sem record)
const { record, products, analysis } = props;

const page = usePage();
const { t } = useT();

// A geração roda em fila: os relatórios não vêm mais no flash do Inertia, e sim
// persistidos na última execução (PlanogramGenerationRun). O flash é mantido como
// fallback para qualquer fluxo legado que ainda o preencha.
const {
    capacityReport: runCapacityReport,
    validationReport: runValidationReport,
    isGenerating,
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
</script>

<template>
    <SimpleLayout :maxWidth="props.maxWidth">
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
        <div
            v-if="isGenerating"
            class="mx-4 mb-2 flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
        >
            <span class="size-2 animate-pulse rounded-full bg-blue-500" />
            {{ t('plannerate.generation.history.in_progress') }}
        </div>
        <!--
            O relatório completo (capacidade, alocados, sugestões, validação) mora em
            página própria: aqui fica só a linha-resumo com o link, para não empurrar
            o planograma para fora da tela.
        -->
        <PlanogramGenerationSummary
            v-if="record?.id"
            :report="capacityReport"
            :validation-report="validationReport"
            :gondola-id="record.id"
            class="mx-4 mb-4"
        />
    </SimpleLayout>
</template>