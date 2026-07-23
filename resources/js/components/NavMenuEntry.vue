<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Blocks,
    Building2,
    Cable,
    CalendarCheck,
    ChevronRight,
    ClipboardCheck,
    Eye,
    FileSpreadsheet,
    FileText,
    FolderKanban,
    FolderTree,
    Handshake,
    KeyRound,
    LayoutDashboard,
    LayoutGrid,
    LayoutTemplate,
    Layers,
    ListChecks,
    ListTree,
    Map as MapIcon,
    Package,
    PackageOpen,
    Ruler,
    Shapes,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
    Store,
    Tag,
    Truck,
    UserCog,
    Users,
    Workflow,
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
    cable: Cable,
    eye: Eye,
    'folder-kanban': FolderKanban,
    'folder-tree': FolderTree,
    'list-tree': ListTree,
    map: MapIcon,
    'shield-check': ShieldCheck,
    users: Users,
    'key-round': KeyRound,
    store: Store,
    'shopping-bag': ShoppingBag,
    tag: Tag,
    'calendar-check': CalendarCheck,
    handshake: Handshake,
    truck: Truck,
    'layout-template': LayoutTemplate,
    layers: Layers,
    ruler: Ruler,
    'badge-dollar-sign': BadgeDollarSign,
    'file-spreadsheet': FileSpreadsheet,
    'file-text': FileText,
    'clipboard-check': ClipboardCheck,
    'list-checks': ListChecks,
    shapes: Shapes,
    workflow: Workflow,
    'user-cog': UserCog,
    'layout-dashboard': LayoutDashboard,
    smartphone: Smartphone,
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
                <span
                    v-if="(node as SharedNavigationItem).badge"
                    class="ml-auto flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-foreground"
                >
                    {{ (node as SharedNavigationItem).badge }}
                </span>
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
