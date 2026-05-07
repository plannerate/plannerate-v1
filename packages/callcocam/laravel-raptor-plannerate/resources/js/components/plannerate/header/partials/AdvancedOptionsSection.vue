<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */

import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';

interface AdvancedOptionsFormState {
    group_by_subcategory: boolean;
    include_products_without_sales: boolean;
}

defineProps<{
    form: AdvancedOptionsFormState;
}>();

const { t } = useT();
</script>

<template>
    <div class="space-y-3">
        <Label class="text-base font-semibold">{{ t('plannerate.header.advanced_options.title') }}</Label>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="group-subcategory">{{ t('plannerate.header.advanced_options.group_subcategory') }}</Label>
                <div class="text-sm text-muted-foreground">
                    {{ t('plannerate.header.advanced_options.group_subcategory_hint') }}
                </div>
            </div>
            <Switch id="group-subcategory" v-model="form.group_by_subcategory" />
        </div>

        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <Label for="include-no-sales">{{ t('plannerate.header.advanced_options.include_no_sales') }}</Label>
                <div class="text-sm text-muted-foreground">
                    {{ t('plannerate.header.advanced_options.include_no_sales_hint') }}
                </div>
            </div>
            <Switch
                id="include-no-sales"
                v-model="form.include_products_without_sales"
            />
        </div>
    </div>
</template>
