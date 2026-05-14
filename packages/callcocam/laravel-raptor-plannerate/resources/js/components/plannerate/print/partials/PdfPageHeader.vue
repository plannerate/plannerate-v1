<script setup lang="ts">
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    FileTextIcon,
    PackageIcon,
    ShoppingCartIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next'

interface GondolaMeta {
    location?: string
    side?: string
    planogram?: {
        start_date?: string
        description?: string
        category?: { name?: string } | null
    } | null
}

interface Props {
    gondola: GondolaMeta
    tenantName?: string
    responsavel?: string
    flowLabel: string
}

defineProps<Props>()
</script>

<template>
    <div class="px-6 py-5 border-b-2 border-primary">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <!-- Logo + empresa + título -->
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-primary rounded-xl flex items-center justify-center shrink-0">
                    <ShoppingCartIcon class="w-8 h-8 text-primary-foreground" />
                </div>
                <div>
                    <p
                        v-if="tenantName"
                        class="text-sm font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest leading-none mb-1"
                    >
                        {{ tenantName }}
                    </p>
                    <div class="flex items-center gap-2">
                        <div class="w-1 h-8 bg-primary rounded-full"></div>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-slate-100 uppercase tracking-wide leading-none">
                            Planograma de Exposição
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Grid de metadados -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-2 gap-x-8 gap-y-3">
                <div class="flex items-center gap-2 text-sm">
                    <StoreIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Loja:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.location || '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <CalendarDaysIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Data de publicação:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.planogram?.start_date || '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <FileTextIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Setor:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.side || '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <FileTextIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Versão:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">V1.0</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <PackageIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Categoria:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ gondola.planogram?.category?.name || '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <UserIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Responsável:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ responsavel || '—' }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm col-span-2">
                    <ArrowRightIcon class="w-4 h-4 text-primary shrink-0" />
                    <span class="text-slate-500 dark:text-slate-400">Posição do fluxo:</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ flowLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
