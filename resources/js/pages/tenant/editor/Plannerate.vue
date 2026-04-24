<script setup lang="ts">
import Planogram from '@/components/plannerate/v3/Planogram.vue';
// @ts-expect-error - BackendBreadcrumb type definition may not be available
import { type BackendBreadcrumb } from '@/composables/useBreadcrumbs';
import SimpleLayout from '@/layouts/SimpleLayout.vue';

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
</script>

<template>
    <SimpleLayout :maxWidth="props.maxWidth">
        <Planogram
            :record="record"
            :products="products"
            :available-users="availableUsers"
            :analysis="analysis"
            :saveChangesRoute="saveChangesRoute"
            :backRoute="backRoute"
            :permissions="permissions"
        />
    </SimpleLayout>
</template>