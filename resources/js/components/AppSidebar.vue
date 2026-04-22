<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutGrid, PackageOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem, SharedNavigation, SharedNavigationItem } from '@/types';

const page = usePage();

const iconMap = {
    'layout-grid': LayoutGrid,
    'package-open': PackageOpen,
    'building-2': Building2,
} as const;

const navigation = computed<SharedNavigation>(() => {
    return (page.props.navigation as SharedNavigation | undefined) ?? {
        context: 'landlord',
        main: [],
    };
});

const mainNavItems = computed<NavItem[]>(() => {
    return navigation.value.main
        .filter((item: SharedNavigationItem) => item.can)
        .map((item: SharedNavigationItem) => ({
            title: item.title,
            href: item.href,
            icon: item.icon ? iconMap[item.icon as keyof typeof iconMap] : undefined,
        }));
});

const homePath = computed<string>(() => {
    const firstItem = mainNavItems.value[0];

    return firstItem ? String(firstItem.href) : '/dashboard';
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="h-14" as-child>
                        <Link :href="homePath">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
