<script setup lang="ts">
import StoreSettingsController from '@/actions/App/Http/Controllers/Settings/StoreSettingsController';
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
    Mail,
    MapPin,
    Phone,
    ScrollText,
    ShieldCheck,
    Store as StoreIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { getClientStatusLabel } from '@/lib/status';

interface Address {
    zip_code?: string;
    street?: string;
    number?: string;
    complement?: string;
    district?: string;
    city?: string;
    state?: string;
    country?: string;
}

interface Store {
    id: string;
    name: string;
    slug?: string;
    code?: string;
    document?: string;
    phone?: string;
    email?: string;
    description?: string;
    status?: string;
    address?: Address;
    client?: {
        id: string;
        name: string;
    };
}

interface Props {
    store: Store;
}

const props = defineProps<Props>();

const storeInitials = computed(() =>
    props.store.name
        ?.split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((chunk) => chunk[0]?.toUpperCase() ?? '')
        .join('') || 'LJ',
);

const storeStatusLabel = computed(() => getClientStatusLabel(props.store.status));

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Configurações da Loja',
        href: '/settings/store',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Configurações da Loja" />

        <SettingsLayout :wide="true">
            <div class="flex flex-col space-y-6">
                <Heading size="sm"
                    title="Informações da Loja"
                    description="Atualize as informações da loja"
                />

                <Form
                    v-bind="StoreSettingsController.update.patch()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                        <Card class="overflow-hidden border-border/70 shadow-sm">
                            <CardHeader class="border-b border-border/60 bg-gradient-to-br from-primary/10 via-background to-card">
                                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/15 text-lg font-semibold text-primary shadow-sm ring-1 ring-primary/20">
                                            {{ storeInitials }}
                                        </div>
                                        <div class="space-y-1">
                                            <CardTitle class="text-xl">{{ store.name }}</CardTitle>
                                            <CardDescription class="max-w-2xl text-sm leading-6">
                                                Organize identidade, contato e endereço operacional da loja em um só lugar.
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <div class="inline-flex h-9 items-center rounded-full border border-border/70 bg-background/90 px-3 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur">
                                        <ShieldCheck class="mr-2 size-4 text-primary" />
                                        {{ storeStatusLabel }}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="grid gap-4 p-6 md:grid-cols-3">
                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <StoreIcon class="size-4 text-primary" />
                                        Loja
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">{{ store.slug || 'Sem slug definido' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Identificador público e amigável da loja.</p>
                                </div>
                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Building2 class="size-4 text-primary" />
                                        Cliente vinculado
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">{{ store.client?.name || 'Sem cliente vinculado' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Cliente responsável por esta operação de loja.</p>
                                </div>
                                <div class="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                        <Mail class="size-4 text-primary" />
                                        Contato principal
                                    </div>
                                    <p class="truncate text-sm font-medium text-foreground">{{ store.email || store.phone || 'Não informado' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Canal prioritário para comunicação da loja.</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="border-border/70 shadow-sm">
                            <CardHeader>
                                <CardTitle class="text-base">Resumo rápido</CardTitle>
                                <CardDescription>Visão condensada dos dados operacionais da loja.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4 p-6 pt-0">
                                <div class="space-y-3 rounded-2xl border border-dashed border-border/70 bg-muted/20 p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-sm font-semibold text-primary ring-1 ring-primary/15">
                                            {{ storeInitials }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-foreground">{{ store.name }}</p>
                                            <p class="truncate text-sm text-muted-foreground">{{ store.client?.name || 'Sem cliente vinculado' }}</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                                        <div class="rounded-xl bg-background p-3">
                                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Código interno</p>
                                            <p class="mt-1 font-medium text-foreground">{{ store.code || 'Não informado' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-background p-3">
                                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Documento</p>
                                            <p class="mt-1 font-medium text-foreground">{{ store.document || 'Não informado' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3 text-sm text-muted-foreground">
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <Phone class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Contato da loja</p>
                                            <p>Mantenha email e telefone atualizados para comunicações operacionais.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <MapPin class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Endereço físico</p>
                                            <p>Dados corretos facilitam roteirização, visitas e conferências de campo.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3 rounded-xl border border-border/60 p-3">
                                        <ScrollText class="mt-0.5 size-4 text-primary" />
                                        <div>
                                            <p class="font-medium text-foreground">Descrição operacional</p>
                                            <p>Use o campo de descrição para registrar contexto útil sobre a loja.</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Identidade da loja</CardTitle>
                            <CardDescription>Dados cadastrais e identificadores internos.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-5 p-6 pt-0 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Nome</Label>
                                <Input id="name" name="name" :default-value="store.name" required placeholder="Nome da loja" />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input id="slug" name="slug" :default-value="store.slug" placeholder="slug-da-loja" readonly disabled />
                                <InputError :message="errors.slug" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="code">Código Interno</Label>
                                <Input id="code" name="code" :default-value="store.code" placeholder="Código interno" />
                                <InputError :message="errors.code" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="document">CNPJ</Label>
                                <Input id="document" name="document" :default-value="store.document" placeholder="00.000.000/0000-00" />
                                <InputError :message="errors.document" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="status">Status</Label>
                                <select id="status" name="status" class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" :default-value="store.status">
                                    <option value="draft">Rascunho</option>
                                    <option value="published">Publicado</option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Contato</CardTitle>
                            <CardDescription>Email, telefone e observações da loja.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-5 p-6 pt-0 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" name="email" type="email" :default-value="store.email" placeholder="contato@loja.com" />
                                <InputError :message="errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone">Telefone</Label>
                                <Input id="phone" name="phone" :default-value="store.phone" placeholder="(00) 00000-0000" />
                                <InputError :message="errors.phone" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="description">Descrição</Label>
                                <Textarea id="description" name="description" :default-value="store.description" placeholder="Descrição da loja" class="min-h-28 resize-y" />
                                <InputError :message="errors.description" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Endereço</CardTitle>
                            <CardDescription>Localização física e dados de referência.</CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-5 p-6 pt-0 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="address_zip_code">CEP</Label>
                                <Input id="address_zip_code" name="address[zip_code]" :default-value="store.address?.zip_code" placeholder="00000-000" />
                                <InputError :message="errors['address.zip_code']" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="address_street">Rua</Label>
                                <Input id="address_street" name="address[street]" :default-value="store.address?.street" placeholder="Nome da rua" />
                                <InputError :message="errors['address.street']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="address_number">Número</Label>
                                <Input id="address_number" name="address[number]" :default-value="store.address?.number" placeholder="123" />
                                <InputError :message="errors['address.number']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="address_complement">Complemento</Label>
                                <Input id="address_complement" name="address[complement]" :default-value="store.address?.complement" placeholder="Apto 101" />
                                <InputError :message="errors['address.complement']" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="address_district">Bairro</Label>
                                <Input id="address_district" name="address[district]" :default-value="store.address?.district" placeholder="Nome do bairro" />
                                <InputError :message="errors['address.district']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="address_city">Cidade</Label>
                                <Input id="address_city" name="address[city]" :default-value="store.address?.city" placeholder="Nome da cidade" />
                                <InputError :message="errors['address.city']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="address_state">Estado</Label>
                                <Input id="address_state" name="address[state]" :default-value="store.address?.state" placeholder="SP" maxlength="2" />
                                <InputError :message="errors['address.state']" />
                            </div>
                        </CardContent>
                        <CardFooter class="flex items-center gap-4 border-t border-border/60 px-6 py-4">
                            <Button :disabled="processing">Salvar alterações</Button>
                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                <p v-show="recentlySuccessful" class="text-sm text-muted-foreground">Salvo.</p>
                            </Transition>
                        </CardFooter>
                    </Card>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
