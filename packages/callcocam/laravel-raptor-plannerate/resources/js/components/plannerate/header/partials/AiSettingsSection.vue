<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';

interface AiOption {
    value: string;
    label: string;
    description: string;
}

interface AiFormState {
    use_ai: boolean;
    model?: string;
    apply_visual_grouping?: boolean;
    intelligent_ordering?: boolean;
    load_balancing?: boolean;
    additional_instructions?: string;
}

defineProps<{
    form: AiFormState;
    aiModelOptions: AiOption[];
}>();

const { t } = useT();
</script>

<template>
    <div
        v-if="form.use_ai"
        class="space-y-4 rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-950/20"
    >
        <Label class="text-base font-semibold text-purple-900 dark:text-purple-100">
            {{ t('plannerate.header.ai_settings.title') }}
        </Label>

        <div class="space-y-3">
            <Label>{{ t('plannerate.header.ai_settings.model') }}</Label>
            <RadioGroup v-model="form.model">
                <div
                    v-for="option in aiModelOptions"
                    :key="option.value"
                    class="flex items-start space-y-0 space-x-3"
                >
                    <RadioGroupItem :id="`model-${option.value}`" :value="option.value" />
                    <Label :for="`model-${option.value}`" class="flex-1 cursor-pointer font-normal">
                        <div class="font-semibold">{{ option.label }}</div>
                        <div class="text-xs text-muted-foreground">{{ option.description }}</div>
                    </Label>
                </div>
            </RadioGroup>
        </div>

        <div class="space-y-3 pt-2">
            <Label class="text-sm font-semibold">{{ t('plannerate.header.ai_settings.advanced_features') }}</Label>

            <div class="flex items-center justify-between">
                <Label for="visual-grouping" class="text-sm font-normal">
                    {{ t('plannerate.header.ai_settings.visual_grouping') }}
                </Label>
                <Switch id="visual-grouping" v-model="form.apply_visual_grouping" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="intelligent-ordering" class="text-sm font-normal">
                    {{ t('plannerate.header.ai_settings.intelligent_ordering') }}
                </Label>
                <Switch id="intelligent-ordering" v-model="form.intelligent_ordering" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="load-balancing" class="text-sm font-normal">
                    {{ t('plannerate.header.ai_settings.load_balancing') }}
                </Label>
                <Switch id="load-balancing" v-model="form.load_balancing" />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="ai-instructions">{{ t('plannerate.header.ai_settings.additional_instructions') }}</Label>
            <textarea
                id="ai-instructions"
                v-model="form.additional_instructions"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                :placeholder="t('plannerate.header.ai_settings.additional_instructions_placeholder')"
                maxlength="1000"
            />
            <p class="text-xs text-muted-foreground">
                {{ t('plannerate.header.ai_settings.additional_instructions_hint') }}
            </p>
        </div>
    </div>
</template>
