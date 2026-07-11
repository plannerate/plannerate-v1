<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import MercadologicoManager from '@/components/mercadologico/MercadologicoManager.vue';
import type { TreeNode } from '@/components/mercadologico/types';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

import { mercadologicoUrls, tenantMercadologicoUrls } from './routes';

const props = defineProps<{
    roots: TreeNode[];
}>();

const { t } = useT();

const pageMeta = useCrudPageMeta({
    headTitle: t('app.landlord.mercadologico.title'),
    title: t('app.landlord.mercadologico.title'),
    description: t('app.landlord.mercadologico.description'),
    breadcrumbs: [
        {
            title: t('app.navigation.dashboard'),
            href: dashboard.url().replace(/^\/\/[^/]+/, ''),
        },
        {
            title: t('app.landlord.mercadologico.navigation'),
            href: mercadologicoUrls.index(),
        },
    ],
});

// URLs do contexto do tenant ativo injetadas no orquestrador reutilizável.
const urls = computed(() => tenantMercadologicoUrls());
</script>

<template>
    <Head :title="t('app.landlord.mercadologico.title')" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-2 sm:p-4">
            <div class="mx-auto w-full max-w-6xl">
                <MercadologicoManager :urls="urls" :roots="props.roots" />
            </div>
        </div>
    </AppLayout>
</template>
