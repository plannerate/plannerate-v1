<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Clock, Database, Globe, Loader2, Package, Settings } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    database: string;
    status: 'provisioning' | 'active' | 'suspended' | 'inactive';
    provisioned_at: string | null;
    provisioning_error: string | null;
    plan: { id: string; name: string } | null;
    primary_domain: { host: string; is_active: boolean } | null;
};

const props = defineProps<{ tenant: TenantPayload }>();

const { t } = useT();

const provisionForm = useForm({});

function provision(): void {
    provisionForm.post(TenantController.provision.url(props.tenant.id));
}

let pollingTimer: ReturnType<typeof setInterval> | null = null;

function startPolling(): void {
    pollingTimer = setInterval(() => {
        if (props.tenant.status !== 'provisioning') {
            stopPolling();
            return;
        }
        router.reload({ only: ['tenant'] });
    }, 3000);
}

function stopPolling(): void {
    if (pollingTimer !== null) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

onMounted(() => {
    if (props.tenant.status === 'provisioning') {
        startPolling();
    }
});

onBeforeUnmount(() => {
    stopPolling();
});

setLayoutProps({
    breadcrumbs: [
        { title: t('app.landlord.tenants.navigation'), href: TenantController.index.url() },
        { title: props.tenant.name, href: TenantController.edit.url(props.tenant.id) },
        { title: t('app.landlord.tenants.setup.title'), href: TenantController.setup.url(props.tenant.id) },
    ],
});
</script>

<template>
    <Head :title="`${t('app.landlord.tenants.setup.title')} - ${props.tenant.name}`" />

    <div class="space-y-6 p-4">
        <div class="flex items-end justify-between gap-4">
            <Heading
                :title="`${t('app.landlord.tenants.setup.title')} — ${props.tenant.name}`"
                :description="t('app.landlord.tenants.setup.description')"
            />
        </div>

        <!-- Status banner -->
        <div
            class="flex items-start gap-4 rounded-xl border p-5"
            :class="{
                'border-primary/30 bg-primary/5': tenant.status === 'active',
                'border-yellow-400/30 bg-yellow-50 dark:bg-yellow-950/20': tenant.status === 'provisioning',
                'border-destructive/30 bg-destructive/5': tenant.provisioning_error,
            }"
        >
            <div class="mt-0.5 shrink-0">
                <Loader2
                    v-if="tenant.status === 'provisioning'"
                    class="size-6 animate-spin text-yellow-500"
                />
                <CheckCircle2
                    v-else-if="tenant.status === 'active'"
                    class="size-6 text-primary"
                />
                <AlertCircle
                    v-else
                    class="size-6 text-destructive"
                />
            </div>
            <div class="flex-1 space-y-1">
                <p class="font-semibold text-foreground">
                    <span v-if="tenant.status === 'provisioning'">{{ t('app.landlord.tenants.setup.provisioning_message') }}</span>
                    <span v-else-if="tenant.status === 'active'">{{ t('app.landlord.tenants.setup.active_message') }}</span>
                    <span v-else>{{ t('app.landlord.tenants.setup.error_message') }}</span>
                </p>
                <p v-if="tenant.provisioning_error" class="text-sm text-destructive">
                    {{ tenant.provisioning_error }}
                </p>
                <p v-if="tenant.provisioned_at" class="text-xs text-muted-foreground">
                    Provisionado em {{ tenant.provisioned_at }}
                </p>
            </div>
            <div v-if="tenant.status !== 'provisioning'" class="shrink-0">
                <Button
                    v-if="tenant.status === 'active'"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="TenantUserAccessController.edit.url(tenant.id)">
                        {{ t('app.landlord.tenants.setup.manage_users_button') }}
                    </Link>
                </Button>
                <Button
                    v-else
                    variant="default"
                    size="sm"
                    :disabled="provisionForm.processing"
                    @click="provision"
                >
                    <Loader2 v-if="provisionForm.processing" class="mr-1 size-4 animate-spin" />
                    {{ tenant.provisioning_error
                        ? t('app.landlord.tenants.setup.retry_button')
                        : t('app.landlord.tenants.setup.provision_button') }}
                </Button>
            </div>
        </div>

        <!-- Info cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Status -->
            <div class="flex items-start gap-4 rounded-xl border border-border bg-card p-5">
                <div class="rounded-lg bg-primary/10 p-2.5">
                    <Settings class="size-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        {{ t('app.landlord.tenants.setup.status_label') }}
                    </p>
                    <p class="mt-1 truncate font-semibold text-foreground capitalize">{{ tenant.status }}</p>
                </div>
            </div>

            <!-- Database -->
            <div class="flex items-start gap-4 rounded-xl border border-border bg-card p-5">
                <div class="rounded-lg bg-primary/10 p-2.5">
                    <Database class="size-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        {{ t('app.landlord.tenants.setup.database_label') }}
                    </p>
                    <p class="mt-1 truncate font-mono text-sm font-semibold text-foreground">{{ tenant.database }}</p>
                </div>
            </div>

            <!-- Domain -->
            <div class="flex items-start gap-4 rounded-xl border border-border bg-card p-5">
                <div class="rounded-lg bg-primary/10 p-2.5">
                    <Globe class="size-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        {{ t('app.landlord.tenants.setup.domain_label') }}
                    </p>
                    <p class="mt-1 truncate font-mono text-sm font-semibold text-foreground">
                        {{ tenant.primary_domain?.host ?? '—' }}
                    </p>
                    <span
                        v-if="tenant.primary_domain"
                        class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs"
                        :class="tenant.primary_domain.is_active
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground'"
                    >
                        {{ tenant.primary_domain.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
            </div>

            <!-- Plan -->
            <div class="flex items-start gap-4 rounded-xl border border-border bg-card p-5">
                <div class="rounded-lg bg-primary/10 p-2.5">
                    <Package class="size-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        {{ t('app.landlord.tenants.setup.plan_label') }}
                    </p>
                    <p class="mt-1 truncate font-semibold text-foreground">
                        {{ tenant.plan?.name ?? '—' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Provisioning steps -->
        <div class="rounded-xl border border-border bg-card p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-widest text-muted-foreground">
                Etapas do provisionamento
            </h3>
            <ol class="space-y-3">
                <li
                    v-for="(step, i) in [
                        'Criar banco de dados MySQL',
                        'Executar migrations do tenant',
                        'Ativar ambiente',
                    ]"
                    :key="i"
                    class="flex items-center gap-3 text-sm"
                >
                    <span
                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="tenant.status === 'active'
                            ? 'bg-primary text-primary-foreground'
                            : tenant.provisioning_error
                                ? 'bg-destructive/20 text-destructive'
                                : 'bg-muted text-muted-foreground'"
                    >
                        <CheckCircle2 v-if="tenant.status === 'active'" class="size-4" />
                        <Clock v-else-if="tenant.status === 'provisioning'" class="size-4 animate-pulse" />
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span :class="tenant.status === 'active' ? 'text-foreground' : 'text-muted-foreground'">
                        {{ step }}
                    </span>
                </li>
            </ol>
        </div>
    </div>
</template>
