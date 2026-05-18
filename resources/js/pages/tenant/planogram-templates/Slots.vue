<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import GondolaGrid from '@/components/planogram-templates/GondolaGrid.vue';
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
const productsPath = computed(() => `${baseUrl.value}/products`);
const groupingSearchUrl = computed(() =>
    ProductController.sortimentAttributes
        .url(props.subdomain)
        .replace(/^\/\/[^/]+/, ''),
);

// ── Wizard ─────────────────────────────────────────────────────────────────────
const wizardSteps: WizardStep[] = [
    {
        step: 1,
        label: 'Dados básicos',
        description: 'Código, nome e departamento',
    },
    { step: 2, label: 'Slots', description: 'Grade de gôndola' },
    { step: 3, label: 'Produtos', description: 'Mix do template' },
];

function navigateWizard(step: 1 | 2 | 3): void {
    if (step === 1) {
        router.visit(editPath.value);
    }

    if (step === 3) {
        router.visit(productsPath.value);
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

function selectModules(n: number): void {
    currentModules.value = n;
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

    if (!confirm('Remover este slot?')) {
        return;
    }

    router.delete(`${baseUrl.value}/slots/${slot.id}`, {
        preserveState: true,
        only: ['subtemplates'],
    });
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

    if (targetOccupied && !confirm('Trocar os dois slots?')) {
        return;
    }

    router.post(
        `${baseUrl.value}/slots/reorder`,
        { subtemplate_id: subtemplate.id, from, to },
        { preserveState: true, only: ['subtemplates'] },
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
                <button
                    v-for="n in [1, 2, 3, 4, 5, 6]"
                    :key="n"
                    type="button"
                    class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        currentModules === n
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground hover:border-primary/60 hover:bg-muted/30'
                    "
                    @click="selectModules(n)"
                >
                    {{ n }} módulo{{ n > 1 ? 's' : '' }}
                </button>
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

            <!-- Gondola grid -->
            <GondolaGrid
                :slots="currentSubtemplate?.slots ?? []"
                :num-modules="currentModules"
                :num-shelves="numShelves"
                @cell-click="openSlotEditor"
                @slot-remove="removeSlot"
                @slot-drop="handleSlotDrop"
            />

            <!-- Navigation buttons -->
            <div class="flex justify-between pt-2">
                <Button variant="outline" :as="'a'" :href="editPath">
                    <ChevronLeft class="size-4" />
                    Voltar — Dados básicos
                </Button>
                <Button :as="'a'" :href="productsPath">
                    Próximo — Produtos
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
</template>
