<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
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
import type { SharedNavigation, SharedNavigationNode } from '@/types';

const page = usePage();

const navigation = computed<SharedNavigation>(() => {
    return (page.props.navigation as SharedNavigation | undefined) ?? {
        context: 'landlord',
        main: [],
    };
});

function firstHref(nodes: SharedNavigationNode[]): string | null {
    for (const node of nodes) {
        if (node.type === 'item') {
            return String(node.href);
        }

        if (node.type === 'group' || node.type === 'submenu') {
            const nestedHref = firstHref(node.children);

            if (nestedHref) {
                return nestedHref;
            }
        }
    }

    return null;
}

const homePath = computed<string>(() => {
    return firstHref(navigation.value.main) ?? '/dashboard';
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
            <NavMain :nodes="navigation.main" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
