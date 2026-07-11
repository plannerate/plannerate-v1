<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import TenantController from '@/actions/App/Http/Controllers/Landlord/TenantController';
import MercadologicoManager from '@/components/mercadologico/MercadologicoManager.vue';
import type { TreeNode } from '@/components/mercadologico/types';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

import { landlordMercadologicoUrls, mercadologicoUrls } from './routes';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
};

const props = defineProps<{
    tenant: TenantPayload;
    roots: TreeNode[];
}>();

const { t } = useT();

const pageMeta = useCrudPageMeta({
    headTitle: `${t('app.landlord.mercadologico.title')} - ${props.tenant.name}`,
    title: `${t('app.landlord.mercadologico.title')} - ${props.tenant.name}`,
    description: t('app.landlord.mercadologico.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.tenants.navigation'),
            href: tenantWayfinderPath(TenantController.index.url()),
        },
        {
            title: t('app.landlord.mercadologico.navigation'),
            href: mercadologicoUrls.index(props.tenant.id),
        },
    ],
});

// URLs do contexto landlord injetadas no orquestrador reutilizável.
const urls = computed(() => landlordMercadologicoUrls(props.tenant.id));
</script>

<template>
    <Head :title="`${t('app.landlord.mercadologico.title')} - ${props.tenant.name}`" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-2 sm:p-4">
            <div class="mx-auto w-full max-w-6xl">
                <MercadologicoManager :urls="urls" :roots="props.roots" />
            </div>
        </div>
    </AppLayout>
</template>
