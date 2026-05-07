<script setup lang="ts">
import { CalendarIcon, ClockIcon } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';

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
const { t } = useT();

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
            <h3 class="text-lg font-semibold">{{ t('plannerate.form.step6.title') }}</h3>
            <p class="text-sm text-muted-foreground">
                {{ t('plannerate.form.step6.description') }}
            </p>
        </div>

        <!-- Auto Start Workflow -->
        <div class="flex items-center justify-between space-x-2 rounded-lg border p-4">
            <div class="space-y-0.5">
                <Label class="text-base">{{ t('plannerate.form.step6.auto_start') }}</Label>
                <p class="text-sm text-muted-foreground">
                    {{ t('plannerate.form.step6.auto_start_hint') }}
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
                    {{ t('plannerate.form.step6.start_date_optional') }}
                </Label>
                <Input
                    id="startDate"
                    type="datetime-local"
                    :model-value="props.modelValue.startDate || ''"
                    @update:model-value="(val) => updateField('startDate', val)"
                    :placeholder="t('plannerate.form.step6.start_date_placeholder')"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('plannerate.form.step6.start_date_hint') }}
                </p>
                <p v-if="errors.startDate" class="text-sm text-destructive">
                    {{ errors.startDate }}
                </p>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
                <Label for="workflowNotes" class="flex items-center gap-2">
                    <ClockIcon class="size-4" />
                    {{ t('plannerate.form.step6.notes_optional') }}
                </Label>
                <Textarea
                    id="workflowNotes"
                    :model-value="props.modelValue.notes"
                    @update:model-value="(val) => updateField('notes', val)"
                    :placeholder="t('plannerate.form.step6.notes_placeholder')"
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
                    {{ t('plannerate.form.step6.how_it_works') }}
                </h4>
                <ul class="space-y-1 text-sm text-muted-foreground">
                    <li>• {{ t('plannerate.form.step6.list_1') }}</li>
                    <li>• {{ t('plannerate.form.step6.list_2') }}</li>
                    <li>• {{ t('plannerate.form.step6.list_3') }}</li>
                    <li>• {{ t('plannerate.form.step6.list_4') }}</li>
                    <li>• {{ t('plannerate.form.step6.list_5') }}</li>
                </ul>
            </div>
        </template>
    </div>
</template>
