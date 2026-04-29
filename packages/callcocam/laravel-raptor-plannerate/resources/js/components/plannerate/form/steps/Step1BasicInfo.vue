<script lang="ts">
export const validate = (data: {
    gondolaName: string;
    location: string;
    side: string;
    scaleFactor: number;
    flow: 'left_to_right' | 'right_to_left';
    status: string;
}): boolean => {
    // Usa validação do composable
    return validateGondolaFields(data);
};
</script>

<script setup lang="ts">
import { InfoIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { validateGondolaFields } from '@/composables/plannerate/useGondolaFields';

interface Props {
    modelValue: {
        gondolaName: string;
        location: string;
        side: string;
        scaleFactor: number;
        flow: 'left_to_right' | 'right_to_left';
        status: string;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

function updateField<K extends keyof Props['modelValue']>(
    key: K,
    value: Props['modelValue'][K],
): void {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
}

const setFlow = (flowValue: 'left_to_right' | 'right_to_left') => {
    updateField('flow', flowValue);
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <div class="rounded-full bg-primary/10 p-2">
                <InfoIcon class="h-5 w-5 text-primary" />
            </div>
            <h3 class="text-lg font-medium">Informações Básicas</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="gondolaName">Nome da Gôndola *</Label>
                <Input
                    id="gondolaName"
                    :model-value="props.modelValue.gondolaName"
                    @update:model-value="(val) => updateField('gondolaName', String(val ?? ''))"
                    required
                    :class="{
                        'border-red-500': errors?.gondolaName,
                    }"
                />
                <p v-if="errors?.gondolaName" class="text-xs text-red-500">
                    {{ errors.gondolaName }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="location">Localização Da Gôndola</Label>
                <Input
                    id="location"
                    :model-value="props.modelValue.location"
                    @update:model-value="(val) => updateField('location', String(val ?? ''))"
                    placeholder="Ex.: Corredor de Bebidas"
                    :class="{
                        'border-red-500': errors?.location,
                    }"
                />
                <p v-if="errors?.location" class="text-xs text-red-500">
                    {{ errors.location }}
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    Corredor onde a gôndola está localizada
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="side">Lado do Corredor *</Label>
                <Input
                    id="side"
                    :model-value="props.modelValue.side"
                    @update:model-value="(val) => updateField('side', String(val ?? ''))"
                    placeholder="Ex.: A, B ou 1, 2"
                    :class="{
                        'border-red-500': errors?.side,
                    }"
                />
                <p v-if="errors?.side" class="text-xs text-red-500">
                    {{ errors.side }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="scaleFactor">Fator de Escala *</Label>
                <Input
                    id="scaleFactor"
                    type="number"
                    :model-value="props.modelValue.scaleFactor"
                    @update:model-value="(val) => updateField('scaleFactor', Number(val))"
                    min="1"
                    :class="{
                        'border-red-500': errors?.scaleFactor,
                    }"
                />
                <p v-if="errors?.scaleFactor" class="text-xs text-red-500">
                    {{ errors.scaleFactor }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="status">Status</Label>
                <Select
                    :model-value="props.modelValue.status"
                    @update:model-value="(val) => updateField('status', String(val ?? 'draft'))"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Selecione o status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Status</SelectLabel>
                            <SelectItem value="published">Publicado</SelectItem>
                            <SelectItem value="draft">Rascunho</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="space-y-2">
            <Label>Posição do Fluxo *</Label>
            <div
                class="grid grid-cols-2 gap-2 rounded-md border"
                :class="{ 'border-red-500': errors?.flow }"
            >
                <Button
                    :variant="
                        props.modelValue.flow === 'left_to_right'
                            ? 'default'
                            : 'outline'
                    "
                    @click="setFlow('left_to_right')"
                    type="button"
                    class="justify-center rounded-r-none border-r"
                >
                    Esquerda para Direita
                </Button>
                <Button
                    :variant="
                        props.modelValue.flow === 'right_to_left'
                            ? 'default'
                            : 'outline'
                    "
                    @click="setFlow('right_to_left')"
                    type="button"
                    class="justify-center rounded-l-none"
                >
                    Direita para Esquerda
                </Button>
            </div>
            <p v-if="errors?.flow" class="text-xs text-red-500">
                {{ errors.flow }}
            </p>
        </div>
    </div>
</template>
