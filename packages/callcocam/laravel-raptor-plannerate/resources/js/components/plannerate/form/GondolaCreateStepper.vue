<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Circle, Dot } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { store as storeGondolaRoute } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/GondolaController';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Stepper,
    StepperDescription,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import {
    DEFAULT_GONDOLA_FIELDS,
    generateGondolaCode,
    getInitialGondolaFields,
} from '@/composables/plannerate/fields/useGondolaFields';
import { DEFAULT_SECTION_FIELDS } from '@/composables/plannerate/fields/useSectionFields';
import { DEFAULT_SHELF_FIELDS } from '@/composables/plannerate/fields/useShelfFields';
import { useT } from '@/composables/useT';
import Step1BasicInfo, {
    validate as validateStep1,
} from './steps/Step1BasicInfo.vue';
import Step2Modules, {
    validate as validateStep2,
} from './steps/Step2Modules.vue';
import Step3Base, { validate as validateStep3 } from './steps/Step3Base.vue';
import Step4Rack, { validate as validateStep4 } from './steps/Step4Rack.vue';
import Step5Shelves, {
    validate as validateStep5,
} from './steps/Step5Shelves.vue';
import Step6Workflow, {
    validate as validateStep6,
} from './steps/Step6Workflow.vue';
import StepGeneration, {
    validate as validateGeneration,
} from './steps/StepGeneration.vue';

interface Props {
    open?: boolean;
    planogramId?: string;
    availableUsers?: Array<{ id: string; name: string }>;
    gondolaSettings?: any;
    /** Data de início do planograma — pré-preenche o campo start_date na etapa de geração */
    planogramStartDate?: string | null;
    /** Data de fim do planograma — pré-preenche o campo end_date na etapa de geração */
    planogramEndDate?: string | null;
    /** Categoria-base do planograma — pode ser fornecida pelo componente pai (Index, Kanban) */
    planogramCategoryId?: string | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    planogramId: '',
    availableUsers: () => [],
});

const emit = defineEmits<Emits>();
const { t } = useT();
const isBrowser = typeof window !== 'undefined';
const page = usePage<{
    subdomain?: string;
    record?: {
        planogram_id?: string;
        category_id?: string;
        planogram?: {
            id?: string;
            category_id?: string;
            start_date?: string | null;
            end_date?: string | null;
        };
    };
}>();

const resolvedPlanogramId = computed(() => {
    const planogramIdFromProps = props.planogramId?.toString().trim();

    if (planogramIdFromProps) {
        return planogramIdFromProps;
    }

    const planogramIdFromRecord = page.props.record?.planogram_id?.toString().trim();

    if (planogramIdFromRecord) {
        return planogramIdFromRecord;
    }

    return page.props.record?.planogram?.id?.toString().trim() || '';
});

/**
 * Categoria-base do planograma — trava a cascata de seleção no passo de geração.
 * Prioridade: prop explícita (Index/Kanban) > record do editor > null.
 */
const resolvedPlanogramCategoryId = computed<string | null>(
    () =>
        props.planogramCategoryId ??
        page.props.record?.category_id ??
        page.props.record?.planogram?.category_id ??
        null,
);

/**
 * Data de início do planograma para pré-preenchimento do passo de geração.
 * Prioridade: prop explícita (Index/Kanban) > record do editor > null.
 */
const resolvedPlanogramStartDate = computed<string | null>(
    () =>
        props.planogramStartDate ??
        page.props.record?.planogram?.start_date ??
        null,
);

/**
 * Data de fim do planograma para pré-preenchimento do passo de geração.
 * Prioridade: prop explícita (Index/Kanban) > record do editor > null.
 */
const resolvedPlanogramEndDate = computed<string | null>(
    () =>
        props.planogramEndDate ??
        page.props.record?.planogram?.end_date ??
        null,
);

const resolvedSubdomain = computed(() => {
    const subdomainFromPage = page.props.subdomain?.toString().trim();

    if (subdomainFromPage) {
        return subdomainFromPage;
    }

    if (!isBrowser) {
        return '';
    }

    return window.location.hostname.split('.')[0] || '';
});

// Current step (1-based index dentro dos passos ativos do modo)
const currentStep = ref(1);

// Helper functions for localStorage
const loadScaleFromLocalStorage = (): number | null => {
    if (!isBrowser) {
        return null;
    }

    try {
        const savedScale = window.localStorage.getItem('plannerate-scale-factor');

        if (savedScale) {
            const scale = parseFloat(savedScale);

            if (!isNaN(scale) && scale >= 1) {
                return scale;
            }
        }
    } catch (error) {
        console.warn('Erro ao carregar escala do localStorage:', error);
    }

    return null;
};

const saveScaleToLocalStorage = (scale: number) => {
    if (!isBrowser) {
        return;
    }

    try {
        window.localStorage.setItem('plannerate-scale-factor', scale.toString());
    } catch (error) {
        console.warn('Erro ao salvar escala no localStorage:', error);
    }
};

// Form initialization usando composables
const getInitialFormData = () => {
    const savedScale = loadScaleFromLocalStorage();

    const gondolaFields = getInitialGondolaFields(null, props.gondolaSettings);

    if (savedScale) {
        gondolaFields.scaleFactor = savedScale;
    }

    return {
        // Step 1: Gondola Basic Info
        gondolaName: gondolaFields.gondolaName || generateGondolaCode(),
        location: gondolaFields.location || DEFAULT_GONDOLA_FIELDS.location,
        side: gondolaFields.side || DEFAULT_GONDOLA_FIELDS.side,
        scaleFactor:
            gondolaFields.scaleFactor || DEFAULT_GONDOLA_FIELDS.scaleFactor,
        flow: gondolaFields.flow || DEFAULT_GONDOLA_FIELDS.flow,
        status: gondolaFields.status || DEFAULT_GONDOLA_FIELDS.status,

        // Step 1: Modo de geração (manual por padrão)
        mode: 'manual' as 'manual' | 'template' | 'automatic',
        template_id: null as string | null,
        subtemplate_id: null as string | null,

        // Step 2: Module Configuration
        height: DEFAULT_SECTION_FIELDS.height,
        width: DEFAULT_SECTION_FIELDS.width,
        numModules: DEFAULT_GONDOLA_FIELDS.numModules,

        // Step 3: Base Configuration
        baseHeight: DEFAULT_SECTION_FIELDS.baseHeight,
        baseWidth: DEFAULT_SECTION_FIELDS.baseWidth,
        baseDepth: DEFAULT_SECTION_FIELDS.baseDepth,

        // Step 4: Cremalheira Configuration
        rackWidth: DEFAULT_SECTION_FIELDS.rackWidth,
        holeHeight: DEFAULT_SECTION_FIELDS.holeHeight,
        holeWidth: DEFAULT_SECTION_FIELDS.holeWidth,
        holeSpacing: DEFAULT_SECTION_FIELDS.holeSpacing,

        // Step 5: Shelves Default Configuration
        shelfHeight: DEFAULT_SHELF_FIELDS.shelfHeight,
        shelfWidth: DEFAULT_SHELF_FIELDS.shelfWidth,
        shelfDepth: DEFAULT_SHELF_FIELDS.shelfDepth,
        numShelves: DEFAULT_SHELF_FIELDS.numShelves,
        productType: DEFAULT_SHELF_FIELDS.productType,

        // Step 6: Workflow Configuration
        autoStartWorkflow: true,
        assignToCurrentUser: true,
        assignedUserId: null as string | null,
        startDate: new Date().toISOString().slice(0, 16) as string | null,
        notes: '',

        // Geração automática (campos flat — reaproveitam os partials do modal)
        strategy: 'abc' as 'abc' | 'sales' | 'margin' | 'mix',
        use_existing_analysis: false,
        start_date: '',
        end_date: '',
        min_facings: 1,
        max_facings: 10,
        group_by_subcategory: true,
        include_products_without_sales: false,
        exclude_class_c: false,
        table_type: 'monthly_summaries' as 'sales' | 'monthly_summaries',
        category_id: null as string | null,
        facing_expansion: 'score' as string | null,
        use_target_stock: true,
        space_fallback: 'reduce_c' as string | null,
        max_share_per_sku: null as number | null,
        max_share_per_brand: null as number | null,
        max_share_per_subcategory: null as number | null,
        hot_zone_priority: 'maior_margem' as string | null,
        cold_zone_priority: 'complementar_fria' as string | null,
        flow_direction: null as string | null,
    };
};

const form = useForm(getInitialFormData());

// Pré-preenche a categoria de geração com a categoria-base do planograma
watch(
    resolvedPlanogramCategoryId,
    (categoryId) => {
        if (categoryId && !form.category_id) {
            form.category_id = categoryId;
        }
    },
    { immediate: true },
);

// Pré-preenche as datas de vigência do planograma no passo de geração
watch(
    resolvedPlanogramStartDate,
    (startDate) => {
        if (startDate && !form.start_date) {
            form.start_date = startDate;
        }
    },
    { immediate: true },
);

watch(
    resolvedPlanogramEndDate,
    (endDate) => {
        if (endDate && !form.end_date) {
            form.end_date = endDate;
        }
    },
    { immediate: true },
);

// Watch scale changes to save to localStorage
watch(
    () => form.scaleFactor,
    (newScale) => {
        if (newScale && newScale >= 1) {
            saveScaleToLocalStorage(newScale);
        }
    },
);

// Watch module width changes to update base and shelf widths
watch(
    () => form.width,
    (newWidth) => {
        if (newWidth && newWidth >= 1) {
            form.baseWidth = newWidth;
            form.shelfWidth = newWidth;
        }
    },
);

// Opções de template para o modo de geração "template"
interface TemplateOption {
    value: string;
    label: string;
    description?: string | null;
    subtemplates?: Array<{ id: string; num_modules: number; code?: string }>;
}

const templateOptions = ref<TemplateOption[]>([]);
const templatesLoaded = ref(false);

const loadTemplateOptions = async () => {
    if (templatesLoaded.value || !isBrowser) {
        return;
    }

    try {
        const response = await fetch('/planogram-templates/options', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        templateOptions.value = Array.isArray(data?.templates)
            ? data.templates
            : [];
        templatesLoaded.value = true;
    } catch (error) {
        console.error('Erro ao carregar templates:', error);
    }
};

// Carrega os templates ao abrir o stepper
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            loadTemplateOptions();
        }
    },
    { immediate: true },
);

// Deriva numModules a partir do modelo (subtemplate) escolhido explicitamente
watch(
    () => form.subtemplate_id,
    (subtemplateId) => {
        if (form.mode !== 'template' || !subtemplateId) {
            return;
        }

        const selected = templateOptions.value.find(
            (option) => option.value === form.template_id,
        );
        const subtemplate = (selected?.subtemplates ?? []).find(
            (item) => item.id === subtemplateId,
        );

        if (subtemplate && subtemplate.num_modules >= 1) {
            form.numModules = subtemplate.num_modules;
        }
    },
);

// ── Form slices por passo ───────────────────────────────────────────────────
const step1Data = computed({
    get: () => ({
        gondolaName: form.gondolaName,
        location: form.location,
        side: form.side,
        scaleFactor: form.scaleFactor,
        flow: form.flow,
        status: form.status,
        mode: form.mode,
        template_id: form.template_id,
        subtemplate_id: form.subtemplate_id,
    }),
    set: (value) => {
        Object.assign(form, value);
    },
});

const step2Data = computed({
    get: () => ({
        height: form.height,
        width: form.width,
        numModules: form.numModules,
    }),
    set: (value) => {
        Object.assign(form, value);
    },
});

const step3Data = computed({
    get: () => ({
        baseHeight: form.baseHeight,
        baseWidth: form.baseWidth,
        baseDepth: form.baseDepth,
    }),
    set: (value) => {
        Object.assign(form, value);
    },
});

const step4Data = computed({
    get: () => ({
        rackWidth: form.rackWidth,
        holeHeight: form.holeHeight,
        holeWidth: form.holeWidth,
        holeSpacing: form.holeSpacing,
    }),
    set: (value) => {
        Object.assign(form, value);
    },
});

const step5Data = computed({
    get: () => ({
        shelfHeight: form.shelfHeight,
        shelfWidth: form.shelfWidth,
        shelfDepth: form.shelfDepth,
        numShelves: form.numShelves,
        productType: form.productType,
    }),
    set: (value) => {
        Object.assign(form, value);
    },
});

const moduleData = computed(() => ({
    height: form.height,
    baseHeight: form.baseHeight,
    numModules: form.numModules,
}));

const step6Data = computed({
    get: () => ({
        autoStartWorkflow: form.autoStartWorkflow,
        assignToCurrentUser: form.assignToCurrentUser,
        assignedUserId: form.assignedUserId,
        startDate: form.startDate,
        notes: form.notes,
    }),
    set: (value) => {
        form.autoStartWorkflow = value.autoStartWorkflow;
        form.assignToCurrentUser = value.assignToCurrentUser;
        form.assignedUserId = value.assignedUserId;
        form.startDate = value.startDate;
        form.notes = value.notes;
    },
});

// ── Passos dinâmicos por modo ────────────────────────────────────────────────
type StepKey =
    | 'basic'
    | 'modules'
    | 'base'
    | 'rack'
    | 'shelves'
    | 'generation'
    | 'workflow';

const stepTitle: Record<StepKey, () => string> = {
    basic: () => t('plannerate.form.gondola_create.steps.basic_info.title'),
    modules: () => t('plannerate.form.gondola_create.steps.modules.title'),
    base: () => t('plannerate.form.gondola_create.steps.base.title'),
    rack: () => t('plannerate.form.gondola_create.steps.rack.title'),
    shelves: () => t('plannerate.form.gondola_create.steps.shelves.title'),
    generation: () => t('plannerate.form.gondola_create.steps.generation.title'),
    workflow: () => t('plannerate.form.gondola_create.steps.workflow.title'),
};

const stepDescription: Record<StepKey, () => string> = {
    basic: () => t('plannerate.form.gondola_create.steps.basic_info.description'),
    modules: () => t('plannerate.form.gondola_create.steps.modules.description'),
    base: () => t('plannerate.form.gondola_create.steps.base.description'),
    rack: () => t('plannerate.form.gondola_create.steps.rack.description'),
    shelves: () => t('plannerate.form.gondola_create.steps.shelves.description'),
    generation: () =>
        t('plannerate.form.gondola_create.steps.generation.description'),
    workflow: () => t('plannerate.form.gondola_create.steps.workflow.description'),
};

// Todos os modos coletam a estrutura física completa (módulos + base + cremalheira + prateleiras).
// No automático, o passo de geração vem depois das prateleiras para configurar a análise de vendas.
const stepKeysByMode: Record<'manual' | 'template' | 'automatic', StepKey[]> = {
    manual: ['basic', 'modules', 'base', 'rack', 'shelves', 'workflow'],
    template: ['basic', 'modules', 'base', 'rack', 'shelves', 'workflow'],
    automatic: ['basic', 'modules', 'base', 'rack', 'shelves', 'generation', 'workflow'],
};

const activeStepKeys = computed<StepKey[]>(
    () => stepKeysByMode[form.mode] ?? stepKeysByMode.manual,
);

const steps = computed(() =>
    activeStepKeys.value.map((key, index) => ({
        step: index + 1,
        key,
        title: stepTitle[key](),
        description: stepDescription[key](),
    })),
);

const totalSteps = computed(() => activeStepKeys.value.length);
const currentStepKey = computed<StepKey>(
    () => activeStepKeys.value[currentStep.value - 1] ?? 'basic',
);
const isLastStep = computed(() => currentStep.value >= totalSteps.value);

// Validação do passo ativo
const validateByKey: Record<StepKey, () => boolean> = {
    basic: () => validateStep1(step1Data.value),
    modules: () => validateStep2(step2Data.value),
    base: () => validateStep3(step3Data.value),
    rack: () => validateStep4(step4Data.value),
    shelves: () => validateStep5(step5Data.value),
    generation: () =>
        validateGeneration({
            strategy: form.strategy,
            use_existing_analysis: form.use_existing_analysis,
            start_date: form.start_date,
            end_date: form.end_date,
            min_facings: form.min_facings,
            max_facings: form.max_facings,
            group_by_subcategory: form.group_by_subcategory,
            include_products_without_sales: form.include_products_without_sales,
            exclude_class_c: form.exclude_class_c,
            table_type: form.table_type,
            category_id: form.category_id,
            facing_expansion: form.facing_expansion,
            space_fallback: form.space_fallback,
            use_target_stock: form.use_target_stock,
        }),
    workflow: () => validateStep6(),
};

const canGoNext = computed(() => validateByKey[currentStepKey.value]?.() ?? false);

const nextStep = () => {
    if (canGoNext.value && currentStep.value < totalSteps.value) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const submitLabel = computed(() =>
    form.mode === 'automatic'
        ? t('plannerate.form.gondola_create.create_and_generate')
        : t('plannerate.form.gondola_create.create'),
);

const handleClose = () => {
    emit('update:open', false);
    currentStep.value = 1;
    form.reset();
};

const handleSubmit = () => {
    const planogramId = resolvedPlanogramId.value;
    const subdomain = resolvedSubdomain.value;

    if (!planogramId || !subdomain) {
        console.error(
            'Não foi possível resolver subdomínio/planograma para criar gôndola.',
            { planogramId, subdomain, propsPlanogramId: props.planogramId },
        );

        return;
    }

    // No automático a estrutura física (módulos + prateleiras) é definida pelo usuário no Step 5.
    // O backend cria as seções com exatamente numShelves prateleiras, e o motor de geração
    // usa essa estrutura como envelope fixo para posicionar os produtos.

    form.post(storeGondolaRoute.url({ planogram: planogramId }), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            emit('success');
            handleClose();
        },
        onError: (errors) => {
            console.error('Erros na criação da gôndola:', errors);
        },
    });
};
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent side="right" class="flex w-full flex-col md:max-w-4xl">
            <SheetHeader class="shrink-0 py-2">
                <SheetTitle>{{ t('plannerate.form.gondola_create.title') }}</SheetTitle>
                <SheetDescription>
                    {{ t('plannerate.form.gondola_create.description') }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-1 flex-col gap-6 overflow-y-auto">
                <!-- Stepper -->
                <div class="shrink-0 mt-4 px-6">
                    <Stepper
                        v-model="currentStep"
                        class="flex w-full items-start gap-6"
                    >
                        <StepperItem
                            v-for="step in steps"
                            :key="step.key"
                            v-slot="{ state }"
                            class="relative flex w-full flex-col items-center justify-center"
                            :step="step.step"
                        >
                            <StepperSeparator
                                v-if="step.step !== steps[steps.length - 1]?.step"
                                class="absolute top-5 right-[calc(-50%+20px)] left-[calc(50%+20px)] block h-0.5 shrink-0 rounded-full bg-muted group-data-[state=completed]:bg-primary"
                            />
                            <StepperTrigger as-child>
                                <Button
                                    :variant="
                                        state === 'completed' || state === 'active'
                                            ? 'default'
                                            : 'outline'
                                    "
                                    size="icon"
                                    class="z-10 shrink-0 rounded-full"
                                    :class="[
                                        state === 'active' &&
                                            'ring-2 ring-ring ring-offset-2 ring-offset-background',
                                    ]"
                                >
                                    <Check
                                        v-if="state === 'completed'"
                                        class="size-5"
                                    />
                                    <Circle v-if="state === 'active'" />
                                    <Dot v-if="state === 'inactive'" />
                                </Button>
                            </StepperTrigger>
                            <div
                                class="mt-5 flex flex-col items-center text-center"
                            >
                                <StepperTitle
                                    :class="[state === 'active' && 'text-primary']"
                                    class="text-sm font-semibold transition lg:text-base"
                                >
                                    {{ step.title }}
                                </StepperTitle>
                                <StepperDescription
                                    :class="[state === 'active' && 'text-primary']"
                                    class="sr-only text-xs text-muted-foreground transition md:not-sr-only lg:text-sm"
                                >
                                    {{ step.description }}
                                </StepperDescription>
                            </div>
                        </StepperItem>
                    </Stepper>
                </div>

                <!-- Step Content -->
                <div class="flex-1 rounded-lg border py-2 px-6">
                    <Step1BasicInfo
                        v-if="currentStepKey === 'basic'"
                        v-model="step1Data"
                        :templates="templateOptions"
                        :errors="form.errors"
                    />

                    <Step2Modules
                        v-if="currentStepKey === 'modules'"
                        v-model="step2Data"
                        :errors="form.errors"
                    />

                    <Step3Base
                        v-if="currentStepKey === 'base'"
                        v-model="step3Data"
                        :errors="form.errors"
                    />

                    <Step4Rack
                        v-if="currentStepKey === 'rack'"
                        v-model="step4Data"
                        :errors="form.errors"
                    />

                    <Step5Shelves
                        v-if="currentStepKey === 'shelves'"
                        v-model="step5Data"
                        :module-data="moduleData"
                        :errors="form.errors"
                    />

                    <StepGeneration
                        v-if="currentStepKey === 'generation'"
                        :form="form"
                        :errors="form.errors"
                        :root-category-id="resolvedPlanogramCategoryId"
                    />

                    <Step6Workflow
                        v-if="currentStepKey === 'workflow'"
                        v-model="step6Data"
                        :available-users="availableUsers"
                        :errors="form.errors"
                    />
                </div>

                <!-- Navigation Buttons -->
                <div class="flex shrink-0 justify-between border-t py-2 px-6">
                    <Button
                        variant="outline"
                        @click="prevStep"
                        :disabled="currentStep === 1"
                    >
                        <ChevronLeft class="size-4" />
                        {{ t('plannerate.form.gondola_create.previous') }}
                    </Button>

                    <Button
                        v-if="!isLastStep"
                        @click="nextStep"
                        :disabled="!canGoNext"
                    >
                        {{ t('plannerate.form.gondola_create.next') }}
                        <ChevronRight class="size-4" />
                    </Button>

                    <Button
                        v-else
                        @click="handleSubmit"
                        :disabled="!canGoNext || form.processing"
                    >
                        {{ form.processing ? t('plannerate.form.gondola_create.creating') : submitLabel }}
                        <Check class="size-4" />
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
