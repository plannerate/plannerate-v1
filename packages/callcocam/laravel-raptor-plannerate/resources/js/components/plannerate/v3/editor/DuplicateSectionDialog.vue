<script setup lang="ts">
import { Button } from '../../../../components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../../../../components/ui/dialog';
import type { Section } from '../../../../types/planogram';
import { Copy, Package } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    open: boolean;
    section?: Section;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'confirm', duplicateType: 'structure' | 'complete'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    section: undefined,
});

const emit = defineEmits<Emits>();

const selectedType = ref<'structure' | 'complete' | null>(null);

// Inicia com "complete" selecionado quando modal abre
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedType.value = 'complete';
        }
    },
);

function handleConfirm() {
    if (selectedType.value) {
        emit('confirm', selectedType.value);
        emit('update:open', false);
    }
}

function handleCancel() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="z-[1000]  w-full md:max-w-2xl">
            <DialogHeader>
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10"
                    >
                        <Copy class="size-6 text-primary" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <DialogTitle class="text-xl">
                            Duplicar Seção
                        </DialogTitle>
                        <DialogDescription class="text-base">
                            Escolha como deseja duplicar a seção
                            <span
                                v-if="section?.name"
                                class="font-semibold text-foreground"
                            >
                                "{{ section.name }}"
                            </span>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Opção 1: Estrutura apenas -->
                <button
                    type="button"
                    class="w-full rounded-lg border-2 p-4 text-left transition-colors hover:bg-accent"
                    :class="{
                        'border-primary bg-primary/5':
                            selectedType === 'structure',
                        'border-border': selectedType !== 'structure',
                    }"
                    @click="selectedType = 'structure'"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                            :class="{
                                'border-primary bg-primary':
                                    selectedType === 'structure',
                                'border-muted-foreground':
                                    selectedType !== 'structure',
                            }"
                        >
                            <div
                                v-if="selectedType === 'structure'"
                                class="size-2.5 rounded-full bg-primary-foreground"
                            />
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="font-medium">Estrutura Apenas</div>
                            <div class="text-sm text-muted-foreground">
                                Duplica a seção com todas as prateleiras, mas
                                sem produtos
                            </div>
                            <ul
                                class="mt-2 space-y-1 text-xs text-muted-foreground"
                            >
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1 rounded-full bg-muted-foreground"
                                    ></span>
                                    Módulo/Seção
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1 rounded-full bg-muted-foreground"
                                    ></span>
                                    Prateleiras
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1 rounded-full bg-muted-foreground/50"
                                    ></span>
                                    Produtos (não incluídos)
                                </li>
                            </ul>
                        </div>
                    </div>
                </button>

                <!-- Opção 2: Completa -->
                <button
                    type="button"
                    class="w-full rounded-lg border-2 p-4 text-left transition-colors hover:bg-accent"
                    :class="{
                        'border-primary bg-primary/5':
                            selectedType === 'complete',
                        'border-border': selectedType !== 'complete',
                    }"
                    @click="selectedType = 'complete'"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                            :class="{
                                'border-primary bg-primary':
                                    selectedType === 'complete',
                                'border-muted-foreground':
                                    selectedType !== 'complete',
                            }"
                        >
                            <div
                                v-if="selectedType === 'complete'"
                                class="size-2.5 rounded-full bg-primary-foreground"
                            />
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="font-medium">Duplicação Completa</div>
                            <div class="text-sm text-muted-foreground">
                                Duplica tudo: seção, prateleiras e todos os
                                produtos posicionados
                            </div>
                            <ul
                                class="mt-2 space-y-1 text-xs text-muted-foreground"
                            >
                                <li class="flex items-center gap-2">
                                    <Package class="size-3" />
                                    Módulo/Seção
                                </li>
                                <li class="flex items-center gap-2">
                                    <Package class="size-3" />
                                    Prateleiras
                                </li>
                                <li class="flex items-center gap-2">
                                    <Package class="size-3" />
                                    Produtos e posicionamentos
                                </li>
                            </ul>
                        </div>
                    </div>
                </button>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    Cancelar
                </Button>
                <Button :disabled="!selectedType" @click="handleConfirm">
                    Duplicar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
