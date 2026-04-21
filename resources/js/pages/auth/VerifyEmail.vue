<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const { t } = useT();

setLayoutProps({
    title: t('app.auth.verify_email_title'),
    description: t('app.auth.verify_email_description'),
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('app.auth.verify_email')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ t('app.auth.verify_email_notice') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ t('app.auth.resend_verification_email') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ t('app.auth.logout') }}
        </TextLink>
    </Form>
</template>
