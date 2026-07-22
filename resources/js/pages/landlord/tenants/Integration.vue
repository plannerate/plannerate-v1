<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Link2,
    ListChecks,
    Play,
    Plug,
    Power,
    PowerOff,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import IntegrationApiController from '@/actions/App/Http/Controllers/Landlord/IntegrationApiController';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import DeleteButton from '@/components/DeleteButton.vue';
import FormCard from '@/components/FormCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import ConnectionForm from './integration/ConnectionForm.vue';
import TestPanel from './integration/TestPanel.vue';
import type { IntegrationPayload, IntegrationTypeOption } from './integration/types';

const props = defineProps<{
    tenant: { id: string; name: string };
    integration: IntegrationPayload | null;
    integration_types: IntegrationTypeOption[];
}>();

const { t } = useT();

const tenantsIndexPath = TenantController.index.url().replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: `${t('app.landlord.tenant_integrations.title')} - ${props.tenant.name}`,
    title: `${t('app.landlord.tenant_integrations.title')} - ${props.tenant.name}`,
    description: t('app.landlord.tenant_integrations.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantsIndexPath,
        },
        {
            title: t('app.landlord.tenant_integrations.navigation'),
            href: tenantWayfinderPath(
                TenantIntegrationController.edit.url(props.tenant.id),
            ),
        },
    ],
});

const updateForm = computed(() => {
    const definition = TenantIntegrationController.update.form(props.tenant.id);

    return {
        ...definition,
        action: tenantWayfinderPath(definition.action),
    };
});

const statusLoading = ref(false);
const runLoading = ref<'import' | 'post-import' | null>(null);
const testPanelRef = ref<InstanceType<typeof TestPanel>>();

const canRun = computed(() => props.integration?.is_active === true);

type PipelineStep = 'import' | 'post-import';

/** Etapa aguardando confirmação; null com o modal fechado. */
const pendingStep = ref<PipelineStep | null>(null);

const dialogOpen = computed({
    get: () => pendingStep.value !== null,
    set: (open: boolean) => {
        if (!open) {
            pendingStep.value = null;
        }
    },
});

/**
 * Conteúdo do modal por etapa. As duas disparam trabalho pesado contra a API do
 * ERP, então ambas confirmam — mas só a pós-importação apaga algo, e o tom do
 * aviso reflete isso (âmbar de atenção, não vermelho de destrutivo: o
 * soft-delete é revertido pelo próprio feed no import seguinte).
 */
const dialogContent = computed(() => {
    const prefix =
        pendingStep.value === 'post-import'
            ? 'app.landlord.tenant_integrations.post_import_dialog'
            : 'app.landlord.tenant_integrations.import_dialog';

    const stepKeys =
        pendingStep.value === 'post-import'
            ? ['step_link_sales', 'step_cleanup', 'step_ean_references', 'step_summaries']
            : ['step_discover', 'step_fetch', 'step_persist'];

    return {
        title: t(`${prefix}.title`),
        description: t(`${prefix}.description`),
        steps: stepKeys.map((key) => t(`${prefix}.${key}`)),
        note: t(`${prefix}.warning`),
        destructive: pendingStep.value === 'post-import',
        confirmLabel:
            pendingStep.value === 'post-import'
                ? t('app.landlord.tenant_integrations.actions.run_post_import')
                : t('app.landlord.tenant_integrations.actions.run_import'),
    };
});

function confirmRun(step: PipelineStep): void {
    if (!canRun.value || runLoading.value !== null) {
        return;
    }

    pendingStep.value = step;
}

/**
 * Dispara importação/pós-importação sob demanda, sem esperar o agendamento das
 * 06:00. O backend só enfileira — o trabalho leva minutos e roda no Horizon.
 */
function runPipeline(step: PipelineStep): void {
    if (!canRun.value || runLoading.value !== null) {
        return;
    }

    pendingStep.value = null;
    runLoading.value = step;

    const action =
        step === 'import'
            ? TenantIntegrationController.runImport
            : TenantIntegrationController.runPostImport;

    router.post(
        tenantWayfinderPath(action.url(props.tenant.id)),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                runLoading.value = null;
            },
        },
    );
}

function toggleStatus(): void {
    if (!props.integration || statusLoading.value) {
        return;
    }

    statusLoading.value = true;

    router.patch(
        tenantWayfinderPath(
            TenantIntegrationController.toggleStatus.url(props.tenant.id),
        ),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                statusLoading.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form v-bind="updateForm" v-slot="{ errors, processing }">
                <FormCard
                    :processing="processing"
                    :cancel-href="tenantsIndexPath"
                >
                    <template #icon>
                        <Link2 class="size-5" />
                    </template>
                    <template #header-extra>
                        <Button variant="outline" size="sm" as-child>
                            <WayfinderLink
                                :href="
                                    tenantWayfinderPath(
                                        IntegrationApiController.index.url(),
                                    )
                                "
                            >
                                <Plug class="size-4" />
                                {{
                                    t(
                                        'app.landlord.integration_apis.navigation',
                                    )
                                }}
                            </WayfinderLink>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                !integration || testPanelRef?.isRunning
                            "
                            @click="testPanelRef?.run()"
                        >
                            {{
                                t(
                                    'app.landlord.tenant_integrations.actions.test_connection',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="!canRun || runLoading !== null"
                            :title="
                                t(
                                    'app.landlord.tenant_integrations.actions.run_import',
                                )
                            "
                            @click="confirmRun('import')"
                        >
                            <Play class="size-4" />
                            {{
                                t(
                                    'app.landlord.tenant_integrations.actions.run_import',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="!canRun || runLoading !== null"
                            @click="confirmRun('post-import')"
                        >
                            <ListChecks class="size-4" />
                            {{
                                t(
                                    'app.landlord.tenant_integrations.actions.run_post_import',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            :variant="
                                integration?.is_active ? 'outline' : 'secondary'
                            "
                            size="sm"
                            :disabled="!integration || statusLoading"
                            @click="toggleStatus"
                        >
                            <PowerOff
                                v-if="integration?.is_active"
                                class="size-4"
                            />
                            <Power v-else class="size-4" />
                            {{
                                integration?.is_active
                                    ? t(
                                          'app.landlord.tenant_integrations.actions.deactivate',
                                      )
                                    : t(
                                          'app.landlord.tenant_integrations.actions.activate',
                                      )
                            }}
                        </Button>
                        <DeleteButton
                            v-if="integration"
                            :href="
                                tenantWayfinderPath(
                                    TenantIntegrationController.destroy.url(
                                        tenant.id,
                                    ),
                                )
                            "
                            :label="t('app.landlord.tenant_integrations.title')"
                            require-confirm-word
                        >
                            {{
                                t(
                                    'app.landlord.tenant_integrations.actions.delete',
                                )
                            }}
                        </DeleteButton>
                    </template>

                    <ConnectionForm
                        :integration="integration"
                        :integration-types="integration_types"
                        :errors="errors"
                    />

                    <TestPanel
                        ref="testPanelRef"
                        :integration="integration"
                        :tenant-id="tenant.id"
                    />
                </FormCard>
            </Form>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full"
                            :class="
                                dialogContent.destructive
                                    ? 'bg-amber-500/10'
                                    : 'bg-primary/10'
                            "
                        >
                            <TriangleAlert
                                v-if="dialogContent.destructive"
                                class="size-5 text-amber-600"
                            />
                            <Play v-else class="size-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle>{{ dialogContent.title }}</DialogTitle>
                            <DialogDescription class="mt-0.5">
                                {{ dialogContent.description }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <ol
                    class="list-decimal space-y-1.5 rounded-lg bg-muted/40 py-3 pr-4 pl-8 text-sm text-muted-foreground"
                >
                    <li v-for="step in dialogContent.steps" :key="step">
                        {{ step }}
                    </li>
                </ol>

                <p
                    class="rounded-lg border p-3 text-sm"
                    :class="
                        dialogContent.destructive
                            ? 'border-amber-500/30 bg-amber-500/5 text-foreground'
                            : 'border-border bg-muted/30 text-muted-foreground'
                    "
                >
                    {{ dialogContent.note }}
                </p>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="runLoading !== null"
                        @click="pendingStep = null"
                    >
                        {{ t('app.common.actions.cancel') }}
                    </Button>
                    <Button
                        :disabled="runLoading !== null || pendingStep === null"
                        @click="pendingStep && runPipeline(pendingStep)"
                    >
                        {{ dialogContent.confirmLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
