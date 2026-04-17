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
        storeUrl: string;
        redirectExpand?: string;
        redirectSelected?: string;
    }>(),
    { redirectExpand: '', redirectSelected: '' },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'created'): void;
}>();

const newSubcategoryName = ref('');
const addSubcategoryError = ref('');
const isAdding = ref(false);

watch(() => props.open, (v) => {
    if (v) {
        newSubcategoryName.value = '';
        addSubcategoryError.value = '';
    }
});

function submit() {
    const name = newSubcategoryName.value.trim();
    if (!name) {
        addSubcategoryError.value = 'Informe o nome da categoria.';
        return;
    }
    if (!props.selected) return;
    isAdding.value = true;
    addSubcategoryError.value = '';
    const body: Record<string, string> = { name, category_id: props.selected.id };
    if (props.redirectExpand) body.expand = props.redirectExpand;
    if (props.redirectSelected) body.selected = props.redirectSelected;
    router.post(props.storeUrl, body, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('created');
        },
        onError: (errors: Record<string, unknown>) => {
            const msg = Array.isArray(errors.name) ? errors.name[0] : errors.name;
            addSubcategoryError.value = (msg as string) ?? 'Erro ao criar categoria.';
        },
        onFinish: () => {
            isAdding.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Adicionar subcategoria</DialogTitle>
                <DialogDescription>
                    Informe o nome da nova subcategoria. Ela será criada como filha de
                    <strong>{{ selected?.name ?? '—' }}</strong>.
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-4 py-2" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="new-subcategory-name">Nome</Label>
                    <Input
                        id="new-subcategory-name"
                        v-model="newSubcategoryName"
                        type="text"
                        placeholder="Nome da subcategoria"
                        class="w-full"
                        :disabled="isAdding"
                        autofocus
                    />
                    <p v-if="addSubcategoryError" class="text-sm text-destructive">
                        {{ addSubcategoryError }}
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="isAdding">
                        {{ isAdding ? 'Criando…' : 'Criar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
