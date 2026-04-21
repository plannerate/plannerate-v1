<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useT } from '@/composables/useT';
import { dashboard, login, register } from '@/routes';

const { t } = useT();
const dashboardPath = dashboard.url().replace(/^\/\/[^/]+/, '');

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head :title="t('app.welcome')" />

    <div class="relative min-h-screen overflow-hidden bg-background text-foreground">
        <div class="pointer-events-none absolute inset-0 bg-linear-to-br from-primary/8 via-transparent to-sidebar/30" />

        <header class="relative z-10 border-b border-border/70">
            <nav class="mx-auto flex h-16 w-full items-center justify-between px-6 lg:px-10">
                <div class="flex items-center">
                    <AppLogoIcon class="h-8 w-auto" />
                </div>

                <div class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardPath"
                        class="inline-flex h-9 items-center rounded-md border border-border bg-card px-4 font-medium transition-colors hover:bg-accent"
                    >
                        {{ t('app.navigation.dashboard') }}
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-9 items-center rounded-md px-4 font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('app.auth.login') }}
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex h-9 items-center rounded-md bg-primary px-4 font-semibold text-primary-foreground shadow-sm transition-all hover:brightness-95"
                        >
                            {{ t('app.auth.register') }}
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <main class="relative z-10 mx-auto grid min-h-[calc(100vh-4rem)] w-full items-center gap-8 px-6 py-10 lg:grid-cols-2 lg:px-10">
            <section class="max-w-2xl space-y-6">
                <p class="inline-flex rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-primary">
                    Plannerate Platform
                </p>

                <div class="space-y-4">
                    <h1 class="text-4xl font-semibold tracking-[-0.03em] text-balance lg:text-6xl">
                        Controle total das operacoes com velocidade de equipe pequena.
                    </h1>
                    <p class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg">
                        Centralize usuarios, seguranca e fluxos em um unico painel com identidade clara, execucao rapida e padrao visual consistente.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardPath"
                        class="inline-flex h-11 items-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                    >
                        Acessar painel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-11 items-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                        >
                            Entrar agora
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex h-11 items-center rounded-md border border-border bg-card px-5 text-sm font-semibold transition-colors hover:bg-accent"
                        >
                            Criar conta
                        </Link>
                    </template>
                </div>
            </section>

            <section class="relative">
                <div class="absolute -inset-8 bg-linear-to-tr from-primary/15 via-transparent to-sidebar/35 blur-3xl" />
                <div class="relative rounded-2xl border border-border/80 bg-card/90 p-6 shadow-sm backdrop-blur-sm lg:p-8">
                    <div class="grid gap-4">
                        <div class="rounded-xl border border-border/70 bg-background p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">Seguranca</p>
                            <p class="mt-2 text-sm font-medium">Acessos centralizados com governanca por perfil.</p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-background p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">Operacional</p>
                            <p class="mt-2 text-sm font-medium">Fluxos monitorados em tempo real com menos friccao.</p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-background p-4">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">Escala</p>
                            <p class="mt-2 text-sm font-medium">Experiencia consistente do primeiro usuario ao crescimento multi-tenant.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
