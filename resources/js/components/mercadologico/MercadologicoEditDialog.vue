<script setup lang="ts">
import { ref, watch } from 'vue';
import type { CategoryNode } from '@/composables/useMercadologicoTree';
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
import { router } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        open: boolean;
        selected: CategoryNode | null;
        updateUrl: string;
        redirectExpand?: string;
        redirectSelected?: string;
    }>(),
    { redirectExpand: '', redirectSelected: '' },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const editName = ref('');
const editSlug = ref('');
const editError = ref('');
const isUpdating = ref(false);

watch(
    () => props.open,
    (v) => {
        if (v) {
            editName.value = props.selected?.name ?? '';
            editSlug.value = props.selected?.slug ?? '';
            editError.value = '';
        }
    },
);

function submit() {
    const name = editName.value.trim();
    if (!name) {
        editError.value = 'Informe o nome da categoria.';
        return;
    }
    if (!props.selected) {
        return;
    }
    isUpdating.value = true;
    editError.value = '';

    const payload: Record<string, string> = { id: props.selected.id, name };
    const slug = editSlug.value.trim();
    if (slug) payload.slug = slug;
    if (props.redirectExpand) payload.expand = props.redirectExpand;
    if (props.redirectSelected) payload.selected = props.redirectSelected;

    router.patch(props.updateUrl, payload, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('updated');
        },
        onError: (errors: Record<string, unknown>) => {
            const msg = Array.isArray(errors.name) ? errors.name[0] : (errors.name ?? errors.slug ?? errors.id);
            editError.value = (msg as string) ?? 'Erro ao atualizar categoria.';
        },
        onFinish: () => {
            isUpdating.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Editar categoria</DialogTitle>
                <DialogDescription>
                    Altere o nome ou slug de <strong>{{ selected?.name ?? '—' }}</strong>.
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-4 py-2" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="edit-category-name">Nome</Label>
                    <Input
                        id="edit-category-name"
                        v-model="editName"
                        type="text"
                        placeholder="Nome da categoria"
                        class="w-full"
                        :disabled="isUpdating"
                        autofocus
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-category-slug">
                        Slug
                        <span class="ml-1 text-xs text-muted-foreground">(opcional)</span>
                    </Label>
                    <Input
                        id="edit-category-slug"
                        v-model="editSlug"
                        type="text"
                        placeholder="slug-da-categoria"
                        class="w-full"
                        :disabled="isUpdating"
                    />
                </div>
                <p v-if="editError" class="text-sm text-destructive">
                    {{ editError }}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="isUpdating">
                        {{ isUpdating ? 'Salvando…' : 'Salvar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
