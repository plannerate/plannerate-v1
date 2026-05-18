<script setup lang="ts">
import { Download, Trash2, Upload } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useT } from '@/composables/useT';
import type { PlanogramTemplateProduct } from './types';

const props = defineProps<{
    products: PlanogramTemplateProduct[];
    availableGroupings: string[];
}>();

const emit = defineEmits<{
    'update-grouping': [product: PlanogramTemplateProduct, grouping: string];
    'remove-product': [product: PlanogramTemplateProduct];
    'import-xlsx': [file: File];
    'download-template': [];
}>();

const { t } = useT();

function onImportChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) emit('import-xlsx', file);
    (event.target as HTMLInputElement).value = '';
}
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                {{ products.length }} {{ products.length === 1 ? t('planogram-templates.product_table.count_singular') : t('planogram-templates.product_table.count_plural') }}
            </p>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="emit('download-template')">
                    <Download class="size-3.5" />
                    {{ t('planogram-templates.product_table.download_button') }}
                </Button>
                <label class="cursor-pointer">
                    <Button variant="outline" size="sm" as="span">
                        <Upload class="size-3.5" />
                        {{ t('planogram-templates.product_table.import_button') }}
                    </Button>
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="sr-only"
                        @change="onImportChange"
                    />
                </label>
            </div>
        </div>

        <!-- Table -->
        <div class="flex-1 overflow-auto rounded-md border border-border   max-h-[calc(100vh-8rem)]">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-32 font-mono text-xs">{{ t('planogram-templates.product_table.columns.ean') }}</TableHead>
                        <TableHead>{{ t('planogram-templates.product_table.columns.description') }}</TableHead>
                        <TableHead class="w-28">{{ t('planogram-templates.product_table.columns.brand') }}</TableHead>
                        <TableHead class="w-44">{{ t('planogram-templates.product_table.columns.grouping') }}</TableHead>
                        <TableHead class="w-20">{{ t('planogram-templates.product_table.columns.package_type') }}</TableHead>
                        <TableHead class="w-20">{{ t('planogram-templates.product_table.columns.package_content') }}</TableHead>
                        <TableHead class="w-24">{{ t('planogram-templates.product_table.columns.status') }}</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <div class="  max-h-[calc(100vh-8rem)] overflow-auto" :style="{
                    maxHeight: `calc(100vh - 8rem)`,
                }">
                    <TableRow v-if="products.length === 0">
                        <TableCell colspan="8" class="py-10 text-center text-sm text-muted-foreground">
                            {{ t('planogram-templates.product_table.empty_message') }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="product in products" :key="product.id">
                        <TableCell class="font-mono text-xs">{{ product.ean }}</TableCell>
                        <TableCell class="max-w-[14rem] truncate text-sm">{{ product.description }}</TableCell>
                        <TableCell class="text-sm">{{ product.brand }}</TableCell>
                        <TableCell>
                            <Select
                                :model-value="product.grouping"
                                @update:model-value="emit('update-grouping', product, $event as string)"
                            >
                                <SelectTrigger class="h-7 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="g in availableGroupings"
                                        :key="g"
                                        :value="g"
                                        class="text-xs"
                                    >
                                        {{ g }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ product.package_type ?? t('planogram-templates.product_table.empty_value') }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ product.package_content ?? t('planogram-templates.product_table.empty_value') }}</TableCell>
                        <TableCell>
                            <Badge :variant="product.product_id ? 'default' : 'secondary'" class="text-[10px]">
                                {{ product.product_id ? t('planogram-templates.product_table.status_in_mix') : t('planogram-templates.product_table.status_out_of_mix') }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <button
                                type="button"
                                class="rounded p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                @click="emit('remove-product', product)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </TableCell>
                    </TableRow>
                </div>
            </Table>
        </div>
    </div>
</template>
