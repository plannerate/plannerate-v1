<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

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
</script>

<template>
    <div v-if="permissions.can_autogenate_gondola"
        class="space-y-3 rounded-lg border bg-muted/30 p-4"
        :class="form.generate_by_sections ? 'border-primary/50' : 'border-border'"
    >
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="generate-by-sections" class="text-base font-semibold">
                    Gerar por section (módulo a módulo)
                </Label>
                <div class="text-sm text-muted-foreground">
                    {{
                        form.generate_by_sections
                            ? '📐 Gera section por section (regras ou IA por módulo via Laravel AI). Mais previsível.'
                            : '📦 Gera a gôndola inteira de uma vez (algoritmo ou IA Prism).'
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
                    {{ form.use_ai ? 'Geração com IA' : 'Algoritmo (regras)' }}
                </Label>
                <div class="text-sm text-muted-foreground">
                    {{
                        form.generate_by_sections
                            ? form.use_ai
                                ? '🤖 IA por section (Laravel AI SDK, por módulo)'
                                : '⚡ Regras de merchandising por section'
                            : form.use_ai
                              ? '🤖 IA gôndola inteira (Prism, ~8-15s)'
                              : '⚡ Algoritmo rápido gôndola inteira (~2s)'
                    }}
                </div>
            </div>
            <Switch id="use-ai" v-model="form.use_ai" />
        </div>
    </div>
</template>
