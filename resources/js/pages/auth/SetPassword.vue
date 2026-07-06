<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';

const { t } = useT();

setLayoutProps({
    title: t('app.password_setup.page.title'),
    description: t('app.password_setup.page.description'),
});

const props = defineProps<{
    code: string;
    email: string | null;
}>();
</script>

<template>
    <Head :title="t('app.password_setup.page.title')" />
    <AuthLayout
        :title="t('app.password_setup.page.title')"
        :description="t('app.password_setup.page.description')"
    >
        <Form
            :action="`/password/setup/${props.code}`"
            method="post"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div v-if="props.email" class="grid gap-2">
                    <Label for="email">{{ t('app.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        :model-value="props.email"
                        class="mt-1 block w-full"
                        readonly
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('app.password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        autofocus
                        :placeholder="t('app.password')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        {{ t('app.auth.confirm_password') }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        :placeholder="t('app.auth.confirm_password_placeholder')"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="processing"
                    data-test="set-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('app.password_setup.page.submit') }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
