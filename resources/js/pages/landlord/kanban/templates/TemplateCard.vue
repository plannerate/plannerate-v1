<script setup lang="ts">
import { Edit, Trash2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import WorkflowTemplateController from '@/actions/App/Http/Controllers/Landlord/WorkflowTemplateController';
import type { TemplateRow } from './Index.vue';

const props = defineProps<{
    template: TemplateRow;
    tenantId: string;
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
                        :style="{ backgroundColor: template.color, ringColor: template.color + '33' }"
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
                <span v-if="template.user_ids.length > 0">
                    {{ template.user_ids.length }} usuário(s) sugerido(s)
                </span>
            </div>

            <!-- Actions -->
            <div class="border-t border-border pt-4">
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                        @click="emit('edit', template)"
                    >
                        <Edit class="size-3" />
                        Editar
                    </button>
                    <Link
                        :href="WorkflowTemplateController.destroy.url(tenantId, template.id)"
                        method="delete"
                        as="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/20"
                    >
                        <Trash2 class="size-3" />
                        Excluir
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
