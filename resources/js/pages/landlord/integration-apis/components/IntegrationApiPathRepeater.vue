<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import IntegrationApiCalculationRepeater from './IntegrationApiCalculationRepeater.vue';
import IntegrationApiFieldMapRepeater from './IntegrationApiFieldMapRepeater.vue';
import type { RequestPathRow } from './types';

const props = defineProps<{
    modelValue: RequestPathRow[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: RequestPathRow[]];
}>();

const { t } = useT();

function newPath(): RequestPathRow {
    return {
        id: `path-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        name: '',
        fallback_path: '',
        changed_since: '',
        start: '',
        end: '',
        field_map: [],
        calculations: [],
    };
}

function addPath(): void {
    emit('update:modelValue', [...props.modelValue, newPath()]);
}

function updatePath(index: number, patch: Partial<RequestPathRow>): void {
    emit(
        'update:modelValue',
        props.modelValue.map((path, pathIndex) => (pathIndex === index ? { ...path, ...patch } : path)),
    );
}

function removePath(index: number): void {
    emit('update:modelValue', props.modelValue.filter((_, pathIndex) => pathIndex !== index));
}

function fieldOptions(path: RequestPathRow): string[] {
    return path.field_map.map((field) => field.target).filter((field) => field.trim() !== '');
}

const hasPaths = computed(() => props.modelValue.length > 0);
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-3">
            <Label>{{ t('app.landlord.integration_apis.fields.paths') }}</Label>
        </div>

        <div class="space-y-3">
            <div
                v-for="(requestPath, pathIndex) in props.modelValue"
                :key="requestPath.id"
                class="space-y-4 rounded-lg border border-border bg-muted/10 p-3"
            >
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-name-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.key') }}</Label>
                        <Input
                            :id="`path-name-${requestPath.id}`"
                            :model-value="requestPath.name"
                            :placeholder="t('app.landlord.integration_apis.placeholders.path_key')"
                            @update:model-value="updatePath(pathIndex, { name: String($event) })"
                        />
                    </div>
                    <div class="grid gap-2 md:col-span-7">
                        <Label :for="`path-value-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.fallback_path') }}</Label>
                        <Input
                            :id="`path-value-${requestPath.id}`"
                            :model-value="requestPath.fallback_path"
                            :placeholder="t('app.landlord.integration_apis.placeholders.fallback_path')"
                            @update:model-value="updatePath(pathIndex, { fallback_path: String($event) })"
                        />
                    </div>
                    <div class="flex items-end justify-end md:col-span-1">
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                            @click="removePath(pathIndex)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-changed-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.date_changed_since') }}</Label>
                        <Input
                            :id="`path-changed-${requestPath.id}`"
                            :model-value="requestPath.changed_since"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_changed_since')"
                            @update:model-value="updatePath(pathIndex, { changed_since: String($event) })"
                        />
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-start-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.date_start') }}</Label>
                        <Input
                            :id="`path-start-${requestPath.id}`"
                            :model-value="requestPath.start"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_start')"
                            @update:model-value="updatePath(pathIndex, { start: String($event) })"
                        />
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-end-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.date_end') }}</Label>
                        <Input
                            :id="`path-end-${requestPath.id}`"
                            :model-value="requestPath.end"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_end')"
                            @update:model-value="updatePath(pathIndex, { end: String($event) })"
                        />
                    </div>
                </div>

                <IntegrationApiFieldMapRepeater
                    :model-value="requestPath.field_map"
                    @update:model-value="updatePath(pathIndex, { field_map: $event })"
                />

                <IntegrationApiCalculationRepeater
                    :model-value="requestPath.calculations"
                    :field-options="fieldOptions(requestPath)"
                    @update:model-value="updatePath(pathIndex, { calculations: $event })"
                />
            </div>

            <div
                v-if="!hasPaths"
                class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground"
            >
                {{ t('app.landlord.integration_apis.empty_states.paths') }}
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addPath"
            >
                <Plus class="size-3.5" />
                {{ t('app.landlord.integration_apis.actions.add_path') }}
            </button>
        </div>
    </div>
</template>
