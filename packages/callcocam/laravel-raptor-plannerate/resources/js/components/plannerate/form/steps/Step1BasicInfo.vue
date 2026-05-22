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
}): boolean => {
    // Modo template exige um template selecionado
    if (data.mode === 'template' && !data.template_id) {
        return false;
    }

    // Usa validação do composable
    return validateGondolaFields(data as any);
};
</script>

<script setup lang="ts">
import { InfoIcon } from 'lucide-vue-next';
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
        // Limpa o template ao sair do modo template
        template_id: modeValue === 'template' ? props.modelValue.template_id : null,
    });
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

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2" :class="{ 'md:col-span-2': props.modelValue.mode !== 'template' }">
                <Label for="mode">{{ t('plannerate.form.step1.mode.label') }}</Label>
                <Select
                    :model-value="props.modelValue.mode"
                    @update:model-value="(val) => setMode((String(val ?? 'manual')) as 'manual' | 'template' | 'automatic')"
                >
                    <SelectTrigger id="mode" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>{{ t('plannerate.form.step1.mode.label') }}</SelectLabel>
                            <SelectItem value="manual">{{ t('plannerate.form.step1.mode.manual') }}</SelectItem>
                            <SelectItem value="template">{{ t('plannerate.form.step1.mode.template') }}</SelectItem>
                            <SelectItem value="automatic">{{ t('plannerate.form.step1.mode.automatic') }}</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{ t('plannerate.form.step1.mode.hint') }}
                </p>
            </div>

            <div v-if="props.modelValue.mode === 'template'" class="space-y-2">
                <Label for="template_id">{{ t('plannerate.form.step1.mode.template_label') }} *</Label>
                <Select
                    :model-value="props.modelValue.template_id ?? ''"
                    @update:model-value="(val) => updateField('template_id', val ? String(val) : null)"
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
                <p v-else-if="props.templates.length === 0" class="text-xs text-amber-600">
                    {{ t('plannerate.form.step1.mode.no_templates') }}
                </p>
            </div>
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
