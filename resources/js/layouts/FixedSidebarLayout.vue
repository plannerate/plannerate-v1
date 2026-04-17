<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import ResourceLayout from '~/layouts/ResourceLayout.vue'
import { useSidebarState } from '@/composables/useSidebarState'
import type { BackendBreadcrumb } from '~/composables/useBreadcrumbs'

interface Props {
    title: string
    headTitle?: string
    maxWidth?: string
    breadcrumbs?: BackendBreadcrumb[]
    message?: string
    resourceLabel?: string
    resourcePluralLabel?: string
}

withDefaults(defineProps<Props>(), {
    maxWidth: 'full',
})

// Pegar estado da sidebar para ajustar posição do container fixo
const { sidebarWidth } = useSidebarState()
</script>

<template>
    <ResourceLayout 
        :max-width="maxWidth" 
        :title="title"
        :breadcrumbs="breadcrumbs"
        :message="message"
        :resource-label="resourceLabel"
        :resource-plural-label="resourcePluralLabel"
    >

        <Head :title="headTitle || title" />
        <template #header>
            <slot name="header-fixed-sidebar" />
        </template>
        <!-- Container fixo que acompanha a sidebar -->
        <div class="fixed inset-0 top-16 flex flex-col bg-background transition-[left] duration-200 ease-linear"
            :style="{ left: sidebarWidth }">
            <!-- Header slot -->
            <slot name="content-header" />

            <!-- Content com scroll -->
            <div class="flex-1 overflow-x-auto overflow-y-hidden p-4">
                <slot />
            </div>
        </div>

        <!-- Modals/Overlays slot (fora do container fixo) -->
        <slot name="modals" />
    </ResourceLayout>
</template>
