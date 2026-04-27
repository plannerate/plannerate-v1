<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Kanban, LayoutList, Map } from 'lucide-vue-next';
import { computed } from 'vue';
import planograms from '@/routes/tenant/planograms';

const props = defineProps<{
    subdomain: string;
}>();

const page = usePage();

const activeModules = computed<string[]>(() => {
    const tenant = (page.props.tenant ?? null) as { active_modules?: string[] } | null;

    return Array.isArray(tenant?.active_modules) ? tenant.active_modules : [];
});

const canUseKanban = computed(() => activeModules.value.includes('kanban'));

const currentPath = computed(() => {
    const [path] = page.url.split('?');

    return path;
});

const isListActive = computed(() => currentPath.value === '/planograms');
const isKanbanActive = computed(() => currentPath.value === '/planograms/kanban');
const isMapsActive = computed(() => currentPath.value === '/planograms/maps');

const listPath = computed(() => planograms.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''));
const kanbanPath = computed(() => planograms.kanban.url(props.subdomain).replace(/^\/\/[^/]+/, ''));
const mapsPath = computed(() => planograms.maps.url(props.subdomain).replace(/^\/\/[^/]+/, ''));

function linkClasses(isActive: boolean): string {
    return [
        'group inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
        isActive
            ? 'bg-card text-primary shadow-sm'
            : 'text-muted-foreground hover:text-foreground',
    ].join(' ');
}
</script>

<template>
    <nav class="mb-3 inline-flex items-center rounded-xl bg-muted p-1 mx-2" aria-label="Planogram views">
        <Link :href="listPath" :class="linkClasses(isListActive)">
            <LayoutList class="h-4 w-4" />
            <span>Lista</span>
        </Link>

        <Link v-if="canUseKanban" :href="kanbanPath" :class="linkClasses(isKanbanActive)">
            <Kanban class="h-4 w-4" />
            <span>Kanban</span>
        </Link>

        <Link :href="mapsPath" :class="linkClasses(isMapsActive)">
            <Map class="h-4 w-4" />
            <span>Maps</span>
        </Link>
    </nav>
</template>
