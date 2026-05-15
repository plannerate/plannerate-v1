<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { IntegrationPayload, IntegrationTestResult } from './types';

const props = defineProps<{
    integration: IntegrationPayload | null;
    tenantId: string;
}>();

const { t } = useT();

const availablePaths = computed(() => props.integration?.api_paths ?? []);

const selectedPathKey = ref(availablePaths.value[0]?.key ?? '');
const testPath = ref(availablePaths.value[0]?.path ?? '/');
const testMethod = ref(props.integration?.api_method ?? 'post');
const testBody = ref('');
const testLoading = ref(false);
const testError = ref<string | null>(null);
const testResult = ref<unknown>(null);

watch(selectedPathKey, (key) => {
    const found = availablePaths.value.find((p) => p.key === key);
    if (found) {
        testPath.value = found.path;
        testMethod.value = props.integration?.api_method ?? 'post';
    }
});

const removeFlashListener = router.on('flash', (event) => {
    const flash = (event as CustomEvent).detail?.flash as
        | { tenant_integration_test?: IntegrationTestResult }
        | undefined;

    const payload = flash?.tenant_integration_test;

    if (!payload) {
        return;
    }

    testResult.value = payload;
    testError.value = payload.ok
        ? null
        : (payload.message ?? t('app.messages.generic_error'));
});

onBeforeUnmount(() => {
    removeFlashListener();
});

function run(): void {
    if (!props.integration || testLoading.value) {
        return;
    }

    testLoading.value = true;
    testError.value = null;
    testResult.value = null;

    router.post(
        tenantWayfinderPath(
            TenantIntegrationController.testConnection.url(props.tenantId),
        ),
        {
            test_path: testPath.value,
            test_method: testMethod.value,
            test_body: testBody.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                testError.value = t('app.messages.generic_error');
            },
            onFinish: () => {
                testLoading.value = false;
            },
        },
    );
}

defineExpose({ run, isRunning: computed(() => testLoading.value) });
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{
                t(
                    'app.landlord.tenant_integrations.actions.test_connection',
                )
            }}
        </h3>

        <div v-if="availablePaths.length > 0" class="space-y-1">
            <label
                for="test_path_key"
                class="text-sm font-medium text-foreground"
            >
                Path
            </label>
            <select
                id="test_path_key"
                v-model="selectedPathKey"
                class="h-9 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option
                    v-for="p in availablePaths"
                    :key="p.key"
                    :value="p.key"
                >
                    {{ p.key }} — {{ p.path }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                {{ testMethod.toUpperCase() }} {{ testPath }}
            </p>
        </div>
        <div
            v-else
            class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 text-sm text-muted-foreground"
        >
            Salve a integração para selecionar um path de teste.
        </div>

        <div class="space-y-1">
            <label
                for="test_body"
                class="text-sm font-medium text-foreground"
            >
                {{ t('app.landlord.tenant_integrations.fields.test_body') }}
            </label>
            <textarea
                id="test_body"
                v-model="testBody"
                rows="6"
                class="w-full rounded-lg border border-input bg-background px-3 py-2 font-mono text-xs text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                :placeholder="
                    t(
                        'app.landlord.tenant_integrations.placeholders.test_body',
                    )
                "
            />
        </div>

        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="secondary"
                size="sm"
                :disabled="!integration || testLoading"
                @click="run"
            >
                {{
                    t(
                        'app.landlord.tenant_integrations.actions.run_test',
                    )
                }}
            </Button>
            <span v-if="testLoading" class="text-xs text-muted-foreground">
                {{ t('app.loading') }}
            </span>
        </div>

        <div
            v-if="testError"
            class="rounded border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            {{ testError }}
        </div>

        <div v-if="testResult !== null" class="space-y-1">
            <p class="text-xs font-semibold text-muted-foreground">
                {{
                    t(
                        'app.landlord.tenant_integrations.fields.test_response',
                    )
                }}
            </p>
            <pre
                class="max-h-96 overflow-auto rounded-md border border-border bg-background p-3 text-xs text-foreground"
                >{{ JSON.stringify(testResult, null, 2) }}</pre
            >
        </div>
    </div>
</template>
