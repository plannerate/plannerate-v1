<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import PermissionController from '@/actions/App/Http/Controllers/Landlord/PermissionController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

type PermissionRow = {
    id: string;
    name: string;
    type: string;
    is_protected: boolean;
};

type Paginator<T> = {
    data: T[];
};

defineProps<{
    permissions: Paginator<PermissionRow>;
}>();

const { t } = useT();
const permissionsIndexPath = PermissionController.index.url().replace(/^\/\/[^/]+/, '');

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.landlord.permissions.navigation'),
            href: permissionsIndexPath,
        },
    ],
});
</script>

<template>
    <Head :title="t('app.landlord.permissions.title')" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading :title="t('app.landlord.permissions.title')" :description="t('app.landlord.permissions.description')" />

            <Button as-child>
                <Link :href="PermissionController.create.url()">
                    {{ t('app.landlord.permissions.actions.new') }}
                </Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="bg-muted/30 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.permissions.fields.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('app.landlord.permissions.fields.type') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('app.landlord.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="permissions.data.length === 0">
                        <td class="px-4 py-6 text-muted-foreground" colspan="3">
                            {{ t('app.landlord.common.empty') }}
                        </td>
                    </tr>
                    <tr
                        v-for="permission in permissions.data"
                        :key="permission.id"
                        class="border-t border-sidebar-border/60 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-medium">{{ permission.name }}</td>
                        <td class="px-4 py-3">{{ t(`app.landlord.roles.types.${permission.type}`) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="PermissionController.edit.url(permission.id)">
                                        {{ t('app.landlord.common.edit') }}
                                    </Link>
                                </Button>
                                <Button v-if="!permission.is_protected" variant="destructive" size="sm" as-child>
                                    <Link
                                        :href="PermissionController.destroy.url(permission.id)"
                                        method="delete"
                                        as="button"
                                    >
                                        {{ t('app.landlord.common.delete') }}
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
