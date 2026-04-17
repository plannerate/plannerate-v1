<script setup lang="ts">
import { FlowKanbanView } from '@flow';
import FixedSidebarLayout from '@/layouts/FixedSidebarLayout.vue';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import GondolaCreateStepper from '@/components/plannerate/v3/form/GondolaCreateStepper.vue';
import type { KanbanIndexProps } from '@/types/workflow';
import type { BackendBreadcrumb } from '~/composables/useBreadcrumbs';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<
    KanbanIndexProps & {
        breadcrumbs?: BackendBreadcrumb[];
        resourceLabel?: string;
        message?: string;
        resourceName?: string;
        resourcePluralName?: string;
        resourcePluralLabel?: string;
        maxWidth?: string;
        lojas?: any[];
    }
>();

const page = usePage();
const currentUserId = computed(() => (page.props.auth as any)?.user?.id ?? null);
const isCreateModalOpen = ref(false);

const planogramIdForCreate = computed(() => props.planogramIdForCreate ?? null);
const canCreateGondola = computed(() => !!planogramIdForCreate.value);

function handleGondolaCreated() {
    isCreateModalOpen.value = false;
    router.reload({ only: ['board'] });
}
</script>

<template>
    <FixedSidebarLayout
        :title="resourceName || 'Kanban'"
        :max-width="maxWidth || 'full'"
        :breadcrumbs="breadcrumbs"
        :message="message"
        :resource-label="resourceLabel"
    >
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
        >
            <template #header-actions>
                <Button
                    v-if="canCreateGondola"
                    @click="isCreateModalOpen = true"
                    size="sm"
                    class="gap-2"
                >
                    <ActionIconBox variant="default">
                        <Plus />
                    </ActionIconBox>
                    Nova Gôndola
                </Button>
            </template>
        </FlowKanbanView>

        <GondolaCreateStepper
            v-if="canCreateGondola && planogramIdForCreate"
            v-model:open="isCreateModalOpen"
            :planogram-id="planogramIdForCreate"
            @success="handleGondolaCreated"
        />
    </FixedSidebarLayout>
</template>
