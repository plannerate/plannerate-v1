<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdjacencyMatrixController from '@/actions/App/Http/Controllers/Settings/AdjacencyMatrixController';
import ScoringWeightsController from '@/actions/App/Http/Controllers/Settings/ScoringWeightsController';
import ShelfLevelPreferencesController from '@/actions/App/Http/Controllers/Settings/ShelfLevelPreferencesController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useT } from '@/composables/useT';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const { t } = useT();
const page = usePage();
const subdomain = computed(() => (page.props.tenant as any)?.slug as string | undefined);

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('app.settings_nav.profile'),
            href: editProfile(),
        },
        {
            title: t('app.settings_nav.security'),
            href: editSecurity(),
        },
        {
            title: t('app.settings_nav.appearance'),
            href: editAppearance(),
        },
    ];

    if (subdomain.value) {
        items.push(
            {
                title: t('app.settings_nav.scoring_weights'),
                href: ScoringWeightsController.edit.url(subdomain.value),
            },
            {
                title: t('app.settings_nav.adjacency_matrix'),
                href: AdjacencyMatrixController.edit.url(subdomain.value),
            },
            {
                title: t('app.settings_nav.shelf_level_preferences'),
                href: ShelfLevelPreferencesController.edit.url(subdomain.value),
            },
        );
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('app.settings')"
            :description="t('app.settings_description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    :aria-label="t('app.settings')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
