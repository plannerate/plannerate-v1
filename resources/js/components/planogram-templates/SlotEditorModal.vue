<script setup lang="ts">
import { reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { PlanogramTemplateSlot } from './types';

type SlotDraft = Omit<PlanogramTemplateSlot, 'id' | 'subtemplate_id' | 'grouping_normalized' | 'ordering'>;

const props = defineProps<{
    open: boolean;
    moduleNumber: number;
    shelfOrder: number;
    slot?: PlanogramTemplateSlot | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [slot: SlotDraft];
}>();

const draft = reactive<SlotDraft>({
    module_number: props.moduleNumber,
    shelf_order: props.shelfOrder,
    grouping: '',
    category: '',
    subcategory: '',
    min_facings: 1,
    priority: 1,
    price_order: 'none',
    size_order: 'none',
    brand_exposure: 'horizontal',
    flavor_exposure: 'horizontal',
    space_fallback: 'reduce_c',
    use_target_stock: false,
});

watch(
    () => [props.open, props.slot, props.moduleNumber, props.shelfOrder] as const,
    ([open, slot, module, shelf]) => {
        if (!open) return;
        draft.module_number = module;
        draft.shelf_order = shelf;
        draft.grouping = slot?.grouping ?? '';
        draft.category = slot?.category ?? '';
        draft.subcategory = slot?.subcategory ?? '';
        draft.min_facings = slot?.min_facings ?? 1;
        draft.priority = slot?.priority ?? 1;
        draft.price_order = slot?.price_order ?? 'none';
        draft.size_order = slot?.size_order ?? 'none';
        draft.brand_exposure = slot?.brand_exposure ?? 'horizontal';
        draft.flavor_exposure = slot?.flavor_exposure ?? 'horizontal';
        draft.space_fallback = slot?.space_fallback ?? 'reduce_c';
        draft.use_target_stock = slot?.use_target_stock ?? false;
    },
    { immediate: true },
);

function saveSlot(): void {
    if (!draft.grouping.trim()) return;
    emit('save', { ...draft });
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Configurar slot — Módulo #{{ moduleNumber }}, Prat #{{ shelfOrder }}</DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <!-- Grouping -->
                <div class="grid gap-1.5">
                    <Label for="slot-grouping">Agrupamento de exposição *</Label>
                    <Input
                        id="slot-grouping"
                        v-model="draft.grouping"
                        placeholder="Ex: LAVA ROUPAS PÓ PACOTE"
                    />
                    <p class="text-xs text-muted-foreground">Chave que vincula o slot aos produtos</p>
                </div>

                <!-- Categoria / Subcategoria -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="slot-category">Categoria</Label>
                        <Input id="slot-category" v-model="draft.category" placeholder="Ex: LAVA ROUPAS" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="slot-subcategory">Subcategoria</Label>
                        <Input id="slot-subcategory" v-model="draft.subcategory" placeholder="Ex: PÓ PACOTE" />
                    </div>
                </div>

                <!-- Min facings / Prioridade -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="slot-min-facings">Frentes mínimas</Label>
                        <Input
                            id="slot-min-facings"
                            v-model.number="draft.min_facings"
                            type="number"
                            :min="1"
                            :max="20"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="slot-priority">Prioridade</Label>
                        <Input
                            id="slot-priority"
                            v-model.number="draft.priority"
                            type="number"
                            :min="1"
                            :max="10"
                        />
                        <p class="text-xs text-muted-foreground">1 = mais importante</p>
                    </div>
                </div>

                <!-- Ordem por preço / tamanho -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Ordem por preço</Label>
                        <Select v-model="draft.price_order">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sem ordenação</SelectItem>
                                <SelectItem value="asc">Mais barato primeiro</SelectItem>
                                <SelectItem value="desc">Mais caro primeiro</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Ordem por tamanho</Label>
                        <Select v-model="draft.size_order">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sem ordenação</SelectItem>
                                <SelectItem value="asc">Menor primeiro</SelectItem>
                                <SelectItem value="desc">Maior primeiro</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Exposição marca / fragrância -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Exposição por marca</Label>
                        <Select v-model="draft.brand_exposure">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="vertical">Vertical</SelectItem>
                                <SelectItem value="horizontal">Horizontal</SelectItem>
                                <SelectItem value="mixed">Misto</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Exposição por fragrância</Label>
                        <Select v-model="draft.flavor_exposure">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="vertical">Vertical</SelectItem>
                                <SelectItem value="horizontal">Horizontal</SelectItem>
                                <SelectItem value="mixed">Misto</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Se faltar espaço / Estoque alvo -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Se faltar espaço</Label>
                        <Select v-model="draft.space_fallback">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="reduce_c">Remover curva C primeiro</SelectItem>
                                <SelectItem value="reduce_facings">Reduzir frentes para 1</SelectItem>
                                <SelectItem value="skip">Deixar incompleto</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end gap-3 pb-1">
                        <Switch
                            id="slot-target-stock"
                            :checked="draft.use_target_stock"
                            @update:checked="draft.use_target_stock = $event"
                        />
                        <Label for="slot-target-stock" class="cursor-pointer">Usar estoque alvo</Label>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">Cancelar</Button>
                <Button :disabled="!draft.grouping.trim()" @click="saveSlot">Salvar slot</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
