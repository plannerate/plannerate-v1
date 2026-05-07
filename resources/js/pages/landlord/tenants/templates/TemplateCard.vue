<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';
import type { TemplateRow, UserOption } from './Index.vue';

const props = defineProps<{
    template: TemplateRow;
    tenantId: string;
    users: UserOption[];
}>();

const emit = defineEmits<{
    edit: [template: TemplateRow];
}>();

const statusLabels: Record<string, string> = {
    draft: 'Rascunho',
    published: 'Publicado',
};

const statusClasses: Record<string, string> = {
    draft: 'border-yellow-400/30 bg-yellow-50 text-yellow-700 dark:bg-yellow-950/20 dark:text-yellow-400',
    published: 'border-primary/30 bg-primary/10 text-primary',
};

const localUserIds = ref([...props.template.user_ids]);
watch(() => props.template.user_ids, (val) => { localUserIds.value = [...val]; });

const flushUsers = useDebounceFn(() => {
    router.patch(tenantWayfinderPath(WorkflowTemplateController.syncUsers.url({ tenant: props.tenantId, template: props.template.id })), {
        user_ids: localUserIds.value,
    }, { preserveScroll: true });
}, 1000);

function onUserChange(userId: string, checked: boolean): void {
    localUserIds.value = checked
        ? [...localUserIds.value, userId]
        : localUserIds.value.filter((id) => id !== userId);
    flushUsers();
}
</script>

<template>
    <div
        class="group overflow-hidden rounded-xl border border-border bg-card transition-all duration-300 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5"
    >
        <div class="p-6">
            <!-- Top: color + order badge + status -->
            <div class="mb-5 flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <!-- Color dot -->
                    <div
                        v-if="template.color"
                        class="size-10 shrink-0 rounded-full ring-4 ring-offset-0 transition-all duration-500 group-hover:ring-primary/20"
                        :style="{ backgroundColor: template.color }"
                    />
                    <div
                        v-else
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted ring-4 ring-muted/30 transition-all duration-500 group-hover:ring-primary/20"
                    >
                        <span class="text-sm font-bold text-muted-foreground">{{ template.suggested_order }}</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">
                            Etapa {{ template.suggested_order }}
                        </p>
                        <h3 class="text-base font-semibold leading-tight">{{ template.name }}</h3>
                    </div>
                </div>

                <span
                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-widest"
                    :class="statusClasses[template.status] ?? 'border-border bg-muted text-muted-foreground'"
                >
                    {{ statusLabels[template.status] ?? template.status }}
                </span>
            </div>

            <!-- Description -->
            <p v-if="template.description" class="mb-4 line-clamp-2 text-sm text-muted-foreground">
                {{ template.description }}
            </p>

            <!-- Meta -->
            <div class="mb-5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span v-if="template.estimated_duration_days">
                    {{ template.estimated_duration_days }}d estimados
                </span>
                <span v-if="template.is_required_by_default" class="font-medium text-primary">
                    Obrigatória
                </span>
            </div>

            <!-- Suggested users inline checkboxes -->
            <div class="border-t border-border pt-4">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">Usuários sugeridos</p>
                <p v-if="users.length === 0" class="text-sm text-muted-foreground">Nenhum usuário disponível</p>
                <div v-else class="flex flex-wrap gap-1.5">
                    <label
                        v-for="user in users"
                        :key="user.id"
                        class="flex cursor-pointer items-center gap-1.5 rounded-full border border-input px-3 py-1 text-sm transition-colors hover:bg-accent has-checked:border-primary/60 has-checked:bg-primary/5 has-checked:text-primary"
                    >
                        <input
                            type="checkbox"
                            :value="user.id"
                            :checked="localUserIds.includes(user.id)"
                            class="accent-primary"
                            @change="onUserChange(user.id, ($event.target as HTMLInputElement).checked)"
                        />
                        <span class="font-medium">{{ user.name }}</span>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-4 border-t border-border pt-4">
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                        @click="emit('edit', template)"
                    >
                        <Edit class="size-3" />
                        Editar
                    </button>
                    <WayfinderLink
                        :href="WorkflowTemplateController.destroy.url({ tenant: tenantId, template: template.id })"
                        method="delete"
                        as="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/20"
                    >
                        <Trash2 class="size-3" />
                        Excluir
                    </WayfinderLink>
                </div>
            </div>
        </div>
    </div>
</template>
