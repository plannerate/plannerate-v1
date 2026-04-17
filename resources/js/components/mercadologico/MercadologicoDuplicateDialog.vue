<script setup lang="ts">
import type { CategoryNode } from '@/composables/useMercadologicoTree';
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { router } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        open: boolean;
        selected: CategoryNode | null;
        duplicateUrl: string;
        redirectExpand?: string;
        redirectSelected?: string;
    }>(),
    { redirectExpand: '', redirectSelected: '' },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'duplicated'): void;
}>();

const isDuplicating = ref(false);

function confirmDuplicate() {
    if (!props.selected || !props.duplicateUrl) return;
    isDuplicating.value = true;
    const body: Record<string, string> = { id: props.selected.id };
    if (props.redirectExpand) body.expand = props.redirectExpand;
    if (props.redirectSelected) body.selected = props.redirectSelected;
    router.post(props.duplicateUrl, body, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('duplicated');
        },
        onFinish: () => {
            isDuplicating.value = false;
        },
    });
}
</script>

<template>
    <AlertDialog :open="open" @update:open="(v) => emit('update:open', v)">
        <AlertDialogContent class="sm:max-w-md">
            <AlertDialogHeader>
                <AlertDialogTitle>Duplicar categoria?</AlertDialogTitle>
                <AlertDialogDescription>
                    Será criada uma cópia de
                    <strong>{{ selected?.name ?? '—' }}</strong>
                    no mesmo nível (como irmã da categoria atual). A cópia não inclui subcategorias.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction
                    :disabled="isDuplicating"
                    @click.prevent="confirmDuplicate"
                >
                    {{ isDuplicating ? 'Duplicando…' : 'Duplicar' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
