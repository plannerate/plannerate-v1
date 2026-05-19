<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import TemplateSlotController from '@/actions/App/Http/Controllers/Tenant/TemplateSlotController';
import ModuleSelectorButtons from '@/components/planogram-templates/ModuleSelectorButtons.vue';
import ReviewSlotProductsPanel from '@/components/planogram-templates/ReviewSlotProductsPanel.vue';
import ReviewSlotsList from '@/components/planogram-templates/ReviewSlotsList.vue';
import type {
    SlotAnalysisData,
    PlanogramSubtemplate,
    PlanogramTemplateSlot,
    WizardStep,
} from '@/components/planogram-templates/types';
import WizardProgress from '@/components/planogram-templates/WizardProgress.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type TemplateBasic = {
    id: string;
    code: string;
    name: string;
    department: string;
    is_active: boolean;
};

const props = defineProps<{
    subdomain: string;
    template: TemplateBasic;
    subtemplates: PlanogramSubtemplate[];
}>();

const { t } = useT();

const indexPath = PlanogramTemplateController.index
    .url(props.subdomain)
    .replace(/^\/\/[^/]+/, '');
const editPath = computed(() =>
    PlanogramTemplateController.edit
        .url({
            subdomain: props.subdomain,
            planogramTemplate: props.template.id,
        })
        .replace(/^\/\/[^/]+/, ''),
);
const slotsPath = computed(() =>
    TemplateSlotController.index
        .url({
            subdomain: props.subdomain,
            planogramTemplate: props.template.id,
        })
        .replace(/^\/\/[^/]+/, ''),
);
const productsPath = computed(() =>
    PlanogramTemplateController.show
        .url({
            subdomain: props.subdomain,
            planogramTemplate: props.template.id,
        })
        .replace(/^\/\/[^/]+/, '') + '#products',
);

const wizardSteps: WizardStep[] = [
    {
        step: 1,
        label: t('planogram-templates.wizard.step1_label'),
        description: t('planogram-templates.wizard.step1_description'),
    },
    {
        step: 2,
        label: t('planogram-templates.wizard.step2_label'),
        description: t('planogram-templates.wizard.step2_description'),
    },
    {
        step: 3,
        label: 'Revisão',
        description: 'Visualize slots e produtos relacionados',
    },
];

function navigateWizard(step: 1 | 2 | 3): void {
    if (step === 1) {
        router.visit(editPath.value);

        return;
    }

    if (step === 2) {
        router.visit(slotsPath.value);
    }
}

const currentModules = ref(props.subtemplates[0]?.num_modules ?? 1);

const currentSubtemplate = computed(
    () =>
        props.subtemplates.find(
            (subtemplate) => subtemplate.num_modules === currentModules.value,
        ) ?? null,
);

const allSlots = computed(() =>
    (currentSubtemplate.value?.slots ?? []).slice().sort((a, b) => {
        if (a.module_number === b.module_number) {
            return a.shelf_order - b.shelf_order;
        }

        return a.module_number - b.module_number;
    }),
);

const selectedSlotId = ref<string | null>(null);
const slotAnalysis = ref<SlotAnalysisData | null>(null);
const productsLoading = ref(false);

const selectedSlot = computed(
    () => allSlots.value.find((slot) => slot.id === selectedSlotId.value) ?? null,
);

function normalizeGrouping(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '-');
}

async function loadSlotProducts(slot: PlanogramTemplateSlot): Promise<void> {
    const groupingNormalized =
        slot.grouping_normalized || normalizeGrouping(slot.grouping ?? '');

    if (!groupingNormalized) {
        slotAnalysis.value = null;

        return;
    }

    productsLoading.value = true;

    try {
        const response = await fetch(
            TemplateSlotController.slotAnalysis
                .url(
                    {
                        subdomain: props.subdomain,
                        planogramTemplate: props.template.id,
                    },
                    {
                        query: {
                            slot_id: slot.id,
                        },
                    },
                )
                .replace(/^\/\/[^/]+/, ''),
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!response.ok) {
            slotAnalysis.value = null;

            return;
        }

        const payload = (await response.json()) as { data?: SlotAnalysisData };
        slotAnalysis.value = payload.data ?? null;
    } catch {
        slotAnalysis.value = null;
    } finally {
        productsLoading.value = false;
    }
}

function selectSlotForProducts(slotId: string): void {
    selectedSlotId.value = slotId;
    const slot = allSlots.value.find((item) => item.id === slotId);

    if (slot) {
        void loadSlotProducts(slot);
    }
}

watch(currentSubtemplate, () => {
    selectedSlotId.value = null;
    slotAnalysis.value = null;
});

const breadcrumbs = [
    {
        title: t('app.navigation.dashboard'),
        href: dashboard.url().replace(/^\/\/[^/]+/, ''),
    },
    { title: t('app.tenant.planogram_templates.navigation'), href: indexPath },
    { title: props.template.code, href: editPath.value },
    { title: 'Revisão', href: '#' },
];
</script>

<template>
    <Head :title="`Revisão — ${template.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="mx-auto max-w-3xl">
                <WizardProgress
                    :current-step="3"
                    :steps="wizardSteps"
                    @navigate="navigateWizard"
                />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ template.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Etapa 3 — revise os slots e os produtos relacionados
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-muted-foreground">Módulos:</span>
                <ModuleSelectorButtons
                    :current-module="currentModules"
                    @select="currentModules = $event"
                />
                <Badge
                    :variant="
                        props.subtemplates.some((item) => item.num_modules === currentModules)
                            ? 'default'
                            : 'secondary'
                    "
                >
                    {{
                        props.subtemplates.some((item) => item.num_modules === currentModules)
                            ? 'Configurado'
                            : 'Novo'
                    }}
                </Badge>
            </div>

            <div class="grid gap-4  grid-cols-12">
                <ReviewSlotsList
                    :slots="allSlots"
                    :selected-slot-id="selectedSlotId"
                    @select="selectSlotForProducts"
                />
                <ReviewSlotProductsPanel
                    :selected-slot="selectedSlot"
                    :analysis="slotAnalysis"
                    :loading="productsLoading"
                />
            </div>

            <div class="flex justify-between pt-2">
                <Button variant="outline" :as="'a'" :href="slotsPath">
                    <ChevronLeft class="size-4" />
                    Voltar — Slots
                </Button>
                <Button :as="'a'" :href="productsPath">
                    Próximo — Produtos
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
