<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useT } from '@/composables/useT';
import { home } from '@/routes';

const { t } = useT();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Painel esquerdo: branding escuro -->
        <div class="relative hidden lg:flex lg:w-[58%] xl:w-[80%] flex-col overflow-hidden">
            <!-- Imagem de fundo com overlay -->
            <div class="absolute inset-0">
                <img src="/img/auth.png" alt="" class="h-full w-full scale-110 object-cover object-center blur-in" />
                <div class="absolute inset-0" style="background: oklch(0.21 0.014 258 / 0.82)" />
                <!-- Padrão de pontos -->
                <div class="absolute inset-0 opacity-[0.13]" style="background-image: radial-gradient(circle at 1.5px 1.5px, hsl(87 82% 56%) 1px, transparent 0); background-size: 36px 36px;" />
            </div>

            <!-- Conteúdo sobreposto -->
            <div class="relative z-10 flex h-full flex-col justify-between p-12 xl:p-16">
                <!-- Logo -->
                <Link :href="home()">
                    <AppLogoIcon class="h-32 w-auto" />
                </Link>

                <!-- Tagline principal -->
                <div class="max-w-xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-5 py-2">
                        <span class="size-1.5 rounded-full bg-primary" />
                        <span class="text-xs font-bold uppercase tracking-widest text-primary">
                            {{ t('app.auth_layout.badge') }}
                        </span>
                    </div>

                    <h1 class="mb-6 text-5xl font-bold leading-[1.12] tracking-tight text-white xl:text-6xl">
                        {{ t('app.auth_layout.headline_line_1') }}<br />
                        {{ t('app.auth_layout.headline_line_2') }}<br />
                        <span class="text-primary italic">
                            {{ t('app.auth_layout.headline_highlight_line_1') }}<br />
                            {{ t('app.auth_layout.headline_highlight_line_2') }}
                        </span>
                    </h1>

                    <p class="max-w-md text-lg leading-relaxed text-white/55">
                        {{ t('app.auth_layout.description') }}
                    </p>
                </div>

                <!-- Stats -->
                <div class="flex flex-wrap gap-12 border-t border-white/10 pt-9">
                    <div>
                        <div class="text-3xl font-bold text-primary">{{ t('app.auth_layout.stats.inventory_precision_value') }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-white/40">
                            {{ t('app.auth_layout.stats.inventory_precision') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-primary">{{ t('app.auth_layout.stats.cloud_sync_value') }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-white/40">
                            {{ t('app.auth_layout.stats.cloud_sync') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-primary">{{ t('app.auth_layout.stats.global_deployments_value') }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-white/40">
                            {{ t('app.auth_layout.stats.global_deployments') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Painel direito: formulário -->
        <div class="flex flex-1 flex-col items-center justify-center bg-background py-12">
            <!-- Logo mobile -->
            <Link :href="home()" class="mb-10 lg:hidden">
                <AppLogoIcon class="h-8 w-auto" />
            </Link>

            <div class="w-full max-w-md px-5">
                <div v-if="title || description" class="mb-8">
                    <h2 v-if="title" class="text-2xl font-bold tracking-tight text-foreground">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="mt-1.5 text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
