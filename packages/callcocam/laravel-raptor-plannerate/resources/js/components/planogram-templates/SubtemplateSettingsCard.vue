<script setup lang="ts">
import { Settings2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import FormSelectField from '@/components/form/FormSelectField.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import type { FlowDirection, LayoutOrientation, PlanogramSubtemplate, ZonePriority } from './types';
import { validateSubtemplateSettings  } from './validation';
import type {SubtemplateSettingsValidationErrors} from './validation';

/**
 * Seção "Configurações do subtemplate" — campos globais que valem para TODOS os
 * módulos do subtemplate (layout_orientation, flow_direction e zonas térmicas).
 * Editados aqui (e não no modal por módulo) para evitar a impressão enganosa de
 * que cada módulo tem o seu valor: salvar num módulo sobrescreveria o outro.
 */

export type SubtemplateSettingsDraft = {
    hot_zone_priority: ZonePriority | null;
    cold_zone_priority: ZonePriority | null;
    flow_direction: FlowDirection | null;
    layout_orientation: LayoutOrientation | null;
};

const props = defineProps<{
    subtemplate: PlanogramSubtemplate;
    busy?: boolean;
}>();

const emit = defineEmits<{
    save: [settings: SubtemplateSettingsDraft];
}>();

const { t } = useT();

const draft = reactive<SubtemplateSettingsDraft>({
    hot_zone_priority: null,
    cold_zone_priority: null,
    flow_direction: null,
    layout_orientation: null,
});

const errors = ref<SubtemplateSettingsValidationErrors>({});

function saveSettings(): void {
    const validationErrors = validateSubtemplateSettings(draft);

    if (Object.keys(validationErrors).length > 0) {
        errors.value = validationErrors;

        return;
    }

    errors.value = {};
    emit('save', { ...draft });
}

// Re-sincroniza o draft sempre que o subtemplate selecionado muda ou é recarregado
watch(
    () => props.subtemplate,
    (sub) => {
        draft.hot_zone_priority = sub.hot_zone_priority ?? null;
        draft.cold_zone_priority = sub.cold_zone_priority ?? null;
        draft.flow_direction = sub.flow_direction ?? null;
        draft.layout_orientation = sub.layout_orientation ?? null;
        errors.value = {};
    },
    { immediate: true },
);

// FormSelectField só aceita string — '' representa "sem critério" (null no draft)
const hotZoneModel = computed<string>({
    get: () => draft.hot_zone_priority ?? '',
    set: (value) => {
 draft.hot_zone_priority = (value || null) as ZonePriority | null; 
},
});

const coldZoneModel = computed<string>({
    get: () => draft.cold_zone_priority ?? '',
    set: (value) => {
 draft.cold_zone_priority = (value || null) as ZonePriority | null; 
},
});
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                    <Settings2 class="size-4 text-muted-foreground" />
                    {{ t('planogram-templates.subtemplate_settings.title') }}
                </h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('planogram-templates.subtemplate_settings.hint', { count: String(subtemplate.num_modules) }) }}
                </p>
            </div>
            <Button size="sm" :disabled="busy" @click="saveSettings">
                {{ t('planogram-templates.subtemplate_settings.save_button') }}
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Sentido de leitura do cliente -->
            <div class="rounded-md border border-border p-3">
                <p class="mb-2 text-sm font-medium">{{ t('planogram-templates.flow_direction.title') }}</p>
                <p class="mb-3 text-xs text-muted-foreground">
                    {{ t('planogram-templates.flow_direction.description') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="
                            !draft.flow_direction || draft.flow_direction === 'left_to_right'
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : 'border-border text-muted-foreground hover:border-primary/50'
                        "
                        @click="draft.flow_direction = 'left_to_right'"
                    >
                        <span>→</span> {{ t('planogram-templates.flow_direction.left_to_right') }} <span class="ml-1 text-xs opacity-60">{{ t('planogram-templates.flow_direction.left_to_right_default') }}</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="
                            draft.flow_direction === 'right_to_left'
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : 'border-border text-muted-foreground hover:border-primary/50'
                        "
                        @click="draft.flow_direction = 'right_to_left'"
                    >
                        <span>←</span> {{ t('planogram-templates.flow_direction.right_to_left') }}
                    </button>
                </div>
            </div>

            <!-- Disposição dos produtos (horizontal × blocagem vertical por marca) -->
            <div class="rounded-md border border-border p-3">
                <p class="mb-2 text-sm font-medium">{{ t('planogram-templates.layout_orientation.title') }}</p>
                <p class="mb-3 text-xs text-muted-foreground">
                    {{ t('planogram-templates.layout_orientation.description') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="
                            !draft.layout_orientation || draft.layout_orientation === 'horizontal'
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : 'border-border text-muted-foreground hover:border-primary/50'
                        "
                        @click="draft.layout_orientation = 'horizontal'"
                    >
                        {{ t('planogram-templates.layout_orientation.horizontal') }} <span class="ml-1 text-xs opacity-60">{{ t('planogram-templates.layout_orientation.horizontal_default') }}</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm transition-colors"
                        :class="
                            draft.layout_orientation === 'vertical'
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : 'border-border text-muted-foreground hover:border-primary/50'
                        "
                        @click="draft.layout_orientation = 'vertical'"
                    >
                        {{ t('planogram-templates.layout_orientation.vertical') }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ t('planogram-templates.layout_orientation.regenerate_hint') }}
                </p>
            </div>

            <!-- Priorização por zona térmica -->
            <div class="rounded-md border border-border p-3 lg:col-span-2">
                <p class="mb-2 text-sm font-medium">{{ t('planogram-templates.zone_priority.title') }}</p>
                <p class="mb-3 text-xs text-muted-foreground">
                    {{ t('planogram-templates.zone_priority.description') }}
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormSelectField
                        id="subtemplate-hot-zone-priority"
                        v-model="hotZoneModel"
                        name="hot_zone_priority"
                        :label="t('planogram-templates.zone_priority.hot_zone_label')"
                        :hint="t('planogram-templates.zone_priority.hot_zone_hint')"
                        :error="errors.hot_zone_priority"
                    >
                        <option value="">{{ t('planogram-templates.zone_priority.no_criteria') }}</option>
                        <option value="maior_margem">{{ t('planogram-templates.zone_priority.hot.maior_margem') }}</option>
                        <option value="maior_giro">{{ t('planogram-templates.zone_priority.hot.maior_giro') }}</option>
                        <option value="maior_valor_vendido">{{ t('planogram-templates.zone_priority.hot.maior_valor_vendido') }}</option>
                        <option value="curva_a">{{ t('planogram-templates.zone_priority.hot.curva_a') }}</option>
                    </FormSelectField>
                    <FormSelectField
                        id="subtemplate-cold-zone-priority"
                        v-model="coldZoneModel"
                        name="cold_zone_priority"
                        :label="t('planogram-templates.zone_priority.cold_zone_label')"
                        :hint="t('planogram-templates.zone_priority.cold_zone_hint')"
                        :error="errors.cold_zone_priority"
                    >
                        <option value="">{{ t('planogram-templates.zone_priority.no_criteria') }}</option>
                        <option value="menor_margem">{{ t('planogram-templates.zone_priority.cold.menor_margem') }}</option>
                        <option value="complementar_fria">{{ t('planogram-templates.zone_priority.cold.complementar_fria') }}</option>
                        <option value="maior_volume">{{ t('planogram-templates.zone_priority.cold.maior_volume') }}</option>
                        <option value="menor_prioridade">{{ t('planogram-templates.zone_priority.cold.menor_prioridade') }}</option>
                    </FormSelectField>
                </div>
            </div>
        </div>
    </div>
</template>
