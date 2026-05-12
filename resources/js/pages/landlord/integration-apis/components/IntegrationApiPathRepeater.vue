<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import IntegrationApiFieldMapRepeater from './IntegrationApiFieldMapRepeater.vue';
import type { FieldMapTableOption, RequestPathRow } from './types';

const props = defineProps<{
    modelValue: RequestPathRow[];
    fieldMapTables: Record<string, FieldMapTableOption>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: RequestPathRow[]];
}>();

const { t } = useT();

function newPath(): RequestPathRow {
    return {
        id: `path-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        name: '',
        target_table: '',
        fallback_path: '',
        unique_by: '',
        include_store_in_id: false,
        initial_days: '',
        changed_since: '',
        start: '',
        end: '',
        field_map: [],
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

function tableColumns(path: RequestPathRow): string[] {
    return props.fieldMapTables[path.target_table]?.columns ?? [];
}

const hasPaths = computed(() => props.modelValue.length > 0);
const tableOptions = computed(() => Object.entries(props.fieldMapTables));
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
                    <div class="grid gap-2 md:col-span-3">
                        <Label :for="`path-table-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.target_table') }}</Label>
                        <select
                            :id="`path-table-${requestPath.id}`"
                            :value="requestPath.target_table"
                            required
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @change="updatePath(pathIndex, { target_table: ($event.target as HTMLSelectElement).value })"
                        >
                            <option value="">{{ t('app.landlord.integration_apis.placeholders.target_table') }}</option>
                            <option v-for="[table, option] in tableOptions" :key="table" :value="table">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-value-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.fallback_path') }}</Label>
                        <Input
                            :id="`path-value-${requestPath.id}`"
                            :model-value="requestPath.fallback_path"
                            :placeholder="t('app.landlord.integration_apis.placeholders.fallback_path')"
                            required
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

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-8">
                        <Label :for="`path-unique-by-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.unique_by') }}</Label>
                        <Input
                            :id="`path-unique-by-${requestPath.id}`"
                            :model-value="requestPath.unique_by"
                            :placeholder="t('app.landlord.integration_apis.placeholders.unique_by')"
                            @update:model-value="updatePath(pathIndex, { unique_by: String($event) })"
                        />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label :for="`path-initial-days-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.initial_days') }}</Label>
                        <Input
                            :id="`path-initial-days-${requestPath.id}`"
                            type="number"
                            :model-value="requestPath.initial_days"
                            :placeholder="t('app.landlord.integration_apis.placeholders.initial_days')"
                            @update:model-value="updatePath(pathIndex, { initial_days: String($event) })"
                        />
                    </div>
                    <div class="flex flex-col justify-end gap-2 md:col-span-2">
                        <Label :for="`path-store-id-${requestPath.id}`" class="text-xs leading-tight">
                            {{ t('app.landlord.integration_apis.fields.include_store_in_id') }}
                        </Label>
                        <div class="flex h-9 items-center">
                            <input
                                :id="`path-store-id-${requestPath.id}`"
                                type="checkbox"
                                :checked="requestPath.include_store_in_id"
                                class="size-4 rounded border-input accent-primary"
                                @change="updatePath(pathIndex, { include_store_in_id: ($event.target as HTMLInputElement).checked })"
                            />
                        </div>
                    </div>
                </div>

                <IntegrationApiFieldMapRepeater
                    :model-value="requestPath.field_map"
                    :target-options="tableColumns(requestPath)"
                    @update:model-value="updatePath(pathIndex, { field_map: $event })"
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
