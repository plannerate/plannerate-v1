<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Loader2,
    UploadCloud,
    PackageX,
    Replace,
    Maximize,
    Box,
    SearchX,
    FileQuestion,
    MinusCircle,
    MoreHorizontal,
} from 'lucide-vue-next';
import { computed, ref, toRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';
import { executionRoutes } from '../routes';
import type { ExecutionDivergence } from '../types';
import { useExecutionStructure } from '../useExecutionStructure';

/**
 * Modal "Apontar divergência" (export §16–§19, mockup 2.png): tipo em chips com
 * ícone, localização (módulo/prateleira/posição) por selects derivados da
 * gôndola, busca de produto, fotos opcionais e tabela das divergências já
 * registradas com tratativa (justificar/resolver).
 */
const props = defineProps<{
    open: boolean;
    executionId: string;
    divergences: ExecutionDivergence[];
    sections: Section[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { t } = useT();
const { modules, shelvesFor, positionsFor, products, productName } = useExecutionStructure(toRef(props, 'sections'));

const NOTES_MAX = 500;
const MAX_PHOTOS = 3;

/** Tipos de divergência com ícone (export §16). */
const divergenceTypes = [
    { value: 'ruptura', icon: PackageX },
    { value: 'divergente', icon: Replace },
    { value: 'falta_espaco', icon: Maximize },
    { value: 'embalagem_diferente', icon: Box },
    { value: 'nao_localizado', icon: SearchX },
    { value: 'sem_cadastro', icon: FileQuestion },
    { value: 'quantidade_insuficiente', icon: MinusCircle },
    { value: 'outro', icon: MoreHorizontal },
] as const;

const type = ref<string>('ruptura');
const moduleValue = ref<string>('');
const shelfValue = ref<string>('');
const positionValue = ref<string>('');
const productId = ref<string>('');
const productQuery = ref<string>('');
const showProductList = ref(false);
const notes = ref('');
const photos = ref<File[]>([]);
const saving = ref(false);

const shelves = computed(() => shelvesFor(moduleValue.value || null));
const positions = computed(() => positionsFor(moduleValue.value || null, shelfValue.value || null));

// Trocar o módulo invalida a prateleira/posição; trocar a prateleira invalida a posição.
watch(moduleValue, () => {
    shelfValue.value = '';
    positionValue.value = '';
});
watch(shelfValue, () => {
    positionValue.value = '';
});

/** Produtos filtrados pela busca (código, nome ou EAN). */
const filteredProducts = computed(() => {
    const query = productQuery.value.trim().toLowerCase();

    if (!query) {
        return products.value.slice(0, 20);
    }

    return products.value
        .filter((product) =>
            [product.name, product.ean, product.codigo_erp]
                .filter(Boolean)
                .some((field) => String(field).toLowerCase().includes(query)),
        )
        .slice(0, 20);
});

const canSave = computed(() => !!type.value && !saving.value);

function selectProduct(id: string, name: string): void {
    productId.value = id;
    productQuery.value = name;
    showProductList.value = false;
}

function onPhotos(event: Event): void {
    const incoming = (event.target as HTMLInputElement).files;
    photos.value = incoming ? Array.from(incoming).slice(0, MAX_PHOTOS) : [];
}

function moduleLabel(): string | null {
    return modules.value.find((option) => option.value === moduleValue.value)?.label ?? null;
}

function shelfLabel(): string | null {
    return shelves.value.find((option) => option.value === shelfValue.value)?.label ?? null;
}

function statusClass(status: string | null): string {
    switch (status) {
        case 'resolvida':
            return 'bg-emerald-100 text-emerald-700';
        case 'justificada':
            return 'bg-blue-100 text-blue-700';
        case 'rejeitada':
            return 'bg-slate-200 text-slate-600';
        case 'em_analise':
            return 'bg-amber-100 text-amber-700';
        default:
            return 'bg-red-100 text-red-700';
    }
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('pt-BR') : '—';
}

/**
 * Salva a divergência. `keepOpen` mantém o modal aberto ("Salvar e continuar").
 */
function save(keepOpen: boolean): void {
    if (!canSave.value) {
        return;
    }

    saving.value = true;

    router.post(
        executionRoutes.divergenceStore(props.executionId),
        {
            type: type.value,
            module_label: moduleLabel(),
            shelf_label: shelfLabel(),
            position_label: positionValue.value || null,
            product_id: productId.value || null,
            notes: notes.value || null,
            photos: photos.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            only: ['execution'],
            onSuccess: () => {
                resetForm();

                if (!keepOpen) {
                    emit('update:open', false);
                }
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

/** Atualiza o estado de uma divergência existente. */
function updateStatus(divergence: ExecutionDivergence, status: string): void {
    router.patch(
        executionRoutes.divergenceUpdate(props.executionId, divergence.id),
        { status },
        { preserveScroll: true, preserveState: true, only: ['execution'] },
    );
}

function resetForm(): void {
    type.value = 'ruptura';
    moduleValue.value = '';
    shelfValue.value = '';
    positionValue.value = '';
    productId.value = '';
    productQuery.value = '';
    notes.value = '';
    photos.value = [];
}

function close(): void {
    if (saving.value) {
        return;
    }

    resetForm();
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (value ? null : close())">
        <DialogContent class="force-light z-[1000] flex max-h-[92vh] flex-col sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ t('plannerate.execution.divergence.title') }}</DialogTitle>
                <DialogDescription>{{ t('plannerate.execution.divergence.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto pr-1">
                <!-- Tipo (chips com ícone) -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.type') }}</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="option in divergenceTypes"
                            :key="option.value"
                            type="button"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-left text-xs font-medium transition"
                            :class="type === option.value
                                ? 'border-red-400 bg-red-50 text-red-700'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            @click="type = option.value"
                        >
                            <component :is="option.icon" class="size-4 shrink-0" />
                            {{ t(`plannerate.execution.divergence.types.${option.value}`) }}
                        </button>
                    </div>
                </div>

                <!-- Módulo / Prateleira / Posição -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.module') }}</label>
                        <select v-model="moduleValue" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-emerald-500/60">
                            <option value="">{{ t('plannerate.execution.divergence.select_module') }}</option>
                            <option v-for="option in modules" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.shelf') }}</label>
                        <select v-model="shelfValue" :disabled="!moduleValue" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-emerald-500/60 disabled:bg-slate-100">
                            <option value="">{{ t('plannerate.execution.divergence.select_shelf') }}</option>
                            <option v-for="option in shelves" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.position') }}</label>
                        <select v-model="positionValue" :disabled="!shelfValue" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm outline-none focus:border-emerald-500/60 disabled:bg-slate-100">
                            <option value="">{{ t('plannerate.execution.divergence.select_position') }}</option>
                            <option v-for="option in positions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- Produto (busca) -->
                <div class="relative space-y-1">
                    <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.product') }}</label>
                    <input
                        v-model="productQuery"
                        type="text"
                        :placeholder="t('plannerate.execution.divergence.product_search')"
                        class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-500/60"
                        @focus="showProductList = true"
                        @input="showProductList = true; productId = ''"
                    />
                    <ul
                        v-if="showProductList && filteredProducts.length"
                        class="absolute z-10 mt-1 max-h-44 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                    >
                        <li
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="cursor-pointer px-3 py-1.5 text-sm hover:bg-emerald-50"
                            @click="selectProduct(product.id, product.name)"
                        >
                            <span class="font-medium text-slate-700">{{ product.name }}</span>
                            <span class="ml-2 text-xs text-slate-400">{{ product.ean ?? product.codigo_erp ?? '' }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Observação -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.notes') }}</label>
                        <span class="text-[10px] text-slate-400">{{ notes.length }}/{{ NOTES_MAX }}</span>
                    </div>
                    <Textarea v-model="notes" rows="2" :maxlength="NOTES_MAX" :placeholder="t('plannerate.execution.divergence.notes_placeholder')" />
                </div>

                <!-- Fotos -->
                <div class="space-y-1">
                    <label class="text-xs font-medium text-slate-600">{{ t('plannerate.execution.divergence.photos') }}</label>
                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-lg border-2 border-dashed border-slate-300 p-4 text-center hover:border-emerald-400/50">
                        <UploadCloud class="size-6 text-slate-400" />
                        <span class="text-sm text-slate-600">{{ t('plannerate.execution.divergence.photos_hint') }}</span>
                        <span class="text-xs text-slate-400">{{ t('plannerate.execution.divergence.photos_limits') }}</span>
                        <input type="file" accept="image/*" multiple class="sr-only" @change="onPhotos" />
                    </label>
                    <p v-if="photos.length" class="text-xs text-slate-500">
                        {{ t('plannerate.execution.divergence.photos_selected', { count: String(photos.length) }) }}
                    </p>
                </div>

                <!-- Tabela de divergências registradas -->
                <div class="space-y-1.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ t('plannerate.execution.divergence.registered') }}
                    </h4>
                    <p v-if="!divergences.length" class="text-sm text-slate-400">
                        {{ t('plannerate.execution.divergence.empty') }}
                    </p>
                    <div v-else class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th class="px-2 py-1.5">{{ t('plannerate.execution.divergence.col_datetime') }}</th>
                                    <th class="px-2 py-1.5">{{ t('plannerate.execution.divergence.col_description') }}</th>
                                    <th class="px-2 py-1.5">{{ t('plannerate.execution.divergence.col_product') }}</th>
                                    <th class="px-2 py-1.5">{{ t('plannerate.execution.divergence.col_location') }}</th>
                                    <th class="px-2 py-1.5">{{ t('plannerate.execution.divergence.col_status') }}</th>
                                    <th class="px-2 py-1.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="divergence in divergences" :key="divergence.id">
                                    <td class="px-2 py-1.5 text-slate-500">{{ formatDate(divergence.created_at) }}</td>
                                    <td class="px-2 py-1.5">
                                        <span class="font-medium text-slate-700">
                                            {{ t(`plannerate.execution.divergence.types.${divergence.type}`) }}
                                        </span>
                                        <span v-if="divergence.notes" class="block text-slate-400">{{ divergence.notes }}</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-slate-600">{{ productName(divergence.product_id) ?? '—' }}</td>
                                    <td class="px-2 py-1.5 text-slate-600">
                                        {{ [divergence.module_label, divergence.shelf_label].filter(Boolean).join(' / ') || '—' }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusClass(divergence.status)">
                                            {{ t(`plannerate.execution.divergence.status.${divergence.status}`) }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <div v-if="divergence.status === 'aberta' || divergence.status === 'em_analise'" class="flex gap-1">
                                            <button class="text-[10px] font-medium text-blue-600 hover:underline" @click="updateStatus(divergence, 'justificada')">
                                                {{ t('plannerate.execution.divergence.justify') }}
                                            </button>
                                            <button class="text-[10px] font-medium text-emerald-600 hover:underline" @click="updateStatus(divergence, 'resolvida')">
                                                {{ t('plannerate.execution.divergence.resolve') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="saving" @click="close">
                    {{ t('plannerate.execution.common.cancel') }}
                </Button>
                <Button variant="outline" :disabled="!canSave" @click="save(true)">
                    <Loader2 v-if="saving" class="mr-1.5 size-4 animate-spin" />
                    {{ t('plannerate.execution.divergence.save_continue') }}
                </Button>
                <Button class="bg-red-600 hover:bg-red-700" :disabled="!canSave" @click="save(false)">
                    {{ t('plannerate.execution.divergence.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
