<script setup lang="ts">
import { ClipboardListIcon } from 'lucide-vue-next';
import { useT } from '@/composables/useT';

interface Props {
    observacoes: string;
    /** Título do planograma, movido do cabeçalho para a área de observações. */
    title?: string;
}

defineProps<Props>();

const { t } = useT();
</script>

<template>
    <div>
        <div
            class="flex items-start gap-4 border-t border-slate-100 px-6 py-4 dark:border-slate-800"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary"
            >
                <ClipboardListIcon class="h-5 w-5 text-primary-foreground" />
            </div>
            <div>
                <h2
                    v-if="title"
                    class="text-lg leading-none font-black tracking-wide text-slate-900 uppercase dark:text-slate-100"
                >
                    {{ title }}
                </h2>
                <p
                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    :class="{ 'mt-1.5': title }"
                >
                    {{ t('plannerate.print.labels.observations') }}:
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ observacoes }}
                </p>
            </div>
        </div>
        <div
            class="relative h-10 overflow-hidden bg-slate-900 dark:bg-slate-950"
        >
            <div
                class="absolute right-0 bottom-0 h-20 w-20 rounded-tl-full bg-primary"
            ></div>
        </div>
    </div>
</template>
