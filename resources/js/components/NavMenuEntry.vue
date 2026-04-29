<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Blocks,
    Building2,
    ChevronRight,
    FolderKanban,
    FolderTree,
    KeyRound,
    LayoutGrid,
    LayoutTemplate,
    Package,
    PackageOpen,
    ShieldCheck,
    Store,
    Truck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarMenuItem,
    SidebarMenuButton,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { SharedNavigationItem, SharedNavigationNode, SharedNavigationSubmenu } from '@/types';

const props = defineProps<{
    node: SharedNavigationNode;
}>();

const { isCurrentUrl } = useCurrentUrl();

const iconMap = {
    'layout-grid': LayoutGrid,
    'package-open': PackageOpen,
    package: Package,
    'building-2': Building2,
    blocks: Blocks,
    'folder-kanban': FolderKanban,
    'folder-tree': FolderTree,
    'shield-check': ShieldCheck,
    users: Users,
    'key-round': KeyRound,
    store: Store,
    truck: Truck,
    'layout-template': LayoutTemplate,
    'badge-dollar-sign': BadgeDollarSign,
} as const;

const isItemNode = computed(() => props.node.type === 'item');
const isSeparatorNode = computed(() => props.node.type === 'separator');
const isSubmenuNode = computed(() => props.node.type === 'submenu');

function resolveIcon(icon?: string) {
    return icon ? iconMap[icon as keyof typeof iconMap] : undefined;
}

function submenuHasActive(node: SharedNavigationSubmenu): boolean {
    return node.children.some((child) => child.type === 'item' && isCurrentUrl(child.href));
}

function isItem(child: SharedNavigationNode): child is SharedNavigationItem {
    return child.type === 'item';
}
</script>

<template>
    <SidebarSeparator v-if="isSeparatorNode" class="my-2" />

    <SidebarMenuItem v-else-if="isItemNode">
        <SidebarMenuButton
            as-child
            :is-active="isCurrentUrl((node as SharedNavigationItem).href)"
            :tooltip="(node as SharedNavigationItem).title"
        >
            <Link :href="(node as SharedNavigationItem).href">
                <component :is="resolveIcon((node as SharedNavigationItem).icon)" />
                <span>{{ (node as SharedNavigationItem).title }}</span>
            </Link>
        </SidebarMenuButton>
    </SidebarMenuItem>

    <Collapsible
        v-else-if="isSubmenuNode"
        as-child
        :default-open="submenuHasActive(node as SharedNavigationSubmenu)"
    >
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton
                    :is-active="submenuHasActive(node as SharedNavigationSubmenu)"
                    :tooltip="(node as SharedNavigationSubmenu).title"
                >
                    <component :is="resolveIcon((node as SharedNavigationSubmenu).icon)" />
                    <span>{{ (node as SharedNavigationSubmenu).title }}</span>
                    <ChevronRight class="ml-auto transition-transform duration-200" />
                </SidebarMenuButton>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <SidebarMenuSub>
                    <SidebarMenuSubItem
                        v-for="child in (node as SharedNavigationSubmenu).children"
                        :key="child.key"
                    >
                        <SidebarSeparator v-if="child.type === 'separator'" class="my-1" />

                        <SidebarMenuSubButton
                            v-else-if="isItem(child)"
                            as-child
                            :is-active="isCurrentUrl(child.href)"
                        >
                            <Link :href="child.href">
                                <component :is="resolveIcon(child.icon)" />
                                <span>{{ child.title }}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
</template>
