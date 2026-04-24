<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

interface AdvancedOptionsFormState {
    group_by_subcategory: boolean;
    include_products_without_sales: boolean;
}

defineProps<{
    form: AdvancedOptionsFormState;
}>();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">Opções Avançadas</Label>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="group-subcategory">Agrupar por subcategoria</Label>
                <div class="text-sm text-muted-foreground">
                    Mantém produtos da mesma subcategoria juntos
                </div>
            </div>
            <Switch id="group-subcategory" v-model="form.group_by_subcategory" />
        </div>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="include-no-sales">Incluir produtos sem vendas</Label>
                <div class="text-sm text-muted-foreground">
                    Produtos novos ou sem vendas no período
                </div>
            </div>
            <Switch
                id="include-no-sales"
                v-model="form.include_products_without_sales"
            />
        </div>
    </div>
</template>
