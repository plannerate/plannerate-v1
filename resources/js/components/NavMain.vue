<script setup lang="ts">
import { computed } from 'vue';
import NavMenuEntry from '@/components/NavMenuEntry.vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
} from '@/components/ui/sidebar';
import type { SharedNavigationGroup, SharedNavigationNode } from '@/types';

const props = defineProps<{
    nodes: SharedNavigationNode[];
}>();

const rootNodes = computed(() => props.nodes.filter((node) => node.type !== 'group'));
const groups = computed(() => props.nodes.filter((node): node is SharedNavigationGroup => node.type === 'group'));
</script>

<template>
    <SidebarGroup v-if="rootNodes.length > 0" class="px-2 py-0">
        <SidebarMenu>
            <NavMenuEntry v-for="node in rootNodes" :key="node.key" :node="node" />
        </SidebarMenu>
    </SidebarGroup>

    <SidebarGroup v-for="group in groups" :key="group.key" class="px-2 py-0">
        <SidebarGroupLabel class="mb-1 mt-2 border-t border-sidebar-border/70 pt-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
            {{ group.title }}
        </SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <NavMenuEntry v-for="node in group.children" :key="node.key" :node="node" />
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
