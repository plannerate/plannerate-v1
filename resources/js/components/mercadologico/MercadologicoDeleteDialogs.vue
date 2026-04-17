<script setup lang="ts">
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
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';

export interface CategoryUsage {
    children_count: number;
    products_count: number;
    planograms_count: number;
}

const props = withDefaults(
    defineProps<{
        showDeleteConfirm: boolean;
        showDeleteBlocked: boolean;
        deleteBlockedMessage: string;
        selectedId: string | null;
        destroyUrl: string;
        usage: CategoryUsage | null;
        redirectExpand?: string;
        redirectSelected?: string;
    }>(),
    { redirectExpand: '', redirectSelected: '' },
);

const emit = defineEmits<{
    (e: 'update:showDeleteConfirm', v: boolean): void;
    (e: 'update:showDeleteBlocked', v: boolean): void;
    (e: 'update:deleteBlockedMessage', v: string): void;
    (e: 'deleted'): void;
}>();

const isDeleting = ref(false);

function confirmDelete() {
    if (!props.selectedId || !props.destroyUrl) return;
    isDeleting.value = true;
    const params = new URLSearchParams({ id: props.selectedId });
    if (props.redirectExpand) params.set('expand', props.redirectExpand);
    if (props.redirectSelected) params.set('selected', props.redirectSelected);
    const url = `${props.destroyUrl}?${params.toString()}`;
    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:showDeleteConfirm', false);
            emit('deleted');
        },
        onError: (errors: Record<string, unknown>) => {
            const msg = (errors.destroy as string) ?? (errors.id as string) ?? 'Não foi possível excluir a categoria.';
            emit('update:deleteBlockedMessage', msg);
            emit('update:showDeleteBlocked', true);
            emit('update:showDeleteConfirm', false);
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="showDeleteBlocked" @update:open="(v) => emit('update:showDeleteBlocked', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Não é possível excluir</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                {{ deleteBlockedMessage }}
            </p>
            <DialogFooter>
                <Button variant="outline" @click="emit('update:showDeleteBlocked', false)">
                    Entendi
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="showDeleteConfirm" @update:open="(v) => emit('update:showDeleteConfirm', v)">
        <AlertDialogContent class="sm:max-w-md">
            <AlertDialogHeader>
                <AlertDialogTitle>Remover categoria?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esta categoria será excluída. Esta ação não pode ser desfeita.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="isDeleting"
                    @click.prevent="confirmDelete"
                >
                    {{ isDeleting ? 'Excluindo…' : 'Excluir' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
