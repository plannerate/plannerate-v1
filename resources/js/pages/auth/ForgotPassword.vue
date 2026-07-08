<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

const { t } = useT();

setLayoutProps({
    title: t('app.auth.forgot_password_title'),
    description: t('app.auth.forgot_password_description'),
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('app.auth.forgot_password')" />
    <AuthLayout
        :title="t('app.auth.forgot_password_title')"
        :description="t('app.auth.forgot_password_description')"
    >
        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label
                        for="email"
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ t('app.labels.email_address') }}
                    </Label>
                    <div class="relative">
                        <Mail
                            class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="off"
                            autofocus
                            placeholder="email@example.com"
                            class="h-11 rounded-xl border-border/60 bg-muted/50 pr-4 pl-11 shadow-none"
                        />
                    </div>
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button
                        variant="gradient"
                        class="h-11 w-full rounded-xl text-sm font-semibold"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('app.auth.email_reset_link') }}
                    </Button>
                </div>
            </Form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>{{ t('app.auth.or_return_to') }}</span>
                <TextLink :href="login()">{{ t('app.auth.login') }}</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
