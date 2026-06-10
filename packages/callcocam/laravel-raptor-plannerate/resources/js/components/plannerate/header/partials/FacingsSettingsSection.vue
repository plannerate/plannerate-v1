<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';

interface FacingsFormState {
    min_facings: number;
    max_facings: number;
    /** Exibe campos de expansão, fallback e estoque alvo (modo automático do stepper) */
    facing_expansion?: string | null;
    space_fallback?: string | null;
    use_target_stock?: boolean;
}

const props = defineProps<{
    form: FacingsFormState;
    errors?: Record<string, string>;
    /** Quando true, exibe os campos de expansão de frentes, Se faltar espaço e Usar estoque alvo */
    showExpansionOptions?: boolean;
}>();

const { t } = useT();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">{{ t('plannerate.header.facings.title') }}</Label>

        <!-- Todos os campos em uma linha (5 colunas quando modo auto, 2 quando básico) -->
        <div class="grid gap-4" :class="showExpansionOptions ? 'grid-cols-5' : 'grid-cols-2'">
            <div class="space-y-2">
                <Label for="min-facings">{{ t('plannerate.header.facings.min') }}</Label>
                <Input
                    id="min-facings"
                    v-model.number="form.min_facings"
                    type="number"
                    min="1"
                    max="10"
                />
                <p v-if="errors?.min_facings" class="text-xs text-red-500">{{ errors.min_facings }}</p>
                <p v-else class="text-xs text-muted-foreground">{{ t('plannerate.header.facings.min_hint') }}</p>
            </div>
            <div class="space-y-2">
                <Label for="max-facings">{{ t('plannerate.header.facings.max') }}</Label>
                <Input
                    id="max-facings"
                    v-model.number="form.max_facings"
                    type="number"
                    min="1"
                    max="20"
                />
                <p v-if="errors?.max_facings" class="text-xs text-red-500">{{ errors.max_facings }}</p>
                <p v-else class="text-xs text-muted-foreground">{{ t('plannerate.header.facings.max_hint') }}</p>
            </div>

            <template v-if="showExpansionOptions">
                <div class="space-y-2">
                    <Label for="facing-expansion">{{ t('planogram-templates.facing_expansion.label') }}</Label>
                    <select
                        id="facing-expansion"
                        v-model="form.facing_expansion"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option :value="null">{{ t('planogram-templates.facing_expansion.none') }}</option>
                        <option value="score">{{ t('planogram-templates.facing_expansion.score') }}</option>
                        <option value="current_stock">{{ t('planogram-templates.facing_expansion.current_stock') }}</option>
                        <option value="target_stock">{{ t('planogram-templates.facing_expansion.target_stock') }}</option>
                        <option value="equal">{{ t('planogram-templates.facing_expansion.equal') }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">{{ t('planogram-templates.facing_expansion.hint_module') }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="space-fallback">{{ t('planogram-templates.slot_editor.space_fallback_label') }}</Label>
                    <select
                        id="space-fallback"
                        v-model="form.space_fallback"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option :value="null">—</option>
                        <option value="reduce_c">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_c') }}</option>
                        <option value="reduce_facings">{{ t('planogram-templates.slot_editor.space_fallback_options.reduce_facings') }}</option>
                        <option value="skip">{{ t('planogram-templates.slot_editor.space_fallback_options.skip') }}</option>
                        <option value="remove_dog">{{ t('planogram-templates.slot_editor.space_fallback_options.remove_dog') }}</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="use-target-stock">{{ t('planogram-templates.slot_editor.target_stock_label') }}</Label>
                    <div class="flex h-9 items-center gap-2">
                        <Switch id="use-target-stock" v-model="form.use_target_stock" />
                        <span class="text-sm text-muted-foreground">
                            {{ form.use_target_stock ? t('plannerate.common.yes') : t('plannerate.common.no') }}
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
