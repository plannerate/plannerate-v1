<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance/index';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configurações de aparência',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configurações de aparência" />

        <SettingsLayout>
            <div class="space-y-6">
                <Heading size="sm"
                    title="Configurações de aparência"
                    description="Atualize as configurações de aparência da sua conta"
                />

                <section class="space-y-3">
                    <div class="space-y-1">
                        <h2 class="text-sm font-medium text-foreground">Modo</h2>
                        <p class="text-sm text-muted-foreground">
                            Escolha entre claro, escuro ou seguir o sistema.
                        </p>
                    </div>
                    <AppearanceTabs />
                </section>

                <section class="rounded-lg border border-border bg-card p-4">
                    <div class="space-y-1">
                        <h2 class="text-sm font-medium text-foreground">Tema</h2>
                        <p class="text-sm text-muted-foreground">
                            Apenas o tema padrão está disponível no momento.
                        </p>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
