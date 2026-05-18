<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import GlobalPlanogramTemplateController from '@/actions/App/Http/Controllers/Landlord/GlobalPlanogramTemplateController';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

const { t } = useT();
const indexPath = GlobalPlanogramTemplateController.index.url().replace(/^\/\/[^/]+/, '');
const fileName = ref<string | null>(null);

const form = useForm({
    file: null as File | null,
});

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.file = file;
    fileName.value = file?.name ?? null;
}

function submit(): void {
    form.post(GlobalPlanogramTemplateController.importMethod.url(), {
        forceFormData: true,
    });
}

const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: t('app.landlord.planogram_templates.navigation'), href: indexPath },
    { title: t('app.landlord.planogram_templates.import.title'), href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('app.landlord.planogram_templates.import.title')" />

        <div class="mx-auto max-w-2xl py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('app.landlord.planogram_templates.import.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground" v-html="t('app.landlord.planogram_templates.import.description')" />
            </div>

            <div class="rounded-xl border border-border bg-card p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label
                            for="file-upload"
                            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border p-10 text-center transition hover:border-primary/60 hover:bg-muted/20"
                            :class="{ 'border-primary bg-primary/5': fileName }"
                        >
                            <FileSpreadsheet class="size-10 text-muted-foreground" :class="{ 'text-primary': fileName }" />
                            <div v-if="fileName">
                                <p class="font-medium text-foreground">{{ fileName }}</p>
                                <p class="text-sm text-muted-foreground">{{ t('app.landlord.planogram_templates.import.file_change') }}</p>
                            </div>
                            <div v-else>
                                <p class="font-medium text-foreground">{{ t('app.landlord.planogram_templates.import.file_select') }}</p>
                                <p class="text-sm text-muted-foreground">{{ t('app.landlord.planogram_templates.import.file_support') }}</p>
                            </div>
                            <input
                                id="file-upload"
                                type="file"
                                accept=".xlsx,.xls"
                                class="hidden"
                                @change="onFileChange"
                            />
                        </label>
                        <p v-if="form.errors.file" class="mt-2 text-sm text-destructive">
                            {{ form.errors.file }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-muted/40 p-4 text-sm">
                        <p class="mb-2 font-medium text-foreground">{{ t('app.landlord.planogram_templates.import.excel_structure') }}</p>
                        <ul class="space-y-1 text-muted-foreground">
                            <li>• <strong>{{ t('app.landlord.planogram_templates.import.sheet_templates_label') }}</strong> {{ t('app.landlord.planogram_templates.import.sheet_templates_desc') }}</li>
                            <li>• <strong>{{ t('app.landlord.planogram_templates.import.sheet_products_label') }}</strong> {{ t('app.landlord.planogram_templates.import.sheet_products_desc') }}</li>
                        </ul>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Button variant="outline" type="button" :as="'a'" :href="indexPath">
                            {{ t('app.landlord.planogram_templates.actions.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="!form.file || form.processing">
                            <Upload class="size-4" />
                            {{ form.processing ? t('app.landlord.planogram_templates.import.importing') : t('app.landlord.planogram_templates.actions.import') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
