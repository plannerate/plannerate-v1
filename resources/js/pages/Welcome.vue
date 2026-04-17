<script setup lang="ts">
import { dashboard } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AuthLayout from '@/layouts/HomeLayout.vue';
import {
    LayoutGrid,
    Zap,
    MessageCircle,
    Clock,
    BarChart3,
    Shield,
    Image,
    Users,
    CheckCircle2,
    Globe,
    ArrowDown,
} from 'lucide-vue-next';

interface Client {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    logo: string | null;
}

defineProps<{
    canRegister: boolean;
    clients: Client[];
    gondolaCount: number;
    clientsCount: number;
}>();

// Função para abrir WhatsApp
const openWhatsApp = () => {
    // Substitua pelo número do WhatsApp desejado (formato: 5511999999999)
    const phoneNumber = '5511999999999';
    const message = encodeURIComponent('Olá! Gostaria de saber mais sobre o Plannerate.');
    window.open(`https://wa.me/${phoneNumber}?text=${message}`, '_blank');
};
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <AuthLayout>
        <Head title="Plannerate - Planograma Digital">
            <meta name="description"
                content="Plannerate: Plataforma completa para edição visual de planogramas com drag-and-drop, autosave e gestão de múltiplos clientes." />
            <meta name="og:title" content="Plannerate - Planograma Digital" />
            <meta name="og:description"
                content="Edite planogramas de forma intuitiva com a solução cloud do Plannerate." />
        </Head>

        <div class="min-h-screen bg-background flex flex-col w-full">
            <!-- Header/Navigation -->
            <header
                class="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur-md shadow-sm right-0 left-0">
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 sm:h-20 items-center justify-between max-w-7xl mx-auto">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg">
                                <LayoutGrid class="h-6 w-6" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-lg sm:text-xl font-bold text-foreground">Plannerate</span>
                                <span class="text-xs text-muted-foreground -mt-0.5">Planograma Digital</span>
                            </div>
                        </div>

                        <nav class="flex items-center gap-3">
                            <Link v-if="$page.props.auth.user" :href="dashboard()">
                                <Button size="sm" class="sm:size-default gap-2">
                                    <ActionIconBox variant="default">
                                        <Zap />
                                    </ActionIconBox>
                                    <span class="hidden sm:inline">Dashboard</span>
                                </Button>
                            </Link>
                            <Button v-else @click="openWhatsApp" size="sm" class="sm:size-default gap-2">
                                <ActionIconBox variant="outline">
                                    <MessageCircle />
                                </ActionIconBox>
                                <span class="hidden sm:inline">Falar Conosco</span>
                                <span class="sm:hidden">Contato</span>
                            </Button>
                        </nav>
                    </div>
                </div>
            </header>

            <!-- Hero Section -->
            <section class="relative overflow-hidden bg-background w-full">
                <!-- Background Pattern -->
                <div
                    class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAxMCAwIEwgMCAwIDAgMTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0iY3VycmVudENvbG9yIiBzdHJva2Utb3BhY2l0eT0iMC4wMyIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-20">
                </div>

                <div class="relative w-full mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16 sm:py-24 lg:py-32">
                    <div class="mx-auto max-w-4xl text-center">
                        <Badge class="mb-6 bg-primary/10 text-primary hover:bg-primary/20 border-0 px-4 py-1.5">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="relative flex h-2 w-2">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                                </span>
                                Edição Visual de Planogramas
                            </span>
                        </Badge>

                        <h1
                            class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black tracking-tight text-foreground mb-6 sm:mb-8">
                            Planogramas
                            <span class="block text-primary animate-gradient">
                                Inteligentes
                            </span>
                            em Nuvem
                        </h1>

                        <p class="mt-4 sm:mt-6 text-lg sm:text-xl leading-relaxed text-muted-foreground max-w-3xl mx-auto">
                            Edite gôndolas, prateleiras e produtos com <strong
                                class="text-foreground font-semibold">drag-and-drop intuitivo</strong>.
                            Autosave automático, histórico completo de versões e gestão multi-tenant para toda a sua
                            equipe.
                        </p>

                        <div class="mt-8 sm:mt-12 flex flex-col sm:flex-row justify-center gap-3 sm:gap-4">
                            <Link v-if="$page.props.auth.user" :href="dashboard()">
                                <Button size="lg" class="group gap-2">
                                    <ActionIconBox variant="default">
                                        <Zap class="group-hover:rotate-12 transition-transform" />
                                    </ActionIconBox>
                                    Ir para Editor
                                </Button>
                            </Link>
                            <Button v-else size="lg" @click="openWhatsApp" class="group gap-2">
                                <ActionIconBox variant="default">
                                    <MessageCircle class="group-hover:scale-110 transition-transform" />
                                </ActionIconBox>
                                Falar Conosco
                                <ArrowDown class="h-4 w-4 group-hover:translate-y-1 transition-transform" />
                            </Button>
                            <Button size="lg" variant="outline" as-child class="gap-2">
                                <a href="#recursos">
                                    Ver Recursos
                                    <ArrowDown class="h-4 w-4" />
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Gondola Illustration -->
                    <div class="relative mt-12 sm:mt-16 lg:mt-20 mx-auto max-w-5xl w-full">
                        <Card class="relative overflow-hidden border border-border bg-card shadow-lg">
                            <!-- Glow effect -->
                            <div class="absolute -inset-4 bg-primary/10 blur-3xl"></div>

                            <div class="relative aspect-video p-8 sm:p-12 bg-background">
                                <svg class="h-full w-full" viewBox="0 0 800 500" preserveAspectRatio="xMidYMid meet">
                                    <!-- Background -->
                                    <rect width="800" height="500" fill="url(#bgGradient)" />

                                    <!-- Definitions -->
                                    <defs>
                                        <linearGradient id="bgGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:hsl(var(--card));stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:hsl(var(--muted));stop-opacity:0.3" />
                                        </linearGradient>
                                        <linearGradient id="gondolaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" style="stop-color:hsl(var(--muted-foreground));stop-opacity:0.08" />
                                            <stop offset="100%" style="stop-color:hsl(var(--muted-foreground));stop-opacity:0.12" />
                                        </linearGradient>
                                        <linearGradient id="shelfGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" style="stop-color:hsl(var(--border));stop-opacity:0.4" />
                                            <stop offset="100%" style="stop-color:hsl(var(--muted));stop-opacity:0.6" />
                                        </linearGradient>
                                        <filter id="shadow">
                                            <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.15" />
                                        </filter>
                                    </defs>

                                    <!-- Main Gondola Structure -->
                                    <g filter="url(#shadow)">
                                        <!-- Frame -->
                                        <rect x="150" y="100" width="500" height="350" rx="8"
                                            fill="url(#gondolaGradient)" stroke="hsl(var(--border))" stroke-width="1.5" />

                                        <!-- Shelves -->
                                        <rect x="150" y="165" width="500" height="12" fill="url(#shelfGradient)"
                                            stroke="hsl(var(--border))" stroke-width="1" />
                                        <rect x="150" y="245" width="500" height="12" fill="url(#shelfGradient)"
                                            stroke="hsl(var(--border))" stroke-width="1" />
                                        <rect x="150" y="325" width="500" height="12" fill="url(#shelfGradient)"
                                            stroke="hsl(var(--border))" stroke-width="1" />
                                        <rect x="150" y="405" width="500" height="12" fill="url(#shelfGradient)"
                                            stroke="hsl(var(--border))" stroke-width="1" />
                                    </g>

                                    <!-- Products on shelves -->
                                    <!-- Shelf 1 - Cores vibrantes -->
                                    <rect x="170" y="120" width="50" height="40" rx="4" fill="#3b82f6" opacity="0.8" stroke="#2563eb" stroke-width="1.5" />
                                    <rect x="230" y="120" width="50" height="40" rx="4" fill="#10b981" opacity="0.8" stroke="#059669" stroke-width="1.5" />
                                    <rect x="290" y="120" width="50" height="40" rx="4" fill="#f59e0b" opacity="0.8" stroke="#d97706" stroke-width="1.5" />
                                    <rect x="350" y="120" width="50" height="40" rx="4" fill="#ef4444" opacity="0.8" stroke="#dc2626" stroke-width="1.5" />
                                    <rect x="410" y="120" width="50" height="40" rx="4" fill="#8b5cf6" opacity="0.8" stroke="#7c3aed" stroke-width="1.5" />
                                    <rect x="470" y="120" width="50" height="40" rx="4" fill="#ec4899" opacity="0.8" stroke="#db2777" stroke-width="1.5" />
                                    <rect x="530" y="120" width="50" height="40" rx="4" fill="#06b6d4" opacity="0.8" stroke="#0891b2" stroke-width="1.5" />
                                    <rect x="590" y="120" width="50" height="40" rx="4" fill="#f97316" opacity="0.8" stroke="#ea580c" stroke-width="1.5" />

                                    <!-- Shelf 2 - Cores vibrantes -->
                                    <rect x="170" y="185" width="60" height="55" rx="4" fill="#6366f1" opacity="0.85" stroke="#4f46e5" stroke-width="1.5" />
                                    <rect x="240" y="185" width="60" height="55" rx="4" fill="#14b8a6" opacity="0.85" stroke="#0d9488" stroke-width="1.5" />
                                    <rect x="310" y="185" width="60" height="55" rx="4" fill="#fbbf24" opacity="0.85" stroke="#f59e0b" stroke-width="1.5" />
                                    <rect x="380" y="185" width="60" height="55" rx="4" fill="#f87171" opacity="0.85" stroke="#ef4444" stroke-width="1.5" />
                                    <rect x="450" y="185" width="60" height="55" rx="4" fill="#a78bfa" opacity="0.85" stroke="#8b5cf6" stroke-width="1.5" />
                                    <rect x="520" y="185" width="60" height="55" rx="4" fill="#fb7185" opacity="0.85" stroke="#ec4899" stroke-width="1.5" />
                                    <rect x="590" y="185" width="40" height="55" rx="4" fill="#22d3ee" opacity="0.85" stroke="#06b6d4" stroke-width="1.5" />

                                    <!-- Shelf 3 - Cores vibrantes -->
                                    <rect x="170" y="265" width="70" height="55" rx="4" fill="#4f46e5" opacity="0.85" stroke="#4338ca" stroke-width="1.5" />
                                    <rect x="250" y="265" width="70" height="55" rx="4" fill="#10b981" opacity="0.85" stroke="#059669" stroke-width="1.5" />
                                    <rect x="330" y="265" width="70" height="55" rx="4" fill="#f59e0b" opacity="0.85" stroke="#d97706" stroke-width="1.5" />
                                    <rect x="410" y="265" width="70" height="55" rx="4" fill="#ef4444" opacity="0.85" stroke="#dc2626" stroke-width="1.5" />
                                    <rect x="490" y="265" width="70" height="55" rx="4" fill="#8b5cf6" opacity="0.85" stroke="#7c3aed" stroke-width="1.5" />
                                    <rect x="570" y="265" width="60" height="55" rx="4" fill="#ec4899" opacity="0.85" stroke="#db2777" stroke-width="1.5" />

                                    <!-- Shelf 4 - Cores vibrantes -->
                                    <rect x="170" y="345" width="65" height="55" rx="4" fill="#3b82f6" opacity="0.85" stroke="#2563eb" stroke-width="1.5" />
                                    <rect x="245" y="345" width="65" height="55" rx="4" fill="#14b8a6" opacity="0.85" stroke="#0d9488" stroke-width="1.5" />
                                    <rect x="320" y="345" width="65" height="55" rx="4" fill="#fbbf24" opacity="0.85" stroke="#f59e0b" stroke-width="1.5" />
                                    <rect x="395" y="345" width="65" height="55" rx="4" fill="#f87171" opacity="0.85" stroke="#ef4444" stroke-width="1.5" />
                                    <rect x="470" y="345" width="65" height="55" rx="4" fill="#a78bfa" opacity="0.85" stroke="#8b5cf6" stroke-width="1.5" />
                                    <rect x="545" y="345" width="85" height="55" rx="4" fill="#fb7185" opacity="0.85" stroke="#ec4899" stroke-width="1.5" />

                                    <!-- Floating label -->
                                    <g transform="translate(300, 230)">
                                        <rect width="200" height="50" rx="25" fill="hsl(var(--card))" opacity="0.98"
                                            filter="url(#shadow)" stroke="hsl(var(--border))" stroke-width="1.5" />
                                        <text x="100" y="32" text-anchor="middle" fill="hsl(var(--primary))"
                                            font-size="20" font-weight="bold">Editor Visual</text>
                                    </g>
                                </svg>
                            </div>
                        </Card>

                        <!-- Floating cards -->
                        <div class="absolute -left-4 top-1/4 hidden lg:block animate-float">
                            <Card class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full bg-primary animate-pulse"></div>
                                    <span class="text-xs font-medium text-foreground">Autosave</span>
                                </div>
                            </Card>
                        </div>
                        <div class="absolute -right-4 top-1/3 hidden lg:block animate-float-delayed">
                            <Card class="p-3">
                                <div class="flex items-center gap-2">
                                    <Zap class="h-4 w-4 text-primary" />
                                    <span class="text-xs font-medium text-foreground">Tempo Real</span>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section id="recursos" class="py-16 sm:py-24 lg:py-32 bg-background w-full">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
                    <div class="mx-auto max-w-2xl text-center mb-12 sm:mb-16 lg:mb-20">
                        <Badge class="mb-4 bg-muted text-muted-foreground hover:bg-muted/80 border-0 px-4 py-1.5">
                            Recursos Poderosos
                        </Badge>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-foreground mb-4 sm:mb-6">
                            Tudo que você precisa para <span class="text-primary">gerenciar planogramas</span>
                        </h2>
                        <p class="text-base sm:text-lg text-muted-foreground">
                            Recursos profissionais para otimizar a gestão visual do seu varejo
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Feature 1 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <LayoutGrid class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Drag & Drop Intuitivo
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    Interface visual moderna para organizar produtos nas prateleiras de forma rápida e
                                    fácil, sem necessidade de treinamento complexo
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Feature 2 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <Clock class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Autosave Inteligente
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    Sistema automático que salva cada alteração em tempo real com histórico completo de
                                    versões para restauração quando necessário
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Feature 3 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <Zap class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Colaboração em Tempo Real
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    WebSockets para edição simultânea entre múltiplos usuários, veja as alterações
                                    acontecendo ao vivo na tela
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Feature 4 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <BarChart3 class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Analytics Avançado
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    Dashboard completo com métricas detalhadas sobre utilização de espaço, performance
                                    de produtos e otimização de gondolas
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Feature 5 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <Shield class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Multi-tenant Seguro
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    Arquitetura robusta para gerenciar múltiplos clientes e lojas com total isolamento e
                                    segurança de dados
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Feature 6 -->
                        <Card class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div
                                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 group-hover:bg-primary transition-all duration-300 group-hover:scale-110">
                                    <Image class="h-6 w-6 text-primary group-hover:text-primary-foreground transition-colors" />
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    Exportação Visual
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-muted-foreground leading-relaxed">
                                    Exporte planogramas em alta qualidade PDF e PNG para impressão, apresentações e
                                    distribuição para equipes
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="py-16 sm:py-24 lg:py-32 bg-muted/50 w-full">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
                    <div class="mx-auto max-w-2xl text-center mb-12 sm:mb-16">
                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-foreground mb-3 sm:mb-4">
                            Números que Impressionam
                        </h2>
                        <p class="text-base sm:text-lg text-muted-foreground">
                            Resultados reais de quem confia no Plannerate
                        </p>
                    </div>

                    <div class="grid gap-8 sm:grid-cols-3">
                        <Card class="relative overflow-hidden border-0 bg-primary text-primary-foreground shadow-lg">
                            <CardHeader class="relative">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <CardTitle class="text-5xl font-black mb-2">
                                            {{ gondolaCount.toLocaleString() }}
                                        </CardTitle>
                                        <CardDescription class="text-primary-foreground/80 text-base font-medium">
                                            Gôndolas Criadas
                                        </CardDescription>
                                    </div>
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-foreground/20 backdrop-blur-sm">
                                        <LayoutGrid class="h-7 w-7" />
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>

                        <Card class="relative overflow-hidden border-0 bg-primary text-primary-foreground shadow-lg">
                            <CardHeader class="relative">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <CardTitle class="text-5xl font-black mb-2">
                                            {{ clientsCount }}
                                        </CardTitle>
                                        <CardDescription class="text-primary-foreground/80 text-base font-medium">
                                            Clientes Ativos
                                        </CardDescription>
                                    </div>
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-foreground/20 backdrop-blur-sm">
                                        <Users class="h-7 w-7" />
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>

                        <Card class="relative overflow-hidden border-0 bg-primary text-primary-foreground shadow-lg">
                            <CardHeader class="relative">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <CardTitle class="text-5xl font-black mb-2">
                                            99.9%
                                        </CardTitle>
                                        <CardDescription class="text-primary-foreground/80 text-base font-medium">
                                            Uptime Garantido
                                        </CardDescription>
                                    </div>
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-foreground/20 backdrop-blur-sm">
                                        <CheckCircle2 class="h-7 w-7" />
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- Clients Showcase Section -->
            <section v-if="clients?.length > 0" class="py-16 sm:py-24 lg:py-32 bg-background w-full">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
                    <div class="mx-auto max-w-2xl text-center mb-12 sm:mb-16">
                        <Badge class="mb-4 bg-muted text-muted-foreground hover:bg-muted/80 border-0 px-4 py-1.5">
                            Casos de Sucesso
                        </Badge>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-foreground mb-4 sm:mb-6">
                            Empresas que <span class="text-primary">Confiam</span> em Nós
                        </h2>
                        <p class="text-base sm:text-lg text-muted-foreground">
                            Grandes marcas do varejo brasileiro utilizam Plannerate para otimizar seus planogramas
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="client in clients" :key="client.id"
                            class="group relative overflow-hidden border-2 hover:border-primary transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                            <div
                                class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <CardHeader class="relative">
                                <div v-if="client.logo"
                                    class="mb-4 h-16 w-16 rounded-xl bg-muted flex items-center justify-center p-2 shadow-md group-hover:shadow-lg transition-shadow">
                                    <img :src="client.logo" :alt="client.name"
                                        class="h-full w-full object-contain rounded-lg" />
                                </div>
                                <div v-else
                                    class="mb-4 h-16 w-16 rounded-xl bg-primary text-primary-foreground flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all">
                                    <span class="text-2xl font-black">{{ client.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <CardTitle class="text-xl font-bold group-hover:text-primary transition-colors">
                                    {{ client.name }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="relative">
                                <p class="text-sm text-muted-foreground leading-relaxed mb-4">
                                    Transformando a gestão de planogramas com tecnologia de ponta
                                </p>
                                <div v-if="client.domain" class="flex items-center gap-2">
                                    <Badge variant="secondary" class="text-xs font-medium">
                                        <Globe class="h-3 w-3 mr-1" />
                                        {{ client.domain }}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="relative py-16 sm:py-24 lg:py-32 overflow-hidden bg-primary text-primary-foreground w-full">
                <div
                    class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAxMCAwIEwgMCAwIDAgMTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0iY3VycmVudENvbG9yIiBzdHJva2Utb3BhY2l0eT0iMC4xIiBzdHJva2Utd2lkdGg9IjEiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjZ3JpZCkiLz48L3N2Zz4=')] opacity-30">
                </div>

                <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 w-full">
                    <Card
                        class="rounded-2xl sm:rounded-3xl bg-primary-foreground/10 backdrop-blur-md border-2 border-primary-foreground/20 px-6 py-12 sm:px-8 sm:py-16 lg:px-16 text-center shadow-2xl">
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black mb-4 sm:mb-6">
                            Pronto para Começar?
                        </h2>
                        <p class="mt-4 sm:mt-6 text-lg sm:text-xl leading-relaxed text-primary-foreground/90 max-w-2xl mx-auto">
                            Junte-se a centenas de empresas que já transformaram sua gestão de planogramas com o
                            Plannerate
                        </p>
                        <div class="mt-8 sm:mt-10 flex flex-col sm:flex-row justify-center gap-3 sm:gap-4">
                            <Button size="lg" @click="openWhatsApp" variant="secondary" class="group gap-2">
                                <ActionIconBox variant="secondary">
                                    <MessageCircle class="group-hover:scale-110 transition-transform" />
                                </ActionIconBox>
                                Falar Conosco
                                <ArrowDown class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </Button>
                        </div>
                        <p class="mt-6 text-sm text-primary-foreground/70">
                            Atendimento personalizado • Respostas rápidas
                        </p>
                    </Card>
                </div>
            </section>

            <!-- Footer -->
            <footer class="border-t border-border bg-muted py-8 sm:py-12 w-full">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
                    <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg">
                                <LayoutGrid class="h-6 w-6" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-lg font-bold text-foreground">Plannerate</span>
                                <span class="text-xs text-muted-foreground -mt-0.5">Planograma Digital</span>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            © 2025 Plannerate. Todos os direitos reservados.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </AuthLayout>
</template>

<style scoped>
@keyframes gradient {
    0%,
    100% {
        background-position: 0% 50%;
    }

    50% {
        background-position: 100% 50%;
    }
}

.animate-gradient {
    background-size: 200% auto;
    animation: gradient 3s ease infinite;
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }

    50% {
        transform: translateY(-10px);
    }
}

@keyframes float-delayed {
    0%,
    100% {
        transform: translateY(0px);
    }

    50% {
        transform: translateY(-15px);
    }
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.animate-float-delayed {
    animation: float-delayed 4s ease-in-out infinite;
}
</style>