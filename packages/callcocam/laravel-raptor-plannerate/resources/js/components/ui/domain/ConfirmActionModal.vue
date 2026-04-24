<script setup lang="ts">
import { ref } from 'vue';
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

interface Props {
    open: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    /**
     * Se definido, ativa o modo de confirmação por digitação.
     * O usuário precisa digitar este token para habilitar o botão de confirmar.
     */
    confirmationToken?: string;
    destructive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Confirmar ação',
    description: 'Tem certeza que deseja continuar? Esta ação não pode ser desfeita.',
    confirmLabel: 'Confirmar',
    confirmationToken: undefined,
    destructive: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
    cancel: [];
}>();

const typedToken = ref('');

function handleConfirm() {
    if (props.confirmationToken && typedToken.value !== props.confirmationToken) {
        return;
    }
    emit('confirm');
    emit('update:open', false);
    typedToken.value = '';
}

function handleCancel() {
    emit('cancel');
    emit('update:open', false);
    typedToken.value = '';
}

const isConfirmEnabled = () => {
    if (props.confirmationToken) {
        return typedToken.value === props.confirmationToken;
    }

    return true;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleCancel">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle :class="destructive ? 'text-error' : ''">
                    {{ title }}
                </DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <!-- Modo de confirmação por digitação -->
            <div v-if="confirmationToken" class="space-y-3">
                <p class="text-xs font-mono text-on-surface-variant">
                    Para confirmar, digite <strong class="text-on-surface font-mono">{{ confirmationToken }}</strong> abaixo:
                </p>
                <Input
                    v-model="typedToken"
                    placeholder="Digite para confirmar..."
                    class="font-mono"
                    autocomplete="off"
                />
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="handleCancel">
                    Cancelar
                </Button>
                <Button
                    :disabled="!isConfirmEnabled()"
                    :class="destructive
                        ? 'bg-error text-white hover:bg-error/90'
                        : 'bg-primary text-on-primary hover:brightness-95'"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
