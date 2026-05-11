<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Plug } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import IntegrationApiController from '@/actions/App/Http/Controllers/Landlord/IntegrationApiController';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import IntegrationApiPathRepeater from './components/IntegrationApiPathRepeater.vue';
import type { FieldMapTableOption, RequestPathRow } from './components/types';

type IntegrationApiPayload = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    requests_json: string;
    response_json: string;
    is_active: boolean;
};

let nextPathId = 1;

const props = defineProps<{
    integrationApi: IntegrationApiPayload | null;
    defaults: {
        requests_json: string;
        response_json: string;
    };
    fieldMapTables: Record<string, FieldMapTableOption>;
}>();

const { t } = useT();
const isEdit = computed(() => props.integrationApi !== null);
const integrationApisIndexPath = IntegrationApiController.index.url().replace(/^\/\/[^/]+/, '');
const initialRequests = parseObject(props.integrationApi?.requests_json ?? props.defaults.requests_json);
const initialResponse = parseObject(props.integrationApi?.response_json ?? props.defaults.response_json);
const requestMethod = ref(valueToInput(initialRequests.method || 'POST'));
const requestPayload = ref(valueToInput(initialRequests.payload || 'body'));
const pageField = ref(valueToInput(initialRequests.page_field || 'pagina'));
const pageValueType = ref(valueToInput(initialRequests.page_value_type || 'string'));
const pageSizeField = ref(valueToInput(initialRequests.page_size_field || 'tamanho_pagina'));
const pageSizePayload = ref(valueToInput(initialRequests.page_size_payload || 'body'));
const minPageSize = ref(valueToInput(initialRequests.min_page_size || 100));
const maxPageSize = ref(valueToInput(initialRequests.max_page_size || 5000));
const storeDocumentField = ref(valueToInput(initialRequests.store_document_field || 'empresa'));
const requestPaths = ref<RequestPathRow[]>(objectToRequestPaths(initialRequests));
const responseItemsPath = ref(valueToInput(initialResponse.items_path || 'data'));
const initialPagination = parseObjectValue(initialResponse.pagination);
const currentPagePath = ref(valueToInput(initialPagination.current_page_path || 'pagination.current_page'));
const perPagePath = ref(valueToInput(initialPagination.per_page_path || 'pagination.per_page'));
const totalPath = ref(valueToInput(initialPagination.total_path || 'pagination.total'));
const lastPagePath = ref(valueToInput(initialPagination.last_page_path || 'pagination.last_page'));
const requestsJson = computed(() => JSON.stringify(buildRequestsPayload(), null, 2));
const responseJson = computed(() => JSON.stringify(buildResponsePayload(), null, 2));
const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value ? t('app.landlord.integration_apis.actions.edit') : t('app.landlord.integration_apis.actions.new'),
    title: isEdit.value ? t('app.landlord.integration_apis.actions.edit') : t('app.landlord.integration_apis.actions.new'),
    description: t('app.landlord.integration_apis.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.integration_apis.navigation'),
            href: integrationApisIndexPath,
        },
        {
            title: isEdit.value ? t('app.landlord.common.edit') : t('app.landlord.common.create'),
            href: isEdit.value ? tenantWayfinderPath(IntegrationApiController.edit.url(props.integrationApi!.id)) : tenantWayfinderPath(IntegrationApiController.create.url()),
        },
    ],
});

function parseObject(value: string): Record<string, unknown> {
    try {
        const decoded = JSON.parse(value);

        return decoded && typeof decoded === 'object' && !Array.isArray(decoded)
            ? decoded
            : {};
    } catch {
        return {};
    }
}

function parseObjectValue(value: unknown): Record<string, unknown> {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function valueToInput(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function objectToRequestPaths(source: Record<string, unknown>): RequestPathRow[] {
    const reserved = new Set([
        'method',
        'payload',
        'paths',
        'page_field',
        'page_value_type',
        'page_size_field',
        'page_size_payload',
        'default_page_size',
        'min_page_size',
        'max_page_size',
        'store_document_field',
        'fixed_query',
    ]);

    const configuredPaths = parseObjectValue(source.paths);
    const legacyPaths = Object.fromEntries(
        Object.entries(source)
            .filter(([key, value]) => !reserved.has(key) && value && typeof value === 'object' && !Array.isArray(value)),
    );
    const pathsSource = Object.keys(configuredPaths).length > 0 ? configuredPaths : legacyPaths;
    const paths = Object.entries(pathsSource)
        .filter(([key, value]) => !reserved.has(key) && value && typeof value === 'object' && !Array.isArray(value))
        .map(([name, value]) => {
            const pathConfig = value as Record<string, unknown>;

            return {
                id: newPathId(),
                name,
                target_table: valueToInput(pathConfig.target_table || (props.fieldMapTables[name] ? name : '')),
                fallback_path: valueToInput(pathConfig.fallback_path),
                changed_since: valueToInput(parseObjectValue(pathConfig.date_fields).changed_since),
                start: valueToInput(parseObjectValue(pathConfig.date_fields).start),
                end: valueToInput(parseObjectValue(pathConfig.date_fields).end),
                field_map: objectToFieldMapRows(pathConfig.field_map),
            };
        });

    return paths.length > 0
        ? paths
        : [
            {
                id: newPathId(),
                name: 'products',
                target_table: 'products',
                fallback_path: '/hubprodutos.listar_produtos',
                changed_since: 'data_ultima_alteracao',
                start: '',
                end: '',
                field_map: [],
            },
            {
                id: newPathId(),
                name: 'sales',
                target_table: 'sales',
                fallback_path: '/hubvendas.vendas_produtos',
                changed_since: '',
                start: 'data_inicial',
                end: 'data_final',
                field_map: [],
            },
        ];
}

function objectToFieldMapRows(value: unknown): RequestPathRow['field_map'] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((row): row is Record<string, unknown> => row !== null && typeof row === 'object' && !Array.isArray(row))
        .map((row) => ({
            id: newPathId(),
            target: valueToInput(row.target),
            source: valueToInput(row.source),
            transforms: arrayOfStrings(row.transforms),
        }));
}

function arrayOfStrings(value: unknown): string[] {
    return Array.isArray(value) ? value.map((item) => String(item)).filter((item) => item.trim() !== '') : [];
}

function numberValue(value: string): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function buildRequestsPayload(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        method: requestMethod.value,
        payload: requestPayload.value,
        page_field: pageField.value,
        page_value_type: pageValueType.value,
        page_size_field: pageSizeField.value,
        page_size_payload: pageSizePayload.value,
        min_page_size: numberValue(minPageSize.value),
        max_page_size: numberValue(maxPageSize.value),
        store_document_field: storeDocumentField.value,
    };
    const paths: Record<string, unknown> = {};

    requestPaths.value.forEach((requestPath) => {
        const name = requestPath.name.trim();

        if (name === '') {
            return;
        }

        paths[name] = {
            target_table: requestPath.target_table,
            fallback_path: requestPath.fallback_path,
        };

        const dateFields = {
            changed_since: requestPath.changed_since,
            start: requestPath.start,
            end: requestPath.end,
        };
        const filteredDateFields = Object.fromEntries(
            Object.entries(dateFields).filter(([, value]) => value.trim() !== ''),
        );

        if (Object.keys(filteredDateFields).length > 0) {
            (paths[name] as Record<string, unknown>).date_fields = filteredDateFields;
        }

        const fieldMap = requestPath.field_map
            .filter((field) => field.target.trim() !== '' && field.source.trim() !== '')
            .map((field) => ({
                target: field.target,
                source: field.source,
                transforms: field.transforms,
            }));

        if (fieldMap.length > 0) {
            (paths[name] as Record<string, unknown>).field_map = fieldMap;
        }

    });

    payload.paths = paths;

    return payload;
}

function buildResponsePayload(): Record<string, unknown> {
    return {
        items_path: responseItemsPath.value,
        pagination: {
            current_page_path: currentPagePath.value,
            per_page_path: perPagePath.value,
            total_path: totalPath.value,
            last_page_path: lastPagePath.value,
        },
    };
}

function newPathId(): string {
    nextPathId += 1;

    return `path-${nextPathId}`;
}
</script>

<template>

    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="isEdit
                    ? { ...IntegrationApiController.update.form(props.integrationApi!.id), action: tenantWayfinderPath(IntegrationApiController.update.form(props.integrationApi!.id).action) }
                    : { ...IntegrationApiController.store.form(), action: tenantWayfinderPath(IntegrationApiController.store.form().action) }"
                v-slot="{ errors, processing }">
                <FormCard :processing="processing" :cancel-href="integrationApisIndexPath">
                    <template #icon>
                        <Plug class="size-5" />
                    </template>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <div class="grid gap-2 md:col-span-6">
                            <Label for="name">{{ t('app.landlord.integration_apis.fields.name') }}</Label>
                            <Input id="name" name="name" :default-value="props.integrationApi?.name ?? ''" required />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2 md:col-span-6">
                            <Label for="slug">{{ t('app.landlord.integration_apis.fields.slug') }}</Label>
                            <Input id="slug" name="slug" :default-value="props.integrationApi?.slug ?? ''" required />
                            <InputError :message="errors.slug" />
                        </div>
                    </div>

                    <fieldset class="grid gap-4 rounded-lg border border-border bg-muted/10 p-4">
                        <legend class="px-2 text-sm font-medium text-muted-foreground">
                            {{ t('app.landlord.integration_apis.fields.requests') }}
                        </legend>
                        <input type="hidden" name="requests_json" :value="requestsJson" />
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="request_method">{{ t('app.landlord.integration_apis.fields.method') }}</Label>
                                <select id="request_method" v-model="requestMethod"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                                    <option value="GET">GET</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                            </div>
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="request_payload">{{ t('app.landlord.integration_apis.fields.payload') }}</Label>
                                <select id="request_payload" v-model="requestPayload"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                                    <option value="body">body</option>
                                    <option value="query">query</option>
                                </select>
                            </div>
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="page_field">{{ t('app.landlord.integration_apis.fields.page_field') }}</Label>
                                <Input id="page_field" v-model="pageField" />
                            </div>
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="page_value_type">{{ t('app.landlord.integration_apis.fields.page_value_type') }}</Label>
                                <select id="page_value_type" v-model="pageValueType"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                                    <option value="string">string</option>
                                    <option value="integer">integer</option>
                                </select>
                            </div>
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="page_size_field">{{ t('app.landlord.integration_apis.fields.page_size_field') }}</Label>
                                <Input id="page_size_field" v-model="pageSizeField" />
                            </div>
                            <div class="grid gap-2 md:col-span-3">
                                <Label for="page_size_payload">{{ t('app.landlord.integration_apis.fields.page_size_payload') }}</Label>
                                <select id="page_size_payload" v-model="pageSizePayload"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20">
                                    <option value="body">body</option>
                                    <option value="query">query</option>
                                </select>
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="min_page_size">{{ t('app.landlord.integration_apis.fields.min_page_size') }}</Label>
                                <Input id="min_page_size" v-model="minPageSize" type="number" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="max_page_size">{{ t('app.landlord.integration_apis.fields.max_page_size') }}</Label>
                                <Input id="max_page_size" v-model="maxPageSize" type="number" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="store_document_field">{{ t('app.landlord.integration_apis.fields.store_document_field') }}</Label>
                                <Input id="store_document_field" v-model="storeDocumentField" />
                            </div>
                        </div>
                        <InputError :message="errors.requests_json" />
                        <IntegrationApiPathRepeater v-model="requestPaths" :field-map-tables="props.fieldMapTables" />
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border border-border bg-muted/10 p-4">
                        <legend class="px-2 text-sm font-medium text-muted-foreground">
                            {{ t('app.landlord.integration_apis.fields.response') }}
                        </legend>
                        <input type="hidden" name="response_json" :value="responseJson" />
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                            <div class="grid gap-2 md:col-span-4">
                                <Label for="items_path">{{ t('app.landlord.integration_apis.fields.items_path') }}</Label>
                                <Input id="items_path" v-model="responseItemsPath" />
                            </div>
                        </div>
                        <InputError :message="errors.response_json" />

                        <div class="grid gap-2">
                            <Label>{{ t('app.landlord.integration_apis.fields.pagination') }}</Label>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                <div class="grid gap-2 md:col-span-3">
                                    <Label for="current_page_path">{{ t('app.landlord.integration_apis.fields.current_page_path') }}</Label>
                                    <Input id="current_page_path" v-model="currentPagePath" />
                                </div>
                                <div class="grid gap-2 md:col-span-3">
                                    <Label for="per_page_path">{{ t('app.landlord.integration_apis.fields.per_page_path') }}</Label>
                                    <Input id="per_page_path" v-model="perPagePath" />
                                </div>
                                <div class="grid gap-2 md:col-span-3">
                                    <Label for="total_path">{{ t('app.landlord.integration_apis.fields.total_path') }}</Label>
                                    <Input id="total_path" v-model="totalPath" />
                                </div>
                                <div class="grid gap-2 md:col-span-3">
                                    <Label for="last_page_path">{{ t('app.landlord.integration_apis.fields.last_page_path') }}</Label>
                                    <Input id="last_page_path" v-model="lastPagePath" />
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
                        <input type="hidden" name="is_active" value="0" />
                        <input id="is_active" name="is_active" type="checkbox" value="1"
                            :checked="props.integrationApi?.is_active ?? true" class="accent-primary" />
                        <span class="text-sm font-medium">{{ t('app.landlord.integration_apis.fields.is_active')
                            }}</span>
                        <InputError :message="errors.is_active" />
                    </label>


                    <div class="grid gap-2">
                        <Label for="description">{{ t('app.landlord.integration_apis.fields.description') }}</Label>
                        <textarea id="description" name="description" rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            :value="props.integrationApi?.description ?? ''"></textarea>
                        <InputError :message="errors.description" />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>
