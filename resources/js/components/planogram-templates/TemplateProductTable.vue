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
                {{ products.length }} produto{{ products.length !== 1 ? 's' : '' }} no template
            </p>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="emit('download-template')">
                    <Download class="size-3.5" />
                    Baixar modelo
                </Button>
                <label class="cursor-pointer">
                    <Button variant="outline" size="sm" as="span">
                        <Upload class="size-3.5" />
                        Importar planilha
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
        <div class="flex-1 overflow-auto rounded-md border border-border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-32 font-mono text-xs">EAN</TableHead>
                        <TableHead>Descrição</TableHead>
                        <TableHead class="w-28">Marca</TableHead>
                        <TableHead class="w-44">Agrupamento</TableHead>
                        <TableHead class="w-20">Embalagem</TableHead>
                        <TableHead class="w-20">Conteúdo</TableHead>
                        <TableHead class="w-24">Status</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="products.length === 0">
                        <TableCell colspan="8" class="py-10 text-center text-sm text-muted-foreground">
                            Nenhum produto adicionado ainda
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="product in products" :key="product.id">
                        <TableCell class="font-mono text-xs">{{ product.ean }}</TableCell>
                        <TableCell class="max-w-[14rem] truncate text-sm">{{ product.description }}</TableCell>
                        <TableCell class="text-sm">{{ product.brand }}</TableCell>
                        <TableCell>
                            <Select
                                :model-value="product.grouping"
                                @update:model-value="emit('update-grouping', product, $event)"
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
                        <TableCell class="text-xs text-muted-foreground">{{ product.package_type ?? '—' }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ product.package_content ?? '—' }}</TableCell>
                        <TableCell>
                            <Badge :variant="product.product_id ? 'default' : 'secondary'" class="text-[10px]">
                                {{ product.product_id ? 'No mix' : 'Fora do mix' }}
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
                </TableBody>
            </Table>
        </div>
    </div>
</template>
