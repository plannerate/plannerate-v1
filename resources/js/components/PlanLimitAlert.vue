<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useT } from '@/composables/useT';

const props = defineProps<{
    message: string;
    upgradeUrl?: string | null;
}>();

const { t } = useT();
</script>

<template>
    <Alert variant="destructive">
        <TriangleAlert class="size-4" />
        <AlertTitle>{{ t('app.tenant.common.plan_limit.title') }}</AlertTitle>
        <AlertDescription class="flex items-center justify-between gap-4">
            <span>{{ props.message }}</span>
            <Link
                v-if="props.upgradeUrl"
                :href="props.upgradeUrl"
                class="shrink-0 text-sm font-medium underline underline-offset-2 hover:no-underline"
            >
                {{ t('app.tenant.common.plan_limit.upgrade') }}
            </Link>
        </AlertDescription>
    </Alert>
</template>
