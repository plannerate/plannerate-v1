<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Link2, Plug, PowerOff, Power } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import IntegrationApiController from '@/actions/App/Http/Controllers/Landlord/IntegrationApiController';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantIntegrationController from '@/actions/App/Http/Controllers/Landlord/TenantIntegrationController';
import DeleteButton from '@/components/DeleteButton.vue';
import FormCard from '@/components/FormCard.vue';
import { Button } from '@/components/ui/button';
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
const testPanelRef = ref<InstanceType<typeof TestPanel>>();

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
    </AppLayout>
</template>
