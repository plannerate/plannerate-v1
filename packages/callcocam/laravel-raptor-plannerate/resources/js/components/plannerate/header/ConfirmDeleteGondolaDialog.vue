<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

interface Props {
    open: boolean;
    gondolaName?: string;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    gondolaName: '',
});

const emit = defineEmits<Emits>();
const { t } = useT();

// Palavra de confirmação aleatória
const confirmationWords = ['DELETAR', 'REMOVER', 'CONFIRMAR', 'EXCLUIR', 'APAGAR'];

const confirmationWord = ref('');
const userInput = ref('');

// Gera palavra aleatória quando o dialog abre
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            confirmationWord.value =
                confirmationWords[
                    Math.floor(Math.random() * confirmationWords.length)
                ];
            userInput.value = '';
        }
    },
);

const isConfirmationValid = computed(() => {
    return userInput.value === confirmationWord.value;
});

const handleConfirm = () => {
    if (isConfirmationValid.value) {
        emit('confirm');
        emit('update:open', false);
    }
};

const handleCancel = () => {
    emit('update:open', false);
};
</script>

<template>
    <AlertDialog :open="open" @update:open="(val) => emit('update:open', val)">
        <AlertDialogContent class="max-w-full md:max-w-xl z-[1000]">
            <AlertDialogHeader>
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                    >
                        <AlertTriangle class="size-6 text-destructive" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <AlertDialogTitle class="text-xl">
                            {{ t('plannerate.confirm_delete_gondola.title') }}
                        </AlertDialogTitle>
                        <AlertDialogDescription class="text-base">
                            {{ t('plannerate.confirm_delete_gondola.description_prefix') }}
                            <span class="font-semibold text-foreground">
                                {{ gondolaName || t('plannerate.confirm_delete_gondola.this_gondola') }}
                            </span>
                            {{ t('plannerate.confirm_delete_gondola.description_suffix') }}
                        </AlertDialogDescription>
                    </div>
                </div>
            </AlertDialogHeader>

            <div class="space-y-4">
                <div
                    class="rounded-lg border border-destructive/20 bg-destructive/5 p-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('plannerate.confirm_delete_gondola.this_action_removes') }}
                    </p>
                    <ul class="mt-2 space-y-1 text-sm text-foreground">
                        <li class="flex items-center gap-2">
                            <span
                                class="size-1.5 rounded-full bg-destructive"
                            ></span>
                            {{ t('plannerate.confirm_delete_gondola.remove_modules_sections') }}
                        </li>
                        <li class="flex items-center gap-2">
                            <span
                                class="size-1.5 rounded-full bg-destructive"
                            ></span>
                            {{ t('plannerate.confirm_delete_gondola.remove_shelves') }}
                        </li>
                        <li class="flex items-center gap-2">
                            <span
                                class="size-1.5 rounded-full bg-destructive"
                            ></span>
                            {{ t('plannerate.confirm_delete_gondola.remove_positioned_products') }}
                        </li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <Label for="confirmation" class="text-sm font-medium">
                        {{ t('plannerate.confirm_delete_gondola.type_to_confirm') }}
                        <span
                            class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-sm font-bold text-foreground"
                        >
                            {{ confirmationWord }}
                        </span>
                        {{ t('plannerate.confirm_delete_gondola.below') }}
                    </Label>
                    <Input
                        id="confirmation"
                        v-model="userInput"
                        type="text"
                        :placeholder="t('plannerate.confirm_delete_gondola.type_here')"
                        class="font-mono uppercase"
                        @keyup.enter="handleConfirm"
                        autofocus
                    />
                    <p
                        v-if="userInput && !isConfirmationValid"
                        class="text-xs text-destructive"
                    >
                        {{ t('plannerate.confirm_delete_gondola.invalid_text') }}
                    </p>
                </div>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel @click="handleCancel">
                    {{ t('app.actions.cancel') }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :disabled="!isConfirmationValid"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="handleConfirm"
                >
                    {{ t('plannerate.confirm_delete_gondola.confirm_action') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
