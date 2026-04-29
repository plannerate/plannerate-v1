<script setup lang="ts">
import { Check, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Layer, Section, Shelf } from '../../../types/planogram';

interface Props {
    open: boolean;
    type?: 'section' | 'shelf' | 'layer';
    item?: Section | Shelf | Layer;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    type: undefined,
    item: undefined,
});

const emit = defineEmits<Emits>();
const isBrowser = typeof window !== 'undefined';

const dontAskAgain = ref(false);

// Reseta checkbox quando modal abre
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            dontAskAgain.value = false;
        }
    },
);

function handleConfirm() {
    // Salva preferência no localStorage se marcado
    if (dontAskAgain.value) {
        const expiryTime = Date.now() + 5 * 60 * 1000; // 5 minutos

        if (isBrowser) {
            window.localStorage.setItem(
                `planogram-delete-confirm-${props.type}`,
                expiryTime.toString(),
            );
        }
    }

    emit('confirm');
    emit('update:open', false);
}

function handleCancel() {
    emit('update:open', false);
}

// Mensagens dinâmicas baseadas no tipo
const itemName = computed(() => {
    if (!props.item) {
return '';
}

    if (props.type === 'section') {
        return (props.item as Section).name || 'esta seção';
    }

    if (props.type === 'shelf') {
        return `prateleira #${(props.item as Shelf).ordering || ''}`;
    }

    if (props.type === 'layer') {
        return 'esta camada de produto';
    }

    return 'este item';
});

const itemTypeLabel = computed(() => {
    switch (props.type) {
        case 'section':
            return 'Seção';
        case 'shelf':
            return 'Prateleira';
        case 'layer':
            return 'Camada de Produto';
        default:
            return 'Item';
    }
});
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val) => emit('update:open', val)"
        data-modal
        class="z-[1000]"
    >
        <DialogContent class="max-w-full md:max-w-2xl z-[1000]">
            <DialogHeader>
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                    >
                        <Trash2 class="size-6 text-destructive" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <DialogTitle class="text-xl">
                            Remover {{ itemTypeLabel }}?
                        </DialogTitle>
                        <DialogDescription class="text-base">
                            Você está prestes a remover
                            <span class="font-semibold text-foreground">
                                {{ itemName }}
                            </span>
                            .
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    class="rounded-lg border border-destructive/20 bg-destructive/5 p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Esta ação irá marcar o item como removido. Você pode
                        restaurá-lo posteriormente se necessário.
                    </p>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="dont-ask-again"
                        :model-value="dontAskAgain"
                        @update:model-value="
                            (val: boolean | 'indeterminate') =>
                                (dontAskAgain =
                                    val === 'indeterminate'
                                        ? false
                                        : (val ?? false))
                        "
                    >
                        <template #indicator>
                            <Check class="size-3.5 text-primary" />
                        </template>
                    </Checkbox>
                    <label
                        for="dont-ask-again"
                        class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                        Não perguntar novamente por 5 minutos
                    </label>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    Cancelar
                </Button>
                <Button variant="destructive" @click="handleConfirm">
                    Remover
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
