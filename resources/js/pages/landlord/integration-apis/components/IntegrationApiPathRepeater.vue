<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';
import IntegrationApiFieldMapRepeater from './IntegrationApiFieldMapRepeater.vue';
import IntegrationApiPivotRepeater from './IntegrationApiPivotRepeater.vue';
import IntegrationApiValidationRepeater from './IntegrationApiValidationRepeater.vue';
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
        target_table: '',
        fallback_path: '',
        id_prefix: '',
        unique_by: '',
        include_store_in_id: false,
        initial_days: '',
        chunk_days: '',
        last_date_column: '',
        max_page: '',
        min_page_size: '',
        max_page_size: '',
        changed_since: '',
        start: '',
        end: '',
        field_map: [],
        pivot_tables: [],
        validations: [],
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

const fieldMapOpenCookieName = 'integration_api_field_map_open';
const fieldMapOpenState = ref<Record<string, boolean>>({});

function pathStateKey(path: RequestPathRow, index: number): string {
    const table = path.target_table.trim();

    return table !== '' ? table : `index-${index}`;
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;
    const cookie = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(prefix));

    return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

function writeCookie(name: string, value: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; samesite=lax`;
}

function persistFieldMapState(): void {
    writeCookie(fieldMapOpenCookieName, JSON.stringify(fieldMapOpenState.value));
}

function isFieldMapOpen(path: RequestPathRow, index: number): boolean {
    const key = pathStateKey(path, index);
    const savedState = fieldMapOpenState.value[key];

    if (savedState !== undefined) {
        return savedState;
    }

    return path.field_map.length > 0;
}

function handleFieldMapToggle(event: Event, path: RequestPathRow, index: number): void {
    const target = event.target;

    if (!(target instanceof HTMLDetailsElement)) {
        return;
    }

    fieldMapOpenState.value[pathStateKey(path, index)] = target.open;
    persistFieldMapState();
}

onMounted(() => {
    const raw = readCookie(fieldMapOpenCookieName);

    if (!raw) {
        return;
    }

    try {
        const parsed = JSON.parse(raw);

        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            fieldMapOpenState.value = parsed as Record<string, boolean>;
        }
    } catch {
        fieldMapOpenState.value = {};
    }
});
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-3">
            <Label>{{ t('app.landlord.integration_apis.fields.paths') }}</Label>
        </div>

        <div class="space-y-3">
            <div v-for="(requestPath, pathIndex) in props.modelValue" :key="requestPath.id"
                class="space-y-4 rounded-lg border border-border bg-muted/10 p-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-table-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.target_table') }}</Label>
                        <select :id="`path-table-${requestPath.id}`" :value="requestPath.target_table" required
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @change="updatePath(pathIndex, { target_table: ($event.target as HTMLSelectElement).value })">
                            <option value="">{{ t('app.landlord.integration_apis.placeholders.target_table') }}</option>
                            <option v-for="[table, option] in tableOptions" :key="table" :value="table">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2 md:col-span-7">
                        <Label :for="`path-value-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.fallback_path') }}</Label>
                        <Input :id="`path-value-${requestPath.id}`" :model-value="requestPath.fallback_path"
                            :placeholder="t('app.landlord.integration_apis.placeholders.fallback_path')" required
                            @update:model-value="updatePath(pathIndex, { fallback_path: String($event) })" />
                    </div>
                    <div class="flex items-end justify-end md:col-span-1">
                        <button type="button"
                            class="flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                            @click="removePath(pathIndex)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-changed-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.date_changed_since') }}</Label>
                        <Input :id="`path-changed-${requestPath.id}`" :model-value="requestPath.changed_since"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_changed_since')"
                            @update:model-value="updatePath(pathIndex, { changed_since: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-start-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.date_start') }}</Label>
                        <Input :id="`path-start-${requestPath.id}`" :model-value="requestPath.start"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_start')"
                            @update:model-value="updatePath(pathIndex, { start: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-4">
                        <Label :for="`path-end-${requestPath.id}`">{{ t('app.landlord.integration_apis.fields.date_end')
                            }}</Label>
                        <Input :id="`path-end-${requestPath.id}`" :model-value="requestPath.end"
                            :placeholder="t('app.landlord.integration_apis.placeholders.date_end')"
                            @update:model-value="updatePath(pathIndex, { end: String($event) })" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-3">
                        <Label :for="`path-unique-by-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.unique_by') }}</Label>
                        <Input :id="`path-unique-by-${requestPath.id}`" :model-value="requestPath.unique_by"
                            :placeholder="t('app.landlord.integration_apis.placeholders.unique_by')"
                            @update:model-value="updatePath(pathIndex, { unique_by: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label :for="`path-id-prefix-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.id_prefix') }}</Label>
                        <Input :id="`path-id-prefix-${requestPath.id}`" :model-value="requestPath.id_prefix"
                            :placeholder="t('app.landlord.integration_apis.placeholders.id_prefix')"
                            @update:model-value="updatePath(pathIndex, { id_prefix: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label :for="`path-initial-days-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.initial_days') }}</Label>
                        <Input :id="`path-initial-days-${requestPath.id}`" type="number"
                            :model-value="requestPath.initial_days"
                            :placeholder="t('app.landlord.integration_apis.placeholders.initial_days')"
                            @update:model-value="updatePath(pathIndex, { initial_days: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label :for="`path-chunk-days-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.chunk_days') }}</Label>
                        <Input :id="`path-chunk-days-${requestPath.id}`" type="number"
                            :model-value="requestPath.chunk_days"
                            :placeholder="t('app.landlord.integration_apis.placeholders.chunk_days')"
                            @update:model-value="updatePath(pathIndex, { chunk_days: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label :for="`path-max-page-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.max_page') }}</Label>
                        <Input :id="`path-max-page-${requestPath.id}`" type="number"
                            :model-value="requestPath.max_page"
                            :placeholder="t('app.landlord.integration_apis.placeholders.max_page')"
                            @update:model-value="updatePath(pathIndex, { max_page: String($event) })" />
                    </div>
                    <div class="grid gap-1 md:col-span-3">
                        <Label :for="`path-store-id-${requestPath.id}`" class="text-xs leading-tight flex flex-col justify-start items-start">
                            <span> {{ t('app.landlord.integration_apis.fields.include_store_in_id') }}</span>
                            <div class="flex h-9 items-start space-x-1 ">
                                <input :id="`path-store-id-${requestPath.id}`" type="checkbox"
                                    :checked="requestPath.include_store_in_id"
                                    class="size-4 rounded border-input accent-primary"
                                    @change="updatePath(pathIndex, { include_store_in_id: ($event.target as HTMLInputElement).checked })" />
                                <p class="text-xs text-muted-foreground">
                                    {{ t('app.landlord.integration_apis.hints.include_store_in_id') }}
                                </p>
                            </div>
                        </Label>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="grid gap-2 md:col-span-3">
                        <Label :for="`path-min-page-size-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.min_page_size') }}</Label>
                        <Input :id="`path-min-page-size-${requestPath.id}`" type="number"
                            :model-value="requestPath.min_page_size"
                            placeholder="1"
                            @update:model-value="updatePath(pathIndex, { min_page_size: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-3">
                        <Label :for="`path-max-page-size-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.max_page_size') }}</Label>
                        <Input :id="`path-max-page-size-${requestPath.id}`" type="number"
                            :model-value="requestPath.max_page_size"
                            placeholder="1000"
                            @update:model-value="updatePath(pathIndex, { max_page_size: String($event) })" />
                    </div>
                    <div class="grid gap-2 md:col-span-6">
                        <Label :for="`path-last-date-column-${requestPath.id}`">{{
                            t('app.landlord.integration_apis.fields.last_date_column') }}</Label>
                        <Input :id="`path-last-date-column-${requestPath.id}`"
                            :model-value="requestPath.last_date_column"
                            :placeholder="t('app.landlord.integration_apis.placeholders.last_date_column')"
                            @update:model-value="updatePath(pathIndex, { last_date_column: String($event) })" />
                    </div>
                </div>

                <details
                    class="rounded-md border border-border bg-background/60"
                    :open="isFieldMapOpen(requestPath, pathIndex)"
                    @toggle="handleFieldMapToggle($event, requestPath, pathIndex)"
                >
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-muted-foreground">
                        {{ t('app.landlord.integration_apis.fields.field_map') }}
                    </summary>
                    <div class="border-t border-border p-3">
                        <IntegrationApiFieldMapRepeater :model-value="requestPath.field_map"
                            :target-options="tableColumns(requestPath)"
                            @update:model-value="updatePath(pathIndex, { field_map: $event })" />
                    </div>
                </details>

                <details class="rounded-md border border-border bg-background/60">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-muted-foreground">
                        {{ t('app.landlord.integration_apis.fields.pivot_tables') }}
                        <span v-if="requestPath.pivot_tables.length > 0" class="ml-1 text-xs text-primary">
                            ({{ requestPath.pivot_tables.length }})
                        </span>
                    </summary>
                    <div class="border-t border-border p-3">
                        <IntegrationApiPivotRepeater
                            :model-value="requestPath.pivot_tables"
                            @update:model-value="updatePath(pathIndex, { pivot_tables: $event })"
                        />
                    </div>
                </details>

                <details class="rounded-md border border-border bg-background/60">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-muted-foreground">
                        Validações de grupo
                        <span v-if="requestPath.validations.length > 0" class="ml-1 text-xs text-primary">
                            ({{ requestPath.validations.length }})
                        </span>
                    </summary>
                    <div class="border-t border-border p-3">
                        <IntegrationApiValidationRepeater
                            :model-value="requestPath.validations"
                            @update:model-value="updatePath(pathIndex, { validations: $event })"
                        />
                    </div>
                </details>
            </div>

            <div v-if="!hasPaths"
                class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                {{ t('app.landlord.integration_apis.empty_states.paths') }}
            </div>
        </div>

        <div class="flex justify-center pt-1">
            <button type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-3 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground"
                @click="addPath">
                <Plus class="size-3.5" />
                {{ t('app.landlord.integration_apis.actions.add_path') }}
            </button>
        </div>
    </div>
</template>
