<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { CalendarIcon, ClockIcon } from 'lucide-vue-next';

interface Props {
    modelValue: {
        autoStartWorkflow: boolean;
        startDate: string | null;
        notes: string;
    };
    errors?: Record<string, string>;
}

interface Emits {
    (e: 'update:modelValue', value: Props['modelValue']): void;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
});

const emit = defineEmits<Emits>();

const updateField = (field: keyof Props['modelValue'], value: any) => {
    emit('update:modelValue', {
        ...props.modelValue,
        [field]: value,
    });
};
</script>

<script lang="ts">
export const validate = (): boolean => {
    // Workflow é sempre válido (campos opcionais)
    return true;
};
</script> 

<template>
    <div class="space-y-6 py-4">
        <div class="space-y-2">
            <h3 class="text-lg font-semibold">Configuração de Workflow</h3>
            <p class="text-sm text-muted-foreground">
                Configure como o workflow será iniciado para esta gôndola
            </p>
        </div>

        <!-- Auto Start Workflow -->
        <div class="flex items-center justify-between space-x-2 rounded-lg border p-4">
            <div class="space-y-0.5">
                <Label class="text-base">Iniciar workflow automaticamente</Label>
                <p class="text-sm text-muted-foreground">
                    Inicia o workflow imediatamente com status "Em Progresso"
                </p>
            </div>
            <Switch
                :model-value="props.modelValue.autoStartWorkflow"
                @update:model-value="(val: boolean) => updateField('autoStartWorkflow', val)"
            />
        </div>

        <template v-if="props.modelValue.autoStartWorkflow">
            <!-- Start Date -->
            <div class="space-y-2">
                <Label for="startDate" class="flex items-center gap-2">
                    <CalendarIcon class="size-4" />
                    Data de início (opcional)
                </Label>
                <Input
                    id="startDate"
                    type="datetime-local"
                    :model-value="props.modelValue.startDate || ''"
                    @update:model-value="(val) => updateField('startDate', val)"
                    placeholder="Deixe vazio para iniciar imediatamente"
                />
                <p class="text-xs text-muted-foreground">
                    Se não informar, será considerado o momento da criação
                </p>
                <p v-if="errors.startDate" class="text-sm text-destructive">
                    {{ errors.startDate }}
                </p>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
                <Label for="workflowNotes" class="flex items-center gap-2">
                    <ClockIcon class="size-4" />
                    Observações sobre o workflow (opcional)
                </Label>
                <Textarea
                    id="workflowNotes"
                    :model-value="props.modelValue.notes"
                    @update:model-value="(val) => updateField('notes', val)"
                    placeholder="Ex: Projeto prioritário, atenção especial ao prazo, etc."
                    rows="3"
                />
                <p v-if="errors.notes" class="text-sm text-destructive">
                    {{ errors.notes }}
                </p>
            </div>

            <!-- Info Box -->
            <div class="rounded-lg bg-muted/50 p-4">
                <h4 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                    <ClockIcon class="size-4" />
                    Como funciona o workflow?
                </h4>
                <ul class="space-y-1 text-sm text-muted-foreground">
                    <li>• As etapas são criadas automaticamente baseadas no planograma</li>
                    <li>• O responsável é definido na configuração de cada etapa</li>
                    <li>• Cada etapa tem um prazo estimado configurado</li>
                    <li>• Você pode acompanhar o progresso no Kanban</li>
                    <li>• Notificações são enviadas quando houver atrasos</li>
                </ul>
            </div>
        </template>
    </div>
</template>
