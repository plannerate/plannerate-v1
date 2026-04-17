<script setup lang="ts">
import BackendTabs from '~/components/table/BackendTabs.vue';
import type { KanbanIndexProps } from '@/types/workflow';
import { FlowKanbanView } from '@flow';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BackendBreadcrumb } from '~/composables/useBreadcrumbs';
import ResourceLayout from '~/layouts/ResourceLayout.vue';

interface BackendTab {
    key: string;
    name: string;
    href: string;
    icon?: string;
    badge?: number | null;
    active?: boolean;
}

interface Props extends KanbanIndexProps {
    message?: string;
    resourceLabel?: string;
    resourcePluralLabel?: string;
    breadcrumbs?: BackendBreadcrumb[];
    tabs?: BackendTab[];
}

const props = defineProps<Props>();

const page = usePage();
const currentUserId = computed(
    () => (page.props.auth as any)?.user?.id ?? null,
);

const layoutProps = {
    message: props.message,
    resourceLabel: props.resourceLabel,
    resourcePluralLabel: props.resourcePluralLabel,
    breadcrumbs: props.breadcrumbs,
};
</script>

<template>
    <ResourceLayout v-bind="layoutProps" title="Kanban" :full-height="true">
        <div class="flex h-full flex-1 flex-col">
            <div class="shrink-0 border-b border-border/60 px-4 py-3">
                 <BackendTabs v-if="tabs?.length" :tabs="tabs" />
            </div>

            <div class="min-h-0 flex-1">
                <FlowKanbanView
                    :board="board"
                    :group-configs="groupConfigs"
                    :filters="filters"
                    :user-roles="userRoles"
                    :card-config="cardConfig"
                    :current-user-id="currentUserId"
                    :detail-modal-config="detailModalConfig"
                    title="Kanban"
                    :show-filters="true"
                />
            </div>
        </div>
    </ResourceLayout>
</template>
