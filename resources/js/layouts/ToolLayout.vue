<script setup lang="ts">
/**
 * Layout de ferramenta em tela cheia: autenticado, mas sem sidebar nem cabeçalho do app.
 *
 * Para ferramentas de painel duplo (editor + preview) que precisam de rolagem interna.
 * A altura é travada em `h-screen` com `overflow-hidden`: assim o conteúdo herda uma
 * altura concreta e cada painel pode rolar por dentro. O AppLayout não serve para isso
 * porque o SidebarInset usa `min-h-svh` — a altura vem do conteúdo, então quem rola
 * acaba sendo a página inteira.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import { useT } from '@/composables/useT';

const {
    backHref,
    backLabel = '',
    title = '',
} = defineProps<{
    backHref: string;
    backLabel?: string;
    title?: string;
}>();

const { t } = useT();
</script>

<template>
    <div class="flex h-screen w-full flex-col overflow-hidden bg-background">
        <ImpersonationBanner />

        <header
            class="flex h-11 flex-none items-center gap-3 border-b border-sidebar-border/70 px-3 print:hidden"
        >
            <Link
                :href="backHref"
                class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ backLabel || t('app.common.actions.back') }}
            </Link>

            <span v-if="title" class="truncate text-sm font-semibold">
                {{ title }}
            </span>

            <div class="ml-auto flex items-center gap-2">
                <slot name="header-actions" />
            </div>
        </header>

        <div class="flex min-h-0 flex-1 flex-col">
            <slot />
        </div>

        <Toaster />
    </div>
</template>
