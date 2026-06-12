<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Info, Loader2, Search, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SalesDataSection from '@/components/plannerate/header/partials/SalesDataSection.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useT } from '@/composables/useT';
import type { Gondola } from '@/types/planogram';

interface TemplateOption {
    value: string;
    label: string;
    description?: string;
}

const props = defineProps<{
    open: boolean;
    gondola: Gondola;
    startDate?: string;
    endDate?: string;
    planogramTemplates?: TemplateOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

/** True quando a gôndola já foi gerada pelo automático e agora segue o fluxo de template. */
const isAutoTemplateMode = computed(
    () => props.gondola.generation_mode === 'template' && props.gondola.template_origin === 'auto',
);

const form = useForm({
    strategy: 'abc' as 'abc' | 'sales' | 'margin' | 'mix',
    use_existing_analysis: false,
    start_date: props.startDate ?? '',
    end_date: props.endDate ?? '',
    min_facings: 1,
    max_facings: 10,
    group_by_subcategory: true,
    include_products_without_sales: false,
    table_type: 'monthly_summaries' as 'sales' | 'monthly_summaries',
    category_id: null as string | null,
    template_id: props.gondola.template_id ?? null as string | null,
});

const templateComboboxOpen = ref(false);
const templateSearch = ref('');

const normalizedTemplates = computed(() =>
    (props.planogramTemplates ?? [])
        .filter((template) => !!template?.value)
        .map((template) => ({
            value: template.value,
            label: template.label?.trim() || t('plannerate.header.auto_generate.template_no_name'),
            description: template.description?.trim() || t('plannerate.header.auto_generate.template_no_department'),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR')),
);

const filteredTemplates = computed(() => {
    const search = templateSearch.value.trim().toLocaleLowerCase('pt-BR');

    if (!search) {
        return normalizedTemplates.value;
    }

    return normalizedTemplates.value.filter((template) => {
        const haystack = `${template.label} ${template.description}`.toLocaleLowerCase('pt-BR');

        return haystack.includes(search);
    });
});

const selectedTemplate = computed(
    () => normalizedTemplates.value.find((template) => template.value === form.template_id) ?? null,
);

const isFormValid = computed(() => {
    if (!form.template_id) {
        return false;
    }

    if (!form.use_existing_analysis) {
        return !!(form.start_date && form.end_date && form.start_date <= form.end_date);
    }

    return true;
});

watch(
    () => [props.startDate, props.endDate] as const,
    ([newStart, newEnd]) => {
        if (newStart) {
            form.start_date = newStart;
        }

        if (newEnd) {
            form.end_date = newEnd;
        }
    },
);

function handleClose(): void {
    emit('update:open', false);
    form.reset();
    templateComboboxOpen.value = false;
    templateSearch.value = '';
}

function handleGenerate(): void {
    if (!isFormValid.value) {
        return;
    }

    form.post(`/api/gondolas/${props.gondola.id}/auto-generate`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
        onError: (errors) => {
            alert(
                t('plannerate.header.auto_generate.error_prefix') +
                    (Object.values(errors)[0] || t('plannerate.header.auto_generate.unknown_error')),
            );
        },
    });
}

function selectTemplate(templateId: string): void {
    form.template_id = templateId;
    templateComboboxOpen.value = false;
}
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="flex max-h-[90vh] max-w-full flex-col md:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Sparkles class="size-5 text-primary" />
                    {{ t('plannerate.header.auto_generate.template_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('plannerate.header.auto_generate.template_description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-6 overflow-x-hidden overflow-y-auto py-4">
                <!-- Aviso de transição automático → template -->
                <div
                    v-if="isAutoTemplateMode"
                    class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950"
                >
                    <Info class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        {{ t('plannerate.header.auto_generate.auto_template_notice') }}
                    </p>
                </div>

                <!-- Seleção de template -->
                <div class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                    <Label class="text-sm font-medium text-amber-900 dark:text-amber-100">{{ t('plannerate.header.auto_generate.template_selector_label') }}</Label>
                    <Popover v-model:open="templateComboboxOpen">
                        <PopoverTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                role="combobox"
                                :aria-expanded="templateComboboxOpen"
                                class="w-full justify-between bg-background font-normal"
                            >
                                <span class="truncate">
                                    {{ selectedTemplate?.label ?? t('plannerate.header.auto_generate.template_selector_placeholder') }}
                                </span>
                                <ChevronsUpDown class="ml-2 size-4 shrink-0 opacity-60" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[var(--reka-popover-trigger-width)] p-2">
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="templateSearch"
                                    class="pl-9"
                                    :placeholder="t('plannerate.header.auto_generate.template_search_placeholder')"
                                />
                            </div>
                            <div class="mt-2 max-h-56 overflow-y-auto rounded-md border bg-background">
                                <button
                                    v-for="tpl in filteredTemplates"
                                    :key="tpl.value"
                                    type="button"
                                    class="flex w-full items-start justify-between gap-2 border-b px-3 py-2 text-left last:border-b-0 hover:bg-muted/50"
                                    :class="form.template_id === tpl.value ? 'bg-muted/70' : ''"
                                    @click="selectTemplate(tpl.value)"
                                >
                                    <span class="flex min-w-0 flex-col">
                                        <span class="truncate text-sm font-medium">{{ tpl.label }}</span>
                                        <span class="truncate text-xs text-muted-foreground">{{ tpl.description }}</span>
                                    </span>
                                    <Check v-if="form.template_id === tpl.value" class="mt-0.5 size-4 shrink-0 text-primary" />
                                </button>
                                <div
                                    v-if="filteredTemplates.length === 0"
                                    class="px-3 py-4 text-center text-sm text-muted-foreground"
                                >
                                    {{ t('plannerate.header.auto_generate.template_none_found') }}
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                    <div v-if="selectedTemplate" class="rounded-md border bg-background/70 p-3 text-xs">
                        <p class="font-medium text-foreground">{{ selectedTemplate.label }}</p>
                        <p class="text-muted-foreground">{{ selectedTemplate.description }}</p>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        {{ t('plannerate.header.auto_generate.template_closest_subtemplate_hint') }}
                    </p>
                </div>

                <!-- Aviso: configurações de slot vêm do template, não deste modal -->
                <div class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-3">
                    <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <p class="text-xs text-muted-foreground">
                        {{ t('plannerate.header.auto_generate.defined_by_template') }}
                    </p>
                </div>

                <div class="border-t" />
                <SalesDataSection :form="form" />
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="form.processing" @click="handleClose">
                    {{ t('plannerate.common.cancel') }}
                </Button>
                <Button
                    type="button"
                    class="gap-2"
                    :disabled="!isFormValid || form.processing"
                    @click="handleGenerate"
                >
                    <Loader2 v-if="form.processing" class="animate-spin" />
                    <Sparkles v-else />
                    {{ form.processing ? t('plannerate.header.auto_generate.generating') : t('plannerate.header.auto_generate.generate') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
