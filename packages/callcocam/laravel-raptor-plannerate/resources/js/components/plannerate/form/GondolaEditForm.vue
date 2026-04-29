<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
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
    DEFAULT_GONDOLA_FIELDS,
    toSnakeCase,
} from '@/composables/plannerate/useGondolaFields';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import type { Gondola } from '@/types/planogram';
import SectionShelfBulkUpdate from './SectionShelfBulkUpdate.vue';

// ============================================================================
// COMPOSABLES
// ============================================================================

const editor = usePlanogramEditor();

// ============================================================================
// EMITS
// ============================================================================

const emit = defineEmits<{
    close: [];
    save: [gondola: Partial<Gondola>];
}>();

// ============================================================================
// STATE
// ============================================================================

// Inicializa formData com valores padrão do composable
const getDefaultFormData = () => {
    const defaults = toSnakeCase(DEFAULT_GONDOLA_FIELDS);

    return {
        name: '',
        num_modulos: defaults.num_modulos ?? 0,
        side: defaults.side ?? 'A',
        alignment: (defaults.alignment ?? 'left') as
            | 'left'
            | 'right'
            | 'center'
            | 'justify',
        flow:
            defaults.flow ??
            ('left_to_right' as 'left_to_right' | 'right_to_left'),
        location: defaults.location ?? '',
        scale_factor: defaults.scale_factor ?? 3,
        updated_at: new Date().toISOString(),
    };
};

const formData = reactive(getDefaultFormData());

// ============================================================================
// COMPUTED
// ============================================================================

const currentGondola = computed(() => editor.currentGondola.value);

// ============================================================================
// WATCHERS
// ============================================================================

// Carrega dados da gôndola atual no formulário usando composable
watch(
    currentGondola,
    (gondola) => {
        if (gondola) {
            // Usa valores padrão do composable como fallback
            const defaults = toSnakeCase(DEFAULT_GONDOLA_FIELDS);

            formData.name = gondola.name || '';
            formData.num_modulos =
                gondola.num_modulos ?? defaults.num_modulos ?? 0;
            formData.side = gondola.side || defaults.side || 'A';
            formData.alignment = (gondola.alignment ||
                defaults.alignment ||
                'left') as 'left' | 'right' | 'center' | 'justify';
            formData.flow = (gondola.flow ||
                defaults.flow ||
                'left_to_right') as 'left_to_right' | 'right_to_left';
            formData.location = gondola.location || defaults.location || '';
            formData.scale_factor =
                gondola.scale_factor ?? defaults.scale_factor ?? 3;
            formData.updated_at = new Date().toISOString();
        }
    },
    { immediate: true },
);

// ============================================================================
// METHODS
// ============================================================================

function handleSave() {
    if (!currentGondola.value?.id) {
        console.error('❌ Nenhuma gôndola selecionada');

        return;
    }

    // Atualiza gôndola via composable (commit otimista + auto-save)
    const updated = editor.updateGondola({
        name: formData.name,
        num_modulos: formData.num_modulos,
        side: formData.side,
        alignment: formData.alignment,
        flow: formData.flow,
        location: formData.location,
        scale_factor: formData.scale_factor,
        updated_at: formData.updated_at,
    });

    if (updated) {
        emit('save', formData);
        emit('close');
    } else {
        console.error('❌ Erro ao atualizar gôndola');
    }
}

function handleCancel() {
    // Recarrega dados originais usando composable
    if (currentGondola.value) {
        const defaults = toSnakeCase(DEFAULT_GONDOLA_FIELDS);
        const gondola = currentGondola.value;

        formData.name = gondola.name || '';
        formData.num_modulos = gondola.num_modulos ?? defaults.num_modulos ?? 0;
        formData.side = gondola.side || defaults.side || 'A';
        formData.alignment = (gondola.alignment ||
            defaults.alignment ||
            'left') as 'left' | 'right' | 'center' | 'justify';
        formData.flow = (gondola.flow || defaults.flow || 'left_to_right') as
            | 'left_to_right'
            | 'right_to_left';
        formData.location = gondola.location || defaults.location || '';
        formData.scale_factor =
            gondola.scale_factor ?? defaults.scale_factor ?? 3;
        formData.updated_at = new Date().toISOString();
    }

    emit('close');
}
</script>

<template>
    <div class="relative z-[9999] flex h-full flex-col">
        <!-- Header -->
        <div class="border-b p-4">
            <h3 class="text-lg font-semibold">Editar Gôndola</h3>
            <p class="text-sm text-muted-foreground">
                Configure as propriedades da gôndola
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 space-y-6 overflow-y-auto p-4">
            <!-- Nome -->
            <div class="space-y-2">
                <Label for="gondola-name">Nome</Label>
                <Input
                    id="gondola-name"
                    v-model="formData.name"
                    placeholder="Digite o nome da gôndola"
                />
            </div>

            <Separator />

            <div class="grid grid-cols-2 gap-4">
                <!-- Localização -->
                <div class="space-y-2">
                    <Label for="gondola-location">Localização</Label>
                    <Input
                        id="gondola-location"
                        v-model="formData.location"
                        placeholder="Ex: Corredor 1, Parede Direita"
                    />
                </div>

                <!-- Escala Padrão -->
                <div class="space-y-2">
                    <Label for="gondola-scale">Escala/Zoom Padrão</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="gondola-scale"
                            v-model.number="formData.scale_factor"
                            type="number"
                            min="1"
                            max="10"
                            step="0.5"
                        />
                        <span class="text-sm text-muted-foreground">
                            {{ formData.scale_factor.toFixed(1) }}x
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Fator de escala inicial ao abrir a gôndola (1.0 a 10.0)
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Número de Módulos -->
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="gondola-modules">Número de Módulos</Label>
                    <Input
                        id="gondola-modules"
                        v-model.number="formData.num_modulos"
                        type="number"
                        min="0"
                        placeholder="0"
                    />
                    <p class="text-xs text-muted-foreground">
                        Quantidade de módulos/sections na gôndola
                    </p>
                </div>

                <!-- Lado -->
                <div class="space-y-2">
                    <Label for="gondola-side">Lado</Label>
                    <Select v-model="formData.side">
                        <SelectTrigger id="gondola-side" class="h-9 w-full">
                            <SelectValue placeholder="Selecione o lado" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="A">Lado A</SelectItem>
                            <SelectItem value="B">Lado B</SelectItem>
                            <SelectItem value="both">Ambos os lados</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <Separator />

            <!-- Alinhamento e Fluxo -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Alinhamento -->
                <div class="space-y-2">
                    <Label for="gondola-alignment"
                        >Alinhamento de Produtos</Label
                    >
                    <Select v-model="formData.alignment">
                        <SelectTrigger
                            id="gondola-alignment"
                            class="h-9 w-full"
                        >
                            <SelectValue
                                placeholder="Selecione o alinhamento"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="left">Esquerda</SelectItem>
                            <SelectItem value="center">Centro</SelectItem>
                            <SelectItem value="right">Direita</SelectItem>
                            <SelectItem value="justify">Justificado</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Como os produtos serão alinhados nas prateleiras
                    </p>
                </div>

                <!-- Fluxo -->
                <div class="space-y-2">
                    <Label for="gondola-flow">Direção do Fluxo</Label>
                    <Select v-model="formData.flow">
                        <SelectTrigger id="gondola-flow" class="h-9 w-full">
                            <SelectValue placeholder="Selecione a direção" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="left_to_right">
                                Esquerda → Direita
                            </SelectItem>
                            <SelectItem value="right_to_left">
                                Direita → Esquerda
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Onde posicionar produtos premium (início do fluxo)
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Bulk Section/Shelf Update -->
            <SectionShelfBulkUpdate />
        </div>

        <!-- Footer Actions -->
        <div class="border-t p-4">
            <div class="flex gap-2">
                <Button variant="outline" class="flex-1" @click="handleCancel">
                    <X class="mr-2 size-4" />
                    Cancelar
                </Button>
                <Button class="flex-1" @click="handleSave">
                    <Check class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>
        </div>
    </div>
</template>
