<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { home } from '@/routes';

const { t } = useT();

/**
 * Código de status HTTP enviado pelo handler de exceções (bootstrap/app.php).
 * Determina qual título/descrição e ilustração exibir.
 */
const props = defineProps<{
    status: number;
}>();

/**
 * Lista de status com textos dedicados em lang/pt_BR/errors.php.
 * Qualquer outro código cai no bloco genérico.
 */
const knownStatuses = [403, 404, 419, 429, 500, 503];

/** Chave base de tradução para o status atual (ou 'generic'). */
const key = computed(() =>
    knownStatuses.includes(props.status) ? String(props.status) : 'generic',
);

const title = computed(() => t(`errors.${key.value}.title`));
const description = computed(() => t(`errors.${key.value}.description`));

/** Volta para a página anterior usando o histórico do navegador. */
function goBack(): void {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(home().url);
}
</script>

<template>
    <Head :title="`${status} — ${title}`" />

    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-6 py-12 text-foreground"
    >
        <!-- Padrão de pontos sutil no fundo (mesma linguagem visual do login) -->
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.05]"
            style="
                background-image: radial-gradient(
                    circle at 1.5px 1.5px,
                    hsl(87 82% 56%) 1px,
                    transparent 0
                );
                background-size: 36px 36px;
            "
        />

        <!-- Brilho radial atrás do código -->
        <div
            class="pointer-events-none absolute left-1/2 top-1/3 -z-0 h-[420px] w-[420px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/10 blur-3xl"
        />

        <div
            class="relative z-10 flex w-full max-w-xl flex-col items-center text-center"
        >
            <!-- Logo -->
            <Link :href="home()" class="mb-10">
                <AppLogoIcon class="h-10 w-auto" />
            </Link>

            <!-- Código do status em destaque -->
            <div class="flex items-baseline gap-3">
                <span
                    class="bg-gradient-to-b from-foreground to-muted-foreground/60 bg-clip-text text-7xl font-black leading-none tracking-tighter text-transparent sm:text-8xl"
                >
                    {{ status }}
                </span>
            </div>

            <!-- Rótulo -->
            <div
                class="mt-6 inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-4 py-1.5"
            >
                <span class="size-1.5 rounded-full bg-primary" />
                <span
                    class="text-[11px] font-bold uppercase tracking-widest text-primary"
                >
                    {{ t('errors.label') }} {{ status }}
                </span>
            </div>

            <!-- Título e descrição -->
            <h1
                class="mt-6 text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                {{ title }}
            </h1>
            <p
                class="mt-3 max-w-md text-base leading-relaxed text-muted-foreground"
            >
                {{ description }}
            </p>

            <!-- Ações -->
            <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                <Button as-child size="lg">
                    <Link :href="home()">
                        {{ t('errors.actions.home') }}
                    </Link>
                </Button>
                <Button variant="outline" size="lg" @click="goBack">
                    {{ t('errors.actions.back') }}
                </Button>
            </div>
        </div>
    </div>
</template>
