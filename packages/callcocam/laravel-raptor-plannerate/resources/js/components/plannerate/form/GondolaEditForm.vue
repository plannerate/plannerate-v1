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
} from '@/composables/plannerate/fields/useGondolaFields';
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useT } from '@/composables/useT';
import type { Gondola } from '@/types/planogram';
import SectionShelfBulkUpdate from './SectionShelfBulkUpdate.vue';

// ============================================================================
// COMPOSABLES
// ============================================================================

const editor = usePlanogramEditor();
const { t } = useT();

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
            <h3 class="text-lg font-semibold">{{ t('plannerate.form.gondola_edit.title') }}</h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.form.gondola_edit.description') }}
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 space-y-6 overflow-y-auto p-4">
                <!-- Nome -->
                <div class="space-y-2">
                <Label for="gondola-name">{{ t('plannerate.print.product_detail.name') }}</Label>
                <Input
                    id="gondola-name"
                    v-model="formData.name"
                    :placeholder="t('plannerate.form.gondola_edit.name_placeholder')"
                />
            </div>

            <Separator />

            <div class="grid grid-cols-2 gap-4">
                <!-- Localização -->
                <div class="space-y-2">
                    <Label for="gondola-location">{{ t('plannerate.form.gondola_edit.location') }}</Label>
                    <Input
                        id="gondola-location"
                        v-model="formData.location"
                        :placeholder="t('plannerate.form.gondola_edit.location_placeholder')"
                    />
                </div>

                <!-- Escala Padrão -->
                <div class="space-y-2">
                    <Label for="gondola-scale">{{ t('plannerate.form.gondola_edit.scale') }}</Label>
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
                        {{ t('plannerate.form.gondola_edit.scale_hint') }}
                    </p>
                </div>
            </div>

            <Separator />

            <!-- Número de Módulos -->
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="gondola-modules">{{ t('plannerate.form.gondola_edit.modules') }}</Label>
                    <Input
                        id="gondola-modules"
                        v-model.number="formData.num_modulos"
                        type="number"
                        min="0"
                        placeholder="0"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.form.gondola_edit.modules_hint') }}
                    </p>
                </div>

                <!-- Lado -->
                <div class="space-y-2">
                    <Label for="gondola-side">{{ t('plannerate.form.gondola_edit.side') }}</Label>
                    <Select v-model="formData.side">
                        <SelectTrigger id="gondola-side" class="h-9 w-full">
                            <SelectValue :placeholder="t('plannerate.form.gondola_edit.select_side')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="A">{{ t('plannerate.form.gondola_edit.side_a') }}</SelectItem>
                            <SelectItem value="B">{{ t('plannerate.form.gondola_edit.side_b') }}</SelectItem>
                            <SelectItem value="both">{{ t('plannerate.form.gondola_edit.side_both') }}</SelectItem>
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
                        >{{ t('plannerate.form.gondola_edit.alignment') }}</Label
                    >
                    <Select v-model="formData.alignment">
                        <SelectTrigger
                            id="gondola-alignment"
                            class="h-9 w-full"
                        >
                            <SelectValue
                                :placeholder="t('plannerate.form.gondola_edit.select_alignment')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="left">{{ t('plannerate.form.gondola_edit.left') }}</SelectItem>
                            <SelectItem value="center">{{ t('plannerate.form.gondola_edit.center') }}</SelectItem>
                            <SelectItem value="right">{{ t('plannerate.form.gondola_edit.right') }}</SelectItem>
                            <SelectItem value="justify">{{ t('plannerate.form.gondola_edit.justify') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.form.gondola_edit.alignment_hint') }}
                    </p>
                </div>

                <!-- Fluxo -->
                <div class="space-y-2">
                    <Label for="gondola-flow">{{ t('plannerate.form.gondola_edit.flow') }}</Label>
                    <Select v-model="formData.flow">
                        <SelectTrigger id="gondola-flow" class="h-9 w-full">
                            <SelectValue :placeholder="t('plannerate.form.gondola_edit.select_flow')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="left_to_right">
                                {{ t('plannerate.form.gondola_edit.flow_left_to_right') }}
                            </SelectItem>
                            <SelectItem value="right_to_left">
                                {{ t('plannerate.form.gondola_edit.flow_right_to_left') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.form.gondola_edit.flow_hint') }}
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
                    {{ t('plannerate.common.cancel') }}
                </Button>
                <Button class="flex-1" @click="handleSave">
                    <Check class="mr-2 size-4" />
                    {{ t('plannerate.toolbar.save') }}
                </Button>
            </div>
        </div>
    </div>
</template>
