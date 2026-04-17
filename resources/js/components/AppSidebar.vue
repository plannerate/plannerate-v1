<script setup lang="ts">
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import RaptorSidebar from '~/components/RaptorSidebar.vue';
import AppLogo from './AppLogo.vue';

const footerNavItems: NavItem[] = [];
</script>

<template>
    <RaptorSidebar
        :dashboard-url="dashboard().url"
        :footer-items="footerNavItems"
    >
        <template #logo>
            <Link :href="dashboard().url" class="flex items-center gap-0 opacity-90 transition-opacity hover:opacity-100">
                <!-- Full horizontal logo — hidden in icon-only collapsed mode -->
                <AppLogo variant="dark" class="group-data-[collapsible=icon]:hidden" />
                <!-- Compact monogram — shown only in icon-only collapsed mode -->
                <span
                    class="hidden size-7 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sm font-bold tracking-tight text-sidebar-primary-foreground group-data-[collapsible=icon]:flex"
                >
                    P
                </span>
            </Link>
        </template>
    </RaptorSidebar>
</template>
