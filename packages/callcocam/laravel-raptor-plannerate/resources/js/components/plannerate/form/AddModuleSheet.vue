<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    getInitialSectionFields,
    toSnakeCase as sectionToSnakeCase,
} from '@/composables/plannerate/useSectionFields';
import {
    getInitialShelfFields,
    toSnakeCase as shelfToSnakeCase,
} from '@/composables/plannerate/useShelfFields';
import type { Section } from '@/types/planogram';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Props {
    open?: boolean;
    gondolaId?: string;
    gondolaHeight?: number;
    sections?: Section[];
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'success', section: any): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    gondolaId: '',
    gondolaHeight: 200,
    sections: () => [],
});

const emit = defineEmits<Emits>();

// Função para obter valores da última seção ou valores padrão usando composables
const getInitialFormData = () => {
    const lastSection =
        props.sections && props.sections.length > 0
            ? props.sections[props.sections.length - 1]
            : null;

    const lastShelf =
        lastSection?.shelves && lastSection.shelves.length > 0
            ? lastSection.shelves[0]
            : null;

    // Usa composables para obter campos iniciais
    const sectionIndex = props.sections?.length || 0;
    const sectionFields = getInitialSectionFields(
        null,
        lastSection || undefined,
        props.gondolaHeight,
        sectionIndex,
    );

    const shelfFields = getInitialShelfFields(null, lastShelf || undefined);

    // Converte para snake_case (formato do backend)
    const sectionSnake = sectionToSnakeCase(sectionFields);
    const shelfSnake = shelfToSnakeCase(shelfFields);

    return {
        // Dados da Seção
        name: sectionFields.name || `Módulo ${sectionIndex + 1}`,
        height: sectionSnake.height ?? props.gondolaHeight ?? 200,
        width: sectionSnake.width ?? 100,

        // Base
        base_height: sectionSnake.base_height ?? 20,
        base_width: sectionSnake.base_width ?? 100,
        base_depth: sectionSnake.base_depth ?? 50,

        // Cremalheira
        cremalheira_width: sectionSnake.cremalheira_width ?? 5,
        hole_height: sectionSnake.hole_height ?? 2,
        hole_width: sectionSnake.hole_width ?? 2,
        hole_spacing: sectionSnake.hole_spacing ?? 5,

        // Prateleiras Padrão
        shelf_height: shelfSnake.shelf_height ?? 2,
        shelf_width: shelfSnake.shelf_width ?? 100,
        shelf_depth: shelfSnake.shelf_depth ?? 50,
        num_shelves: sectionSnake.num_shelves ?? shelfSnake.num_shelves ?? 4,
        product_type: (shelfSnake.product_type || 'normal') as
            | 'normal'
            | 'hook',
    };
};

const form = useForm(getInitialFormData());

// Atualizar formulário quando o sheet abrir
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            const initialData = getInitialFormData();
            Object.assign(form, initialData);
            form.clearErrors();
        }
    },
);

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

const handleClose = () => {
    emit('update:open', false);
    form.reset();
    form.clearErrors();
};

const handleSubmit = () => {
    if (!props.gondolaId) {
        console.error('Gondola ID não fornecido');
        return;
    }

    form.post(`/api/editor/gondolas/${props.gondolaId}/sections`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: (/* _response */) => {
            emit('success', form.data());
            handleClose();
        },
    });
};
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent side="right" class="flex w-full flex-col md:max-w-4xl">
            <SheetHeader class="shrink-0 py-2">
                <SheetTitle>Adicionar Novo Módulo</SheetTitle>
                <SheetDescription>
                    Configure o novo módulo para a gôndola
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-1 flex-col gap-6 overflow-y-auto px-6">
                <!-- Mensagens de Erro -->
                <div v-if="hasErrors" class="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                    <p class="mb-2 font-medium text-destructive">
                        Por favor, corrija os seguintes erros:
                    </p>
                    <ul class="list-inside list-disc space-y-1 text-sm text-destructive/80">
                        <li v-for="(error, key) in form.errors" :key="key">
                            {{
                                Array.isArray(error) ? error.join(', ') : error
                            }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="handleSubmit" class="flex-1 space-y-6">
                    <!-- Informações Básicas -->
                    <div class="space-y-4">
                        <h3 class="font-medium">Informações do Módulo</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div class="space-y-2 md:col-span-2">
                                <Label for="section_name">Nome do Módulo *</Label>
                                <Input id="section_name" v-model="form.name" type="text" placeholder="Ex: Módulo 5"
                                    :class="{
                                        'border-red-500': form.errors.name,
                                    }" />
                                <p v-if="form.errors.name" class="text-xs text-red-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="width">Altura (cm) *</Label>
                                <Input id="width" v-model.number="form.height" type="number" min="1" step="any" :class="{
                                    'border-red-500': form.errors.height,
                                }" />
                                <p v-if="form.errors.height" class="text-xs text-red-500">
                                    {{ form.errors.height }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="width">Largura (cm) *</Label>
                                <Input id="width" v-model.number="form.width" type="number" min="1" step="any" :class="{
                                    'border-red-500': form.errors.width,
                                }" />
                                <p v-if="form.errors.width" class="text-xs text-red-500">
                                    {{ form.errors.width }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Base -->
                    <div class="space-y-4">
                        <h3 class="font-medium">Base</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="base_height">Altura (cm) *</Label>
                                <Input id="base_height" v-model.number="form.base_height" type="number" min="1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.base_height,
                                    }" />
                                <p v-if="form.errors.base_height" class="text-xs text-red-500">
                                    {{ form.errors.base_height }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="base_width">Largura (cm) *</Label>
                                <Input id="base_width" v-model.number="form.base_width" type="number" min="1" step="any"
                                    :class="{
                                        'border-red-500':
                                            form.errors.base_width,
                                    }" />
                                <p v-if="form.errors.base_width" class="text-xs text-red-500">
                                    {{ form.errors.base_width }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="base_depth">Profundidade (cm) *</Label>
                                <Input id="base_depth" v-model.number="form.base_depth" type="number" min="1" step="any"
                                    :class="{
                                        'border-red-500':
                                            form.errors.base_depth,
                                    }" />
                                <p v-if="form.errors.base_depth" class="text-xs text-red-500">
                                    {{ form.errors.base_depth }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Cremalheira -->
                    <div class="space-y-4">
                        <h3 class="font-medium">Cremalheira</h3>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div class="space-y-2">
                                <Label for="cremalheira_width">Largura (cm) *</Label>
                                <Input id="cremalheira_width" v-model.number="form.cremalheira_width" type="number"
                                    min="1" step="any" :class="{
                                        'border-red-500':
                                            form.errors.cremalheira_width,
                                    }" />
                                <p v-if="form.errors.cremalheira_width" class="text-xs text-red-500">
                                    {{ form.errors.cremalheira_width }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="hole_height">Altura Furo (cm) *</Label>
                                <Input id="hole_height" v-model.number="form.hole_height" type="number" min="0.1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.hole_height,
                                    }" />
                                <p v-if="form.errors.hole_height" class="text-xs text-red-500">
                                    {{ form.errors.hole_height }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="hole_width">Largura Furo (cm) *</Label>
                                <Input id="hole_width" v-model.number="form.hole_width" type="number" min="0.1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.hole_width,
                                    }" />
                                <p v-if="form.errors.hole_width" class="text-xs text-red-500">
                                    {{ form.errors.hole_width }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="hole_spacing">Espaçamento (cm) *</Label>
                                <Input id="hole_spacing" v-model.number="form.hole_spacing" type="number" min="0.1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.hole_spacing,
                                    }" />
                                <p v-if="form.errors.hole_spacing" class="text-xs text-red-500">
                                    {{ form.errors.hole_spacing }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Prateleiras -->
                    <div class="space-y-4">
                        <h3 class="font-medium">Prateleiras Padrão</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div class="space-y-2">
                                <Label for="shelf_height">Espessura (cm) *</Label>
                                <Input id="shelf_height" v-model.number="form.shelf_height" type="number" min="1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.shelf_height,
                                    }" />
                                <p v-if="form.errors.shelf_height" class="text-xs text-red-500">
                                    {{ form.errors.shelf_height }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="shelf_width">Largura (cm) *</Label>
                                <Input id="shelf_width" v-model.number="form.shelf_width" type="number" min="1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.shelf_width,
                                    }" />
                                <p v-if="form.errors.shelf_width" class="text-xs text-red-500">
                                    {{ form.errors.shelf_width }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="shelf_depth">Profundidade (cm) *</Label>
                                <Input id="shelf_depth" v-model.number="form.shelf_depth" type="number" min="1"
                                    step="any" :class="{
                                        'border-red-500':
                                            form.errors.shelf_depth,
                                    }" />
                                <p v-if="form.errors.shelf_depth" class="text-xs text-red-500">
                                    {{ form.errors.shelf_depth }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="num_shelves">Nº de Prateleiras *</Label>
                                <Input id="num_shelves" v-model.number="form.num_shelves" type="number" min="0" :class="{
                                    'border-red-500':
                                        form.errors.num_shelves,
                                }" />
                                <p v-if="form.errors.num_shelves" class="text-xs text-red-500">
                                    {{ form.errors.num_shelves }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="product_type">Tipo de Produto Padrão *</Label>
                            <Select v-model="form.product_type">
                                <SelectTrigger id="product_type">
                                    <SelectValue placeholder="Selecione o tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="normal">Normal</SelectItem>
                                    <SelectItem value="hook">Gancheira</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.product_type" class="text-xs text-red-500">
                                {{ form.errors.product_type }}
                            </p>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="flex shrink-0 justify-end gap-2 border-t py-4">
                        <Button type="button" variant="outline" @click="handleClose" :disabled="form.processing">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing || !form.name">
                            {{
                                form.processing
                                    ? 'Adicionando...'
                                    : 'Adicionar Módulo'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </SheetContent>
    </Sheet>
</template>
