<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { Lock, Mail } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/composables/useT';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';

const { t } = useT();

setLayoutProps({
    title: t('app.auth.reset_password'),
    description: t('app.auth.reset_password_description'),
});

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head :title="t('app.auth.reset_password')" />
    <AuthLayout
        :title="t('app.auth.reset_password')"
        :description="t('app.auth.reset_password_description')"
    >
        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label
                        for="email"
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ t('app.email') }}
                    </Label>
                    <div class="relative">
                        <Mail
                            class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="inputEmail"
                            class="h-11 rounded-xl border-border/60 bg-muted/50 pr-4 pl-11 shadow-none"
                            readonly
                        />
                    </div>
                    <InputError :message="errors.email" class="mt-2" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="password"
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
                            autocomplete="new-password"
                            class="h-11 rounded-xl border-border/60 bg-muted/50 pl-11 shadow-none"
                            autofocus
                            :placeholder="t('app.password')"
                        />
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="password_confirmation"
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ t('app.auth.confirm_password') }}
                    </Label>
                    <div class="relative">
                        <Lock
                            class="pointer-events-none absolute top-1/2 left-4 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="h-11 rounded-xl border-border/60 bg-muted/50 pl-11 shadow-none"
                            :placeholder="
                                t('app.auth.confirm_password_placeholder')
                            "
                        />
                    </div>
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    variant="gradient"
                    class="mt-2 h-11 w-full rounded-xl text-sm font-semibold"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('app.auth.reset_password') }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
