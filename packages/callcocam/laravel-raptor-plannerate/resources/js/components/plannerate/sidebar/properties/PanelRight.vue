<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div
            v-if="open"
            class="z-20 flex h-full w-full sm:w-80 2xl:w-96  flex-col overflow-hidden border-l border-border bg-background"
            data-properties-panel
        >
            <!-- Header -->
            <div
                class="flex w-full shrink-0 items-center justify-start border-b border-border"
            >
                <button
                    class="rounded p-3 transition-colors hover:bg-accent"
                    @click="emit('close')"
                    type="button"
                >
                    <X class="size-4 text-foreground" />
                </button>
            </div>

            <!-- Content -->
            <div class="w-full flex-1 overflow-y-auto p-4 pb-8">
                <component
                    :is="currentComponent"
                    :item="selectedItem?.item"
                    v-if="selectedItem"
                />
                <NoSelection v-else />
            </div>
        </div>
    </Transition>
</template>
<script setup lang="ts">
import { usePlanogramSelection } from '@/composables/plannerate/usePlanogramSelection';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import NoSelection from './partials/NoSelection.vue';
import ProductDetails from './partials/ProductDetails.vue';
import SectionDetails from './partials/SectionDetails.vue';
import SegmentDetails from './partials/SegmentDetails.vue';
import ShelfDetails from './partials/ShelfDetails.vue';

const open = defineModel<boolean>('open');

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const selection = usePlanogramSelection();
const selectedItem = computed(() => selection.selectedItem.value);

const currentComponent = computed(() => {
    if (!selectedItem.value) {
        return NoSelection;
    }
    switch (selectedItem.value.type) {
        case 'product':
            return ProductDetails;
        // Adicione mais casos conforme necessário:
        case 'shelf':
            return ShelfDetails;
        case 'section':
            return SectionDetails;
        case 'segment':
        case 'layer':
            return SegmentDetails;
        default:
            return NoSelection;
    }
});
</script>
