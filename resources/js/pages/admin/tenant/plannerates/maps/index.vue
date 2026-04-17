<script setup lang="ts">
import ResourceLayout from '~/layouts/ResourceLayout.vue';
import BackendTabs from '~/components/table/BackendTabs.vue';
import StoreMapViewer from '@/components/plannerate/StoreMapViewer.vue';
import type { BackendBreadcrumb } from '~/composables/useBreadcrumbs';

interface BackendTab {
    key: string;
    name: string;
    href: string;
    icon?: string;
    badge?: number | null;
    active?: boolean;
}

interface Props {
    message?: string;
    resourceLabel?: string;
    resourcePluralLabel?: string;
    breadcrumbs?: BackendBreadcrumb[];
    stores?: any[];
    tabs?: BackendTab[];
}

const props = defineProps<Props>();

const layoutProps = {
    message: props.message,
    resourceLabel: props.resourceLabel,
    resourcePluralLabel: props.resourcePluralLabel,
    breadcrumbs: props.breadcrumbs,
};
</script>

<template>
    <ResourceLayout v-bind="layoutProps" title="Mapas" :max-width="'full'">
        <template #content>
               <div>
                  <BackendTabs v-if="tabs?.length" :tabs="tabs" />
               </div>
            <StoreMapViewer :stores="stores || []" />
        </template>
    </ResourceLayout>
</template>
