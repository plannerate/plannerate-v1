<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';

const { t } = useT();

setLayoutProps({
    title: t('app.auth.confirm_your_password'),
    description: t('app.auth.confirm_your_password_description'),
});
</script>

<template>
    <Head :title="t('app.auth.confirm_password')" />
    <AuthLayout
        :title="t('app.auth.confirm_your_password')"
        :description="t('app.auth.confirm_your_password_description')"
    >
        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label
                        htmlFor="password"
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ t('app.password') }}
                    </Label>
                    <div class="relative">
                        <Lock
                            class="pointer-events-none absolute top-1/2 left-4 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <PasswordInput
                            id="password"
                            name="password"
                            class="h-11 rounded-xl border-border/60 bg-muted/50 pl-11 shadow-none"
                            required
                            autocomplete="current-password"
                            autofocus
                        />
                    </div>

                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button
                        variant="gradient"
                        class="h-11 w-full rounded-xl text-sm font-semibold"
                        :disabled="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('app.auth.confirm_password') }}
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
