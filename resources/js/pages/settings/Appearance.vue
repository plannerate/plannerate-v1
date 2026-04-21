<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useT } from '@/composables/useT';
import { edit } from '@/routes/appearance';

const { t } = useT();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.appearance_settings'),
            href: edit(),
        },
    ],
});
</script>

<template>
    <Head :title="t('app.appearance_settings')" />

    <h1 class="sr-only">{{ t('app.appearance_settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('app.appearance_settings')"
            :description="t('app.appearance_settings_description')"
        />
        <AppearanceTabs />
    </div>
</template>
