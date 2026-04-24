<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface FacingsFormState {
    min_facings: number;
    max_facings: number;
}

defineProps<{
    form: FacingsFormState;
}>();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">Configurações de Frentes</Label>
        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
                <Label for="min-facings">Frentes Mínimo</Label>
                <Input
                    id="min-facings"
                    v-model.number="form.min_facings"
                    type="number"
                    min="1"
                    max="10"
                />
                <p class="text-xs text-muted-foreground">
                    Mínimo de produtos lado a lado (1-10)
                </p>
            </div>
            <div class="space-y-2">
                <Label for="max-facings">Frentes Máximo</Label>
                <Input
                    id="max-facings"
                    v-model.number="form.max_facings"
                    type="number"
                    min="1"
                    max="20"
                />
                <p class="text-xs text-muted-foreground">
                    Máximo de produtos lado a lado (1-20)
                </p>
            </div>
        </div>
    </div>
</template>
