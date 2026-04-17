<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '~/components/ui/button';
import { Head, router } from '@inertiajs/vue3';
import {
    CreditCard,
    Lock,
    SearchX,
    ServerCrash,
    ShieldX,
    Timer,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = defineProps<{
    status: number;
}>();

const goBack = (): void => {
    window.history.back();
};

interface ErrorConfig {
    title: string;
    description: string;
    icon: Component;
    cta: string;
    ctaAction: () => void;
    secondaryCta?: string;
    secondaryAction?: () => void;
}

const configs: Record<number, ErrorConfig> = {
    401: {
        title: 'Não autorizado',
        description:
            'Você precisa estar autenticado para acessar esta página.',
        icon: Lock,
        cta: 'Fazer login',
        ctaAction: () => router.visit('/login'),
    },
    402: {
        title: 'Pagamento necessário',
        description:
            'Este recurso requer um plano ativo. Verifique sua conta ou entre em contato com o suporte.',
        icon: CreditCard,
        cta: 'Ir para o início',
        ctaAction: () => router.visit('/'),
    },
    403: {
        title: 'Acesso negado',
        description:
            'Você não tem permissão para acessar esta página. Se acredita que isso é um erro, entre em contato com o administrador.',
        icon: ShieldX,
        cta: 'Ir para o início',
        ctaAction: () => router.visit('/'),
        secondaryCta: 'Voltar',
        secondaryAction: goBack,
    },
    404: {
        title: 'Página não encontrada',
        description:
            'A página que você está procurando não existe ou foi movida.',
        icon: SearchX,
        cta: 'Ir para o início',
        ctaAction: () => router.visit('/'),
        secondaryCta: 'Voltar',
        secondaryAction: goBack,
    },
    419: {
        title: 'Sessão expirada',
        description:
            'Sua sessão expirou por inatividade. Por favor, recarregue a página para continuar.',
        icon: Timer,
        cta: 'Recarregar página',
        ctaAction: () => window.location.reload(),
    },
    429: {
        title: 'Muitas tentativas',
        description:
            'Você excedeu o limite de requisições permitidas. Aguarde alguns instantes e tente novamente.',
        icon: Zap,
        cta: 'Tentar novamente',
        ctaAction: goBack,
    },
    500: {
        title: 'Erro interno do servidor',
        description:
            'Algo deu errado no servidor. Nossa equipe foi notificada e está trabalhando para resolver o problema.',
        icon: ServerCrash,
        cta: 'Ir para o início',
        ctaAction: () => router.visit('/'),
    },
    503: {
        title: 'Serviço temporariamente indisponível',
        description:
            'O sistema está em manutenção no momento. Por favor, tente novamente em alguns minutos.',
        icon: Wrench,
        cta: 'Tentar novamente',
        ctaAction: () => window.location.reload(),
    },
};

const config = computed(() => configs[props.status] ?? configs[500]);
const pageTitle = computed(() => `${props.status} — ${config.value.title}`);
const statusLabel = computed(() => String(props.status).padStart(3, '0'));
</script>

<template>
    <Head :title="pageTitle" />

    <div class="relative min-h-screen overflow-hidden bg-background text-foreground">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(163,230,53,0.18),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(239,68,68,0.12),_transparent_28%)]" />
        <div class="absolute inset-0 bg-[linear-gradient(to_right,theme(colors.border/.18)_1px,transparent_1px),linear-gradient(to_bottom,theme(colors.border/.18)_1px,transparent_1px)] bg-[size:4.5rem_4.5rem] opacity-30" />

        <div class="relative mx-auto grid min-h-screen max-w-7xl grid-cols-1 lg:grid-cols-[minmax(0,1.1fr)_30rem]">
            <section class="flex items-center px-6 py-16 sm:px-10 lg:px-14">
                <div class="flex max-w-3xl flex-col gap-8">
                    <div class="inline-flex w-fit items-center rounded-full border border-border/60 bg-background/70 px-4 py-2 text-sm text-muted-foreground backdrop-blur">
                        Plannerate system status
                    </div>

                    <AppLogo class="h-14 w-auto sm:h-16" />

                    <div class="flex flex-col gap-4">
                        <p class="text-sm font-medium uppercase tracking-[0.32em] text-primary">
                            Erro {{ statusLabel }}
                        </p>
                        <h1 class="max-w-2xl text-4xl font-black tracking-tight text-foreground sm:text-5xl lg:text-6xl">
                            {{ config.title }}
                        </h1>
                        <p class="max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">
                            {{ config.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button size="lg" class="min-w-44" @click="config.ctaAction">
                            {{ config.cta }}
                        </Button>
                        <Button
                            v-if="config.secondaryCta"
                            variant="outline"
                            size="lg"
                            class="min-w-36"
                            @click="config.secondaryAction?.()"
                        >
                            {{ config.secondaryCta }}
                        </Button>
                    </div>
                </div>
            </section>

            <aside class="flex items-center justify-center px-6 pb-12 pt-0 sm:px-10 lg:px-10 lg:py-16">
                <div class="relative w-full max-w-md overflow-hidden rounded-[2rem] border border-border/70 bg-card/85 p-8 shadow-2xl shadow-black/10 backdrop-blur-xl sm:p-10">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary/30 via-primary to-primary/30" />

                    <div class="flex flex-col gap-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm uppercase tracking-[0.28em] text-muted-foreground">
                                    Incident
                                </p>
                                <p class="mt-2 text-6xl font-black tracking-tight text-foreground/85">
                                    {{ status }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-destructive/10 p-4 text-destructive">
                                <component
                                    :is="config.icon"
                                    class="h-8 w-8"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-2xl border border-border/60 bg-background/70 p-5">
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-muted-foreground">Status</span>
                                <span class="font-semibold text-foreground">{{ status }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-muted-foreground">Contexto</span>
                                <span class="font-semibold text-foreground">Navegação web</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-muted-foreground">Ação recomendada</span>
                                <span class="font-semibold text-foreground">{{ config.cta }}</span>
                            </div>
                        </div>

                        <p class="text-sm leading-6 text-muted-foreground">
                            Se o problema persistir, registre o código <span class="font-semibold text-foreground">{{ status }}</span> e informe a equipe responsável.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
