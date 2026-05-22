<script setup lang="ts">
import { ArrowUpDown, LayoutGrid, RefreshCw, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import type { Gondola } from '../../../types/planogram';

interface Props {
    open: boolean;
    gondola: Gondola;
}

interface Emits {
    (e: 'close'): void;
}

const props = defineProps<Props>();
defineEmits<Emits>();
const { t } = useT();

const modeLabel: Record<string, string> = {
    template: 'Template',
    automatic: 'Automático',
    manual: 'Manual',
};

const modeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    template: 'default',
    automatic: 'secondary',
    manual: 'outline',
};

const currentMode = props.gondola.generation_mode ?? 'manual';
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
    >
        <div
            v-if="open"
            class="relative z-20 flex h-full w-full sm:w-80 2xl:w-96 flex-col border-r border-border bg-background"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">{{ t('plannerate.sidebar.generation.title') }}</span>
                    <Badge :variant="modeVariant[currentMode] ?? 'outline'">
                        {{ modeLabel[currentMode] ?? currentMode }}
                    </Badge>
                </div>
                <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="$emit('close')" type="button">
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Template info -->
            <div v-if="gondola.template_id" class="border-b border-border bg-muted/30 px-4 py-2">
                <p class="text-xs text-muted-foreground">{{ t('plannerate.sidebar.generation.template_in_use') }}</p>
                <p class="mt-0.5 text-xs font-medium text-foreground">{{ gondola.template_id }}</p>
            </div>

            <!-- Actions -->
            <div class="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                <!-- Regerar -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-primary/10 p-2">
                            <RefreshCw class="size-4 text-primary" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.regenerate.title') }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.regenerate.description') }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground/70 italic">
                                {{ t('plannerate.sidebar.generation.regenerate.hint') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Redistribuir -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm opacity-60">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-amber-500/10 p-2">
                            <LayoutGrid class="size-4 text-amber-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.redistribute.title') }}</p>
                                <Badge variant="outline" class="text-[10px]">{{ t('plannerate.sidebar.generation.coming_soon') }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.redistribute.description') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Reordenar -->
                <div class="rounded-lg border border-border bg-background p-4 shadow-sm opacity-60">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-md bg-blue-500/10 p-2">
                            <ArrowUpDown class="size-4 text-blue-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium">{{ t('plannerate.sidebar.generation.reorder.title') }}</p>
                                <Badge variant="outline" class="text-[10px]">{{ t('plannerate.sidebar.generation.coming_soon') }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('plannerate.sidebar.generation.reorder.description') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
