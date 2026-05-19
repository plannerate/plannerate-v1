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
import { useT } from '@/composables/useT';
import type { PlanogramTemplateProduct } from './types';

defineProps<{
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

const dimensionFormatter = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 2,
});

function onImportChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        emit('import-xlsx', file);
    }

    (event.target as HTMLInputElement).value = '';
}

function formatDimensionValue(
    value: string | number | null | undefined,
): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(value);

    if (!Number.isFinite(numericValue) || numericValue <= 0) {
        return null;
    }

    return dimensionFormatter.format(numericValue);
}

function productDimensions(product: PlanogramTemplateProduct): string | null {
    const width = formatDimensionValue(product.width);
    const height = formatDimensionValue(product.height);
    const depth = formatDimensionValue(product.depth);

    if (!width || !height || !depth) {
        return null;
    }

    return `${width} x ${height} x ${depth} ${product.unit ?? 'cm'}`;
}
</script>

<template>
    <div
        class="flex min-h-[24rem] min-w-0 flex-col gap-2 overflow-hidden lg:max-h-[calc(100vh-17rem)]"
    >
        <!-- Toolbar -->
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-medium text-muted-foreground">
                {{ products.length }}
                {{
                    products.length === 1
                        ? t('planogram-templates.product_table.count_singular')
                        : t('planogram-templates.product_table.count_plural')
                }}
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8"
                    @click="emit('download-template')"
                >
                    <Download class="size-3.5" />
                    {{ t('planogram-templates.product_table.download_button') }}
                </Button>
                <label class="cursor-pointer">
                    <Button variant="outline" size="sm" class="h-8" as="span">
                        <Upload class="size-3.5" />
                        {{
                            t('planogram-templates.product_table.import_button')
                        }}
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
        <div
            class="min-h-0 flex-1 overflow-auto rounded-md border border-border"
        >
            <table class="w-full min-w-[66rem] caption-bottom text-sm">
                <thead
                    class="sticky top-0 z-20 bg-card shadow-sm [&_tr]:border-b"
                >
                    <tr class="border-b transition-colors">
                        <th
                            class="h-9 w-32 px-2 text-left align-middle font-mono text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.ean',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.description',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-28 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.brand',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-44 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.grouping',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-20 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.package_type',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-20 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.package_content',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-32 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.dimensions',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-24 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.columns.status',
                                )
                            }}
                        </th>
                        <th
                            class="h-9 w-10 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-foreground"
                        />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-if="products.length === 0"
                        class="border-b transition-colors"
                    >
                        <td
                            colspan="9"
                            class="p-2 py-10 text-center align-middle text-sm whitespace-nowrap text-muted-foreground"
                        >
                            {{
                                t(
                                    'planogram-templates.product_table.empty_message',
                                )
                            }}
                        </td>
                    </tr>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td
                            class="p-2 align-middle font-mono text-xs whitespace-nowrap"
                        >
                            {{ product.ean }}
                        </td>
                        <td
                            class="max-w-[14rem] truncate p-2 align-middle text-sm whitespace-nowrap"
                        >
                            {{ product.description }}
                        </td>
                        <td class="p-2 align-middle text-sm whitespace-nowrap">
                            {{ product.brand }}
                        </td>
                        <td class="p-2 align-middle whitespace-nowrap">
                            <Select
                                :model-value="product.grouping"
                                @update:model-value="
                                    emit(
                                        'update-grouping',
                                        product,
                                        $event as string,
                                    )
                                "
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
                        </td>
                        <td
                            class="p-2 align-middle text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{
                                product.package_type ??
                                t(
                                    'planogram-templates.product_table.empty_value',
                                )
                            }}
                        </td>
                        <td
                            class="p-2 align-middle text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{
                                product.package_content ??
                                t(
                                    'planogram-templates.product_table.empty_value',
                                )
                            }}
                        </td>
                        <td
                            class="p-2 align-middle font-mono text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{
                                productDimensions(product) ??
                                t(
                                    'planogram-templates.product_table.empty_value',
                                )
                            }}
                        </td>
                        <td class="p-2 align-middle whitespace-nowrap">
                            <Badge
                                :variant="
                                    product.product_id ? 'default' : 'secondary'
                                "
                                class="text-[10px]"
                            >
                                {{
                                    product.product_id
                                        ? t(
                                              'planogram-templates.product_table.status_in_mix',
                                          )
                                        : t(
                                              'planogram-templates.product_table.status_out_of_mix',
                                          )
                                }}
                            </Badge>
                        </td>
                        <td class="p-2 align-middle whitespace-nowrap">
                            <button
                                type="button"
                                class="rounded p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                @click="emit('remove-product', product)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
