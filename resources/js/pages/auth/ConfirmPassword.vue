<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '~/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm/index';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout
        title="Confirme sua senha"
        description="Esta é uma área segura do aplicativo. Por favor, confirme sua senha antes de continuar."
    >
        <Head title="Confirmar senha" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label htmlFor="password">Senha</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button type="submit" class="w-full" :disabled="processing">
                        <Spinner
                            v-if="processing"
                            class="h-4 w-4 animate-spin"
                        />
                        Confirmar Senha
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
