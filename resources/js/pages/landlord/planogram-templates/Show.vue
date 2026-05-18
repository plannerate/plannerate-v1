<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Layers, Share2, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import GlobalPlanogramTemplateController from '@/actions/App/Http/Controllers/Landlord/GlobalPlanogramTemplateController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type Subtemplate = {
    id: string;
    code: string;
    num_modules: number;
    slots_count: number;
};

type ShareEntry = {
    id: string;
    tenant_id: string;
    tenant_name: string | null;
    shared_at: string | null;
    shared_by_name: string | null;
};

type AvailableTenant = {
    id: string;
    name: string;
};

type Template = {
    id: string;
    code: string;
    name: string;
    department: string;
    description: string | null;
    is_active: boolean;
    subtemplates_count: number;
    subtemplates: Subtemplate[];
    created_at: string | null;
};

const props = defineProps<{
    template: Template;
    shares: ShareEntry[];
    available_tenants: AvailableTenant[];
}>();

const { t } = useT();
const indexPath = GlobalPlanogramTemplateController.index.url().replace(/^\/\/[^/]+/, '');

const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: t('app.landlord.planogram_templates.navigation'), href: indexPath },
    { title: props.template.code, href: '#' },
];

const selectedTenantIds = ref<string[]>([]);

const shareForm = useForm({
    tenant_ids: [] as string[],
});

function toggleTenant(id: string): void {
    const idx = selectedTenantIds.value.indexOf(id);
    if (idx === -1) {
        selectedTenantIds.value.push(id);
    } else {
        selectedTenantIds.value.splice(idx, 1);
    }
}

function submitShare(): void {
    shareForm.tenant_ids = [...selectedTenantIds.value];
    shareForm.post(GlobalPlanogramTemplateController.share.url(props.template.id), {
        onSuccess: () => {
            selectedTenantIds.value = [];
        },
    });
}

function confirmDelete(): void {
    if (confirm(t('app.landlord.planogram_templates.show.confirm_delete', { name: props.template.name }))) {
        router.delete(GlobalPlanogramTemplateController.destroy.url(props.template.id));
    }
}

function formatDate(date: string | null): string {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('pt-BR');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Template ${template.code}`" />

        <div class="mx-auto max-w-4xl space-y-6 py-8">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ template.name }}</h1>
                        <Badge :variant="template.is_active ? 'default' : 'secondary'">
                            {{ template.is_active ? t('app.landlord.planogram_templates.status.active') : t('app.landlord.planogram_templates.status.inactive') }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.show.code_prefix') }} <strong>{{ template.code }}</strong> · {{ t('app.landlord.planogram_templates.show.department_prefix') }} <strong>{{ template.department }}</strong>
                    </p>
                    <p v-if="template.description" class="mt-2 text-sm text-muted-foreground">{{ template.description }}</p>
                </div>
                <Button variant="destructive" size="sm" @click="confirmDelete">
                    <Trash2 class="size-4" />
                    {{ t('app.landlord.planogram_templates.actions.delete') }}
                </Button>
            </div>

            <!-- Subtemplates -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-6 py-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <Layers class="size-4 text-muted-foreground" />
                        {{ t('app.landlord.planogram_templates.show.subtemplates_title') }} ({{ template.subtemplates_count }})
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.show.subtemplates_description') }}
                    </p>
                </div>
                <div class="divide-y divide-border">
                    <div
                        v-for="sub in template.subtemplates"
                        :key="sub.id"
                        class="flex items-center justify-between px-6 py-4"
                    >
                        <div>
                            <p class="font-medium">{{ sub.code }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ sub.num_modules }} {{ sub.num_modules !== 1 ? t('app.landlord.planogram_templates.show.modules_plural') : t('app.landlord.planogram_templates.show.modules_singular') }}
                            </p>
                        </div>
                        <Badge variant="outline">{{ sub.slots_count }} {{ sub.slots_count !== 1 ? t('app.landlord.planogram_templates.show.slots_plural') : t('app.landlord.planogram_templates.show.slots_singular') }}</Badge>
                    </div>
                    <div v-if="template.subtemplates.length === 0" class="px-6 py-8 text-center text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.show.empty_subtemplates') }}
                    </div>
                </div>
            </div>

            <!-- Sharing -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-6 py-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <Share2 class="size-4 text-muted-foreground" />
                        {{ t('app.landlord.planogram_templates.shares.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.shares.description') }}
                    </p>
                </div>

                <!-- Already shared -->
                <div class="px-6 py-4">
                    <p v-if="shares.length === 0" class="text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.shares.not_shared') }}
                    </p>
                    <div v-else class="space-y-2">
                        <p class="text-sm font-medium text-foreground">
                            {{ t('app.landlord.planogram_templates.shares.shared_with_count', { count: shares.length }) }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <div
                                v-for="share in shares"
                                :key="share.id"
                                class="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ share.tenant_name }}</span>
                                <span class="text-muted-foreground">
                                    · {{ t('app.landlord.planogram_templates.shares.shared_at') }} {{ formatDate(share.shared_at) }}
                                    <template v-if="share.shared_by_name"> {{ t('app.landlord.planogram_templates.shares.shared_by') }} {{ share.shared_by_name }}</template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Share with new tenants -->
                <div v-if="available_tenants.length > 0" class="border-t border-border px-6 py-4">
                    <p class="mb-3 text-sm font-medium text-foreground">
                        {{ t('app.landlord.planogram_templates.shares.available_tenants') }}
                    </p>
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            v-for="tenant in available_tenants"
                            :key="tenant.id"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-sm transition"
                            :class="selectedTenantIds.includes(tenant.id)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-transparent text-foreground hover:border-primary/60 hover:bg-muted/30'"
                            @click="toggleTenant(tenant.id)"
                        >
                            {{ tenant.name }}
                        </button>
                    </div>
                    <Button
                        :disabled="selectedTenantIds.length === 0 || shareForm.processing"
                        @click="submitShare"
                    >
                        <Share2 class="size-4" />
                        {{ shareForm.processing ? t('app.landlord.planogram_templates.shares.sharing') : t('app.landlord.planogram_templates.shares.share_action') }}
                        <span v-if="selectedTenantIds.length > 0" class="ml-1 opacity-70">({{ selectedTenantIds.length }})</span>
                    </Button>
                </div>
                <div v-else-if="shares.length > 0" class="border-t border-border px-6 py-4">
                    <p class="text-sm text-muted-foreground">
                        {{ t('app.landlord.planogram_templates.shares.no_available_tenants') }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
