<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const CONFIRM_WORDS = ['EXCLUIR', 'DELETAR', 'CONFIRMAR', 'REMOVER'] as const;

const props = withDefaults(
    defineProps<{
        href: string;
        label?: string;
        requireConfirmWord?: boolean;
    }>(),
    {
        label: undefined,
        requireConfirmWord: false,
    },
);

const isOpen = ref(false);
const typed = ref('');
const confirmWord = ref(CONFIRM_WORDS[0]);
const isDeleting = ref(false);

const canConfirm = computed(() => {
    if (!props.requireConfirmWord) return true;
    return typed.value === confirmWord.value;
});

watch(isOpen, (open) => {
    if (open) {
        typed.value = '';
        confirmWord.value = CONFIRM_WORDS[Math.floor(Math.random() * CONFIRM_WORDS.length)];
    }
});

function handleConfirm(): void {
    if (!canConfirm.value) return;
    isDeleting.value = true;
    router.delete(props.href, {
        onFinish: () => {
            isDeleting.value = false;
            isOpen.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="destructive" size="sm">
                <slot>Excluir</slot>
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                        <TriangleAlert class="size-5 text-destructive" />
                    </div>
                    <div>
                        <DialogTitle>
                            {{ label ? `Excluir "${label}"?` : 'Confirmar exclusão?' }}
                        </DialogTitle>
                        <DialogDescription class="mt-0.5">
                            Esta ação não pode ser desfeita.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="requireConfirmWord" class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Digite
                    <span class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono font-semibold text-foreground tracking-wider">{{ confirmWord }}</span>
                    para confirmar:
                </p>
                <Input
                    v-model="typed"
                    :placeholder="confirmWord"
                    class="font-mono tracking-wider"
                    autocomplete="off"
                    @keydown.enter="handleConfirm"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isDeleting" @click="isOpen = false">
                    Cancelar
                </Button>
                <Button variant="destructive" :disabled="!canConfirm || isDeleting" @click="handleConfirm">
                    {{ isDeleting ? 'Excluindo...' : 'Excluir' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
