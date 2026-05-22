<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Layers, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import PlanogramConfirmDialog from '@/components/planogram-templates/PlanogramConfirmDialog.vue';
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
}>();

const { t } = useT();
const indexPath = PlanogramTemplateController.index
    .url()
    .replace(/^\/\/[^/]+/, '');
const deleteDialogOpen = ref(false);
const deleteDialogBusy = ref(false);

const breadcrumbs = [
    {
        title: t('app.navigation.dashboard'),
        href: dashboard.url().replace(/^\/\/[^/]+/, ''),
    },
    { title: t('app.tenant.planogram_templates.navigation'), href: indexPath },
    { title: props.template.code, href: '#' },
];

function confirmDelete(): void {
    deleteDialogOpen.value = true;
}

function deleteTemplate(): void {
    deleteDialogBusy.value = true;

    router.delete(
        PlanogramTemplateController.destroy.url({
            planogramTemplate: props.template.id,
        }),
        {
            onFinish: () => {
                deleteDialogBusy.value = false;
                deleteDialogOpen.value = false;
            },
        },
    );
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
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ template.name }}
                        </h1>
                        <Badge
                            :variant="
                                template.is_active ? 'default' : 'secondary'
                            "
                        >
                            {{
                                template.is_active
                                    ? t(
                                          'app.tenant.planogram_templates.status.active',
                                      )
                                    : t(
                                          'app.tenant.planogram_templates.status.inactive',
                                      )
                            }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            t('app.tenant.planogram_templates.show.code_prefix')
                        }}
                        <strong>{{ template.code }}</strong> ·
                        {{
                            t(
                                'app.tenant.planogram_templates.show.department_prefix',
                            )
                        }}
                        <strong>{{ template.department }}</strong>
                    </p>
                    <p
                        v-if="template.description"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ template.description }}
                    </p>
                </div>
                <Button variant="destructive" size="sm" @click="confirmDelete">
                    <Trash2 class="size-4" />
                    {{ t('app.tenant.planogram_templates.actions.delete') }}
                </Button>
            </div>

            <!-- Subtemplates -->
            <div class="rounded-xl border border-border bg-card">
                <div class="border-b border-border px-6 py-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <Layers class="size-4 text-muted-foreground" />
                        {{
                            t(
                                'app.tenant.planogram_templates.show.subtemplates_title',
                            )
                        }}
                        ({{ template.subtemplates_count }})
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            t(
                                'app.tenant.planogram_templates.show.subtemplates_description',
                            )
                        }}
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
                                {{ sub.num_modules }}
                                {{
                                    sub.num_modules !== 1
                                        ? t(
                                              'app.tenant.planogram_templates.show.modules_plural',
                                          )
                                        : t(
                                              'app.tenant.planogram_templates.show.modules_singular',
                                          )
                                }}
                            </p>
                        </div>
                        <Badge variant="outline"
                            >{{ sub.slots_count }}
                            {{
                                sub.slots_count !== 1
                                    ? t(
                                          'app.tenant.planogram_templates.show.slots_plural',
                                      )
                                    : t(
                                          'app.tenant.planogram_templates.show.slots_singular',
                                      )
                            }}</Badge
                        >
                    </div>
                    <div
                        v-if="template.subtemplates.length === 0"
                        class="px-6 py-8 text-center text-sm text-muted-foreground"
                    >
                        {{
                            t(
                                'app.tenant.planogram_templates.show.empty_subtemplates',
                            )
                        }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <PlanogramConfirmDialog
        v-model:open="deleteDialogOpen"
        :title="
            t('app.tenant.planogram_templates.show.confirm_delete', {
                name: props.template.name,
            })
        "
        description="Esta ação não pode ser desfeita."
        :confirm-label="t('app.tenant.planogram_templates.actions.delete')"
        kind="delete"
        :busy="deleteDialogBusy"
        @confirm="deleteTemplate"
    />
</template>
