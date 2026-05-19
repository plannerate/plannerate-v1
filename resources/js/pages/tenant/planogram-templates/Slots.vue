<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import GondolaGrid from '@/components/planogram-templates/GondolaGrid.vue';
import ModuleSelectorButtons from '@/components/planogram-templates/ModuleSelectorButtons.vue';
import PlanogramConfirmDialog from '@/components/planogram-templates/PlanogramConfirmDialog.vue';
import SlotEditorModal from '@/components/planogram-templates/SlotEditorModal.vue';
import type {
    PlanogramSubtemplate,
    PlanogramTemplateSlot,
    WizardStep,
} from '@/components/planogram-templates/types';
import WizardProgress from '@/components/planogram-templates/WizardProgress.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type SlotDropPosition = { module_number: number; shelf_order: number };

type PendingSlotAction =
    | { type: 'remove'; slotId: string }
    | { type: 'swap'; from: SlotDropPosition; to: SlotDropPosition };

const props = defineProps<{
    subdomain: string;
    template: TemplateBasic;
    subtemplates: PlanogramSubtemplate[];
}>();

const { t } = useT();

// ── URL helpers ────────────────────────────────────────────────────────────────
const baseUrl = computed(() =>
    PlanogramTemplateController.show
        .url({
            subdomain: props.subdomain,
            planogramTemplate: props.template.id,
        })
        .replace(/^\/\/[^/]+/, ''),
);
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
const groupingSearchUrl = computed(() =>
    ProductController.sortimentAttributes
        .url(props.subdomain)
        .replace(/^\/\/[^/]+/, ''),
);
const reviewPath = computed(() =>
    `${baseUrl.value}/review`,
);

const pendingSlotAction = ref<PendingSlotAction | null>(null);
const confirmDialogOpen = ref(false);
const confirmDialogBusy = ref(false);
const cloneInProgress = ref(false);
const highlightNewModule = ref<number | undefined>(undefined);

const confirmDialogContent = computed(() => {
    if (pendingSlotAction.value?.type === 'remove') {
        return {
            title: 'Remover slot?',
            description:
                'Este grouping será removido desta posição do template.',
            confirmLabel: 'Remover',
            kind: 'delete' as const,
        };
    }

    return {
        title: 'Trocar slots?',
        description:
            'A posição de destino já está ocupada. Os dois slots serão trocados.',
        confirmLabel: 'Trocar',
        kind: 'move' as const,
    };
});

// ── Wizard ─────────────────────────────────────────────────────────────────────
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
        label: 'Revisar slots',
        description: 'Selecione um slot e veja os produtos relacionados',
    },
];

function navigateWizard(step: 1 | 2 | 3): void {
    if (step === 1) {
        router.visit(editPath.value);

        return;
    }
    if (step === 3) {
        router.visit(reviewPath.value);
    }
}

// ── Subtemplate selector ───────────────────────────────────────────────────────
const currentModules = ref(props.subtemplates[0]?.num_modules ?? 1);

const currentSubtemplate = computed(
    () =>
        props.subtemplates.find(
            (s) => s.num_modules === currentModules.value,
        ) ?? null,
);

const subtemplateExists = (n: number): boolean =>
    props.subtemplates.some((s) => s.num_modules === n);

function largestSubtemplateBelow(targetModules: number): PlanogramSubtemplate | null {
    return (
        props.subtemplates
            .filter((s) => s.num_modules < targetModules)
            .sort((a, b) => b.num_modules - a.num_modules)[0] ?? null
    );
}

function selectModules(n: number): void {
    currentModules.value = n;
    if (subtemplateExists(n)) {
        highlightNewModule.value = undefined;
    }
}

function createEmptySubtemplate(n: number): void {
    router.post(
        `${baseUrl.value}/subtemplates`,
        { num_modules: n },
        { preserveState: true, only: ['subtemplates'] },
    );
}

function cloneSubtemplate(source: PlanogramSubtemplate, targetModules: number): void {
    cloneInProgress.value = true;
    router.post(
        `${baseUrl.value}/subtemplates/${source.id}/clone`,
        { target_modules: targetModules },
        {
            preserveState: true,
            only: ['subtemplates'],
            onSuccess: () => {
                highlightNewModule.value = source.num_modules + 1;
            },
            onFinish: () => {
                cloneInProgress.value = false;
            },
        },
    );
}

// ── Shelf count ────────────────────────────────────────────────────────────────
const numShelves = ref(4);

watch(
    currentSubtemplate,
    (sub) => {
        if (!sub) {
            numShelves.value = 4;

            return;
        }

        const maxShelf = sub.slots.reduce(
            (max, s) => Math.max(max, s.shelf_order),
            0,
        );
        numShelves.value = Math.max(maxShelf, 4);
    },
    { immediate: true },
);

// ── Slot editor ────────────────────────────────────────────────────────────────
const slotEditorOpen = ref(false);
const editingModule = ref(1);
const editingShelf = ref(1);
const editingSlot = ref<PlanogramTemplateSlot | null>(null);

function openSlotEditor(
    module: number,
    shelf: number,
    slot: PlanogramTemplateSlot | null,
): void {
    editingModule.value = module;
    editingShelf.value = shelf;
    editingSlot.value = slot;
    slotEditorOpen.value = true;
}

function saveSlot(
    draft: Omit<
        PlanogramTemplateSlot,
        'id' | 'subtemplate_id' | 'grouping_normalized' | 'ordering'
    >,
): void {
    const existingSlot = currentSubtemplate.value?.slots.find(
        (s) =>
            s.module_number === draft.module_number &&
            s.shelf_order === draft.shelf_order,
    );

    if (existingSlot?.id) {
        // Update
        router.put(`${baseUrl.value}/slots/${existingSlot.id}`, draft, {
            preserveState: true,
            only: ['subtemplates'],
        });

        return;
    }

    const subtemplateId = currentSubtemplate.value?.id;

    if (subtemplateId) {
        // Create slot in existing subtemplate
        router.post(
            `${baseUrl.value}/subtemplates/${subtemplateId}/slots`,
            draft,
            {
                preserveState: true,
                only: ['subtemplates'],
            },
        );
    } else {
        // Create subtemplate first, then slot
        router.post(
            `${baseUrl.value}/subtemplates`,
            { num_modules: currentModules.value },
            {
                preserveState: true,
                only: ['subtemplates'],
                onSuccess: () => {
                    const newSub = props.subtemplates.find(
                        (s) => s.num_modules === currentModules.value,
                    );

                    if (!newSub) {
                        return;
                    }

                    router.post(
                        `${baseUrl.value}/subtemplates/${newSub.id}/slots`,
                        draft,
                        {
                            preserveState: true,
                            only: ['subtemplates'],
                        },
                    );
                },
            },
        );
    }
}

function removeSlot(module: number, shelf: number): void {
    const slot = currentSubtemplate.value?.slots.find(
        (s) => s.module_number === module && s.shelf_order === shelf,
    );

    if (!slot?.id) {
        return;
    }

    pendingSlotAction.value = { type: 'remove', slotId: slot.id };
    confirmDialogOpen.value = true;
}

function deleteSlot(slotId: string): void {
    confirmDialogBusy.value = true;

    router.delete(`${baseUrl.value}/slots/${slotId}`, {
        preserveState: true,
        only: ['subtemplates'],
        onFinish: () => {
            confirmDialogBusy.value = false;
            confirmDialogOpen.value = false;
            pendingSlotAction.value = null;
        },
    });
}

function reorderSlots(
    subtemplateId: string,
    from: SlotDropPosition,
    to: SlotDropPosition,
): void {
    router.post(
        `${baseUrl.value}/slots/reorder`,
        { subtemplate_id: subtemplateId, from, to },
        { preserveState: true, only: ['subtemplates'] },
    );
}

function handleSlotDrop(
    from: { module_number: number; shelf_order: number },
    to: { module_number: number; shelf_order: number },
): void {
    const subtemplate = currentSubtemplate.value;

    if (!subtemplate) {
        return;
    }

    const targetOccupied = subtemplate.slots.some(
        (s) =>
            s.module_number === to.module_number &&
            s.shelf_order === to.shelf_order,
    );

    if (targetOccupied) {
        pendingSlotAction.value = { type: 'swap', from, to };
        confirmDialogOpen.value = true;

        return;
    }

    reorderSlots(subtemplate.id, from, to);
}

function confirmSlotAction(): void {
    const action = pendingSlotAction.value;
    const subtemplate = currentSubtemplate.value;

    if (!action) {
        return;
    }

    if (action.type === 'remove') {
        deleteSlot(action.slotId);

        return;
    }

    if (!subtemplate) {
        confirmDialogOpen.value = false;
        pendingSlotAction.value = null;

        return;
    }

    confirmDialogBusy.value = true;
    router.post(
        `${baseUrl.value}/slots/reorder`,
        {
            subtemplate_id: subtemplate.id,
            from: action.from,
            to: action.to,
        },
        {
            preserveState: true,
            only: ['subtemplates'],
            onFinish: () => {
                confirmDialogBusy.value = false;
                confirmDialogOpen.value = false;
                pendingSlotAction.value = null;
            },
        },
    );
}

// ── Export / Import ────────────────────────────────────────────────────────────
function exportTemplate(): void {
    window.location.href = `${baseUrl.value}/export`;
}

function handleImport(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    router.post(`${baseUrl.value}/import`, formData, {
        only: ['subtemplates'],
    });
    (event.target as HTMLInputElement).value = '';
}

// ── Breadcrumbs ────────────────────────────────────────────────────────────────
const breadcrumbs = [
    {
        title: t('app.navigation.dashboard'),
        href: dashboard.url().replace(/^\/\/[^/]+/, ''),
    },
    { title: t('app.tenant.planogram_templates.navigation'), href: indexPath },
    { title: props.template.code, href: editPath.value },
    { title: 'Slots', href: '#' },
];
</script>

<template>
    <Head :title="`Slots — ${template.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <!-- Wizard progress -->
            <div class="mx-auto max-w-3xl">
                <WizardProgress
                    :current-step="2"
                    :steps="wizardSteps"
                    @navigate="navigateWizard"
                />
            </div>

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ template.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Etapa 2 — configure os groupings por módulo e prateleira
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="exportTemplate">
                        <Download class="size-3.5" />
                        Exportar planilha
                    </Button>
                    <label>
                        <Button variant="outline" size="sm" as="span">
                            <Upload class="size-3.5" />
                            Importar planilha
                        </Button>
                        <input
                            type="file"
                            accept=".xlsx,.xls"
                            class="sr-only"
                            @change="handleImport"
                        />
                    </label>
                </div>
            </div>

            <!-- Subtemplate selector -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-muted-foreground"
                    >Módulos:</span
                >
                <ModuleSelectorButtons
                    :current-module="currentModules"
                    @select="selectModules"
                />
                <Badge
                    :variant="
                        subtemplateExists(currentModules)
                            ? 'default'
                            : 'secondary'
                    "
                >
                    {{
                        subtemplateExists(currentModules)
                            ? 'Configurado'
                            : 'Novo'
                    }}
                </Badge>
            </div>

            <!-- Shelf count control -->
            <div class="flex items-center gap-3">
                <Label for="num-shelves" class="text-sm">Prateleiras:</Label>
                <Input
                    id="num-shelves"
                    v-model.number="numShelves"
                    type="number"
                    :min="1"
                    :max="10"
                    class="w-20"
                />
            </div>

            <!-- New subtemplate options when no subtemplate exists for selected module count -->
            <div
                v-if="!subtemplateExists(currentModules)"
                class="rounded-lg border border-dashed border-border bg-muted/30 p-6"
            >
                <p class="mb-4 text-sm text-muted-foreground">
                    Nenhum subtemplate configurado para
                    <strong>{{ currentModules }} módulo{{ currentModules > 1 ? 's' : '' }}</strong>.
                    Como deseja criar?
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="createEmptySubtemplate(currentModules)"
                    >
                        Criar do zero
                    </Button>
                    <Button
                        v-if="largestSubtemplateBelow(currentModules)"
                        size="sm"
                        :disabled="cloneInProgress"
                        @click="cloneSubtemplate(largestSubtemplateBelow(currentModules)!, currentModules)"
                    >
                        Copiar de {{ largestSubtemplateBelow(currentModules)!.num_modules }} módulos
                        <span class="ml-1 opacity-70 text-xs">
                            ({{ largestSubtemplateBelow(currentModules)!.slots.length }} slots)
                        </span>
                    </Button>
                </div>
            </div>

            <GondolaGrid
                v-else
                :slots="currentSubtemplate?.slots ?? []"
                :num-modules="currentModules"
                :num-shelves="numShelves"
                :highlight-new-module="highlightNewModule"
                @cell-click="openSlotEditor"
                @slot-remove="removeSlot"
                @slot-drop="handleSlotDrop"
            />

            <!-- Navigation buttons -->
            <div class="flex justify-between pt-2">
                <Button variant="outline" :as="'a'" :href="editPath">
                    <ChevronLeft class="size-4" />
                    {{ t('planogram-templates.wizard.back_to_basics_button') }}
                </Button>
                <Button :as="'a'" :href="reviewPath">
                    Próximo — Revisão de slots
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </AppLayout>

    <!-- Slot editor modal -->
    <SlotEditorModal
        v-model:open="slotEditorOpen"
        :module-number="editingModule"
        :shelf-order="editingShelf"
        :template-slot="editingSlot"
        :grouping-search-url="groupingSearchUrl"
        @save="saveSlot"
    />

    <PlanogramConfirmDialog
        v-model:open="confirmDialogOpen"
        :title="confirmDialogContent.title"
        :description="confirmDialogContent.description"
        :confirm-label="confirmDialogContent.confirmLabel"
        :kind="confirmDialogContent.kind"
        :busy="confirmDialogBusy"
        @confirm="confirmSlotAction"
    />
</template>
