<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TenantSocialiteProviderController from '@/actions/App/Http/Controllers/Landlord/TenantSocialiteProviderController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';

type SsoProvider = {
    id: string;
    provider: string;
    label: string | null;
    client_id: string;
    azure_tenant: string | null;
    is_active: boolean;
};

type TenantPayload = {
    id: string;
    name: string;
};

const props = defineProps<{
    open: boolean;
    tenant: TenantPayload;
    ssoProvider: SsoProvider | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedProvider = ref(props.ssoProvider?.provider ?? 'google');

watch(() => props.ssoProvider, (val) => {
    selectedProvider.value = val?.provider ?? 'google';
});

const isAzure = computed(() => selectedProvider.value === 'azure');

const defaultValues = computed(() => ({
    provider: props.ssoProvider?.provider ?? 'google',
    label: props.ssoProvider?.label ?? '',
    client_id: props.ssoProvider?.client_id ?? '',
    client_secret: '',
    azure_tenant: props.ssoProvider?.azure_tenant ?? 'common',
    is_active: props.ssoProvider?.is_active ?? true,
}));

function handleDelete(): void {
    router.delete(
        TenantSocialiteProviderController.destroy.url(props.tenant.id),
        { preserveScroll: true, onSuccess: () => emit('update:open', false) },
    );
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full p-0 sm:max-w-md">
            <div class="flex h-full flex-col">
                <!-- Header -->
                <div class="shrink-0 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                    <SheetHeader class="space-y-0 text-left">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                                <KeyRound class="size-5" />
                            </div>
                            <div>
                                <SheetTitle class="text-base">SSO — {{ tenant.name }}</SheetTitle>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Configuração de login social (OAuth)
                                </p>
                            </div>
                        </div>
                    </SheetHeader>
                </div>

                <!-- Body -->
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <!-- Current status badge -->
                    <div v-if="ssoProvider" class="mb-5 flex items-center gap-2 rounded-lg border border-border/50 bg-muted/30 px-4 py-3">
                        <span class="text-sm font-medium text-foreground">
                            {{ ssoProvider.provider === 'google' ? 'Google' : 'Microsoft' }}
                        </span>
                        <Badge :variant="ssoProvider.is_active ? 'default' : 'secondary'" class="text-[10px]">
                            {{ ssoProvider.is_active ? 'Ativo' : 'Inativo' }}
                        </Badge>
                        <button
                            type="button"
                            class="ml-auto text-xs text-destructive hover:underline"
                            @click="handleDelete"
                        >
                            Remover
                        </button>
                    </div>

                    <Form
                        v-bind="TenantSocialiteProviderController.update.form(tenant.id)"
                        :default-values="defaultValues"
                        preserve-scroll
                        v-slot="{ errors, processing }"
                        class="flex min-h-full flex-col"
                    >
                        <div class="space-y-5">
                            <!-- Provider -->
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Provider</p>
                                <Separator />
                            </div>

                            <div class="grid gap-2">
                                <Label>Tipo de provider</Label>
                                <Select name="provider" v-model="selectedProvider">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="google">Google</SelectItem>
                                        <SelectItem value="azure">Microsoft (Azure AD)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.provider" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Label <span class="text-xs text-muted-foreground">(opcional)</span></Label>
                                <Input name="label" placeholder="Ex: Google Workspace" />
                                <InputError :message="errors.label" />
                            </div>

                            <!-- Credentials -->
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Credenciais</p>
                                <Separator />
                            </div>

                            <div class="grid gap-2">
                                <Label>Client ID</Label>
                                <Input name="client_id" required autocomplete="off" />
                                <InputError :message="errors.client_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label>
                                    Client Secret
                                    <span v-if="ssoProvider" class="text-xs text-muted-foreground">(deixe vazio para manter)</span>
                                </Label>
                                <PasswordInput name="client_secret" autocomplete="off" />
                                <InputError :message="errors.client_secret" />
                            </div>

                            <div v-if="isAzure" class="grid gap-2">
                                <Label>Azure Tenant ID</Label>
                                <Input name="azure_tenant" placeholder="common" />
                                <p class="text-xs text-muted-foreground">
                                    Use <code class="rounded bg-muted px-1 text-[11px]">common</code> para multi-tenant
                                    ou o UUID do seu Azure AD.
                                </p>
                                <InputError :message="errors.azure_tenant" />
                            </div>

                            <!-- Status -->
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                                <Separator />
                            </div>

                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2.5 text-sm transition-colors hover:bg-accent has-checked:border-primary/60 has-checked:bg-primary/5">
                                <input type="hidden" name="is_active" value="0" />
                                <input
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    :checked="ssoProvider?.is_active ?? true"
                                    class="accent-primary"
                                />
                                <span class="font-medium">Ativo</span>
                            </label>
                        </div>

                        <!-- Footer -->
                        <div class="sticky bottom-0 z-10 -mx-6 mt-6 border-t border-sidebar-border/70 bg-background/95 px-6 py-4 backdrop-blur dark:border-sidebar-border">
                            <div class="flex items-center gap-3">
                                <Button variant="gradient" :disabled="processing">
                                    Salvar
                                </Button>
                                <Button type="button" variant="outline" @click="emit('update:open', false)">
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    </Form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
