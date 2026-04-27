<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

const props = defineProps<{
    subdomain: string;
}>();

const { t } = useT();
const planogramsIndexPath = PlanogramController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: 'Maps',
    title: 'Maps',
    description: 'Visualização de planogramas em mapa.',
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.tenant.planograms.navigation'), href: planogramsIndexPath },
        { title: 'Maps', href: '/planograms/maps' },
    ],
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />
        <KankanNavigationLinks :subdomain="props.subdomain" />
        <div class="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">
            Página Maps em branco.
        </div>
    </AppLayout>
</template>
