<script lang="ts">
export const validate = (data: {
    gondolaName: string;
    location: string;
    side: string;
    scaleFactor: number;
    flow: 'left_to_right' | 'right_to_left';
    status: string;
    mode?: 'manual' | 'template' | 'automatic';
    template_id?: string | null;
    subtemplate_id?: string | null;
}): boolean => {
    // Modo template exige template E modelo (subtemplate) selecionados
    if (data.mode === 'template' && (!data.template_id || !data.subtemplate_id)) {
        return false;
    }

    // Usa validação do composable
    return validateGondolaFields(data as any);
};
</script>

<script setup lang="ts">
import { InfoIcon, LayoutTemplate, PenLine, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { validateGondolaFields } from '@/composables/plannerate/fields/useGondolaFields';
import { useT } from '@/composables/useT';

interface TemplateOption {
    value: string;
    label: string;
    description?: string | null;
    subtemplates?: Array<{ id: string; num_modules: number; code?: string }>;
}

interface Props {
    modelValue: {
        gondolaName: string;
        location: string;
        side: string;
        scaleFactor: number;
        flow: 'left_to_right' | 'right_to_left';
        status: string;
        mode: 'manual' | 'template' | 'automatic';
        template_id: string | null;
        subtemplate_id: string | null;
    };
    errors?: Record<string, string>;
    templates?: TemplateOption[];
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = withDefaults(defineProps<Props>(), {
    templates: () => [],
});
const emit = defineEmits<Emits>();
const { t } = useT();

function updateField<K extends keyof Props['modelValue']>(
    key: K,
    value: Props['modelValue'][K],
): void {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
}

const setFlow = (flowValue: 'left_to_right' | 'right_to_left') => {
    updateField('flow', flowValue);
};

const setMode = (modeValue: 'manual' | 'template' | 'automatic') => {
    emit('update:modelValue', {
        ...props.modelValue,
        mode: modeValue,
        // Limpa template/modelo ao sair do modo template
        template_id: modeValue === 'template' ? props.modelValue.template_id : null,
        subtemplate_id:
            modeValue === 'template' ? props.modelValue.subtemplate_id : null,
    });
};

/** Subtemplates (modelos) do template selecionado */
const selectedSubtemplates = computed(
    () =>
        props.templates.find(
            (option) => option.value === props.modelValue.template_id,
        )?.subtemplates ?? [],
);

/** Rótulo de um modelo: usa code quando houver, senão "N módulos" */
const subtemplateLabel = (subtemplate: {
    id: string;
    num_modules: number;
    code?: string;
}): string => {
    if (subtemplate.code) {
        return `${subtemplate.code} · ${subtemplate.num_modules} ${t('plannerate.form.step1.mode.modules_suffix')}`;
    }

    return `${subtemplate.num_modules} ${t('plannerate.form.step1.mode.modules_suffix')}`;
};

/** Ao escolher um template, limpa o modelo e auto-seleciona quando houver só um */
const setTemplate = (templateId: string | null) => {
    const subtemplates =
        props.templates.find((option) => option.value === templateId)
            ?.subtemplates ?? [];
    const onlyOne = subtemplates.length === 1 ? subtemplates[0] : null;

    emit('update:modelValue', {
        ...props.modelValue,
        template_id: templateId,
        subtemplate_id: onlyOne?.id ?? null,
    });
};

/** Ao escolher o modelo, propaga o subtemplate selecionado */
const setSubtemplate = (subtemplateId: string | null) => {
    updateField('subtemplate_id', subtemplateId);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <InfoIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">{{ t('plannerate.form.step1.title') }}</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="gondolaName">{{ t('plannerate.form.step1.gondola_name') }} *</Label>
                <Input
                    id="gondolaName"
                    :model-value="props.modelValue.gondolaName"
                    @update:model-value="(val) => updateField('gondolaName', String(val ?? ''))"
                    required
                    :class="{
                        'border-red-500': errors?.gondolaName,
                    }"
                />
                <p v-if="errors?.gondolaName" class="text-xs text-red-500">
                    {{ errors.gondolaName }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="location">{{ t('plannerate.form.step1.location') }}</Label>
                <Input
                    id="location"
                    :model-value="props.modelValue.location"
                    @update:model-value="(val) => updateField('location', String(val ?? ''))"
                    :placeholder="t('plannerate.form.step1.location_placeholder')"
                    :class="{
                        'border-red-500': errors?.location,
                    }"
                />
                <p v-if="errors?.location" class="text-xs text-red-500">
                    {{ errors.location }}
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    {{ t('plannerate.form.step1.location_hint') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="side">{{ t('plannerate.form.step1.side') }} *</Label>
                <Input
                    id="side"
                    :model-value="props.modelValue.side"
                    @update:model-value="(val) => updateField('side', String(val ?? ''))"
                    :placeholder="t('plannerate.form.step1.side_placeholder')"
                    :class="{
                        'border-red-500': errors?.side,
                    }"
                />
                <p v-if="errors?.side" class="text-xs text-red-500">
                    {{ errors.side }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="scaleFactor">{{ t('plannerate.form.step1.scale_factor') }} *</Label>
                <Input
                    id="scaleFactor"
                    type="number"
                    :model-value="props.modelValue.scaleFactor"
                    @update:model-value="(val) => updateField('scaleFactor', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.scaleFactor,
                    }"
                />
                <p v-if="errors?.scaleFactor" class="text-xs text-red-500">
                    {{ errors.scaleFactor }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="status">{{ t('plannerate.analysis.selection.status') }}</Label>
                <Select
                    :model-value="props.modelValue.status"
                    @update:model-value="(val) => updateField('status', String(val ?? 'draft'))"
                >
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="t('plannerate.form.step1.select_status')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>{{ t('plannerate.analysis.selection.status') }}</SelectLabel>
                            <SelectItem value="published">{{ t('plannerate.form.step1.status_published') }}</SelectItem>
                            <SelectItem value="draft">{{ t('plannerate.form.step1.status_draft') }}</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Seleção de modo por cards -->
        <div class="space-y-2">
            <Label>{{ t('plannerate.form.step1.mode.label') }}</Label>
            <div
                class="grid gap-3"
                :class="props.templates.length > 0 ? 'grid-cols-3' : 'grid-cols-2'"
            >
                <!-- Manual -->
                <button
                    type="button"
                    class="flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        props.modelValue.mode === 'manual'
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                            : 'border-border hover:bg-muted/40'
                    "
                    @click="setMode('manual')"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-md"
                        :class="props.modelValue.mode === 'manual' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                    >
                        <PenLine class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium leading-none">{{ t('plannerate.form.step1.mode.manual') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.form.step1.mode.manual_desc') }}</p>
                    </div>
                </button>

                <!-- Por Template — só aparece se houver templates cadastrados -->
                <button
                    v-if="props.templates.length > 0"
                    type="button"
                    class="flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        props.modelValue.mode === 'template'
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                            : 'border-border hover:bg-muted/40'
                    "
                    @click="setMode('template')"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-md"
                        :class="props.modelValue.mode === 'template' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                    >
                        <LayoutTemplate class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium leading-none">{{ t('plannerate.form.step1.mode.template') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.form.step1.mode.template_desc') }}</p>
                    </div>
                </button>

                <!-- Automático -->
                <button
                    type="button"
                    class="flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        props.modelValue.mode === 'automatic'
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                            : 'border-border hover:bg-muted/40'
                    "
                    @click="setMode('automatic')"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-md"
                        :class="props.modelValue.mode === 'automatic' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                    >
                        <Zap class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium leading-none">{{ t('plannerate.form.step1.mode.automatic') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('plannerate.form.step1.mode.automatic_desc') }}</p>
                    </div>
                </button>
            </div>
        </div>

        <div v-if="props.modelValue.mode === 'template'" class="space-y-2">
            <Label for="template_id">{{ t('plannerate.form.step1.mode.template_label') }} *</Label>
            <Select
                :model-value="props.modelValue.template_id ?? ''"
                @update:model-value="(val) => setTemplate(val ? String(val) : null)"
            >
                <SelectTrigger
                    id="template_id"
                    class="w-full"
                    :class="{ 'border-red-500': errors?.template_id }"
                >
                    <SelectValue :placeholder="t('plannerate.form.step1.mode.select_template')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>{{ t('plannerate.form.step1.mode.template_label') }}</SelectLabel>
                        <SelectItem
                            v-for="template in props.templates"
                            :key="template.value"
                            :value="template.value"
                        >
                            {{ template.label }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <p v-if="errors?.template_id" class="text-xs text-red-500">
                {{ errors.template_id }}
            </p>
        </div>

        <!-- Seleção explícita do modelo (subtemplate) — obrigatória no modo template -->
        <div
            v-if="props.modelValue.mode === 'template' && props.modelValue.template_id"
            class="space-y-2"
        >
            <Label for="subtemplate_id">{{ t('plannerate.form.step1.mode.subtemplate_label') }} *</Label>
            <Select
                :model-value="props.modelValue.subtemplate_id ?? ''"
                @update:model-value="(val) => setSubtemplate(val ? String(val) : null)"
            >
                <SelectTrigger
                    id="subtemplate_id"
                    class="w-full"
                    :class="{ 'border-red-500': errors?.subtemplate_id }"
                >
                    <SelectValue :placeholder="t('plannerate.form.step1.mode.select_subtemplate')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel>{{ t('plannerate.form.step1.mode.subtemplate_label') }}</SelectLabel>
                        <SelectItem
                            v-for="subtemplate in selectedSubtemplates"
                            :key="subtemplate.id"
                            :value="subtemplate.id"
                        >
                            {{ subtemplateLabel(subtemplate) }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <p v-if="errors?.subtemplate_id" class="text-xs text-red-500">
                {{ errors.subtemplate_id }}
            </p>
            <p v-else class="text-xs text-muted-foreground">
                {{ t('plannerate.form.step1.mode.subtemplate_hint') }}
            </p>
        </div>

        <div class="space-y-2">
            <Label>{{ t('plannerate.form.step1.flow_position') }} *</Label>
            <div
                class="grid grid-cols-2 gap-2 rounded-md border"
                :class="{ 'border-red-500': errors?.flow }"
            >
                <Button
                    :variant="
                        props.modelValue.flow === 'left_to_right'
                            ? 'default'
                            : 'outline'
                    "
                    @click="setFlow('left_to_right')"
                    type="button"
                    class="justify-center rounded-r-none border-r"
                >
                    {{ t('plannerate.form.step1.flow_left_to_right') }}
                </Button>
                <Button
                    :variant="
                        props.modelValue.flow === 'right_to_left'
                            ? 'default'
                            : 'outline'
                    "
                    @click="setFlow('right_to_left')"
                    type="button"
                    class="justify-center rounded-l-none"
                >
                    {{ t('plannerate.form.step1.flow_right_to_left') }}
                </Button>
            </div>
            <p v-if="errors?.flow" class="text-xs text-red-500">
                {{ errors.flow }}
            </p>
        </div>
    </div>
</template>
