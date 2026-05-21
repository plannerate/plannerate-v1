<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import TemplateSlotController from '@/actions/App/Http/Controllers/Tenant/TemplateSlotController';
import ModuleSelectorButtons from '@/components/planogram-templates/ModuleSelectorButtons.vue';
import ReviewSlotProductsPanel from '@/components/planogram-templates/ReviewSlotProductsPanel.vue';
import ReviewSlotsList from '@/components/planogram-templates/ReviewSlotsList.vue';
import type {
    SlotAnalysisData,
    PlanogramSubtemplate,
    WizardStep,
} from '@/components/planogram-templates/types';
import WizardProgress from '@/components/planogram-templates/WizardProgress.vue';
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
    current_module: number;
    selected_slot_id: string | null;
    slot_analysis: SlotAnalysisData | null;
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

const allSlotsFromTemplate = computed(() =>
    props.subtemplates
        .flatMap((subtemplate) => subtemplate.slots)
        .slice()
        .sort((a, b) => {
            if (a.module_number === b.module_number) {
                return a.shelf_order - b.shelf_order;
            }

            return a.module_number - b.module_number;
        }),
);

const selectedSlotFromTemplate = computed(
    () =>
        allSlotsFromTemplate.value.find(
            (slot) => slot.id === props.selected_slot_id,
        ) ?? null,
);

const currentModules = ref(
    props.current_module ||
        selectedSlotFromTemplate.value?.module_number ||
        props.subtemplates[0]?.num_modules ||
        1,
);

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

const selectedSlotId = ref<string | null>(props.selected_slot_id);
const slotAnalysis = ref<SlotAnalysisData | null>(props.slot_analysis);
const productsLoading = ref(false);

const selectedSlot = computed(
    () => allSlots.value.find((slot) => slot.id === selectedSlotId.value) ?? null,
);

function selectSlotForProducts(slotId: string): void {
    selectedSlotId.value = slotId;
    productsLoading.value = true;
    router.get(
        TemplateSlotController.review.url(
            {
                subdomain: props.subdomain,
                planogramTemplate: props.template.id,
            },
            {
                query: {
                    slot_id: slotId,
                    module: currentModules.value,
                },
            },
        ),
        {},
        {
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                productsLoading.value = false;
            },
        },
    );
}

function syncCurrentAnalysisImages(): void {
    if (!slotAnalysis.value) {
        return;
    }

    const eans = Array.from(
        new Set(
            slotAnalysis.value.rows
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
            planogramTemplate: props.template.id,
        }),
        { eans },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function changeCurrentModule(moduleNumber: number): void {
    currentModules.value = moduleNumber;
    selectedSlotId.value = null;
    slotAnalysis.value = null;
    productsLoading.value = true;

    router.get(
        TemplateSlotController.review.url(
            {
                subdomain: props.subdomain,
                planogramTemplate: props.template.id,
            },
            {
                query: {
                    module: moduleNumber,
                },
            },
        ),
        {},
        {
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                productsLoading.value = false;
            },
        },
    );
}

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
                <WizardProgress :current-step="3" :steps="wizardSteps" @navigate="navigateWizard" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ template.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Etapa 3 — revise os slots e os produtos relacionados
                    </p>
                </div>
            </div>



            <div class="grid gap-4  grid-cols-12">
                <ReviewSlotsList :slots="allSlots" :selected-slot-id="selectedSlotId" @select="selectSlotForProducts">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-muted-foreground">Módulos:</span>
                        <ModuleSelectorButtons
                            :current-module="currentModules"
                            :subtemplates="props.subtemplates"
                            :readonly="true"
                            @select="changeCurrentModule"
                        />
                    </div>
                </ReviewSlotsList>
                <ReviewSlotProductsPanel :selected-slot="selectedSlot" :analysis="slotAnalysis"
                    :loading="productsLoading" @sync-images="syncCurrentAnalysisImages" />
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
