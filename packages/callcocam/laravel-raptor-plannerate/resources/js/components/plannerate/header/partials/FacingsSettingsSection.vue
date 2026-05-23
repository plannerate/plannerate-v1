<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

interface FacingsFormState {
    min_facings: number;
    max_facings: number;
}

defineProps<{
    form: FacingsFormState;
    errors?: Record<string, string>;
}>();

const { t } = useT();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">{{ t('plannerate.header.facings.title') }}</Label>
        <div class="grid grid-cols-2 gap-4">
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
                <p v-else class="text-xs text-muted-foreground">
                    {{ t('plannerate.header.facings.min_hint') }}
                </p>
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
                <p v-else class="text-xs text-muted-foreground">
                    {{ t('plannerate.header.facings.max_hint') }}
                </p>
            </div>
        </div>
    </div>
</template>
