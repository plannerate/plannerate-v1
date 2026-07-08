<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';

import type { CategoryFormData } from './useCategoryCrud';

const props = defineProps<{
    open: boolean;
    mode: 'create-root' | 'create-child' | 'edit';
    parentName?: string | null;
    initial?: CategoryFormData | null;
    saving?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [data: CategoryFormData];
}>();

const { t } = useT();

const name = ref('');
const codigo = ref<string>('');
const status = ref('draft');
const nameInput = ref<InstanceType<typeof Input> | null>(null);

const title = () => {
    if (props.mode === 'edit') {
        return t('app.landlord.mercadologico.form.edit_title');
    }

    if (props.mode === 'create-child') {
        return t('app.landlord.mercadologico.form.create_child_title', {
            parent: props.parentName ?? '',
        });
    }

    return t('app.landlord.mercadologico.form.create_root_title');
};

// Reseta os campos toda vez que a modal abre.
watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        name.value = props.initial?.name ?? '';
        codigo.value =
            props.initial?.codigo != null ? String(props.initial.codigo) : '';
        status.value = props.initial?.status ?? 'draft';

        void nextTick(() => {
            const el = (nameInput.value as unknown as { $el?: HTMLElement })?.$el;
            el?.querySelector?.('input')?.focus();
        });
    },
);

function onOpenChange(open: boolean): void {
    emit('update:open', open);
}

function submit(): void {
    const trimmed = name.value.trim();

    if (trimmed === '' || props.saving) {
        return;
    }

    const parsedCodigo = codigo.value.trim() === '' ? null : Number(codigo.value);

    emit('submit', {
        name: trimmed,
        codigo: Number.isFinite(parsedCodigo as number) ? (parsedCodigo as number) : null,
        status: status.value,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title() }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="merc-cat-name">{{ t('app.landlord.mercadologico.form.name') }}</Label>
                    <Input
                        id="merc-cat-name"
                        ref="nameInput"
                        v-model="name"
                        :placeholder="t('app.landlord.mercadologico.form.name_placeholder')"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="merc-cat-codigo">{{ t('app.landlord.mercadologico.form.codigo') }}</Label>
                        <Input
                            id="merc-cat-codigo"
                            v-model="codigo"
                            type="number"
                            :placeholder="t('app.landlord.mercadologico.form.codigo_placeholder')"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="merc-cat-status">{{ t('app.landlord.mercadologico.form.status') }}</Label>
                        <select
                            id="merc-cat-status"
                            v-model="status"
                            class="h-9 w-full rounded-lg border border-input bg-background px-2 text-sm outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="draft">{{ t('app.landlord.mercadologico.form.status_draft') }}</option>
                            <option value="published">{{ t('app.landlord.mercadologico.form.status_published') }}</option>
                            <!-- Só aparece quando a categoria já é 'importer' (importada), para
                                 não forçar a troca de status ao editar. -->
                            <option v-if="status === 'importer'" value="importer">
                                {{ t('app.landlord.mercadologico.form.status_importer') }}
                            </option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="saving" @click="onOpenChange(false)">
                        {{ t('app.landlord.mercadologico.form.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="saving || name.trim() === ''">
                        <Spinner v-if="saving" class="size-4" />
                        {{ t('app.landlord.mercadologico.form.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
