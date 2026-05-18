<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import PlanogramTemplateController from '@/actions/App/Http/Controllers/Tenant/PlanogramTemplateController';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    subdomain: string;
}>();

const { t } = useT();
const indexPath = PlanogramTemplateController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');
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
    form.post(PlanogramTemplateController.import.url(props.subdomain), {
        forceFormData: true,
    });
}

const breadcrumbs = [
    { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
    { title: 'Templates de Planograma', href: indexPath },
    { title: 'Importar Template', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Importar Template de Planograma" />

        <div class="mx-auto max-w-2xl py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">Importar Template de Planograma</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Faça o upload de um arquivo Excel (.xlsx) com as abas <strong>Templates</strong> e <strong>Produtos</strong>.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- File drop zone -->
                    <div>
                        <label
                            for="file-upload"
                            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border p-10 text-center transition hover:border-primary/60 hover:bg-muted/20"
                            :class="{ 'border-primary bg-primary/5': fileName }"
                        >
                            <FileSpreadsheet class="size-10 text-muted-foreground" :class="{ 'text-primary': fileName }" />
                            <div v-if="fileName">
                                <p class="font-medium text-foreground">{{ fileName }}</p>
                                <p class="text-sm text-muted-foreground">Clique para trocar o arquivo</p>
                            </div>
                            <div v-else>
                                <p class="font-medium text-foreground">Clique para selecionar o arquivo</p>
                                <p class="text-sm text-muted-foreground">Suporte: .xlsx, .xls — máx. 10 MB</p>
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

                    <!-- Structure info -->
                    <div class="rounded-lg bg-muted/40 p-4 text-sm">
                        <p class="mb-2 font-medium text-foreground">Estrutura esperada do Excel:</p>
                        <ul class="space-y-1 text-muted-foreground">
                            <li>• <strong>Aba Templates:</strong> Código, Departamento, Subtemplate, Módulos, Prateleira, Categoria, Subcategoria, Agrupamento, Frentes, Ordenação, etc.</li>
                            <li>• <strong>Aba Produtos:</strong> EAN, Descrição, Departamento, Categoria, Subcategoria, Agrupamento, Marca, Embalagem</li>
                        </ul>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Button
                            variant="outline"
                            type="button"
                            :as="'a'"
                            :href="indexPath"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :disabled="!form.file || form.processing"
                        >
                            <Upload class="size-4" />
                            {{ form.processing ? 'Importando...' : 'Importar Template' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
