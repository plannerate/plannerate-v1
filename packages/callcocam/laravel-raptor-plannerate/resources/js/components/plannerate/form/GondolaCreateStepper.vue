<script setup lang="ts">
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
} from '@/composables/plannerate/useGondolaFields';
import { DEFAULT_SECTION_FIELDS } from '@/composables/plannerate/useSectionFields';
import { DEFAULT_SHELF_FIELDS } from '@/composables/plannerate/useShelfFields';
import { useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Circle, Dot } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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

interface Props {
    open?: boolean;
    planogramId?: string;
    availableUsers?: Array<{ id: string; name: string }>;
    gondolaSettings?: any;
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
const isBrowser = typeof window !== 'undefined';

// Current step
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

    // Usa composable para campos de gôndola
    const gondolaFields = getInitialGondolaFields(null, props.gondolaSettings);

    // Ajusta scaleFactor com valor do localStorage se existir
    if (savedScale) {
        gondolaFields.scaleFactor = savedScale;
    }

    return {
        // Step 1: Gondola Basic Info (do composable)
        gondolaName: gondolaFields.gondolaName || generateGondolaCode(),
        location: gondolaFields.location || DEFAULT_GONDOLA_FIELDS.location,
        side: gondolaFields.side || DEFAULT_GONDOLA_FIELDS.side,
        scaleFactor:
            gondolaFields.scaleFactor || DEFAULT_GONDOLA_FIELDS.scaleFactor,
        flow: gondolaFields.flow || DEFAULT_GONDOLA_FIELDS.flow,
        status: gondolaFields.status || DEFAULT_GONDOLA_FIELDS.status,

        // Step 2: Module Configuration (dimensões do módulo/seção)
        height: DEFAULT_SECTION_FIELDS.height,
        width: DEFAULT_SECTION_FIELDS.width,
        numModules: DEFAULT_GONDOLA_FIELDS.numModules,

        // Step 3: Base Configuration (do composable de seção)
        baseHeight: DEFAULT_SECTION_FIELDS.baseHeight,
        baseWidth: DEFAULT_SECTION_FIELDS.baseWidth,
        baseDepth: DEFAULT_SECTION_FIELDS.baseDepth,

        // Step 4: Cremalheira Configuration (do composable de seção)
        rackWidth: DEFAULT_SECTION_FIELDS.rackWidth,
        holeHeight: DEFAULT_SECTION_FIELDS.holeHeight,
        holeWidth: DEFAULT_SECTION_FIELDS.holeWidth,
        holeSpacing: DEFAULT_SECTION_FIELDS.holeSpacing,

        // Step 5: Shelves Default Configuration (do composable de prateleira)
        shelfHeight: DEFAULT_SHELF_FIELDS.shelfHeight,
        shelfWidth: DEFAULT_SHELF_FIELDS.shelfWidth,
        shelfDepth: DEFAULT_SHELF_FIELDS.shelfDepth,
        numShelves: DEFAULT_SHELF_FIELDS.numShelves,
        productType: DEFAULT_SHELF_FIELDS.productType,

        // Step 6: Workflow Configuration
        autoStartWorkflow: true,
        assignToCurrentUser: true,
        assignedUserId: null as string | null,
        startDate: new Date().toISOString().slice(0, 16) as string | null, // Format: YYYY-MM-DDTHH:mm
        notes: '',
    };
};

const form = useForm(getInitialFormData());

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

// Form data split by steps usando WritableComputedRef para garantir reatividade bidirecional
const step1Data = computed({
    get: () => ({
        gondolaName: form.gondolaName,
        location: form.location,
        side: form.side,
        scaleFactor: form.scaleFactor,
        flow: form.flow,
        status: form.status,
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

// Module data for step 5 calculations
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

// Steps configuration
const steps = [
    {
        step: 1,
        title: 'Informações Básicas',
        description: 'Nome, localização e configurações iniciais',
    },
    {
        step: 2,
        title: 'Módulos',
        description: 'Dimensões e quantidade de módulos',
    },
    {
        step: 3,
        title: 'Base',
        description: 'Configuração da base da gôndola',
    },
    {
        step: 4,
        title: 'Cremalheira',
        description: 'Configuração dos furos e cremalheira',
    },
    {
        step: 5,
        title: 'Prateleiras',
        description: 'Configuração padrão das prateleiras',
    },
    {
        step: 6,
        title: 'Workflow',
        description: 'Configuração do fluxo de trabalho',
    },
];

// Navigation with validation
const canGoNext = computed(() => {
    switch (currentStep.value) {
        case 1:
            return validateStep1(step1Data.value);
        case 2:
            return validateStep2(step2Data.value);
        case 3:
            return validateStep3(step3Data.value);
        case 4:
            return validateStep4(step4Data.value);
        case 5:
            return validateStep5(step5Data.value);
        case 6:
            return validateStep6();
        default:
            return false;
    }
});

const nextStep = () => {
    if (canGoNext.value && currentStep.value < 6) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const handleClose = () => {
    emit('update:open', false);
    currentStep.value = 1;
    form.reset();
};

const handleSubmit = () => {
    if (!props.planogramId) return;
 

    form.post(`/api/editor/planograms/${props.planogramId}/gondolas`, {
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
                <SheetTitle>Criar Nova Gôndola</SheetTitle>
                <SheetDescription>
                    Configure a nova gôndola seguindo os passos abaixo
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
                            :key="step.step"
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
                        v-if="currentStep === 1"
                        v-model="step1Data"
                        :errors="form.errors"
                    />

                    <Step2Modules
                        v-if="currentStep === 2"
                        v-model="step2Data"
                        :errors="form.errors"
                    />

                    <Step3Base
                        v-if="currentStep === 3"
                        v-model="step3Data"
                        :errors="form.errors"
                    />

                    <Step4Rack
                        v-if="currentStep === 4"
                        v-model="step4Data"
                        :errors="form.errors"
                    />

                    <Step5Shelves
                        v-if="currentStep === 5"
                        v-model="step5Data"
                        :module-data="moduleData"
                        :errors="form.errors"
                    />

                    <Step6Workflow
                        v-if="currentStep === 6"
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
                        Anterior
                    </Button>

                    <Button
                        v-if="currentStep < 6"
                        @click="nextStep"
                        :disabled="!canGoNext"
                    >
                        Próximo
                        <ChevronRight class="size-4" />
                    </Button>

                    <Button
                        v-else
                        @click="handleSubmit"
                        :disabled="!canGoNext || form.processing"
                    >
                        {{ form.processing ? 'Criando...' : 'Criar Gôndola' }}
                        <Check class="size-4" />
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
