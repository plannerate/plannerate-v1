<script setup lang="ts">
import GondolaCards from '@/components/plannerate/GondolaCards.vue';
import GondolaCreateStepper from '@/components/plannerate/v3/form/GondolaCreateStepper.vue';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Button } from '~/components/ui/button';
import { BackendBreadcrumb } from '~/composables/useBreadcrumbs';
import ResourceLayout from '~/layouts/ResourceLayout.vue';

interface Props {
    message?: string;
    resourceName?: string;
    resourcePluralName?: string;
    resourceLabel?: string;
    resourcePluralLabel?: string;
    maxWidth?: string;
    breadcrumbs?: BackendBreadcrumb[];
    record: any;
    filters?: any;
    users?: any[];
}

const props = withDefaults(defineProps<Props>(), {
    resourceName: 'planogram',
    resourcePluralName: 'planograms',
    resourceLabel: 'Planograma',
    resourcePluralLabel: 'Planogramas',
    maxWidth: 'full',
    filters: () => ({}),
    users: () => [],
});

// Modal state
const isCreateModalOpen = ref(false);

// Handle success from modal
const handleGondolaCreated = () => {
    isCreateModalOpen.value = false;
    // Reload page to show new gondola
    router.reload({ only: ['record'] });
};

const handleApplyFilters = (filters: any) => {
    router.get(window.location.pathname, filters, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleClearFilters = () => {
    router.get(
        window.location.pathname,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <ResourceLayout v-bind="props" :title="record.name" :message="`Gôndolas - ${record.name}`" :breadcrumbs="breadcrumbs">
        <template #header-actions>
            <Button @click="isCreateModalOpen = true" size="sm" class="gap-2 mt-2">
                <ActionIconBox variant="default">
                    <Plus />
                </ActionIconBox>
                Nova Gôndola
            </Button>
        </template>
        <template #content>

            <div class="flex h-full min-h-0 w-full flex-col gap-4 p-4 sm:p-6"> 
                <!-- Conteúdo -->
                <div class="min-h-0 flex-1">
                    <!-- Gôndolas Cards -->
                    <GondolaCards
                        v-if="record.gondolas && record.gondolas.length > 0"
                        :gondolas="record.gondolas"
                        :planogram-id="record.id"
                    />

                    <div
                        v-else
                        class="flex min-h-[280px] flex-col items-center justify-center gap-4 rounded-lg border border-dashed py-12"
                    >
                        <p class="text-sm text-muted-foreground">
                            Nenhuma gôndola encontrada neste planograma
                        </p>
                        <Button
                            @click="isCreateModalOpen = true"
                            variant="outline"
                            size="sm"
                            class="gap-2"
                        >
                            <ActionIconBox variant="outline">
                                <Plus />
                            </ActionIconBox>
                            Criar Primeira Gôndola
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Modal de criação -->
            <GondolaCreateStepper
                v-model:open="isCreateModalOpen"
                :planogram-id="record.id"
                @success="handleGondolaCreated"
            />
        </template>
    </ResourceLayout>
</template>
