<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlanogramCapacityBanner from '@/components/PlanogramCapacityBanner.vue';
import PlanogramSuggestions from '@/components/PlanogramSuggestions.vue';
import PlanogramValidationReport from '@/components/PlanogramValidationReport.vue';
import Planogram from '@/components/plannerate/Planogram.vue';
import PlanogramAuto from '@/components/plannerate/PlanogramAuto.vue';
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
const validationReport = computed(() => (page.props.flash as any)?.validation_report ?? null);
const capacityReport = computed(() => (page.props.flash as any)?.capacity_report ?? null);
const suggestionsReport = computed(() => {
    const report = capacityReport.value;
    if (!report?.suggestions?.length || !report?.template_id) return null;
    return report;
});

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
        <PlanogramCapacityBanner
            :report="capacityReport"
            class="mx-4 mb-2"
        />
        <PlanogramSuggestions
            v-if="suggestionsReport"
            :suggestions="suggestionsReport.suggestions"
            :template-id="suggestionsReport.template_id"
            class="mx-4 mb-2"
        />
        <PlanogramValidationReport
            v-if="validationReport"
            :report="validationReport"
            class="mx-4 mb-4"
        />
    </SimpleLayout>
</template>