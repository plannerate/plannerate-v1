<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Kanban, LayoutList, Map } from 'lucide-vue-next';
import { computed } from 'vue';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import { useT } from '@/composables/useT';
import { cn } from '@/lib/utils';
import planograms from '@/routes/tenant/planograms';

const props = withDefaults(defineProps<{
    subdomain: string;
    class?: string;
}>(), {
    class: 'mx-2 mb-3 inline-flex items-center rounded-xl bg-muted p-1 ',
});

const page = usePage();
const { t } = useT();

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
const isKanbanActive = computed(() => currentPath.value.startsWith('/kanban'));
const isMapsActive = computed(() => currentPath.value === '/planograms/maps');

const listPath = computed(() => planograms.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''));
const kanbanPath = computed(() =>
    WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, ''),
);
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
    <nav
        :class="cn( props.class)"
        :aria-label="t('app.kanban.navigation_views')"
    >
        <Link :href="listPath" :class="linkClasses(isListActive)">
            <LayoutList class="h-4 w-4" />
            <span>{{ t('app.kanban.navigation_list') }}</span>
        </Link>

        <Link v-if="canUseKanban" :href="kanbanPath" :class="linkClasses(isKanbanActive)">
            <Kanban class="h-4 w-4" />
            <span>{{ t('app.kanban.navigation') }}</span>
        </Link>

        <Link :href="mapsPath" :class="linkClasses(isMapsActive)">
            <Map class="h-4 w-4" />
            <span>{{ t('app.kanban.navigation_maps') }}</span>
        </Link>
    </nav>
</template>
