import { setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, toValue, watchEffect, type MaybeRefOrGetter } from 'vue';
import type { CrudPageMeta, CrudPageMetaDefaults } from '@/types';

type SharedPageMeta = CrudPageMeta;

export function useCrudPageMeta(
    defaults: CrudPageMetaDefaults,
    overrides?: MaybeRefOrGetter<CrudPageMeta | undefined>,
) {
    const page = usePage<{
        pageMeta?: SharedPageMeta;
        page_meta?: SharedPageMeta;
    }>();

    const resolved = computed<CrudPageMetaDefaults>(() => {
        const localMeta = toValue(overrides) ?? {};
        const sharedMeta = page.props.pageMeta ?? page.props.page_meta ?? {};

        return {
            headTitle:
                localMeta.headTitle ??
                sharedMeta.headTitle ??
                defaults.headTitle,
            title: localMeta.title ?? sharedMeta.title ?? defaults.title,
            description:
                localMeta.description ??
                sharedMeta.description ??
                defaults.description,
            breadcrumbs:
                localMeta.breadcrumbs ??
                sharedMeta.breadcrumbs ??
                defaults.breadcrumbs,
        };
    });

    watchEffect(() => {
        setLayoutProps({
            breadcrumbs: resolved.value.breadcrumbs,
            pageHeader: {
                title: resolved.value.title,
                description: resolved.value.description,
            },
        });
    });

    return resolved;
}
