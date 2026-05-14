<script setup lang="ts">
import { ClipboardListIcon } from 'lucide-vue-next'

interface Props {
    observacoes: string
}

defineProps<Props>()
</script>

<template>
    <div>
        <div class="px-6 py-4 flex items-start gap-4 border-t border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shrink-0">
                <ClipboardListIcon class="w-5 h-5 text-primary-foreground" />
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Observações:</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ observacoes }}</p>
            </div>
        </div>
        <div class="h-10 bg-slate-900 dark:bg-slate-950 relative overflow-hidden">
            <div class="absolute bottom-0 right-0 w-20 h-20 bg-primary rounded-tl-full"></div>
        </div>
    </div>
</template>
