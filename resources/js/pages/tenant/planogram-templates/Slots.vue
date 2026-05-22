<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, Settings2, Trash2, Upload } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { computed, ref, watch } from 'vue';
import {
    ALTERATION_LEVEL_LABELS,
    classifyAlteration,
    diffSlotFields,
} from '@/components/planogram-templates/alteration-classifier';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import GondolaGrid from '@/components/planogram-templates/GondolaGrid.vue';
import ModuleDefaultsModal from '@/components/planogram-templates/ModuleDefaultsModal.vue';
import ModuleSelectorButtons from '@/components/planogram-templates/ModuleSelectorButtons.vue';
import PlanogramConfirmDialog from '@/components/planogram-templates/PlanogramConfirmDialog.vue';
import SlotCopyPromptDialog from '@/components/planogram-templates/SlotCopyPromptDialog.vue';
import type { CopyPromptAction } from '@/components/planogram-templates/SlotCopyPromptDialog.vue';
import SlotEditorModal from '@/components/planogram-templates/SlotEditorModal.vue';
import type { SlotDraft } from '@/components/planogram-templates/slot-editor';
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
    category_id: string | null;
    category_name: string | null;
    is_active: boolean;
};

type SlotDropPosition = { module_number: number; shelf_order: number };

type PendingSlotAction =
    | { type: 'remove'; slotId: string }
    | { type: 'swap'; from: SlotDropPosition; to: SlotDropPosition }
    | { type: 'remove-subtemplate'; subtemplateId: string; numModules: number };

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
            description: 'Este slot será removido desta posição do template.',
            confirmLabel: 'Remover',
            kind: 'delete' as const,
        };
    }

    if (pendingSlotAction.value?.type === 'remove-subtemplate') {
        const n = pendingSlotAction.value.numModules;
        return {
            title: `Remover ${n} módulo${n > 1 ? 's' : ''}?`,
            description: `Todos os ${pendingSlotAction.value.numModules === 1 ? 'slot deste módulo será removido' : `slots dos ${n} módulos serão removidos`} permanentemente. Esta ação não pode ser desfeita.`,
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

const currentSlotDefaults = computed(
    () => currentSubtemplate.value?.slot_defaults ?? null,
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
const moduleDefaultsOpen = ref(false);
const editingModule = ref(1);
const editingShelf = ref(1);
const editingSlot = ref<PlanogramTemplateSlot | null>(null);

// ── Cópia em cascata (prateleiras / módulos) ─────────────────────────────────────
const copyPromptOpen = ref(false);
const copyPromptKind = ref<'shelves' | 'module' | null>(null);
const copyPromptModule = ref<number | null>(null);
const copySourceDraft = ref<SlotDraft | null>(null);
const cascadeActive = ref(false);
const cascadeModule = ref<number | null>(null);
const copyBusy = ref(false);
/** distingue fechamento do editor por salvar (mantém cascata) de cancelar (encerra) */
const slotSaveInFlight = ref(false);

/** Conta quantos slots o módulo possui no subtemplate atual. */
function moduleSlotCount(module: number): number {
    return (
        currentSubtemplate.value?.slots.filter(
            (s) => s.module_number === module,
        ).length ?? 0
    );
}

/** Prateleiras (1..numShelves) ainda vazias no módulo. */
function emptyShelvesInModule(module: number): number[] {
    const occupied = new Set(
        (currentSubtemplate.value?.slots ?? [])
            .filter((s) => s.module_number === module)
            .map((s) => s.shelf_order),
    );

    return Array.from({ length: numShelves.value }, (_, i) => i + 1).filter(
        (shelf) => !occupied.has(shelf),
    );
}

function nextEmptyShelf(module: number): number | null {
    return emptyShelvesInModule(module)[0] ?? null;
}

/** Módulos (1..currentModules) totalmente vazios. */
function emptyModules(): number[] {
    return Array.from(
        { length: currentModules.value },
        (_, i) => i + 1,
    ).filter((m) => moduleSlotCount(m) === 0);
}

function isModuleComplete(module: number): boolean {
    return moduleSlotCount(module) >= numShelves.value && numShelves.value > 0;
}

/** Slots de configuração de um módulo, prontos para replicar em outro módulo. */
function moduleSlotsConfig(module: number): PlanogramTemplateSlot[] {
    return (currentSubtemplate.value?.slots ?? []).filter(
        (s) => s.module_number === module,
    );
}

const copyPromptContent = computed<{
    title: string;
    description: string;
    actions: CopyPromptAction[];
}>(() => {
    const module = copyPromptModule.value;

    if (copyPromptKind.value === 'shelves' && module !== null) {
        const actions: CopyPromptAction[] = [
            { key: 'shelves-all', label: 'Copiar e fechar' },
        ];

        if (nextEmptyShelf(module) !== null) {
            actions.push({
                key: 'shelves-next',
                label: 'Copiar e editar a próxima',
                variant: 'outline',
            });
        }

        actions.push({ key: 'dismiss', label: 'Agora não', variant: 'ghost' });

        return {
            title: 'Copiar configuração para as outras prateleiras?',
            description:
                'A mesma configuração (incluindo a categoria) será aplicada às prateleiras vazias deste módulo. Você pode ajustar cada uma depois.',
            actions,
        };
    }

    return {
        title: 'Copiar configuração deste módulo para os outros?',
        description:
            'Os slots deste módulo serão replicados para os módulos ainda vazios.',
        actions: [
            { key: 'module-next', label: 'Próximo módulo' },
            {
                key: 'module-all',
                label: 'Todos os módulos vazios',
                variant: 'outline',
            },
            { key: 'dismiss', label: 'Agora não', variant: 'ghost' },
        ],
    };
});

function endCascade(): void {
    cascadeActive.value = false;
    cascadeModule.value = null;
}

/** Após salvar um slot novo, decide qual aviso de cópia mostrar. */
function maybePromptAfterSave(module: number, wasFirstSlot: boolean): void {
    const inCascade = cascadeActive.value && cascadeModule.value === module;

    if (
        (wasFirstSlot || inCascade) &&
        emptyShelvesInModule(module).length > 0
    ) {
        copyPromptKind.value = 'shelves';
        copyPromptModule.value = module;
        copyPromptOpen.value = true;

        return;
    }

    endCascade();
    maybePromptModule(module);
}

function maybePromptModule(module: number): void {
    if (
        currentModules.value > 1 &&
        isModuleComplete(module) &&
        emptyModules().length > 0
    ) {
        copyPromptKind.value = 'module';
        copyPromptModule.value = module;
        copyPromptOpen.value = true;
    }
}

function bulkCopy(slots: SlotDraft[], onDone?: () => void): void {
    const subtemplate = currentSubtemplate.value;

    if (!subtemplate || slots.length === 0) {
        return;
    }

    copyBusy.value = true;
    router.post(
        `${baseUrl.value}/subtemplates/${subtemplate.id}/slots/bulk`,
        { slots },
        {
            preserveState: true,
            only: ['subtemplates'],
            onError: (errs) => {
                const first = Object.values(errs)[0];
                if (first) toast.error(first);
            },
            onSuccess: () => {
                onDone?.();
            },
            onFinish: () => {
                copyBusy.value = false;
            },
        },
    );
}

function onCopyPromptAction(key: string): void {
    const module = copyPromptModule.value;
    const source = copySourceDraft.value;

    if (key === 'dismiss' || module === null) {
        copyPromptOpen.value = false;
        endCascade();

        return;
    }

    if (key === 'shelves-all' && source) {
        const slots = emptyShelvesInModule(module).map((shelf) => ({
            ...source,
            module_number: module,
            shelf_order: shelf,
        }));
        copyPromptOpen.value = false;
        endCascade();
        bulkCopy(slots, () => maybePromptModule(module));

        return;
    }

    if (key === 'shelves-next' && source) {
        const next = nextEmptyShelf(module);
        if (next === null) {
            copyPromptOpen.value = false;
            endCascade();

            return;
        }

        cascadeActive.value = true;
        cascadeModule.value = module;
        copyPromptOpen.value = false;
        editingModule.value = module;
        editingShelf.value = next;
        editingSlot.value = {
            ...source,
            module_number: module,
            shelf_order: next,
        };
        slotEditorOpen.value = true;

        return;
    }

    if (key === 'module-next' || key === 'module-all') {
        const targets =
            key === 'module-next' ? emptyModules().slice(0, 1) : emptyModules();
        const sourceSlots = moduleSlotsConfig(module);
        const slots = targets.flatMap((target) =>
            sourceSlots.map((slot) => ({
                ...(slot as unknown as SlotDraft),
                module_number: target,
            })),
        );
        copyPromptOpen.value = false;
        bulkCopy(slots);
    }
}

// Encerra a cascata se o editor for fechado sem salvar (cancelar).
watch(slotEditorOpen, (open) => {
    if (open) {
        return;
    }

    if (slotSaveInFlight.value) {
        slotSaveInFlight.value = false;

        return;
    }

    endCascade();
});

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
    draft: Omit<PlanogramTemplateSlot, 'id' | 'subtemplate_id' | 'ordering'>,
): void {
    slotSaveInFlight.value = true;

    const module = draft.module_number;
    const wasFirstSlot = moduleSlotCount(module) === 0;

    const existingSlot = currentSubtemplate.value?.slots.find(
        (s) =>
            s.module_number === draft.module_number &&
            s.shelf_order === draft.shelf_order,
    );

    if (existingSlot?.id) {
        // Detecta o nível de alteração para avisar o usuário sobre o planograma gerado
        const changedFields = diffSlotFields(
            existingSlot as unknown as Record<string, unknown>,
            draft as unknown as Record<string, unknown>,
        );
        const level = classifyAlteration(changedFields);

        router.put(`${baseUrl.value}/slots/${existingSlot.id}`, draft, {
            preserveState: true,
            only: ['subtemplates'],
            onSuccess: () => {
                if (level) {
                    toast.info(
                        `Slot atualizado — planogramas gerados precisam de: ${ALTERATION_LEVEL_LABELS[level]}`,
                    );
                }
            },
            onError: (errs) => {
                const first = Object.values(errs)[0];
                if (first) toast.error(first);
            },
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
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    if (first) toast.error(first);
                },
                onSuccess: () => {
                    copySourceDraft.value = { ...draft } as SlotDraft;
                    maybePromptAfterSave(module, wasFirstSlot);
                },
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
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    if (first) toast.error(first);
                },
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
                            onError: (errs) => {
                                const first = Object.values(errs)[0];
                                if (first) toast.error(first);
                            },
                            onSuccess: () => {
                                copySourceDraft.value = { ...draft } as SlotDraft;
                                maybePromptAfterSave(module, wasFirstSlot);
                            },
                        },
                    );
                },
            },
        );
    }
}

function saveCurrentModuleDefaults(
    defaults: Pick<
        PlanogramTemplateSlot,
        | 'category_id'
        | 'min_facings'
        | 'priority'
        | 'price_order'
        | 'size_order'
        | 'brand_exposure'
        | 'flavor_exposure'
        | 'space_fallback'
        | 'use_target_stock'
    > & { hot_zone_priority?: string | null; cold_zone_priority?: string | null; flow_direction?: string | null },
): void {
    const subtemplate = currentSubtemplate.value;

    if (!subtemplate) {
        return;
    }

    router.put(
        `${baseUrl.value}/subtemplates/${subtemplate.id}/slot-defaults`,
        defaults,
        {
            preserveState: true,
            only: ['subtemplates'],
        },
    );
}

function openModuleDefaultsModal(): void {
    if (!currentSubtemplate.value) {
        return;
    }

    moduleDefaultsOpen.value = true;
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

    if (!action) {
        return;
    }

    if (action.type === 'remove') {
        deleteSlot(action.slotId);
        return;
    }

    if (action.type === 'remove-subtemplate') {
        confirmDialogBusy.value = true;
        router.delete(`${baseUrl.value}/subtemplates/${action.subtemplateId}`, {
            onSuccess: () => {
                currentModules.value = props.subtemplates[0]?.num_modules ?? 1;
            },
            onFinish: () => {
                confirmDialogBusy.value = false;
                confirmDialogOpen.value = false;
                pendingSlotAction.value = null;
            },
        });
        return;
    }

    const subtemplate = currentSubtemplate.value;

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

function requestRemoveSubtemplate(): void {
    const subtemplate = currentSubtemplate.value;
    if (!subtemplate) return;
    pendingSlotAction.value = {
        type: 'remove-subtemplate',
        subtemplateId: subtemplate.id,
        numModules: subtemplate.num_modules,
    };
    confirmDialogOpen.value = true;
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
                        Etapa 2 — configure as categorias por módulo e prateleira
                    </p>
                    <p v-if="template.category_name" class="mt-1 text-sm font-medium text-primary">
                        Categoria: {{ template.category_name }}
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
                    :subtemplates="props.subtemplates"
                    @select="selectModules"
                    @add="selectModules"
                />
                <Button
                    v-if="subtemplateExists(currentModules)"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    title="Remover este subtemplate"
                    @click="requestRemoveSubtemplate"
                >
                    <Trash2 class="size-3.5" />
                </Button>
                <Button
                    v-if="subtemplateExists(currentModules)"
                    variant="outline"
                    size="sm"
                    @click="openModuleDefaultsModal"
                >
                    <Settings2 class="size-3.5" />
                    Configuração padrão do módulo
                </Button>
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
        :slot-defaults="currentSlotDefaults"
        @save="saveSlot"
    />
    <ModuleDefaultsModal
        v-model:open="moduleDefaultsOpen"
        :module-number="currentModules"
        :slot-defaults="currentSlotDefaults"
        :hot-zone-priority="currentSubtemplate?.hot_zone_priority ?? null"
        :cold-zone-priority="currentSubtemplate?.cold_zone_priority ?? null"
        :flow-direction="currentSubtemplate?.flow_direction ?? null"
        @save="saveCurrentModuleDefaults"
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

    <SlotCopyPromptDialog
        v-model:open="copyPromptOpen"
        :title="copyPromptContent.title"
        :description="copyPromptContent.description"
        :actions="copyPromptContent.actions"
        :busy="copyBusy"
        @action="onCopyPromptAction"
    />
</template>
