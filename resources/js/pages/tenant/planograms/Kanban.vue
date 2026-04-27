<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Kanban } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import KanbanBoard from '@/components/kanban/KanbanBoard.vue';
import KanbanFilters from '@/components/kanban/KanbanFilters.vue';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import { useKanban } from '@/composables/useKanban';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';
import type { KanbanPageProps } from '@/components/kanban/types';

const props = defineProps<KanbanPageProps>();

const { t } = useT();

const pageMeta = useCrudPageMeta({
    headTitle: 'Kanban',
    title: 'Kanban',
    description: 'Gerencie o fluxo de trabalho dos planogramas',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: 'Kanban', href: '#' },
    ],
});

const { onlyOverdue, showCompleted, filteredBoard } = useKanban(() => props.board);
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <template #header-actions>
            <KankanNavigationLinks :subdomain="props.subdomain" />
        </template>

        <div class="flex h-full flex-col">
            <div class="border-b border-border bg-background px-4 py-3">
                <KanbanFilters
                    :subdomain="props.subdomain"
                    :planograms="props.planograms"
                    :stores="props.stores"
                    :filters="props.filters"
                    :only-overdue="onlyOverdue"
                    :show-completed="showCompleted"
                    @update:only-overdue="onlyOverdue = $event"
                    @update:show-completed="showCompleted = $event"
                />
            </div>

            <div
                v-if="!props.selected_planogram"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">Selecione um planograma para visualizar o kanban</p>
            </div>

            <div
                v-else-if="filteredBoard.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">Nenhuma etapa configurada para este planograma</p>
            </div>

            <KanbanBoard v-else :board="filteredBoard" />
        </div>
    </AppLayout>
</template>
