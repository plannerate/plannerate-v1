<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';

interface GenerationFormState {
    generate_by_sections: boolean;
    use_ai: boolean;
}

defineProps<{
    form: GenerationFormState;
    permissions: { 
        can_autogenate_gondola: boolean;
        can_autogenate_gondola_ia: boolean;
    };
}>();

const { t } = useT();
</script>

<template>
    <div v-if="permissions.can_autogenate_gondola"
        class="space-y-3 rounded-lg border bg-muted/30 p-4"
        :class="form.generate_by_sections ? 'border-primary/50' : 'border-border'"
    >
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="generate-by-sections" class="text-base font-semibold">
                    {{ t('plannerate.header.generation_mode.by_section') }}
                </Label>
                <div class="text-sm text-muted-foreground">
                    {{
                        form.generate_by_sections
                            ? t('plannerate.header.generation_mode.by_section_enabled')
                            : t('plannerate.header.generation_mode.by_section_disabled')
                    }}
                </div>
            </div>
            <Switch id="generate-by-sections" v-model="form.generate_by_sections" />
        </div>
    </div>

    <div v-if="permissions.can_autogenate_gondola_ia"
        class="space-y-3 rounded-lg border-2 bg-muted/30 p-4"
        :class="form.use_ai ? 'border-purple-500/50' : 'border-border'"
    >
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="use-ai" class="text-base font-semibold">
                    {{ form.use_ai ? t('plannerate.header.generation_mode.ai_generation') : t('plannerate.header.generation_mode.rules_algorithm') }}
                </Label>
                <div class="text-sm text-muted-foreground">
                    {{
                        form.generate_by_sections
                            ? form.use_ai
                                ? t('plannerate.header.generation_mode.ai_by_section')
                                : t('plannerate.header.generation_mode.rules_by_section')
                            : form.use_ai
                              ? t('plannerate.header.generation_mode.ai_full_gondola')
                              : t('plannerate.header.generation_mode.fast_algorithm_full_gondola')
                    }}
                </div>
            </div>
            <Switch id="use-ai" v-model="form.use_ai" />
        </div>
    </div>
</template>
