<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useT } from '@/composables/useT';
import { dashboard, login } from '@/routes';

const { t } = useT();
const dashboardPath = dashboard.url().replace(/^\/\/[^/]+/, '');

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    { canRegister: true },
);

const isScrolled = ref(false);
const mobileMenuOpen = ref(false);

function onScroll() {
    isScrolled.value = window.scrollY > 20;
}

onMounted(() => window.addEventListener('scroll', onScroll));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

function scrollTo(anchor: string) {
    mobileMenuOpen.value = false;
    const el = document.querySelector(anchor);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

const navItems = computed(() => [
    { label: t('site.nav.solution'), anchor: '#solution' },
    { label: t('site.nav.features'), anchor: '#features' },
    { label: t('site.nav.planograms'), anchor: '#planograms' },
    { label: t('site.nav.execution'), anchor: '#execution' },
    { label: t('site.nav.integrations'), anchor: '#integrations' },
    { label: t('site.nav.contact'), anchor: '#contact' },
]);

const painCards = computed(() => [
    { title: t('site.pain.no_planogram.title'), text: t('site.pain.no_planogram.text') },
    { title: t('site.pain.manual.title'), text: t('site.pain.manual.text') },
    { title: t('site.pain.perception.title'), text: t('site.pain.perception.text') },
    { title: t('site.pain.no_standard.title'), text: t('site.pain.no_standard.text') },
    { title: t('site.pain.no_space_view.title'), text: t('site.pain.no_space_view.text') },
    { title: t('site.pain.no_tracking.title'), text: t('site.pain.no_tracking.text') },
]);

const howItWorksSteps = computed(() => [
    { number: t('site.how_it_works.step1.number'), title: t('site.how_it_works.step1.title'), text: t('site.how_it_works.step1.text') },
    { number: t('site.how_it_works.step2.number'), title: t('site.how_it_works.step2.title'), text: t('site.how_it_works.step2.text') },
    { number: t('site.how_it_works.step3.number'), title: t('site.how_it_works.step3.title'), text: t('site.how_it_works.step3.text') },
    { number: t('site.how_it_works.step4.number'), title: t('site.how_it_works.step4.title'), text: t('site.how_it_works.step4.text') },
    { number: t('site.how_it_works.step5.number'), title: t('site.how_it_works.step5.title'), text: t('site.how_it_works.step5.text') },
    { number: t('site.how_it_works.step6.number'), title: t('site.how_it_works.step6.title'), text: t('site.how_it_works.step6.text') },
]);

const strategyFeatures = computed(() => [
    { title: t('site.features.strategy.gondolas.title'), text: t('site.features.strategy.gondolas.text') },
    { title: t('site.features.strategy.products.title'), text: t('site.features.strategy.products.text') },
    { title: t('site.features.strategy.merchandising.title'), text: t('site.features.strategy.merchandising.text') },
    { title: t('site.features.strategy.erp.title'), text: t('site.features.strategy.erp.text') },
]);

const createFeatures = computed(() => [
    { title: t('site.features.create.per_store.title'), text: t('site.features.create.per_store.text') },
    { title: t('site.features.create.automated.title'), text: t('site.features.create.automated.text') },
    { title: t('site.features.create.agility.title'), text: t('site.features.create.agility.text') },
    { title: t('site.features.create.consistency.title'), text: t('site.features.create.consistency.text') },
]);

const executeFeatures = computed(() => [
    { title: t('site.features.execute.proof.title'), text: t('site.features.execute.proof.text') },
    { title: t('site.features.execute.connection.title'), text: t('site.features.execute.connection.text') },
    { title: t('site.features.execute.control.title'), text: t('site.features.execute.control.text') },
    { title: t('site.features.execute.reports.title'), text: t('site.features.execute.reports.text') },
    { title: t('site.features.execute.evolution.title'), text: t('site.features.execute.evolution.text') },
]);

const technologyCards = computed(() => [
    { stat: t('site.technology.web.stat'), text: t('site.technology.web.text') },
    { stat: t('site.technology.platform.stat'), text: t('site.technology.platform.text') },
    { stat: t('site.technology.pillars.stat'), text: t('site.technology.pillars.text') },
    { stat: t('site.technology.levels.stat'), text: t('site.technology.levels.text') },
    { stat: t('site.technology.erp.stat'), text: t('site.technology.erp.text') },
    { stat: t('site.technology.store.stat'), text: t('site.technology.store.text') },
    { stat: t('site.technology.profiles.stat'), text: t('site.technology.profiles.text') },
    { stat: t('site.technology.history.stat'), text: t('site.technology.history.text') },
    { stat: t('site.technology.operation.stat'), text: t('site.technology.operation.text') },
]);

const benefitCards = computed(() => [
    { title: t('site.benefits.standardization.title'), text: t('site.benefits.standardization.text') },
    { title: t('site.benefits.less_rework.title'), text: t('site.benefits.less_rework.text') },
    { title: t('site.benefits.better_space.title'), text: t('site.benefits.better_space.text') },
    { title: t('site.benefits.less_subjective.title'), text: t('site.benefits.less_subjective.text') },
    { title: t('site.benefits.controlled.title'), text: t('site.benefits.controlled.text') },
    { title: t('site.benefits.connection.title'), text: t('site.benefits.connection.text') },
]);

const impactStats = computed(() => [
    { value: t('site.impact.sales.value'), text: t('site.impact.sales.text') },
    { value: t('site.impact.stock.value'), text: t('site.impact.stock.text') },
    { value: t('site.impact.time.value'), text: t('site.impact.time.text') },
    { value: t('site.impact.generation.value'), text: t('site.impact.generation.text') },
    { value: t('site.impact.roi.value'), text: t('site.impact.roi.text') },
    { value: t('site.impact.optimize.value'), text: t('site.impact.optimize.text') },
]);

const indicatorItems = computed(() => [
    { title: t('site.indicators.agility.title'), text: t('site.indicators.agility.text') },
    { title: t('site.indicators.approval.title'), text: t('site.indicators.approval.text') },
    { title: t('site.indicators.adherence.title'), text: t('site.indicators.adherence.text') },
    { title: t('site.indicators.evidence.title'), text: t('site.indicators.evidence.text') },
    { title: t('site.indicators.share.title'), text: t('site.indicators.share.text') },
    { title: t('site.indicators.performance.title'), text: t('site.indicators.performance.text') },
    { title: t('site.indicators.reviews.title'), text: t('site.indicators.reviews.text') },
    { title: t('site.indicators.evolution.title'), text: t('site.indicators.evolution.text') },
]);
</script>

<template>
    <Head :title="t('site.meta.title')" />

    <div class="min-h-screen bg-background text-foreground antialiased">

        <!-- ============================================================
             HEADER STICKY
        ============================================================ -->
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
            :class="isScrolled ? 'border-b border-border/60 bg-background/90 shadow-sm backdrop-blur-md' : 'bg-transparent'"
        >
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                <!-- Logo -->
                <a href="#" @click.prevent="scrollTo('#hero')" class="flex items-center gap-2">
                    <AppLogoIcon class="h-8 w-auto" />
                </a>

                <!-- Nav desktop -->
                <ul class="hidden items-center gap-1 lg:flex">
                    <li v-for="item in navItems" :key="item.anchor">
                        <a
                            :href="item.anchor"
                            @click.prevent="scrollTo(item.anchor)"
                            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                </ul>

                <!-- CTA + login desktop -->
                <div class="hidden items-center gap-3 lg:flex">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardPath"
                        class="inline-flex h-9 items-center rounded-md border border-border bg-card px-4 text-sm font-medium transition-colors hover:bg-accent"
                    >
                        {{ t('site.nav.dashboard') }}
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-9 items-center rounded-md px-4 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ t('site.nav.login') }}
                        </Link>
                        <a
                            href="#contact"
                            @click.prevent="scrollTo('#contact')"
                            class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-px hover:brightness-95"
                        >
                            {{ t('site.nav.demo') }}
                        </a>
                    </template>
                </div>

                <!-- Hamburger mobile -->
                <button
                    class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground lg:hidden"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    aria-label="Menu"
                >
                    <svg v-if="!mobileMenuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </nav>

            <!-- Mobile menu -->
            <div v-if="mobileMenuOpen" class="border-t border-border bg-background/95 px-6 pb-4 backdrop-blur-md lg:hidden">
                <ul class="space-y-1 pt-3">
                    <li v-for="item in navItems" :key="item.anchor">
                        <a
                            :href="item.anchor"
                            @click.prevent="scrollTo(item.anchor)"
                            class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                </ul>
                <div class="mt-4 flex flex-col gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardPath"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-card px-4 text-sm font-medium"
                    >
                        {{ t('site.nav.dashboard') }}
                    </Link>
                    <template v-else>
                        <a
                            href="#contact"
                            @click.prevent="scrollTo('#contact')"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground"
                        >
                            {{ t('site.nav.demo') }}
                        </a>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-border px-4 text-sm font-medium text-muted-foreground"
                        >
                            {{ t('site.nav.login') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main>

            <!-- ============================================================
                 1. HERO
            ============================================================ -->
            <section id="hero" class="relative overflow-hidden pb-24 pt-32 lg:pt-40">
                <!-- Background gradient -->
                <div class="pointer-events-none absolute inset-0 bg-linear-to-br from-primary/10 via-transparent to-sidebar/20" />
                <div class="pointer-events-none absolute -right-40 -top-40 h-96 w-96 rounded-full bg-primary/8 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-20 left-20 h-64 w-64 rounded-full bg-sidebar/20 blur-3xl" />

                <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <!-- Text -->
                        <div class="max-w-2xl space-y-8">
                            <span class="inline-flex rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-primary">
                                {{ t('site.hero.badge') }}
                            </span>

                            <div class="space-y-5">
                                <h1 class="text-balance text-4xl font-bold tracking-tight text-foreground lg:text-5xl xl:text-6xl">
                                    {{ t('site.hero.title') }}
                                </h1>
                                <p class="text-lg leading-relaxed text-muted-foreground">
                                    {{ t('site.hero.subtitle') }}
                                </p>
                                <p class="text-sm leading-relaxed text-muted-foreground/80">
                                    {{ t('site.hero.support') }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <template v-if="$page.props.auth.user">
                                    <Link
                                        :href="dashboardPath"
                                        class="inline-flex h-11 items-center rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                                    >
                                        {{ t('site.hero.cta_access') }}
                                    </Link>
                                </template>
                                <template v-else>
                                    <a
                                        href="#contact"
                                        @click.prevent="scrollTo('#contact')"
                                        class="inline-flex h-11 items-center rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                                    >
                                        {{ t('site.hero.cta_demo') }}
                                    </a>
                                    <a
                                        href="#solution"
                                        @click.prevent="scrollTo('#solution')"
                                        class="inline-flex h-11 items-center rounded-md border border-border bg-card px-6 text-sm font-semibold transition-colors hover:bg-accent"
                                    >
                                        {{ t('site.hero.cta_platform') }}
                                    </a>
                                </template>
                            </div>
                        </div>

                        <!-- Visual card -->
                        <div class="relative hidden lg:block">
                            <div class="absolute -inset-6 rounded-3xl bg-linear-to-tr from-primary/15 via-transparent to-sidebar/25 blur-2xl" />
                            <div class="relative rounded-2xl border border-border/70 bg-card/80 p-8 shadow-xl backdrop-blur-sm">
                                <div class="mb-6 flex items-center gap-2">
                                    <div class="h-2.5 w-2.5 rounded-full bg-red-400" />
                                    <div class="h-2.5 w-2.5 rounded-full bg-yellow-400" />
                                    <div class="h-2.5 w-2.5 rounded-full bg-green-400" />
                                </div>
                                <div class="space-y-4">
                                    <div class="rounded-xl border border-border/60 bg-background p-5">
                                        <div class="flex items-center justify-between">
                                            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Planograma</p>
                                            <span class="rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-600">Ativo</span>
                                        </div>
                                        <p class="mt-2 text-sm font-medium">Refrigerados — Loja Centro</p>
                                        <p class="mt-1 text-xs text-muted-foreground">48 produtos · 4 gôndolas · 3 prateleiras</p>
                                    </div>
                                    <div class="rounded-xl border border-border/60 bg-background p-5">
                                        <div class="flex items-center justify-between">
                                            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Implantação</p>
                                            <span class="rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-600">Em progresso</span>
                                        </div>
                                        <p class="mt-2 text-sm font-medium">Bebidas — Filial Sul</p>
                                        <div class="mt-3">
                                            <div class="flex justify-between text-xs text-muted-foreground">
                                                <span>Execução</span>
                                                <span>72%</span>
                                            </div>
                                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div class="h-full w-[72%] rounded-full bg-primary" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-border/60 bg-background p-5">
                                        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Desempenho</p>
                                        <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                                            <div>
                                                <p class="text-lg font-bold text-foreground">96</p>
                                                <p class="text-xs text-muted-foreground">Lojas</p>
                                            </div>
                                            <div>
                                                <p class="text-lg font-bold text-foreground">384</p>
                                                <p class="text-xs text-muted-foreground">Planogramas</p>
                                            </div>
                                            <div>
                                                <p class="text-lg font-bold text-primary">89%</p>
                                                <p class="text-xs text-muted-foreground">Aderência</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 2. BLOCO DE DOR
            ============================================================ -->
            <section id="solution" class="bg-foreground py-24 text-background">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.pain.title') }}
                        </h2>
                        <p class="mt-4 text-xl font-medium opacity-80">
                            {{ t('site.pain.subtitle') }}
                        </p>
                        <p class="mt-4 leading-relaxed opacity-60">
                            {{ t('site.pain.text') }}
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in painCards"
                            :key="card.title"
                            class="rounded-2xl border border-background/10 bg-background/5 p-6 backdrop-blur-sm transition-colors hover:bg-background/10"
                        >
                            <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-primary/20">
                                <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold">{{ card.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed opacity-60">{{ card.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 3. EVOLUÇÃO DA PLANOGRAMAÇÃO
            ============================================================ -->
            <section class="py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <!-- Accent visual -->
                        <div class="order-2 lg:order-1">
                            <div class="relative rounded-2xl border border-border/60 bg-card p-8">
                                <div class="space-y-5">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary">1</div>
                                        <div>
                                            <p class="text-sm font-semibold">Dados de venda e margem</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">Conectados ao espaço de exposição</p>
                                        </div>
                                    </div>
                                    <div class="ml-4 w-px self-stretch border-l border-dashed border-border" />
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary">2</div>
                                        <div>
                                            <p class="text-sm font-semibold">Regras de merchandising</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">Critérios claros de exposição por categoria</p>
                                        </div>
                                    </div>
                                    <div class="ml-4 w-px self-stretch border-l border-dashed border-border" />
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary">3</div>
                                        <div>
                                            <p class="text-sm font-semibold">Planograma por loja</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">Adaptado à estrutura e sortimento de cada unidade</p>
                                        </div>
                                    </div>
                                    <div class="ml-4 w-px self-stretch border-l border-dashed border-border" />
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-500/20 text-sm font-bold text-green-600">4</div>
                                        <div>
                                            <p class="text-sm font-semibold">Execução acompanhada</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">Com evidências, histórico e revisão periódica</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Text -->
                        <div class="order-1 space-y-6 lg:order-2">
                            <h2 class="text-3xl font-bold tracking-tight text-balance lg:text-4xl">
                                {{ t('site.evolution.title') }}
                            </h2>
                            <p class="text-lg font-medium text-primary">
                                {{ t('site.evolution.subtitle') }}
                            </p>
                            <p class="leading-relaxed text-muted-foreground">
                                {{ t('site.evolution.text') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 4. DE PROCESSOS MANUAIS PARA GESTÃO ESTRATÉGICA
            ============================================================ -->
            <section class="bg-muted/30 py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <!-- Text -->
                        <div class="space-y-5">
                            <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                                {{ t('site.strategic.title') }}
                            </h2>
                            <p class="leading-relaxed text-muted-foreground">{{ t('site.strategic.text1') }}</p>
                            <p class="leading-relaxed text-muted-foreground">{{ t('site.strategic.text2') }}</p>
                            <p class="leading-relaxed text-muted-foreground">{{ t('site.strategic.text3') }}</p>
                        </div>

                        <!-- Highlight card -->
                        <div class="relative">
                            <div class="absolute -inset-2 rounded-3xl bg-primary/10 blur-2xl" />
                            <div class="relative rounded-2xl border border-primary/20 bg-primary/5 p-8">
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15">
                                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold">{{ t('site.strategic.highlight_title') }}</h3>
                                <p class="mt-3 leading-relaxed text-muted-foreground">{{ t('site.strategic.highlight_text') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 5. COMO FUNCIONA
            ============================================================ -->
            <section id="planograms" class="py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.how_it_works.title') }}
                        </h2>
                        <p class="mt-4 leading-relaxed text-muted-foreground">
                            {{ t('site.how_it_works.subtitle') }}
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="step in howItWorksSteps"
                            :key="step.number"
                            class="group relative rounded-2xl border border-border/60 bg-card p-6 transition-shadow hover:shadow-md"
                        >
                            <span class="mb-4 block text-4xl font-black text-primary/20 transition-colors group-hover:text-primary/30">
                                {{ step.number }}
                            </span>
                            <h3 class="text-base font-semibold">{{ step.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ step.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 6. FUNCIONALIDADES
            ============================================================ -->
            <section id="features" class="bg-muted/30 py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.features.title') }}
                        </h2>
                        <p class="mt-4 leading-relaxed text-muted-foreground">
                            {{ t('site.features.subtitle') }}
                        </p>
                    </div>

                    <!-- 6.1 Macroespaço -->
                    <div class="mt-16 rounded-2xl border border-border/60 bg-card p-8 lg:p-10">
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/15">
                                <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">{{ t('site.features.macrospace.title') }}</h3>
                                <p class="mt-2 leading-relaxed text-muted-foreground">{{ t('site.features.macrospace.text') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 6.2 Planeje com estratégia -->
                    <div class="mt-8">
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold">{{ t('site.features.strategy.title') }}</h3>
                            <p class="mt-2 text-muted-foreground">{{ t('site.features.strategy.text') }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="item in strategyFeatures"
                                :key="item.title"
                                class="rounded-xl border border-border/60 bg-card p-5"
                            >
                                <h4 class="text-sm font-semibold">{{ item.title }}</h4>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 6.3 Crie e distribua -->
                    <div id="execution" class="mt-8">
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold">{{ t('site.features.create.title') }}</h3>
                            <p class="mt-2 text-muted-foreground">{{ t('site.features.create.text') }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="item in createFeatures"
                                :key="item.title"
                                class="rounded-xl border border-border/60 bg-card p-5"
                            >
                                <h4 class="text-sm font-semibold">{{ item.title }}</h4>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- 6.4 Execute, acompanhe e evolua -->
                    <div class="mt-8">
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold">{{ t('site.features.execute.title') }}</h3>
                            <p class="mt-2 text-muted-foreground">{{ t('site.features.execute.text') }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="item in executeFeatures"
                                :key="item.title"
                                class="rounded-xl border border-border/60 bg-card p-5"
                            >
                                <h4 class="text-sm font-semibold">{{ item.title }}</h4>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 7. TECNOLOGIA E ESTRUTURA
            ============================================================ -->
            <section id="integrations" class="bg-foreground py-24 text-background">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.technology.title') }}
                        </h2>
                        <p class="mt-4 leading-relaxed opacity-60">
                            {{ t('site.technology.subtitle') }}
                        </p>
                    </div>

                    <div class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in technologyCards"
                            :key="card.stat"
                            class="rounded-xl border border-background/10 bg-background/5 p-6 transition-colors hover:bg-background/10"
                        >
                            <p class="text-2xl font-black text-primary">{{ card.stat }}</p>
                            <p class="mt-2 text-sm leading-relaxed opacity-60">{{ card.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 8. BENEFÍCIOS
            ============================================================ -->
            <section id="segments" class="py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.benefits.title') }}
                        </h2>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="benefit in benefitCards"
                            :key="benefit.title"
                            class="group rounded-2xl border border-border/60 bg-card p-6 transition-all hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="mb-4 h-1 w-8 rounded-full bg-primary transition-all group-hover:w-12" />
                            <h3 class="text-base font-semibold">{{ benefit.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ benefit.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 9. PROVA SOCIAL
            ============================================================ -->
            <section class="border-y border-border/60 bg-muted/20 py-20">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-2xl font-bold">{{ t('site.social_proof.title') }}</h2>
                        <p class="mt-4 leading-relaxed text-muted-foreground">{{ t('site.social_proof.text') }}</p>
                    </div>

                    <!-- Logo placeholders -->
                    <div class="mt-12 flex flex-wrap items-center justify-center gap-8">
                        <div v-for="i in 5" :key="i" class="flex h-12 w-32 items-center justify-center rounded-lg border border-border/60 bg-card">
                            <span class="text-xs font-medium text-muted-foreground/50">Cliente {{ i }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 10. IMPACTO
            ============================================================ -->
            <section class="relative overflow-hidden bg-primary py-24 text-primary-foreground">
                <div class="pointer-events-none absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/5 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-20 -right-20 h-80 w-80 rounded-full bg-white/5 blur-3xl" />

                <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.impact.title') }}
                        </h2>
                        <p class="mt-4 leading-relaxed opacity-75">
                            {{ t('site.impact.subtitle') }}
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="stat in impactStats"
                            :key="stat.value"
                            class="rounded-2xl border border-white/15 bg-white/10 p-6 backdrop-blur-sm"
                        >
                            <p class="text-3xl font-black">{{ stat.value }}</p>
                            <p class="mt-2 text-sm leading-relaxed opacity-75">{{ stat.text }}</p>
                        </div>
                    </div>

                    <p class="mx-auto mt-12 max-w-2xl text-center text-xs opacity-50">
                        {{ t('site.impact.disclaimer') }}
                    </p>
                </div>
            </section>

            <!-- ============================================================
                 11. INDICADORES
            ============================================================ -->
            <section class="py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ t('site.indicators.title') }}
                        </h2>
                    </div>

                    <div class="mt-16 grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="item in indicatorItems"
                            :key="item.title"
                            class="flex gap-4 rounded-xl border border-border/60 bg-card p-5"
                        >
                            <div class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/15">
                                <div class="h-1.5 w-1.5 rounded-full bg-primary" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">{{ item.title }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ item.text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 12. CHAMADA FINAL
            ============================================================ -->
            <section id="contact" class="bg-muted/30 py-24">
                <div class="mx-auto max-w-3xl px-6 text-center lg:px-8">
                    <h2 class="text-3xl font-bold tracking-tight text-balance lg:text-4xl xl:text-5xl">
                        {{ t('site.cta.title') }}
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl leading-relaxed text-muted-foreground">
                        {{ t('site.cta.text') }}
                    </p>

                    <div class="mt-10 flex flex-wrap justify-center gap-4">
                        <template v-if="$page.props.auth.user">
                            <Link
                                :href="dashboardPath"
                                class="inline-flex h-12 items-center rounded-md bg-primary px-8 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                            >
                                {{ t('site.cta.access') }}
                            </Link>
                        </template>
                        <template v-else>
                            <a
                                href="mailto:contato@plannerate.com.br"
                                class="inline-flex h-12 items-center rounded-md bg-primary px-8 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-95"
                            >
                                {{ t('site.cta.demo') }}
                            </a>
                            <a
                                href="mailto:contato@plannerate.com.br"
                                class="inline-flex h-12 items-center rounded-md border border-border bg-card px-8 text-sm font-semibold transition-colors hover:bg-accent"
                            >
                                {{ t('site.cta.specialist') }}
                            </a>
                        </template>
                    </div>
                </div>
            </section>

        </main>

        <!-- Footer -->
        <footer class="border-t border-border/60 py-10">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <AppLogoIcon class="h-7 w-auto opacity-70" />
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Plannerate. Todos os direitos reservados.
                    </p>
                    <nav class="flex gap-6 text-sm text-muted-foreground">
                        <Link :href="login()" class="transition-colors hover:text-foreground">
                            {{ t('site.nav.login') }}
                        </Link>
                        <a href="#contact" @click.prevent="scrollTo('#contact')" class="transition-colors hover:text-foreground">
                            {{ t('site.nav.contact') }}
                        </a>
                    </nav>
                </div>
            </div>
        </footer>

    </div>
</template>
