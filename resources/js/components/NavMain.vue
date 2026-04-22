<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
} from '@/components/ui/sidebar';
import NavMenuEntry from '@/components/NavMenuEntry.vue';
import type { SharedNavigationGroup, SharedNavigationNode } from '@/types';
import { computed } from 'vue';

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
        <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <NavMenuEntry v-for="node in group.children" :key="node.key" :node="node" />
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
