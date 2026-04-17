<script setup lang="ts">
import TenantSettingsController from '@/actions/App/Http/Controllers/Settings/TenantSettingsController';
import { Form, Head } from '@inertiajs/vue3';

import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '~/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    Building2,
    Globe,
    Mail,
    Phone,
    ScrollText,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { getClientStatusLabel } from '@/lib/status';

interface Tenant {
    id: string;
    name: string;
    slug?: string;
    subdomain?: string;
    domain?: string;
    email?: string;
    phone?: string;
    document?: string;
    logo?: string;
    description?: string;
    status?: string;
}

interface Props {
    tenant: Tenant;
}

const props = defineProps<Props>();

const tenantInitials = computed(() =>
    props.tenant.name
        ?.split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((chunk) => chunk[0]?.toUpperCase() ?? '')
        .join('') || 'TN',
);

const tenantStatusLabel = computed(() => getClientStatusLabel(props.tenant.status));

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configurações do Tenant',
        href: '/settings/tenant',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configurações do Tenant" />

        <SettingsLayout :wide="true">
            <div class="flex flex-col space-y-6">
                <Heading size="sm"
                    title="Informações do Tenant"
                    description="Atualize as informações do tenant"
                />

                <Form
                    v-bind="TenantSettingsController.update.patch()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                        <Card class="overflow-hidden border-border/70 shadow-sm">
                            <CardHeader class="border-b border-border/60 bg-gradient-to-br from-primary/10 via-background to-card">
                                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/15 text-lg font-semibold text-primary shadow-sm ring-1 ring-primary/20">
                                            {{ tenantInitials }}
                                        </div>
                                        <div class="space-y-1">
                                            <CardTitle class="text-xl">{{ tenant.name }}</CardTitle>
                                            <CardDescription class="max-w-2xl text-sm leading-6">
                                                Gerencie identidade, presença digital e canais oficiais do tenant em um único lugar.
                                            </CardDescription>
                                        </div>
                                    </div>

                                    <div class="inline-flex h-9 items-center rounded-full border border-border/70 bg-background/90 px-3 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur">
                                        <ShieldCheck class="mr-2 size-4 text-primary" />
                                        {{ tenantStatusLabel }}
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="grid gap-4 p-6 md:grid-cols-1">
                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Building2 class="size-4 text-primary" />
                                        Identidade
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ tenant.slug || 'Sem slug definido' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Slug público usado em URLs e integrações.
                                    </p>
                                </div>

                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Globe class="size-4 text-primary" />
                                        Presença digital
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ tenant.domain || tenant.subdomain || 'Não configurado' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Domínio principal ou subdomínio em uso.
                                    </p>
                                </div>

                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Mail class="size-4 text-primary" />
                                        Contato principal
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ tenant.email || tenant.phone || 'Não informado' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Canal preferencial para comunicações do tenant.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-border/70 shadow-sm">
                            <CardHeader>
                                <CardTitle class="text-base">Resumo rápido</CardTitle>
                                <CardDescription>
                                    Referência visual para revisar os dados antes de salvar.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4 p-6 pt-0">
                                <div class="space-y-3 rounded-2xl border border-dashed border-border/70 bg-muted/20 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-sm font-semibold text-primary ring-1 ring-primary/15">
                                            {{ tenantInitials }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-foreground">{{ tenant.name }}</p>
                                            <p class="truncate text-sm text-muted-foreground">{{ tenant.email || 'Sem email cadastrado' }}</p>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                                        <div class="rounded-xl bg-background p-3">
                                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Documento</p>
                                            <p class="mt-1 font-medium text-foreground">{{ tenant.document || 'Não informado' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-background p-3">
                                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Telefone</p>
                                            <p class="mt-1 font-medium text-foreground">{{ tenant.phone || 'Não informado' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 text-sm text-muted-foreground">
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <Globe class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Domínio e subdomínio</p>
                                            <p>Defina a presença externa do tenant com URLs claras e consistentes.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <ScrollText class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Descrição institucional</p>
                                            <p>Use uma descrição curta e objetiva para contextualizar a operação.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <Phone class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Contato central</p>
                                            <p>Garanta que email e telefone estejam atualizados para suporte e integrações.</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Identidade do tenant</CardTitle>
                            <CardDescription>
                                Dados básicos usados para identificar o tenant no sistema.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-5 p-6 pt-0 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Nome</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    :default-value="tenant.name"
                                    required
                                    placeholder="Nome do tenant"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    :default-value="tenant.slug"
                                    placeholder="slug-do-tenant"
                                    readonly
                                    disabled
                                />
                                <InputError :message="errors.slug" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="document">Documento (CNPJ/CPF)</Label>
                                <Input
                                    id="document"
                                    name="document"
                                    :default-value="tenant.document"
                                    placeholder="00.000.000/0000-00"
                                />
                                <InputError :message="errors.document" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="logo">Logo (URL)</Label>
                                <Input
                                    id="logo"
                                    name="logo"
                                    :default-value="tenant.logo"
                                    placeholder="https://example.com/logo.png"
                                />
                                <InputError :message="errors.logo" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Contato e presença digital</CardTitle>
                            <CardDescription>
                                Canais oficiais usados por usuários, integrações e operações.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-5 p-6 pt-0 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="subdomain">Subdomínio</Label>
                                <Input
                                    id="subdomain"
                                    name="subdomain"
                                    :default-value="tenant.subdomain"
                                    placeholder="subdomain.example.com"
                                    readonly
                                    disabled
                                />
                                <InputError :message="errors.subdomain" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="domain">Domínio</Label>
                                <Input
                                    id="domain"
                                    name="domain"
                                    :default-value="tenant.domain"
                                    placeholder="example.com"
                                    readonly
                                    disabled
                                />
                                <InputError :message="errors.domain" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    :default-value="tenant.email"
                                    placeholder="contato@example.com"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="phone">Telefone</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    :default-value="tenant.phone"
                                    placeholder="(00) 00000-0000"
                                />
                                <InputError :message="errors.phone" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Descrição</CardTitle>
                            <CardDescription>
                                Contexto institucional e observações relevantes sobre o tenant.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6 pt-0">
                            <div class="grid gap-2">
                                <Label for="description">Descrição</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    :default-value="tenant.description"
                                    placeholder="Descreva o tenant, seu contexto operacional e observações relevantes"
                                    class="min-h-32 resize-y"
                                />
                                <InputError :message="errors.description" />
                            </div>
                        </CardContent>
                        <CardFooter class="flex items-center gap-4 border-t border-border/60 px-6 py-4">
                            <Button :disabled="processing">Salvar alterações</Button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-sm text-muted-foreground"
                                >
                                    Salvo.
                                </p>
                            </Transition>
                        </CardFooter>
                    </Card>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
