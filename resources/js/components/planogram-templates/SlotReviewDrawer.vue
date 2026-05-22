<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { BarChart2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import TemplateSlotController from '@/actions/App/Http/Controllers/Tenant/TemplateSlotController';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import ReviewSlotProductsPanel from './ReviewSlotProductsPanel.vue';
import type { PlanogramTemplateSlot, SlotAnalysisData } from './types';

const props = defineProps<{
    open: boolean;
    slot: PlanogramTemplateSlot | null;
    subdomain: string;
    templateId: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const analysisHttp = useHttp<Record<string, string>, { data: SlotAnalysisData }>();
const analysis = ref<SlotAnalysisData | null>(null);

async function loadAnalysis(slot: PlanogramTemplateSlot): Promise<void> {
    analysis.value = null;
    const url = TemplateSlotController.slotAnalysis.url(
        { subdomain: props.subdomain, planogramTemplate: props.templateId },
        { query: { slot_id: slot.id } },
    );
    await analysisHttp.get(url);
    analysis.value = analysisHttp.response?.data ?? null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.slot) {
            loadAnalysis(props.slot);
        } else if (!isOpen) {
            analysis.value = null;
        }
    },
);

watch(
    () => props.slot,
    (slot) => {
        if (props.open && slot) {
            loadAnalysis(slot);
        }
    },
);

function syncImages(): void {
    if (!analysis.value) {
        return;
    }

    const eans = Array.from(
        new Set(
            analysis.value.rows
                .map((row) => row.ean?.trim() ?? '')
                .filter((ean) => ean !== ''),
        ),
    );

    if (eans.length === 0) {
        return;
    }

    router.post(
        TemplateSlotController.syncImages.url({
            subdomain: props.subdomain,
            planogramTemplate: props.templateId,
        }),
        { eans },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (props.slot) {
                    loadAnalysis(props.slot);
                }
            },
        },
    );
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex w-full flex-col overflow-hidden sm:max-w-3xl">
            <SheetHeader class="shrink-0">
                <SheetTitle class="flex items-center gap-2">
                    <BarChart2 class="size-4" />
                    Análise de slot
                </SheetTitle>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto">
                <ReviewSlotProductsPanel
                    class="col-span-full border-0 shadow-none"
                    :selected-slot="slot"
                    :analysis="analysis"
                    :loading="analysisHttp.processing"
                    @sync-images="syncImages"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
