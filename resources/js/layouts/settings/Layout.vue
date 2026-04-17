<script setup lang="ts">
import { Building2, Lock, Store, Users, User } from 'lucide-vue-next';
import { Shield } from 'lucide-vue-next';
import { Palette } from 'lucide-vue-next';
import { computed } from 'vue';

import { edit as editTenant } from '@/actions/App/Http/Controllers/Settings/TenantSettingsController';
import { edit as editClient } from '@/actions/App/Http/Controllers/Settings/ClientSettingsController';
import { edit as editStore } from '@/actions/App/Http/Controllers/Settings/StoreSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance/index';
import { edit as editProfile } from '@/routes/profile/index';
import { show } from '@/routes/two-factor/index';
import { edit as editPassword } from '@/routes/user-password/index';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

interface Props {
    wide?: boolean;
}

withDefaults(defineProps<Props>(), {
    wide: false,
});

const page = usePage();
const settings = page.props.settings as {
    tenant_id?: string;
    client_id?: string;
    store_id?: string;
}; 
const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Perfil',
            href: editProfile(),
            icon: User,
        },
        {
            title: 'Senha',
            href: editPassword(),
            icon: Lock,
        },
        {
            title: 'Autenticação 2FA',
            href: show(),
            icon: Shield,
        },
        {
            title: 'Aparência',
            href: editAppearance(),
            icon: Palette,
        },
    ]; 
    // Adiciona Tenant apenas quando não há domainable (tenant principal)
    if (settings.tenant_id && !settings.client_id && !settings.store_id) {
        items.push({
            title: 'Tenant',
            href: editTenant(),
            icon: Building2,
        });
    }

    // Adiciona Client quando há current_client_id
    if (settings.client_id) {
        items.push({
            title: 'Cliente',
            href: editClient(),
            icon: Users,
        });
    }

    // Adiciona Store quando há current_store_id
    if (settings.store_id) {
        items.push({
            title: 'Loja',
            href: editStore(),
            icon: Store,
        });
    }

    return items;
});

const currentPath = typeof window !== undefined ? window.location.pathname : '';
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Configurações"
            description="Gerencie seu perfil e configurações da conta"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': urlIsActive(item.href, currentPath) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-5xl">
                <section :class="wide ? 'w-full space-y-12' : 'max-w-xl space-y-12'">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
