<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '~/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification/index';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Verificar e-mail"
        description="Por favor, verifique seu endereço de e-mail clicando no link que acabamos de enviar para você."
    >
        <Head title="Verificação de e-mail" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400"
        >
            Um novo link de verificação foi enviado para o endereço de e-mail
            fornecido durante o cadastro.
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button type="submit" :disabled="processing" variant="secondary">
                <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                Reenviar e-mail de verificação
            </Button>

            <TextLink
                :href="logout()"
                method="post"
                as="button"
                class="mx-auto block text-sm"
            >
                Sair
            </TextLink>
        </Form>
    </AuthLayout>
</template>
