<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

const { t } = useT();

setLayoutProps({
    title: t('app.auth.register_title'),
    description: t('app.auth.register_description'),
});
</script>

<template>
    <Head :title="t('app.auth.register')" />
    <AuthLayout
        :title="t('app.auth.register_title')"
        :description="t('app.auth.register_description')"
    >
        <div class="space-y-6">
            <p class="text-sm text-muted-foreground">
                {{ t('app.auth.register_description') }}
            </p>

            <div class="space-x-1 text-sm text-muted-foreground">
                <span>{{ t('app.auth.already_have_account') }}</span>
                <TextLink :href="login()" class="underline underline-offset-4">
                    {{ t('app.auth.login') }}
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
