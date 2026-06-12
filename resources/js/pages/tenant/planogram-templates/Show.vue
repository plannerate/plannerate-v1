<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Layers, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import PlanogramTemplateController from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Templates/PlanogramTemplateController';
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
    layout_orientation: string | null;
    flow_direction: string | null;
    hot_zone_priority: string | null;
    cold_zone_priority: string | null;
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

/**
 * Badges das configurações globais do subtemplate (exibidos só quando não-nulos).
 * Labels reutilizam as mesmas chaves de tradução da tela de Slots.
 */
function subtemplateBadges(sub: Subtemplate): string[] {
    const badges: string[] = [];

    if (sub.layout_orientation) {
        badges.push(t(`planogram-templates.layout_orientation.${sub.layout_orientation}`));
    }

    if (sub.flow_direction === 'left_to_right') {
        badges.push(t('planogram-templates.flow_direction.left_to_right'));
    } else if (sub.flow_direction === 'right_to_left') {
        badges.push(t('planogram-templates.flow_direction.right_to_left'));
    }

    if (sub.hot_zone_priority && sub.hot_zone_priority !== 'none') {
        badges.push(t('planogram-templates.show_page.badge_hot_zone', {
            label: t(`planogram-templates.zone_priority.hot.${sub.hot_zone_priority}`),
        }));
    }

    if (sub.cold_zone_priority && sub.cold_zone_priority !== 'none') {
        badges.push(t('planogram-templates.show_page.badge_cold_zone', {
            label: t(`planogram-templates.zone_priority.cold.${sub.cold_zone_priority}`),
        }));
    }

    return badges;
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
        <Head :title="t('planogram-templates.show_page.head_title', { code: template.code })" />

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
                            <!-- Configurações globais do subtemplate (layout / fluxo / zonas) -->
                            <div
                                v-if="subtemplateBadges(sub).length > 0"
                                class="mt-1.5 flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="badge in subtemplateBadges(sub)"
                                    :key="badge"
                                    variant="secondary"
                                    class="text-xs font-normal"
                                >
                                    {{ badge }}
                                </Badge>
                            </div>
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
        :description="t('planogram-templates.show_page.cannot_undo')"
        :confirm-label="t('app.tenant.planogram_templates.actions.delete')"
        kind="delete"
        :busy="deleteDialogBusy"
        @confirm="deleteTemplate"
    />
</template>
