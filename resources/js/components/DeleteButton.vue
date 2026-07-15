<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2, TriangleAlert } from 'lucide-vue-next';
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
import { useT } from '@/composables/useT';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

const CONFIRM_WORDS = ['EXCLUIR', 'DELETAR', 'CONFIRMAR', 'REMOVER'] as const;

const props = withDefaults(
    defineProps<{
        href: string;
        /** Nome do registro, exibido no título do modal. */
        label?: string;
        /** Rótulo do botão/verbo (padrão: "Excluir" ou "Excluir definitivamente"). */
        text?: string;
        /** Exclusão definitiva (força cópia irreversível mais forte). */
        permanent?: boolean;
        requireConfirmWord?: boolean;
    }>(),
    {
        label: undefined,
        text: undefined,
        permanent: false,
        requireConfirmWord: false,
    },
);

const { t } = useT();

const isOpen = ref(false);
const typed = ref('');
const confirmWord = ref<typeof CONFIRM_WORDS[number]>(CONFIRM_WORDS[0]);
const isDeleting = ref(false);

const buttonText = computed(() =>
    props.text ?? (props.permanent ? t('app.common.actions.delete_permanent') : t('app.common.actions.delete')),
);

const dialogTitle = computed(() => {
    if (props.permanent) {
        return props.label
            ? t('app.common.delete_dialog.title_permanent_named', { label: props.label })
            : t('app.common.delete_dialog.title_permanent');
    }

    return props.label
        ? t('app.common.delete_dialog.title_named', { label: props.label })
        : t('app.common.delete_dialog.title');
});

const dialogDescription = computed(() =>
    props.permanent
        ? t('app.common.delete_dialog.description_permanent')
        : t('app.common.delete_dialog.description'),
);

const canConfirm = computed(() => {
    if (!props.requireConfirmWord) {
        return true;
    }

    return typed.value === confirmWord.value;
});

watch(isOpen, (open) => {
    if (open) {
        typed.value = '';
        confirmWord.value = CONFIRM_WORDS[Math.floor(Math.random() * CONFIRM_WORDS.length)];
    }
});

function handleConfirm(): void {
    if (!canConfirm.value) {
        return;
    }

    isDeleting.value = true;
    router.delete(tenantWayfinderPath(props.href), {
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
            <Button variant="destructive" size="sm" class="inline-flex items-center gap-1.5">
                <Trash2 class="size-3.5" />
                <span class="hidden sm:inline"><slot>{{ buttonText }}</slot></span>
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                        <TriangleAlert class="size-5 text-destructive" />
                    </div>
                    <div>
                        <DialogTitle>{{ dialogTitle }}</DialogTitle>
                        <DialogDescription class="mt-0.5">{{ dialogDescription }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="requireConfirmWord" class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    {{ t('app.common.delete_dialog.confirm_prompt') }}
                    <span class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono font-semibold text-foreground tracking-wider">{{ confirmWord }}</span>
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
                    {{ t('app.common.actions.cancel') }}
                </Button>
                <Button variant="destructive" :disabled="!canConfirm || isDeleting" @click="handleConfirm">
                    {{ isDeleting ? t('app.common.actions.deleting') : buttonText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
